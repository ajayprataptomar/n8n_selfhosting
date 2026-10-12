import express from 'express';
import { body, param } from 'express-validator';
import validate from '../middleware/validator.middleware.js';
import SessionService from '../services/SessionService.js';
import { successResponse, errorResponse } from '../utils/response.js';
import logger from '../utils/logger.js';

const router = express.Router();

/**
 * POST /sessions/init
 * Initialize system and verify license
 */
router.post(
  '/init',
  [body('domain').notEmpty().withMessage('Domain is required')],
  validate,
  async (req, res, next) => {
    try {
      const { domain } = req.body;

      const result = await SessionService.initSystem(domain);

      return successResponse(res, 200, result.message, {
        license: result.license,
      });
    } catch (error) {
      logger.error(`Init failed: ${error.message}`);
      return errorResponse(res, 400, error.message);
    }
  }
);

/**
 * POST /sessions/create
 * Create new WhatsApp session and generate QR code
 */
router.post(
  '/create',
  [
    body('id').notEmpty().withMessage('Session ID is required'),
    body('isLegacy').isBoolean().withMessage('isLegacy must be boolean'),
    body('domain').notEmpty().withMessage('Domain is required'),
  ],
  validate,
  async (req, res, next) => {
    try {
      const { id, isLegacy, domain } = req.body;

      const result = await SessionService.createSession(id, isLegacy, domain);

      // If session reconnected via saved credentials (no QR needed),
      // return status 301 to signal "already connected" to Laravel
      if (result.connected) {
        return successResponse(res, 200, result.message, {
          qr: '',
          connected: true,
        });
      }

      return successResponse(res, 200, result.message, {
        qr: result.qr,
      });
    } catch (error) {
      logger.error(`Session creation failed: ${error.message}`);
      return errorResponse(res, 400, error.message);
    }
  }
);

/**
 * GET /sessions/status/:id
 * Get session connection status
 */
router.get(
  '/status/:id',
  [param('id').notEmpty().withMessage('Session ID is required')],
  validate,
  async (req, res, next) => {
    try {
      const { id } = req.params;

      const result = await SessionService.getSessionStatus(id);

      return successResponse(res, 200, result.message, {
        status: result.status,
        isSession: result.isSession,
        wpInfo: result.wpInfo,
      });
    } catch (error) {
      logger.error(`Get session status failed: ${error.message}`);
      return errorResponse(res, 403, error.message, {
        status: 'connecting',
        isSession: false,
      });
    }
  }
);

/**
 * GET /sessions/check/:id
 * Check if session exists
 */
router.get(
  '/check/:id',
  [param('id').notEmpty().withMessage('Session ID is required')],
  validate,
  async (req, res, next) => {
    try {
      const { id } = req.params;

      const exists = SessionService.checkSessionExists(id);

      if (exists) {
        return successResponse(res, 200, 'Session found');
      } else {
        return errorResponse(res, 403, 'Session not found');
      }
    } catch (error) {
      logger.error(`Check session failed: ${error.message}`);
      return errorResponse(res, 400, error.message);
    }
  }
);

/**
 * DELETE /sessions/delete/:id
 * Delete/logout session
 */
router.delete(
  '/delete/:id',
  [param('id').notEmpty().withMessage('Session ID is required')],
  validate,
  async (req, res, next) => {
    try {
      const { id } = req.params;

      const result = await SessionService.deleteSession(id);

      return successResponse(res, 200, result.message);
    } catch (error) {
      logger.error(`Delete session failed: ${error.message}`);
      return errorResponse(res, 400, error.message);
    }
  }
);

/**
 * GET /sessions/list
 * Get all active sessions
 */
router.get('/list', async (req, res, next) => {
  try {
    const sessions = SessionService.getAllSessions();

    return successResponse(res, 200, 'Active sessions retrieved', {
      sessions,
      count: sessions.length,
    });
  } catch (error) {
    logger.error(`List sessions failed: ${error.message}`);
    return errorResponse(res, 400, error.message);
  }
});

/**
 * GET /sessions/health/:id
 * Get detailed session health status including WebSocket state
 */
router.get(
  '/health/:id',
  [param('id').notEmpty().withMessage('Session ID is required')],
  validate,
  async (req, res, next) => {
    try {
      const { id } = req.params;

      const result = await SessionService.getSessionHealth(id);

      return successResponse(res, result.isHealthy ? 200 : 503, result.message, {
        isHealthy: result.isHealthy,
        wsState: result.wsState,
        isAuthenticated: result.isAuthenticated,
        user: result.user,
        canSendMessages: result.canSendMessages,
      });
    } catch (error) {
      logger.error(`Get session health failed: ${error.message}`);
      return errorResponse(res, 503, error.message, {
        isHealthy: false,
        canSendMessages: false,
      });
    }
  }
);

/**
 * POST /sessions/reconnect/:id
 * Force reconnect a session
 */
router.post(
  '/reconnect/:id',
  [param('id').notEmpty().withMessage('Session ID is required')],
  validate,
  async (req, res, next) => {
    try {
      const { id } = req.params;

      const result = await SessionService.reconnectSession(id);

      return successResponse(res, 200, result.message);
    } catch (error) {
      logger.error(`Reconnect session failed: ${error.message}`);
      return errorResponse(res, 400, error.message);
    }
  }
);

/**
 * POST /sessions/repair/:id
 * Repair session by clearing corrupted encryption keys
 * Use this when session has Bad MAC or encryption errors
 */
router.post(
  '/repair/:id',
  [param('id').notEmpty().withMessage('Session ID is required')],
  validate,
  async (req, res, next) => {
    try {
      const { id } = req.params;

      const result = await SessionService.repairSession(id);

      return successResponse(res, 200, result.message, {
        repaired: result.success,
      });
    } catch (error) {
      logger.error(`Repair session failed: ${error.message}`);
      return errorResponse(res, 400, error.message);
    }
  }
);

export default router;
