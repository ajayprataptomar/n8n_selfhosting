import express from 'express';
import helmet from 'helmet';
import compression from 'compression';
import serverConfig from './config/server.config.js';
import runtimeConfig from './config/runtime.config.js';
import logger from './utils/logger.js';
import routes from './routes/index.js';
import rateLimiter from './middleware/rateLimit.middleware.js';
import { errorHandler, notFoundHandler } from './middleware/errorHandler.middleware.js';
import { licenseMiddleware, initializeLicense } from './middleware/license.middleware.js';
import SessionManager from './core/SessionManager.js';
import GracefulShutdownService from './services/GracefulShutdownService.js';
import HealthService from './services/HealthService.js';

// Ensure WebAssembly is disabled for undici (fixes memory issues on cPanel shared hosting)
if (!process.env.UNDICI_NO_WASM) {
  process.env.UNDICI_NO_WASM = '1';
}

const app = express();

/**
 * Memory Management for Shared Hosting (cPanel)
 * Monitors memory usage and triggers garbage collection when needed
 */
const MEMORY_THRESHOLD_MB = 180; // Trigger GC when memory exceeds this (lowered for cPanel)
const MEMORY_CRITICAL_MB = 220; // Critical threshold - log warning
let lastGcTime = Date.now();
let lastLogTime = 0;

const checkMemoryUsage = () => {
  const memUsage = process.memoryUsage();
  const heapUsedMB = Math.round(memUsage.heapUsed / 1024 / 1024);
  const heapTotalMB = Math.round(memUsage.heapTotal / 1024 / 1024);
  const rssMB = Math.round(memUsage.rss / 1024 / 1024);

  // Log memory usage periodically (every 5 minutes)
  const now = Date.now();
  if (now - lastLogTime > 300000) {
    logger.info(`Memory: ${heapUsedMB}MB heap, ${rssMB}MB RSS`);
    lastLogTime = now;
  }

  // Critical memory warning
  if (heapUsedMB > MEMORY_CRITICAL_MB) {
    logger.warn(`Critical memory usage: ${heapUsedMB}MB heap, ${rssMB}MB RSS - consider restarting`);
  }

  // Request garbage collection if available and memory is high
  if (heapUsedMB > MEMORY_THRESHOLD_MB && global.gc) {
    logger.info(`Memory threshold exceeded (${heapUsedMB}MB), triggering GC`);
    try {
      global.gc();
    } catch (e) {
      logger.debug('GC not available');
    }
    lastGcTime = now;
  }
};

// Check memory every 30 seconds (more frequent for cPanel)
setInterval(checkMemoryUsage, 30000);

/**
 * Security Middleware
 */
app.use(helmet({
  contentSecurityPolicy: false,
}));

/**
 * Dynamic CORS Configuration
 * Uses runtime config that can be updated from Laravel
 */
app.use((req, res, next) => {
  const allowedOrigins = runtimeConfig.get('allowedOrigins');
  const origin = req.headers.origin;

  if (allowedOrigins.includes('*') || allowedOrigins.includes(origin)) {
    res.header('Access-Control-Allow-Origin', origin || '*');
    res.header('Access-Control-Allow-Credentials', 'true');
    res.header('Access-Control-Allow-Methods', 'GET,HEAD,PUT,PATCH,POST,DELETE,OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-Key');
  }

  if (req.method === 'OPTIONS') {
    return res.sendStatus(200);
  }

  next();
});

/**
 * Body Parsing Middleware
 */
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

/**
 * Compression Middleware
 */
app.use(compression());

/**
 * Rate Limiting (applies to all routes)
 */
app.use(rateLimiter);

/**
 * License Verification Middleware
 * Validates license for protected routes
 */
app.use(licenseMiddleware);

/**
 * Request Logging and Metrics
 */
app.use((req, res, next) => {
  const startTime = Date.now();

  logger.info(`${req.method} ${req.path}`, {
    ip: req.ip,
    userAgent: req.get('user-agent'),
  });

  // Record response time on finish
  res.on('finish', () => {
    const responseTime = Date.now() - startTime;
    const success = res.statusCode < 400;
    HealthService.recordRequest(success, responseTime);
  });

  next();
});

/**
 * API Routes
 */
app.use('/', routes);

/**
 * 404 Handler
 */
app.use(notFoundHandler);

/**
 * Global Error Handler
 */
app.use(errorHandler);

/**
 * Start Server
 */
const server = app.listen(serverConfig.port, serverConfig.host, async () => {
  logger.info(`🚀 XSender WhatsApp Service v2.1.0 started on ${serverConfig.host}:${serverConfig.port}`);

  // Initialize graceful shutdown handlers
  GracefulShutdownService.initialize();
  logger.info('Graceful shutdown handlers initialized');

  // Initialize license verification
  await initializeLicense();

  // Restore pending message queue from previous shutdown
  await GracefulShutdownService.restorePendingQueue();

  // Restore existing sessions
  logger.info('Restoring existing sessions...');
  await SessionManager.restoreSessions();

  // Log startup complete
  logger.info('Enterprise services initialized', {
    services: ['HealthService', 'MessageQueueService', 'WebhookEventService', 'GracefulShutdownService'],
  });

  // Send ready signal to PM2 (important for cPanel Node.js apps)
  if (process.send) {
    process.send('ready');
    logger.info('PM2 ready signal sent');
  }
});

/**
 * Note: Graceful shutdown is now handled by GracefulShutdownService
 * which is initialized in the server startup above.
 *
 * The service handles:
 * - SIGTERM and SIGINT signals
 * - Uncaught exceptions
 * - Persisting pending message queue
 * - Disconnecting WhatsApp sessions gracefully
 * - Cleanup resources
 */

export default app;
