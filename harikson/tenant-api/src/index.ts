declare global {
  namespace Express {
    interface Request {
      tenant?: any;
      user?: any;
      usePrimaryDb?: boolean;
      userId?: any;
      id?: string;
    }
  }
}

import logger from './utils/logger.js';
import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import dotenv from 'dotenv';
import path from 'path';
import fs from 'fs';
import { runMigrations } from './utils/migrate.js';
import { pool, checkDbHealth } from './db/pool.js';
import { validateMasterKeyConfig } from './services/documentEncryptionService.js';
import { HariksonScheduler } from './workers/scheduler.js';

// Import domain route modules
import healthRoutes from './routes/health.routes.js';
import authRoutes from './routes/auth.routes.js';
import userRoutes from './routes/user.routes.js';
import chatRoutes from './routes/chat.routes.js';
import documentRoutes from './routes/document.routes.ts';
import billingRoutes from './routes/billing.routes.js';
import agentRoutes from './routes/agent.routes.js';
import widgetRoutes from './routes/widget.routes.js';
import adminRoutes from './routes/admin.routes.js';

// Import existing API sub-routers
import chatRouter from './routes/chat.js';
import documentsRouter from './routes/documents.js';
import widgetRouter from './routes/widget.js';
import memoryRouter from './api/routes/memory.js';
import indexerRouter from './api/routes/indexer.js';
import searchRouter from './api/routes/search.js';
import contextRouter from './api/routes/context.js';
import toolsRouter from './api/routes/tools.js';
import orchestratorRouter from './api/routes/orchestrator-routes.js';

dotenv.config();

if (!process.env.JWT_SECRET) {
  const secretFile = process.env.JWT_SECRET_FILE || './secrets/jwt_secret';
  try {
    const secretPath = path.resolve(secretFile);
    if (fs.existsSync(secretPath)) {
      process.env.JWT_SECRET = fs.readFileSync(secretPath, 'utf8').trim();
    }
  } catch (err) {
    logger.warn(`Failed to read JWT_SECRET_FILE at ${secretFile}:`, err);
  }
}

validateMasterKeyConfig();

if (!process.env.JWT_SECRET || process.env.JWT_SECRET.length < 32) {
  logger.error('FATAL: JWT_SECRET not set or too short (min 32 characters)');
  process.exit(1);
}

const app = express();

// Security and utility middleware stack
app.use(helmet({ contentSecurityPolicy: false }));
app.use(
  cors({
    origin: true,
    credentials: true,
  })
);
app.use(express.json({ limit: '25mb' }));
app.use(express.urlencoded({ extended: true, limit: '25mb' }));

// Custom simple cookie parser middleware
app.use((req, _res, next) => {
  const cookieHeader = req.headers.cookie || '';
  const cookies: Record<string, string> = {};
  cookieHeader.split(';').forEach((cookie) => {
    const parts = cookie.split('=');
    if (parts.length === 2) {
      cookies[parts[0].trim()] = parts[1].trim();
    }
  });
  (req as any).cookies = cookies;
  next();
});

// Middleware: Tenant context resolution from header, subdomain, or API key
app.use(async (req, _res, next) => {
  try {
    const host = req.headers.host || '';
    const authHeader = req.headers.authorization || '';
    const tenantHeader = (req.headers['x-tenant-id'] as string) || '';

    let tenant: any = null;

    if (tenantHeader) {
      const tenantRes = await pool.query('SELECT * FROM tenants WHERE id = $1 OR slug = $1', [tenantHeader]);
      tenant = tenantRes.rows[0];
    } else if (authHeader.startsWith('Bearer hk_live_')) {
      const apiKey = authHeader.substring(7);
      const keyRes = await pool.query(
        'SELECT t.* FROM tenants t JOIN tenant_api_keys k ON k.tenant_id = t.id WHERE k.key_prefix = $1 AND k.status = \'active\'',
        [apiKey.substring(0, 12)]
      );
      tenant = keyRes.rows[0];
    } else {
      const parts = host.split('.');
      if (parts.length > 2 && parts[0] !== 'app' && parts[0] !== 'api' && parts[0] !== 'www') {
        const slug = parts[0];
        const tenantRes = await pool.query('SELECT * FROM tenants WHERE slug = $1', [slug]);
        tenant = tenantRes.rows[0];
      }
    }

    if (!tenant) {
      const defaultTenantRes = await pool.query("SELECT * FROM tenants WHERE slug = 'neuravolt' LIMIT 1");
      tenant = defaultTenantRes.rows[0] || {
        id: '00000000-0000-0000-0000-000000000000',
        name: 'Neuravolt Default',
        slug: 'neuravolt',
        status: 'active',
      };
    }

    req.tenant = tenant;
    next();
  } catch (err) {
    logger.error('Tenant resolution error:', err);
    next();
  }
});

// Register Domain Route Modules
app.use('/', healthRoutes);
app.use('/api/auth', authRoutes);
app.use('/api/v1/auth', authRoutes);
app.use('/api/user', userRoutes);
app.use('/api/v1/user', userRoutes);
app.use('/api/chat', chatRoutes);
app.use('/api/v1/chat', chatRoutes);
app.use('/api/documents', documentRoutes);
app.use('/api/v1/documents', documentRoutes);
app.use('/api/billing', billingRoutes);
app.use('/api/v1/billing', billingRoutes);
app.use('/api/agents', agentRoutes);
app.use('/api/v1/agents', agentRoutes);
app.use('/api/widget', widgetRoutes);
app.use('/api/admin', adminRoutes);

// Register specialized sub-routers
app.use('/api/routes/chat', chatRouter);
app.use('/api/routes/documents', documentsRouter);
app.use('/api/routes/widget', widgetRouter);
app.use('/api/memory', memoryRouter);
app.use('/api/indexer', indexerRouter);
app.use('/api/search', searchRouter);
app.use('/api/context', contextRouter);
app.use('/api/tools', toolsRouter);
app.use('/api/orchestrator', orchestratorRouter);

// Global Error Handler Middleware
app.use((err: any, _req: express.Request, res: express.Response, _next: express.NextFunction) => {
  logger.error('Unhandled server error:', err);
  res.status(err.status || 500).json({
    error: err.message || 'Internal Server Error',
  });
});

const PORT = process.env.PORT || 3008;

async function startServer() {
  try {
    await runMigrations();
    logger.info('Database migrations completed successfully.');

    // Initialize background worker scheduler
    try {
      const scheduler = new HariksonScheduler();
      scheduler.start();
      logger.info('Harikson background scheduler initialized.');
    } catch (schedErr: any) {
      logger.warn('Scheduler failed to start:', schedErr.message);
    }

    app.listen(PORT, () => {
      logger.info(`🚀 [Harikson Tenant API] Running on port ${PORT}`);
    });
  } catch (err: any) {
    logger.error('Failed to start server:', err);
    process.exit(1);
  }
}

startServer();

export default app;
