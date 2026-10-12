import SessionManager from '../core/SessionManager.js';
import logger from '../utils/logger.js';
import axios from 'axios';
import runtimeConfig from '../config/runtime.config.js';

/**
 * Session Service - Handles session operations
 */
class SessionService {
  /**
   * Register domain with license server
   */
  async registerDomain(domain, softwareId, version) {
    try {
      const url = 'https://verifylicense.online/api/licence-verification/register-domain';

      const response = await axios.post(
        url,
        {
          domain: domain,
          software_id: softwareId || 'BX32DOTW4Q797ZF3',
          version: version || '3.3',
        },
        {
          headers: {
            'Content-Type': 'application/json',
          },
          timeout: 15000,
        }
      );

      const { success, code, message } = response.data;

      if (!success || !code || !message) {
        return { valid: false, message: 'Invalid response from license server' };
      }

      return {
        valid: success,
        message: message || 'Domain registered',
        code,
      };
    } catch (error) {
      logger.error(`Domain registration failed: ${error.message}`);
      return {
        valid: false,
        message: 'Domain registration failed',
      };
    }
  }

  /**
   * Verify license (uses new verifylicense.online API)
   */
  async verifyLicense(domain, purchaseKey = null, envatoUsername = null) {
    try {
      // Use provided purchase key or get from runtime config
      const key = purchaseKey || runtimeConfig.get('purchaseKey');
      const username = envatoUsername || runtimeConfig.get('envatoUsername');

      if (!key || key.trim() === '') {
        logger.warn('Purchase key not configured');
        return { valid: true, message: 'License verification skipped (no key)' };
      }

      // Get software info from runtime config or use defaults
      const softwareId = runtimeConfig.get('softwareId') || 'BX32DOTW4Q797ZF3';
      const version = runtimeConfig.get('version') || '3.3';

      // Step 1: Register domain first
      const domainRegistration = await this.registerDomain(domain, softwareId, version);

      if (!domainRegistration.valid) {
        logger.error('Domain registration failed', { message: domainRegistration.message });
        return domainRegistration;
      }

      logger.info('Domain registered successfully');

      // Step 2: Verify purchase key
      const url = 'https://verifylicense.online/api/licence-verification/verify-purchase';

      const response = await axios.post(
        url,
        {
          domain: domain,
          software_id: softwareId,
          version: version,
          purchase_key: key,
          envato_username: username || '',
        },
        {
          headers: {
            'Content-Type': 'application/json',
          },
          timeout: 15000,
        }
      );

      const { success, code, message } = response.data;

      if (!success || !code || !message) {
        return { valid: false, message: 'Invalid response from license server' };
      }

      return {
        valid: success,
        message: message || 'License verified',
        code,
      };
    } catch (error) {
      logger.error(`License verification failed: ${error.message}`, {
        domain,
        error: error.response?.data || error.message,
      });
      return {
        valid: false,
        message: error.response?.data?.message || 'License verification failed',
      };
    }
  }

  /**
   * Initialize system
   */
  async initSystem(domain) {
    const license = await this.verifyLicense(domain);

    if (!license.valid) {
      throw new Error(license.message);
    }

    return {
      message: 'Software license verified',
      license: license.valid,
    };
  }

