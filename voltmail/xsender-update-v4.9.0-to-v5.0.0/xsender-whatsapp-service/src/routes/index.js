import express from 'express';
import sessionsRoute from './sessions.route.js';
import messagesRoute from './messages.route.js';
import configRoute from './config.route.js';
import healthRoute from './health.route.js';
import queueRoute from './queue.route.js';
import logsRoute from './logs.route.js';
import authenticate from '../middleware/auth.middleware.js';
import { successResponse } from '../utils/response.js';

const router = express.Router();

/**
 * cPanel Health Check - Returns HTML (required for cPanel Node.js manager)
 * cPanel checks this endpoint before and after npm install
 */
router.get('/cpanel-health', (req, res) => {
  res.set('Content-Type', 'text/html');
  res.send(`<!DOCTYPE html>
<html>
<head><title>XSender WhatsApp Service</title></head>
<body>
<h1>XSender WhatsApp Service</h1>
<p>Status: Running</p>
<p>Time: ${new Date().toISOString()}</p>
</body>
</html>`);
});

/**
 * Health check endpoints (no auth required)
 * - /health - Full health report with system, session, queue metrics
 * - /health/live - Liveness probe (for Kubernetes/container orchestration)
 * - /health/ready - Readiness probe (for load balancer)
 * - /health/system - System metrics only
 * - /health/sessions - Session metrics only
 * - /health/queue - Queue metrics only
 */
router.use('/health', healthRoute);

/**
 * Simple health check (no auth required) - JSON response
 * Kept for backward compatibility
 */
router.get('/ping', (req, res) => {
  // Support both HTML and JSON based on Accept header (for cPanel compatibility)
  const acceptHeader = req.get('Accept') || '';

  if (acceptHeader.includes('text/html')) {
    res.set('Content-Type', 'text/html');
    return res.send(`<!DOCTYPE html>
<html>
<head><title>XSender WhatsApp Service</title></head>
<body>
<h1>XSender WhatsApp Service</h1>
<p>Status: Healthy</p>
<p>Uptime: ${Math.floor(process.uptime())} seconds</p>
<p>Time: ${new Date().toISOString()}</p>
</body>
</html>`);
  }

  return successResponse(res, 200, 'XSender WhatsApp Service is running', {
    status: 'healthy',
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
  });
});

/**
 * API Info endpoint (no auth required)
 */
router.get('/', (req, res) => {
  // Support both HTML and JSON based on Accept header (for cPanel compatibility)
  const acceptHeader = req.get('Accept') || '';

  if (acceptHeader.includes('text/html') && !acceptHeader.includes('application/json')) {
    res.set('Content-Type', 'text/html');
    return res.send(`<!DOCTYPE html>
<html>
<head><title>XSender WhatsApp Service API</title></head>
<body>
<h1>XSender WhatsApp Service API</h1>
<p>Version: 2.1.0</p>
<p>Status: Running</p>
<p>Powered by: @whiskeysockets/baileys</p>
<ul>
<li>Health: <a href="/health">/health</a></li>
<li>Sessions: /sessions</li>
<li>Messages: /messages</li>
</ul>
</body>
</html>`);
  }

  return successResponse(res, 200, 'XSender WhatsApp Service API', {
    version: '2.1.0',
    powered_by: '@whiskeysockets/baileys',
    documentation: '/api/docs',
    endpoints: {
      sessions: '/sessions',
      messages: '/messages',
      health: '/health',
      queue: '/queue',
    },
  });
});

/**
 * Configuration endpoint (no auth - Laravel pushes config on startup)
 * Note: In production, you may want to add IP whitelisting here
 */
router.use('/config', configRoute);

/**
 * Protected routes (require API key authentication)
 */
router.use('/sessions', authenticate, sessionsRoute);
router.use('/messages', authenticate, messagesRoute);
router.use('/queue', authenticate, queueRoute);
router.use('/logs', authenticate, logsRoute);

export default router;
