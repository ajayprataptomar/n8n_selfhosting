import { Router } from 'express';
import MessageQueueService from '../services/MessageQueueService.js';
import { successResponse, errorResponse } from '../utils/response.js';
import logger from '../utils/logger.js';

const router = Router();

/**
 * GET /queue/status
 * Get status of all queues
 */
router.get('/status', (req, res) => {
  try {
    const status = MessageQueueService.getAllStatus();
    return successResponse(res, 200, 'Queue status retrieved', status);
  } catch (err) {
    logger.error('Failed to get queue status:', { error: err.message });
    return errorResponse(res, 500, 'Failed to get queue status');
  }
});

/**
 * GET /queue/status/:sessionId
 * Get status of specific session queue
 */
router.get('/status/:sessionId', (req, res) => {
  try {
    const { sessionId } = req.params;
    const status = MessageQueueService.getStatus(sessionId);
    return successResponse(res, 200, 'Session queue status retrieved', status);
  } catch (err) {
    logger.error('Failed to get session queue status:', { error: err.message });
    return errorResponse(res, 500, 'Failed to get session queue status');
  }
});

/**
 * POST /queue/configure
 * Update queue configuration
 */
router.post('/configure', (req, res) => {
  try {
    const config = req.body;

    // Validate configuration
    const allowedKeys = [
      'maxQueueSize',
      'maxRetries',
      'retryBaseDelay',
      'processingInterval',
      'batchSize',
      'rateLimitPerMinute',
      'rateLimitWindow',
    ];

    const filteredConfig = {};
    for (const key of allowedKeys) {
      if (config[key] !== undefined) {
        filteredConfig[key] = config[key];
      }
    }

    MessageQueueService.configure(filteredConfig);

    return successResponse(res, 200, 'Queue configured successfully', {
      config: filteredConfig,
    });
  } catch (err) {
    logger.error('Failed to configure queue:', { error: err.message });
    return errorResponse(res, 500, 'Failed to configure queue');
  }
});

/**
 * DELETE /queue/clear/:sessionId
 * Clear queue for specific session
 */
router.delete('/clear/:sessionId', (req, res) => {
  try {
    const { sessionId } = req.params;
    MessageQueueService.clearQueue(sessionId);

    return successResponse(res, 200, `Queue cleared for session: ${sessionId}`);
  } catch (err) {
    logger.error('Failed to clear queue:', { error: err.message });
    return errorResponse(res, 500, 'Failed to clear queue');
  }
});

/**
 * GET /queue/stats
 * Get queue statistics
 */
router.get('/stats', (req, res) => {
  try {
    const status = MessageQueueService.getAllStatus();
    return successResponse(res, 200, 'Queue stats retrieved', {
      stats: status.stats,
      config: status.config,
    });
  } catch (err) {
    logger.error('Failed to get queue stats:', { error: err.message });
    return errorResponse(res, 500, 'Failed to get queue stats');
  }
});

export default router;
