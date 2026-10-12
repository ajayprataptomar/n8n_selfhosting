import LicenseGuard from '../security/LicenseGuard.js';
import runtimeConfig from '../config/runtime.config.js';
import logger from '../utils/logger.js';
import { errorResponse } from '../utils/response.js';

/**
 * License Middleware - Validates license on protected routes
 * Ensures the application is properly licensed before allowing operations
 */

let lastVerification = 0;
let isVerified = false;
let consecutiveFailures = 0;
const VERIFICATION_INTERVAL = 43200000; // 12 hours — reduced frequency to minimize network issues
const MAX_CONSECUTIVE_FAILURES = 20;    // Very generous — never kill sessions over license hiccups

/**
 * Verify license with the remote server
 */
async function verifyLicense() {
  const config = {
    domain: runtimeConfig.get('domain'),
    purchaseKey: runtimeConfig.get('purchaseKey'),
    envatoUsername: runtimeConfig.get('envatoUsername'),
    softwareId: runtimeConfig.get('softwareId'),
    version: runtimeConfig.get('version')
  };

  // Allow local development
  if (config.domain?.includes('localhost') || config.domain?.includes('.test') || config.domain?.includes('127.0.0.1')) {
    logger.debug('Development mode - License check skipped');
    consecutiveFailures = 0;
    return true;
  }

  // Skip if no purchase key configured yet (waiting for Laravel to push config)
  if (!config.purchaseKey || config.purchaseKey.trim() === '') {
    logger.debug('No purchase key configured - waiting for Laravel config push');
    // Don't count as failure - just not configured yet
    return true;
  }

  // Skip if no domain configured (waiting for Laravel to push config)
  if (!config.domain || config.domain.trim() === '') {
    logger.debug('No domain configured - waiting for Laravel config push');
    return true;
  }

  try {
    const result = await LicenseGuard.verify(config);

    if (result.valid) {
      logger.info('License verified successfully');
      consecutiveFailures = 0;
      return true;
    } else {
      logger.warn(`License verification returned invalid: ${result.message}`);
      consecutiveFailures++;

      // If we were previously verified and this is a temporary failure, allow grace period
      if (isVerified && consecutiveFailures < MAX_CONSECUTIVE_FAILURES) {
        logger.warn(`License verification failed but allowing grace period (${consecutiveFailures}/${MAX_CONSECUTIVE_FAILURES})`);
        return true;
      }

      return false;
    }
  } catch (error) {
    logger.error(`License verification error: ${error.message}`);
    consecutiveFailures++;

    // ALWAYS allow on network errors — never disconnect sessions due to license server issues
    // The license server being down is NOT the customer's fault
    if (isVerified || LicenseGuard.check()) {
      logger.warn('License server unreachable - using cached verification');
      return true;
    }

    // Even if never verified before, allow grace period — license server might be temporarily down
    if (consecutiveFailures < MAX_CONSECUTIVE_FAILURES) {
      logger.warn(`License check failed but allowing grace period (${consecutiveFailures}/${MAX_CONSECUTIVE_FAILURES})`);
      return true;
    }

    // After many failures, STILL allow session operations but log warning
    // Never block WhatsApp sessions — this is a messaging service, uptime is critical
    logger.error(`License check failed ${consecutiveFailures} times — allowing with warning`);
    return true;
  }
}

/**
 * License check middleware for Express routes
 */
export async function licenseMiddleware(req, res, next) {
  // Skip license check for critical service endpoints
  // These must always work to maintain WhatsApp session stability
  const skipPaths = ['/health', '/config', '/', '/sessions/status', '/sessions/health'];
  if (skipPaths.some(p => req.path === p || req.path.startsWith('/config') || req.path.startsWith('/sessions/health'))) {
    return next();
  }

  const now = Date.now();

  // If recently verified, continue
  if (isVerified && (now - lastVerification) < VERIFICATION_INTERVAL) {
    return next();
  }

  // Perform verification
  const valid = await verifyLicense();

  if (valid) {
    isVerified = true;
    lastVerification = now;
    return next();
  }

  // License invalid
  logger.error('License validation failed - blocking request');
  return errorResponse(res, 403, 'Invalid license. Please verify your purchase key.', {
    code: 'LICENSE_INVALID',
    support: 'Please contact with your purchase details for assistance.'
  });
}

/**
 * Initialize license verification on startup
 */
export async function initializeLicense() {
  logger.info('Initializing license verification...');

  const valid = await verifyLicense();

  if (valid) {
    isVerified = true;
    lastVerification = Date.now();
    logger.info('License initialization complete');
    return true;
  }

  logger.warn('License could not be verified on startup');
  return false;
}

/**
 * Get current license status
 */
export function getLicenseStatus() {
  return {
    verified: isVerified,
    lastCheck: lastVerification,
    guardStatus: LicenseGuard.getStatus()
  };
}

/**
 * Force license re-verification
 */
export async function revalidateLicense() {
  isVerified = false;
  lastVerification = 0;
  LicenseGuard.invalidate();
  return await verifyLicense();
}

export default licenseMiddleware;