  /**
   * Create session
   */
  async createSession(sessionId, isLegacy, domain) {
    try {
      // Verify license first
      const license = await this.verifyLicense(domain);

      if (!license.valid) {
        throw new Error(license.message);
      }

      // Check if session already exists in memory
      if (SessionManager.exists(sessionId)) {
        const existingStatus = SessionManager.getStatus(sessionId);

        if (existingStatus.isSession) {
          // Session is active and authenticated — return success (no QR needed)
          logger.info(`Session ${sessionId} is already active and connected`);
          return {
            message: 'Session is already connected',
            connected: true,
          };
        }

        // Session exists but is dead/disconnected — clean it up and recreate
        logger.info(`Removing stale session before recreating: ${sessionId}`, {
          status: existingStatus.status,
        });
        await SessionManager.remove(sessionId);
      }

      let qrCode = null;
      let connected = false;
      let timeoutReached = false;

      // Create session with QR callback AND connected callback
      await SessionManager.create(sessionId, {
        onQR: (qr) => {
          qrCode = qr;
        },
        onConnected: (user) => {
          connected = true;
          logger.info(`Session ${sessionId} connected successfully`, { user });
        },
      });

      // Wait for QR code OR direct connection (when saved credentials exist on disk,
      // Baileys reconnects automatically without generating a QR code)
      const timeout = 60000; // 60 seconds
      const startTime = Date.now();

      while (!qrCode && !connected && !timeoutReached) {
        await new Promise((resolve) => setTimeout(resolve, 500));

        // Also check if session became connected via saved credentials
        if (!connected) {
          const status = SessionManager.getStatus(sessionId);
          if (status.isSession) {
            connected = true;
          }
        }

        timeoutReached = Date.now() - startTime > timeout;
      }

      // Session connected directly via saved credentials (no QR needed)
      if (connected) {
        logger.info(`Session ${sessionId} reconnected using saved credentials`);
        return {
          message: 'Session reconnected using saved credentials',
          connected: true,
        };
      }

      // QR code was generated — user needs to scan
      if (qrCode) {
        return {
          message: 'QR code received, please scan the QR code',
          qr: qrCode,
        };
      }

      // Neither QR nor connection — timeout
      await SessionManager.delete(sessionId);
      throw new Error('QR code generation timeout');
    } catch (error) {
      logger.error(`Failed to create session: ${error.message}`, { sessionId });
      throw error;
    }
  }

  /**
   * Get session status
   */
  async getSessionStatus(sessionId) {
    try {
      const status = SessionManager.getStatus(sessionId);

      if (!status.isSession) {
        throw new Error('Session not found or not connected');
      }

      return {
        message: 'Successfully retrieved current status',
        status: status.status,
        isSession: status.isSession,
        wpInfo: status.user || null,
      };
    } catch (error) {
      logger.error(`Failed to get session status: ${error.message}`, { sessionId });
      throw error;
    }
  }

  /**
   * Check if session exists
   */
  checkSessionExists(sessionId) {
    return SessionManager.exists(sessionId);
  }

  /**
   * Delete session
   */
  async deleteSession(sessionId) {
    try {
      if (!SessionManager.exists(sessionId)) {
        throw new Error('Session not found');
      }

      await SessionManager.delete(sessionId);

      return {
        message: 'The session has been successfully deleted',
      };
    } catch (error) {
      logger.error(`Failed to delete session: ${error.message}`, { sessionId });
      throw error;
    }
  }

  /**
   * Get all active sessions
   */
  getAllSessions() {
    return SessionManager.getAll();
  }

  /**
   * Get detailed session health status
   * Checks WebSocket state and authentication
   */
  async getSessionHealth(sessionId) {
    try {
      const client = SessionManager.get(sessionId);

      if (!client) {
        return {
          isHealthy: false,
          message: 'Session not found',
          wsState: 'disconnected',
          isAuthenticated: false,
          user: null,
          canSendMessages: false,
        };
      }

      // Get WebSocket state
      const ws = client.socket?.ws;
      const wsStates = ['connecting', 'open', 'closing', 'closed'];
      const wsState = ws ? wsStates[ws.readyState] || 'unknown' : 'no_socket';

      // Check authentication
      const hasUser = client.socket?.user !== undefined;
      const hasValidCreds =
        (client.authState?.creds?.registered === true) ||
        (client.authState?.creds?.me !== undefined);
      const isAuthenticated = hasUser || hasValidCreds;

      // Determine if we can send messages
      const canSendMessages = wsState === 'open' && isAuthenticated;

      return {
        isHealthy: canSendMessages,
        message: canSendMessages
          ? 'Session is healthy and ready to send messages'
          : `Session is not ready: WebSocket=${wsState}, Auth=${isAuthenticated}`,
        wsState,
        isAuthenticated,
        user: client.socket?.user || null,
        canSendMessages,
      };
    } catch (error) {
      logger.error(`Failed to get session health: ${error.message}`, { sessionId });
      return {
        isHealthy: false,
        message: error.message,
        wsState: 'error',
        isAuthenticated: false,
        user: null,
        canSendMessages: false,
      };
    }
  }

