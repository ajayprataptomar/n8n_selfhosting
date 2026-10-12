import express from 'express';
import { body } from 'express-validator';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import validate from '../middleware/validator.middleware.js';
import runtimeConfig from '../config/runtime.config.js';
import { successResponse, errorResponse } from '../utils/response.js';
import { getLicenseStatus, revalidateLicense } from '../middleware/license.middleware.js';
import logger from '../utils/logger.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const router = express.Router();

/**
 * POST /config/update
 * Update runtime configuration from Laravel
 * This endpoint allows Laravel to push configuration to Node service
 */
router.post(
  '/update',
  [
    body('apiKey').optional().isString().withMessage('API key must be string'),
    body('allowedOrigins').optional(),
    body('purchaseKey').optional().isString(),
    body('envatoUsername').optional().isString(),
    body('softwareId').optional().isString(),
    body('version').optional().isString(),
    body('domain').optional().isString(),
  ],
  validate,
  async (req, res, next) => {
    try {
      const { apiKey, allowedOrigins, purchaseKey, envatoUsername, softwareId, version, domain } = req.body;

      // Update runtime configuration
      runtimeConfig.updateFromLaravel({
        apiKey,
        allowedOrigins,
        purchaseKey,
        envatoUsername,
        softwareId,
        version,
        domain,
      });

      logger.info('Configuration updated from Laravel', {
        hasApiKey: !!apiKey,
        hasAllowedOrigins: !!allowedOrigins,
        hasPurchaseKey: !!purchaseKey,
        hasEnvatoUsername: !!envatoUsername,
        softwareId: softwareId || 'default',
        version: version || 'default',
        domain,
      });

      return successResponse(res, 200, 'Configuration updated successfully', {
        configured: runtimeConfig.isConfigured(),
        settings: {
          hasApiKey: !!runtimeConfig.get('apiKey'),
          allowedOrigins: runtimeConfig.get('allowedOrigins'),
          hasPurchaseKey: !!runtimeConfig.get('purchaseKey'),
          hasEnvatoUsername: !!runtimeConfig.get('envatoUsername'),
          softwareId: runtimeConfig.get('softwareId'),
          version: runtimeConfig.get('version'),
          domain: runtimeConfig.get('domain'),
        },
      });
    } catch (error) {
      logger.error(`Config update failed: ${error.message}`);
      return errorResponse(res, 400, error.message);
    }
  }
);

/**
 * GET /config/status
 * Get current configuration status
 */
router.get('/status', async (req, res, next) => {
  try {
    const config = runtimeConfig.getAll();

    return successResponse(res, 200, 'Configuration status retrieved', {
      configured: runtimeConfig.isConfigured(),
      hasApiKey: !!config.apiKey,
      hasAllowedOrigins: config.allowedOrigins.length > 0,
      hasPurchaseKey: !!config.purchaseKey,
      hasEnvatoUsername: !!config.envatoUsername,
      softwareId: config.softwareId,
      version: config.version,
      domain: config.domain,
      allowedOrigins: config.allowedOrigins,
    });
  } catch (error) {
    logger.error(`Config status failed: ${error.message}`);
    return errorResponse(res, 400, error.message);
  }
});

/**
 * GET /config/license
 * Get current license status
 */
router.get('/license', async (req, res, next) => {
  try {
    const status = getLicenseStatus();

    return successResponse(res, 200, 'License status retrieved', {
      licensed: status.verified,
      lastVerification: status.lastCheck ? new Date(status.lastCheck).toISOString() : null,
      integrity: status.guardStatus?.integrity || true,
    });
  } catch (error) {
    logger.error(`License status failed: ${error.message}`);
    return errorResponse(res, 400, error.message);
  }
});

/**
 * POST /config/verify-license
 * Force license re-verification
 */
router.post('/verify-license', async (req, res, next) => {
  try {
    const valid = await revalidateLicense();

    if (valid) {
      return successResponse(res, 200, 'License verified successfully', {
        licensed: true,
        verifiedAt: new Date().toISOString(),
      });
    } else {
      return errorResponse(res, 403, 'License verification failed', {
        licensed: false,
        support: 'https://codecanyon.net/user/igenteam',
      });
    }
  } catch (error) {
    logger.error(`License verification failed: ${error.message}`);
    return errorResponse(res, 500, error.message);
  }
});

