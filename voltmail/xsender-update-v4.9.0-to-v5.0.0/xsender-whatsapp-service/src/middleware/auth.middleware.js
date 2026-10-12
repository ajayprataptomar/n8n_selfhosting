import runtimeConfig from '../config/runtime.config.js';
import { errorResponse } from '../utils/response.js';
import logger from '../utils/logger.js';

/**
 * API Key Authentication Middleware
 * Uses runtime config that can be updated from Laravel
 */
export const authenticate = (req, res, next) => {
  try {
    const apiKey = req.headers['x-api-key'] || req.query.apiKey || req.body.apiKey;

    if (!apiKey) {
      logger.warn('Missing API key in request', { path: req.path });
      return errorResponse(res, 401, 'API key is required');
    }

    const expectedApiKey = runtimeConfig.get('apiKey');

    // Check if API key is not configured yet
    if (!expectedApiKey || expectedApiKey === 'default-change-me' || expectedApiKey === '') {
      logger.warn('API key not configured - please push config from Laravel first', {
        path: req.path,
        configured: runtimeConfig.isConfigured()
      });
      return errorResponse(res, 503, 'Service not configured. Please wait for configuration sync.', {
        hint: 'Laravel needs to push configuration to Node service first.',
        configured: runtimeConfig.isConfigured()
      });
    }

    if (apiKey !== expectedApiKey) {
      // Before rejecting, try reloading from Laravel .env in case the key was changed
      // This handles: PM2 restart with stale persisted config, Laravel key rotation, etc.
      const reloaded = runtimeConfig.reloadApiKey();
      if (reloaded) {
        const freshApiKey = runtimeConfig.get('apiKey');
        if (apiKey === freshApiKey) {
          logger.info('API key matched after reloading from Laravel .env', { path: req.path });
          return next();
        }
      }

      logger.warn('Invalid API key attempt', {
        ip: req.ip,
        path: req.path,
        receivedKeyPrefix: apiKey.substring(0, 4) + '...',
        expectedKeyPrefix: runtimeConfig.get('apiKey').substring(0, 4) + '...'
      });
      return errorResponse(res, 403, 'Invalid API key');
    }

    next();
  } catch (error) {
    logger.error(`Authentication error: ${error.message}`);
    return errorResponse(res, 500, 'Authentication failed');
  }
};

export default authenticate;