  /**
   * Force reconnect a session
   * Useful when WebSocket is stale but credentials are valid
   */
  async reconnectSession(sessionId) {
    try {
      const client = SessionManager.get(sessionId);

      if (!client) {
        throw new Error('Session not found');
      }

      // Check if we have valid credentials
      const hasValidCreds =
        (client.authState?.creds?.registered === true) ||
        (client.authState?.creds?.me !== undefined);

      if (!hasValidCreds) {
        throw new Error('Session has no valid credentials. Please scan QR code again.');
      }

      logger.info(`Force reconnecting session: ${sessionId}`);

      // Use saved callbacks if available (preserves webhook + message forwarding),
      // otherwise build fresh full callbacks
      let callbacks = client.savedCallbacks;
      if (!callbacks) {
        // Import WebhookService dynamically to avoid circular deps
        const { default: WebhookService } = await import('../services/WebhookService.js');
        const messageHandler = WebhookService.createMessageHandler(sessionId);

        callbacks = {
          onQR: (qr) => {
            WebhookService.notifySessionStatus(sessionId, 'qr', null);
          },
          onConnected: (user) => {
            logger.info(`Session ${sessionId} reconnected successfully`, { user });
            WebhookService.notifySessionStatus(sessionId, 'connected', user);
          },
          onDisconnected: (shouldReconnect, isLoggedOut = false) => {
            const status = shouldReconnect ? 'disconnected' : (isLoggedOut ? 'logged_out' : 'disconnected');
            WebhookService.notifySessionStatus(sessionId, status, null);
            if (!shouldReconnect) {
              if (isLoggedOut) {
                SessionManager.delete(sessionId, true);
              } else {
                SessionManager.remove(sessionId);
              }
            }
          },
          onMessage: async (msg) => {
            await messageHandler(msg);
          },
        };
      }

      // Close existing socket if any
      if (client.socket) {
        try {
          client.socket.end(undefined);
        } catch (e) {
          // Ignore close errors
        }
        client.socket = null;
      }

      // Reset state for clean reconnection
      client.retryCount = 0;
      client.isReconnecting = false;
      client.consecutiveFailures = 0;

      // Reinitialize the client with full callbacks
      await client.initialize(callbacks);

      return {
        message: 'Session reconnection initiated',
      };
    } catch (error) {
      logger.error(`Failed to reconnect session: ${error.message}`, { sessionId });
      throw error;
    }
  }

  /**
   * Repair session by clearing corrupted encryption keys
   * Useful when session has Bad MAC or encryption-related errors
   * This clears sender keys without deleting the main session credentials
   */
  async repairSession(sessionId) {
    try {
      const client = SessionManager.get(sessionId);

      if (!client) {
        throw new Error('Session not found');
      }

      // Check if we have valid credentials before repair
      const hasValidCreds =
        (client.authState?.creds?.registered === true) ||
        (client.authState?.creds?.me !== undefined);

      if (!hasValidCreds) {
        throw new Error('Session has no valid credentials. Please scan QR code again.');
      }

      logger.info(`Starting session repair for: ${sessionId}`);

      // Use saved callbacks if available, otherwise build fresh ones
      let callbacks = client.savedCallbacks;
      if (!callbacks) {
        const { default: WebhookService } = await import('../services/WebhookService.js');
        const messageHandler = WebhookService.createMessageHandler(sessionId);

        callbacks = {
          onConnected: (user) => {
            logger.info(`Session ${sessionId} repaired and reconnected successfully`, { user });
            WebhookService.notifySessionStatus(sessionId, 'connected', user);
          },
          onDisconnected: (shouldReconnect, isLoggedOut = false) => {
            const status = shouldReconnect ? 'disconnected' : (isLoggedOut ? 'logged_out' : 'disconnected');
            WebhookService.notifySessionStatus(sessionId, status, null);
            if (!shouldReconnect) {
              SessionManager.remove(sessionId);
            }
          },
          onMessage: async (msg) => {
            await messageHandler(msg);
          },
        };
      }

      // Use the client's repair method with full callbacks
      const success = await client.repairSession(callbacks);

      return {
        message: success ? 'Session repaired successfully' : 'Session repair completed with issues',
        success,
      };
    } catch (error) {
      logger.error(`Failed to repair session: ${error.message}`, { sessionId });
      throw error;
    }
  }
}

export default new SessionService();