/**
 * POST /config/clear
 * Clear persisted configuration (for troubleshooting)
 * This will force Node to accept new config from Laravel
 */
router.post('/clear', async (req, res, next) => {
  try {
    runtimeConfig.clearPersisted();

    logger.info('Configuration cleared - will reload from Laravel on next request');

    return successResponse(res, 200, 'Configuration cleared successfully', {
      message: 'Persisted config cleared. Please push new config from Laravel.',
    });
  } catch (error) {
    logger.error(`Config clear failed: ${error.message}`);
    return errorResponse(res, 400, error.message);
  }
});

/**
 * POST /config/update-env
 * Update Node .env file directly from Laravel
 * This ensures Node .env stays in sync with Laravel settings
 * Note: Server restart required for HOST/PORT changes to take effect
 */
router.post(
  '/update-env',
  [
    body('serverHost').optional().isString().withMessage('Server host must be string'),
    body('serverPort').optional().isNumeric().withMessage('Server port must be numeric'),
    body('apiKey').optional().isString().withMessage('API key must be string'),
  ],
  validate,
  async (req, res, next) => {
    try {
      const { serverHost, serverPort, apiKey } = req.body;
      const envPath = path.resolve(__dirname, '../../.env');

      // Check if .env file exists
      if (!fs.existsSync(envPath)) {
        logger.warn('.env file not found, creating new one');
        fs.writeFileSync(envPath, '# XSender WhatsApp Service Configuration\nNODE_ENV=production\n');
      }

      let envContent = fs.readFileSync(envPath, 'utf8');
      let updated = [];

      // Helper function to update or add env variable
      const updateEnvVar = (content, key, value) => {
        const regex = new RegExp(`^${key}=.*$`, 'm');
        if (regex.test(content)) {
          return content.replace(regex, `${key}=${value}`);
        } else {
          // Add new line if content doesn't end with newline
          const separator = content.endsWith('\n') ? '' : '\n';
          return content + separator + `${key}=${value}\n`;
        }
      };

      // Update SERVER_HOST if provided (also update HOST for cPanel compatibility)
      if (serverHost !== undefined && serverHost !== '') {
        envContent = updateEnvVar(envContent, 'SERVER_HOST', serverHost);
        envContent = updateEnvVar(envContent, 'HOST', serverHost);
        updated.push('SERVER_HOST');
        updated.push('HOST');
      }

      // Update SERVER_PORT if provided (also update PORT for cPanel compatibility)
      if (serverPort !== undefined && serverPort !== '') {
        envContent = updateEnvVar(envContent, 'SERVER_PORT', serverPort);
        envContent = updateEnvVar(envContent, 'PORT', serverPort);
        updated.push('SERVER_PORT');
        updated.push('PORT');
      }

      // Update API_KEY if provided
      if (apiKey !== undefined && apiKey !== '') {
        envContent = updateEnvVar(envContent, 'API_KEY', apiKey);
        updated.push('API_KEY');

        // Also update runtime config so it takes effect immediately for API calls
        runtimeConfig.updateFromLaravel({ apiKey });
      }

      // Write updated content back to .env
      fs.writeFileSync(envPath, envContent);

      logger.info('Node .env file updated from Laravel', { updated });

      // Determine if restart is needed (HOST or PORT changed)
      const restartRequired = updated.includes('SERVER_HOST') || updated.includes('SERVER_PORT');

      return successResponse(res, 200, 'Environment file updated successfully', {
        updated,
        restartRequired,
        message: restartRequired
          ? 'Server HOST/PORT changed. Please restart Node service (pm2 restart) for changes to take effect.'
          : 'Configuration updated. API key is active immediately.',
      });
    } catch (error) {
      logger.error(`Env file update failed: ${error.message}`);
      return errorResponse(res, 500, error.message);
    }
  }
);

/**
 * GET /config/env
 * Get current Node .env values (for verification)
 */
router.get('/env', async (req, res, next) => {
  try {
    return successResponse(res, 200, 'Environment values retrieved', {
      serverHost: process.env.SERVER_HOST || '0.0.0.0',
      serverPort: process.env.SERVER_PORT || '3001',
      hasApiKey: !!process.env.API_KEY,
      nodeEnv: process.env.NODE_ENV || 'production',
    });
  } catch (error) {
    logger.error(`Env retrieval failed: ${error.message}`);
    return errorResponse(res, 400, error.message);
  }
});

export default router;
