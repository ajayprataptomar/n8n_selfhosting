import { Router } from 'express';
import HealthService from '../services/HealthService.js';
import { successResponse } from '../utils/response.js';

const router = Router();

/**
 * GET /health
 * Comprehensive health check endpoint
 * Returns full system, session, queue, and API metrics
 */
router.get('/', (req, res) => {
  const startTime = Date.now();

  try {
    const health = HealthService.getHealthReport();

    // Record request
    const responseTime = Date.now() - startTime;
    HealthService.recordRequest(true, responseTime);

    // Always return 200 - service is running
    // The health status (healthy/warning/critical) is in the response body
    return res.status(200).json({
      success: true,
      ...health,
    });

  } catch (error) {
    const responseTime = Date.now() - startTime;
    HealthService.recordRequest(false, responseTime);
    HealthService.recordError(error);

    return res.status(500).json({
      success: false,
      status: 'error',
      error: error.message,
    });
  }
});

/**
 * GET /health/live
 * Kubernetes-style liveness probe
 * Returns 200 if service is running
 */
router.get('/live', (req, res) => {
  const liveness = HealthService.getLivenessCheck();
  return res.status(200).json({
    success: true,
    ...liveness,
  });
});

/**
 * GET /health/ready
 * Kubernetes-style readiness probe
 * Returns 200 if service is ready to accept traffic
 */
router.get('/ready', (req, res) => {
  const readiness = HealthService.getReadinessCheck();
  const statusCode = readiness.status === 'ready' ? 200 : 503;

  return res.status(statusCode).json({
    success: readiness.status === 'ready',
    ...readiness,
  });
});

/**
 * GET /health/system
 * System-level metrics only
 */
router.get('/system', (req, res) => {
  const system = HealthService.getSystemHealth();
  return successResponse(res, 200, 'System health retrieved', system);
});

/**
 * GET /health/sessions
 * Session-level metrics only
 */
router.get('/sessions', (req, res) => {
  const sessions = HealthService.getSessionHealth();
  return successResponse(res, 200, 'Session health retrieved', sessions);
});

/**
 * GET /health/queue
 * Queue-level metrics only
 */
router.get('/queue', (req, res) => {
  const queue = HealthService.getQueueHealth();
  return successResponse(res, 200, 'Queue health retrieved', queue);
});

/**
 * GET /health/api
 * API-level metrics only
 */
router.get('/api', (req, res) => {
  const api = HealthService.getApiMetrics();
  return successResponse(res, 200, 'API metrics retrieved', api);
});

export default router;
