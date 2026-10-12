import makeWASocket, {
  useMultiFileAuthState,
  DisconnectReason,
  Browsers,
  delay as baileysDelay,
  fetchLatestBaileysVersion,
} from '@whiskeysockets/baileys';
import { Boom } from '@hapi/boom';
import path from 'path';
import fs from 'fs';
import QRCode from 'qrcode';
import baileysConfig from '../config/baileys.config.js';
import logger from '../utils/logger.js';
import { retryWithBackoff } from '../utils/retry.js';

/**
 * WhatsApp Client - Baileys Wrapper
 */
class WhatsAppClient {
  constructor(sessionId) {
    this.sessionId = sessionId;
    this.socket = null;
    this.qrCode = null;
    this.retryCount = 0;
    this.isLegacy = false;
    this.authState = null;
    this.saveCreds = null;
    // Heartbeat/presence timer to keep connection alive
    this.presenceTimer = null;
    // Connection health monitor timer
    this.healthMonitorTimer = null;
    this.lastActivity = Date.now();
    // Track consecutive failures for smarter reconnection
    this.consecutiveFailures = 0;
    this.maxConsecutiveFailures = 5;
    // Callbacks for automatic reconnection
    this.savedCallbacks = null;
    // Prevent concurrent reconnection attempts
    this.isReconnecting = false;
    // Cooldown delay flag — set when max retries exhausted for recoverable disconnects
    this.useCooldownDelay = false;
  }

  /**
   * Initialize WhatsApp connection
   */
  async initialize(callbacks = {}) {
    try {
      const { onQR, onConnected, onDisconnected, onMessage } = callbacks;

      // Setup session directory
      const sessionPath = this.getSessionPath();
      if (!fs.existsSync(sessionPath)) {
        fs.mkdirSync(sessionPath, { recursive: true });
      }

      // Get latest Baileys version
      const { version } = await fetchLatestBaileysVersion();

      // Setup auth state
      const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
      this.authState = state;
      this.saveCreds = saveCreds;

      // Clear reconnecting flag once we start fresh
      this.isReconnecting = false;

      // CRITICAL: Clean up old event listeners before creating new socket
      // Without this, listeners stack up on every reconnect causing memory leaks
      // and eventually Node.js MaxListenersExceeded warning after 3-7 days
      if (this.socket?.ev) {
        try {
          this.socket.ev.removeAllListeners();
          logger.debug(`Cleaned up old event listeners for session: ${this.sessionId}`);
        } catch (e) {
          // Ignore cleanup errors
        }
      }

      // Close old socket connection if still open
      if (this.socket?.ws) {
        try {
          this.socket.ws.close();
        } catch (e) {
          // Ignore close errors
        }
      }

      // Create socket with proper browser fingerprint
      // Using Baileys built-in browser config to avoid "old version" warning
      this.socket = makeWASocket({
        version,
        auth: this.authState,
        printQRInTerminal: false,
        logger: logger.child({ class: 'Socket', sessionId: this.sessionId }),
        // Use Baileys official browser fingerprint - this is the most compatible option
        browser: Browsers.ubuntu('Chrome'),
        connectTimeoutMs: baileysConfig.connection.connectTimeout,
        // CRITICAL: Keep-alive interval - prevents disconnection on idle
        keepAliveIntervalMs: baileysConfig.connection.keepAliveInterval,
        // Reduced query timeout - fail fast on stale connections
        defaultQueryTimeoutMs: baileysConfig.connection.queryTimeout || 30000,
        // Faster retry for better stability
        retryRequestDelayMs: 2000,
        markOnlineOnConnect: true,
        syncFullHistory: false,
        generateHighQualityLinkPreview: true,
        // Emit events that keep connection alive
        emitOwnEvents: true,
        // Link preview fetching can help keep connection active
        getMessage: async () => undefined,
      });

      // Event: Credentials update - save immediately and verify
      this.socket.ev.on('creds.update', async () => {
        await saveCreds();
        logger.debug(`Credentials saved to disk for session: ${this.sessionId}`);
      });

      // Event: Socket errors - important for stability
      this.socket.ev.on('error', async (err) => {
        const errorMessage = err?.message || String(err);
        logger.error(`Socket error for session: ${this.sessionId}`, {
          error: errorMessage,
        });

        // Handle Bad MAC errors - encryption key corruption
        if (errorMessage.includes('Bad MAC') || errorMessage.includes('bad-mac')) {
          logger.warn(`Bad MAC error detected - clearing sender keys for session: ${this.sessionId}`);
          await this.clearSenderKeys();
        }

        // Update last activity - prevents false idle detection
        this.lastActivity = Date.now();
      });

      // Event: Handle message decryption errors
      this.socket.ev.on('messages.update', async (updates) => {
        for (const update of updates) {
          // Check for decryption failures
          if (update.update?.status === 'ERROR' || update.update?.messageStubType === 'CIPHERTEXT') {
            logger.warn(`Message decryption failed for session: ${this.sessionId}`, {
              messageId: update.key?.id,
              from: update.key?.remoteJid,
            });
          }
        }
      });

      // Event: Handle incoming calls (reject to prevent issues)
      this.socket.ev.on('call', async (calls) => {
        for (const call of calls) {
          try {
            // Reject incoming calls to prevent session issues
            if (!call.isGroup && call.status === 'offer') {
              await this.socket.rejectCall(call.id, call.from);
              logger.info(`Rejected incoming call from: ${call.from}`, {
                sessionId: this.sessionId,
              });
            }
          } catch (err) {
            logger.debug(`Could not reject call: ${err.message}`);
          }
        }
      });

      // Event: Connection update
      this.socket.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        // QR Code received
        if (qr) {
          try {
            this.qrCode = await QRCode.toDataURL(qr);
            logger.info(`QR Code generated for session: ${this.sessionId}`);

            if (onQR) {
              onQR(this.qrCode);
            }
          } catch (error) {
            logger.error(`Failed to generate QR code: ${error.message}`);
          }
        }

        // Connection opened
        if (connection === 'open') {
          this.retryCount = 0;
          this.consecutiveFailures = 0; // Reset failure counter
          this.isReconnecting = false;  // Clear reconnecting flag
          this.qrCode = null;
          this.lastActivity = Date.now();
          logger.info(`Connection opened for session: ${this.sessionId}`);

          // Save callbacks for automatic reconnection
          this.savedCallbacks = callbacks;

          // Start presence update timer to keep connection alive
          this.startPresenceTimer();

          // Start health monitor to detect dead connections
          this.startHealthMonitor();

          if (onConnected) {
            const user = this.socket.user;
            onConnected(user);
          }
        }

        // Connection closed
        if (connection === 'close') {
          // Stop all timers on disconnect
          this.stopPresenceTimer();
          this.stopHealthMonitor();

          const { shouldReconnect, isLoggedOut, isRestartRequired } = this.handleDisconnect(lastDisconnect);

          logger.warn(`Connection closed for session: ${this.sessionId}`, {
            shouldReconnect,
            isLoggedOut,
            isRestartRequired,
            retryCount: this.retryCount,
          });

          // For restartRequired (expected after QR scan), skip the disconnect webhook.
          // WhatsApp always force-disconnects after QR scan and expects an immediate
          // reconnection using the saved credentials. This is NOT a real disconnect.
          if (isRestartRequired) {
            logger.info(`Restart required (post-QR scan) for session: ${this.sessionId} — reconnecting immediately`);
            await this.reconnect(callbacks, true); // immediate reconnect, no delay
            return;
          }

          // For all other disconnect reasons, notify Laravel
          if (onDisconnected) {
            onDisconnected(shouldReconnect, isLoggedOut);
          }

          if (shouldReconnect) {
            await this.reconnect(callbacks, false);
          }
        }
      });

      // Event: Messages
      if (onMessage) {
        this.socket.ev.on('messages.upsert', async ({ messages, type }) => {
          logger.info(`Messages upsert event received`, { sessionId: this.sessionId, type, count: messages.length });
          // Process both 'notify' and 'append' types - retry messages come as different types
          for (const msg of messages) {
            // Skip own messages and messages without content initially
            if (msg.key?.fromMe) {
              continue;
            }
            logger.info(`Processing incoming message`, {
              sessionId: this.sessionId,
              from: msg.key?.remoteJid,
              fromMe: msg.key?.fromMe,
              hasMessage: !!msg.message,
              type: type,
              messageStubType: msg.messageStubType
            });
            // Only forward messages with actual content (not decryption stubs)
            if (msg.message) {
              onMessage(msg);
            }
          }
        });

        // Also listen for message updates (retries with decrypted content)
        this.socket.ev.on('messages.update', async (updates) => {
          logger.info('Messages update event received', { sessionId: this.sessionId, count: updates.length });
          for (const update of updates) {
            // Check if this update includes decrypted content
            if (update.update?.message && !update.key?.fromMe) {
              logger.info('Message update with content received', {
                sessionId: this.sessionId,
                messageId: update.key?.id,
                hasMessage: !!update.update?.message
              });
              // Create a message object from the update
              const msg = {
                key: update.key,
                message: update.update.message,
                messageTimestamp: update.update.messageTimestamp || Math.floor(Date.now() / 1000),
                pushName: update.update.pushName
              };
              onMessage(msg);
            }
          }
        });
      }

      logger.info(`WhatsApp client initialized for session: ${this.sessionId}`);
    } catch (error) {
      logger.error(`Failed to initialize WhatsApp client: ${error.message}`, {
        sessionId: this.sessionId,
        error: error.stack,
      });
      throw error;
    }
  }

  /**
   * Handle disconnect reasons
   *
   * CRITICAL DESIGN: For recoverable disconnects (connectionClosed, connectionLost, timedOut),
   * we ALWAYS reconnect. These are normal server-side events (idle timeout, network blip).
   * Only loggedOut (user removed device) should stop reconnection permanently.
   * When max retries are exhausted, we reset the counter after a cooldown period
   * instead of giving up — this prevents overnight session loss.
   *
   * @returns {{shouldReconnect: boolean, isLoggedOut: boolean, isRestartRequired: boolean}}
   */
  handleDisconnect(lastDisconnect) {
    const statusCode = lastDisconnect?.error?.output?.statusCode;
    const reason = lastDisconnect?.error;

    logger.info(`Disconnect reason: ${statusCode} (${this.getDisconnectReasonName(statusCode)})`, {
      sessionId: this.sessionId,
      error: reason?.message,
    });

    // Restart required — EXPECTED after QR scan. WhatsApp always force-disconnects
    // after QR scan so the client reconnects with proper credentials.
    // This is NOT an error. Reconnect immediately without delay.
    if (statusCode === DisconnectReason.restartRequired) {
      logger.info(`Restart required for session: ${this.sessionId} (normal post-QR behavior)`);
      return { shouldReconnect: true, isLoggedOut: false, isRestartRequired: true };
    }

    // Logged out — user removed the linked device from WhatsApp app
    // Do NOT reconnect, credentials are invalidated
    // This is the ONLY reason to permanently stop reconnection
    if (statusCode === DisconnectReason.loggedOut) {
      logger.warn(`Session logged out: ${this.sessionId}`);
      return { shouldReconnect: false, isLoggedOut: true, isRestartRequired: false };
    }

    // Connection replaced — another WhatsApp Web session took over
    // Credentials are still valid, reconnect with limited retries
    if (statusCode === DisconnectReason.connectionReplaced) {
      logger.warn(`Connection replaced for session: ${this.sessionId} — another client connected`);
      if (this.retryCount < 5) {
        return { shouldReconnect: true, isLoggedOut: false, isRestartRequired: false };
      }
      // After 5 attempts, stop — user is actively using another device
      logger.warn(`Connection replaced too many times for session: ${this.sessionId}, giving up`);
      return { shouldReconnect: false, isLoggedOut: false, isRestartRequired: false };
    }

    // Connection closed (428) — server closed the connection, usually idle timeout
    // Most common production disconnect. ALWAYS reconnect — never give up.
    // This is what happens overnight when WhatsApp server drops idle connections.
    if (statusCode === DisconnectReason.connectionClosed) {
      // If max retries exhausted, reset counter with cooldown (delayed reconnect)
      if (this.retryCount >= baileysConfig.connection.maxRetries) {
        logger.info(`Connection closed (428) - max retries reached, resetting with cooldown for session: ${this.sessionId}`);
        this.retryCount = 0; // Reset for fresh attempts
        this.useCooldownDelay = true; // Signal to use longer delay
      }
      return { shouldReconnect: true, isLoggedOut: false, isRestartRequired: false };
    }

    // Connection lost (408) / Timed out — network issue
    // ALWAYS reconnect — never give up. Network issues are transient.
    if (statusCode === DisconnectReason.connectionLost || statusCode === DisconnectReason.timedOut) {
      if (this.retryCount >= baileysConfig.connection.maxRetries) {
        logger.info(`Connection lost/timed out - max retries reached, resetting with cooldown for session: ${this.sessionId}`);
        this.retryCount = 0;
        this.useCooldownDelay = true;
      }
      return { shouldReconnect: true, isLoggedOut: false, isRestartRequired: false };
    }

    // Multi-device mismatch (411) — version or device conflict
    if (statusCode === DisconnectReason.multideviceMismatch) {
      logger.warn(`Multi-device mismatch for session: ${this.sessionId}`);
      if (this.retryCount < 3) {
        return { shouldReconnect: true, isLoggedOut: false, isRestartRequired: false };
      }
      return { shouldReconnect: false, isLoggedOut: false, isRestartRequired: false };
    }

    // Bad session — try to recover by clearing sender keys
    // Don't treat as logout, files should be preserved
    if (statusCode === DisconnectReason.badSession) {
      logger.warn(`Bad session detected, attempting recovery: ${this.sessionId}`);
      // Clear sender keys to try to recover
      this.clearSenderKeys().catch(err => {
        logger.error(`Failed to clear sender keys during bad session recovery: ${err.message}`);
      });
      if (this.retryCount < baileysConfig.connection.maxRetries) {
        return { shouldReconnect: true, isLoggedOut: false, isRestartRequired: false };
      }
      return { shouldReconnect: false, isLoggedOut: false, isRestartRequired: false };
    }

    // Unknown reason — ALWAYS reconnect (safer default)
    if (this.retryCount >= baileysConfig.connection.maxRetries) {
      logger.info(`Unknown disconnect ${statusCode} - max retries reached, resetting with cooldown for session: ${this.sessionId}`);
      this.retryCount = 0;
      this.useCooldownDelay = true;
    }
    logger.warn(`Unknown disconnect reason ${statusCode} for session: ${this.sessionId} — will reconnect`);
    return { shouldReconnect: true, isLoggedOut: false, isRestartRequired: false };
  }

  /**
   * Get human-readable name for disconnect reason code
   */
  getDisconnectReasonName(statusCode) {
    const names = {
      [DisconnectReason.badSession]: 'badSession',
      [DisconnectReason.connectionClosed]: 'connectionClosed',
      [DisconnectReason.connectionLost]: 'connectionLost',
      [DisconnectReason.connectionReplaced]: 'connectionReplaced',
      [DisconnectReason.loggedOut]: 'loggedOut',
      [DisconnectReason.multideviceMismatch]: 'multideviceMismatch',
      [DisconnectReason.restartRequired]: 'restartRequired',
      [DisconnectReason.timedOut]: 'timedOut',
    };
    return names[statusCode] || `unknown(${statusCode})`;
  }

  /**
   * Reconnect with exponential backoff
   * @param {Object} callbacks - Event callbacks
   * @param {boolean} immediate - If true, skip delay (for restartRequired)
   */
  async reconnect(callbacks, immediate = false) {
    // Prevent concurrent reconnection attempts
    if (this.isReconnecting) {
      logger.debug(`Already reconnecting session: ${this.sessionId}, skipping duplicate attempt`);
      return;
    }

    this.isReconnecting = true;
    this.retryCount++;

    try {
      if (immediate) {
        // For restartRequired: reconnect immediately with minimal wait
        // Just enough time for credentials to be fully saved to disk
        logger.info(`Immediate reconnect (attempt ${this.retryCount}) for session: ${this.sessionId}`);
        await baileysDelay(1500);
      } else if (this.useCooldownDelay) {
        // Cooldown reconnect: after max retries exhausted, wait longer before fresh attempt
        // This prevents rapid reconnection loops while ensuring the session eventually recovers
        const cooldown = baileysConfig.connection.cooldownInterval || 60000; // 1 minute default
        logger.info(`Cooldown reconnect in ${cooldown}ms for session: ${this.sessionId} (retries were exhausted, starting fresh)`);
        this.useCooldownDelay = false; // Reset flag
        await baileysDelay(cooldown);
      } else {
        // Normal exponential backoff: 3s, 6s, 12s, 24s, 30s (capped)
        const delay = Math.min(
          baileysConfig.connection.reconnectInterval * Math.pow(2, this.retryCount - 1),
          30000
        );
        logger.info(`Reconnecting in ${delay}ms... (Attempt ${this.retryCount}) for session: ${this.sessionId}`);
        await baileysDelay(delay);
      }

      await this.initialize(callbacks);
    } catch (error) {
      this.isReconnecting = false;
      logger.error(`Reconnection failed for session ${this.sessionId}: ${error.message}`);
      // Don't throw — let the health monitor or session keeper handle recovery
    }
  }

  /**
   * Start periodic presence update timer
   * This keeps the WhatsApp session alive by sending "available" status
   * Critical for preventing disconnection after 10-15 minutes of inactivity
   */
  startPresenceTimer() {
    this.stopPresenceTimer(); // Clear any existing timer

    const interval = baileysConfig.connection.presenceUpdateInterval || 25000; // 25 seconds default

    this.presenceTimer = setInterval(async () => {
      try {
        if (this.socket && this.socket.user) {
          // Send "available" presence to keep session alive
          await this.socket.sendPresenceUpdate('available');
          this.lastActivity = Date.now();

          logger.debug(`Presence update sent for session: ${this.sessionId}`);
        }
      } catch (error) {
        logger.warn(`Failed to send presence update: ${error.message}`, {
          sessionId: this.sessionId,
        });
        // Don't stop timer on error - let it retry
      }
    }, interval);

    logger.info(`Presence timer started (interval: ${interval}ms) for session: ${this.sessionId}`);
  }

  /**
   * Stop presence update timer
   */
  stopPresenceTimer() {
    if (this.presenceTimer) {
      clearInterval(this.presenceTimer);
      this.presenceTimer = null;
      logger.debug(`Presence timer stopped for session: ${this.sessionId}`);
    }
  }

  /**
   * Start connection health monitor
   * Periodically checks if the socket is still healthy and attempts recovery if needed
   */
  startHealthMonitor() {
    this.stopHealthMonitor(); // Clear any existing monitor

    const interval = baileysConfig.connection.healthCheckInterval || 30000; // 30 seconds default

    this.healthMonitorTimer = setInterval(async () => {
      try {
        // Skip health checks during reconnection
        if (this.isReconnecting) {
          return;
        }

        // Check if socket exists and has user
        if (!this.socket || !this.socket.user) {
          logger.warn(`Health check: Socket unhealthy for session ${this.sessionId}`);
          this.consecutiveFailures++;

          if (this.consecutiveFailures >= this.maxConsecutiveFailures && this.savedCallbacks) {
            logger.info(`Health check: Attempting automatic reconnection for session ${this.sessionId}`);
            this.stopHealthMonitor();
            this.stopPresenceTimer();

            // Reset retry count for fresh reconnection attempt
            this.retryCount = 0;
            await this.reconnect(this.savedCallbacks, false);
          }
          return;
        }

        // Check WebSocket ready state
        const ws = this.socket.ws;
        if (ws && ws.readyState === 3) { // CLOSED
          logger.warn(`Health check: WebSocket closed for session ${this.sessionId}`);
          this.consecutiveFailures++;

          if (this.consecutiveFailures >= this.maxConsecutiveFailures && this.savedCallbacks) {
            logger.info(`Health check: Attempting automatic reconnection for session ${this.sessionId}`);
            this.stopHealthMonitor();
            this.stopPresenceTimer();
            this.retryCount = 0;
            await this.reconnect(this.savedCallbacks, false);
          }
          return;
        }

        // Connection is healthy - reset failure counter
        if (this.consecutiveFailures > 0) {
          logger.info(`Health check: Connection recovered for session ${this.sessionId}`);
          this.consecutiveFailures = 0;
        }

      } catch (error) {
        logger.warn(`Health monitor error for session ${this.sessionId}: ${error.message}`);
        this.consecutiveFailures++;
      }
    }, interval);

    logger.info(`Health monitor started (interval: ${interval}ms) for session: ${this.sessionId}`);
  }

  /**
   * Stop connection health monitor
   */
  stopHealthMonitor() {
    if (this.healthMonitorTimer) {
      clearInterval(this.healthMonitorTimer);
      this.healthMonitorTimer = null;
      logger.debug(`Health monitor stopped for session: ${this.sessionId}`);
    }
  }

  /**
   * Get connection status
   */
  getStatus() {
    if (!this.socket) {
      return { status: this.isReconnecting ? 'reconnecting' : 'disconnected', isSession: false };
    }

    const states = ['connecting', 'connected', 'disconnecting', 'disconnected'];
    let status = states[this.socket.ws?.readyState] || 'disconnected';

    // If we are reconnecting, report that instead of disconnected
    if (status === 'disconnected' && this.isReconnecting) {
      status = 'reconnecting';
    }

    // Check if authenticated via socket.user
    const hasUser = this.socket.user !== undefined;

    // Also check if we have valid credentials (creds.json with me field or registered)
    const hasValidCreds =
      (this.authState?.creds?.registered === true) ||
      (this.authState?.creds?.me !== undefined);

    // Consider authenticated if user is set OR we have valid credentials
    // Note: We check hasUser first because socket might be reconnecting (readyState temporarily undefined)
    const isAuthenticated = hasUser || (status === 'connected' && hasValidCreds);

    // Get user info from socket.user or from credentials
    let userInfo = this.socket.user || null;
    if (!userInfo && hasValidCreds && this.authState?.creds?.me) {
      userInfo = this.authState.creds.me;
    }

    return {
      status: isAuthenticated ? 'authenticated' : status,
      isSession: isAuthenticated,
      user: userInfo,
    };
  }

  /**
   * Logout and cleanup - removes all session files
   * Use this only for manual logout when user wants to disconnect permanently
   */
  async logout() {
    try {
      if (this.socket) {
        await this.socket.logout();
      }
    } catch (error) {
      logger.error(`Error during logout: ${error.message}`);
    } finally {
      this.cleanup();
    }
  }

  /**
   * Disconnect without deleting session files
   * Use this when connection is lost but we want to preserve credentials for reconnection
   */
  async disconnect() {
    try {
      // Stop all timers
      this.stopPresenceTimer();
      this.stopHealthMonitor();

      // Clean up event listeners and close socket without logout
      if (this.socket) {
        try {
          if (this.socket.ev) this.socket.ev.removeAllListeners();
          this.socket.end(undefined);
        } catch (e) {
          // Ignore close errors
        }
        this.socket = null;
      }

      logger.info(`Session disconnected (files preserved): ${this.sessionId}`);
    } catch (error) {
      logger.error(`Error during disconnect: ${error.message}`);
    }
  }

  /**
   * Cleanup resources
   */
  cleanup() {
    try {
      // Stop all timers
      this.stopPresenceTimer();
      this.stopHealthMonitor();

      // Close socket
      if (this.socket) {
        this.socket.end(undefined);
        this.socket = null;
      }

      // Delete session files
      const sessionPath = this.getSessionPath();
      if (fs.existsSync(sessionPath)) {
        fs.rmSync(sessionPath, { recursive: true, force: true });
      }

      logger.info(`Session cleaned up: ${this.sessionId}`);
    } catch (error) {
      logger.error(`Error during cleanup: ${error.message}`);
    }
  }

  /**
   * Get session directory path
   */
  getSessionPath() {
    return path.join(baileysConfig.storage.sessionsPath, `md_${this.sessionId}`);
  }

  /**
   * Clear sender keys to recover from Bad MAC errors
   * This removes corrupted encryption keys that cause decryption failures
   */
  async clearSenderKeys() {
    try {
      const sessionPath = this.getSessionPath();
      const senderKeysPath = path.join(sessionPath, 'sender-keys');
      const senderKeyMemoryPath = path.join(sessionPath, 'sender-key-memory.json');

      // Remove sender-keys directory if exists
      if (fs.existsSync(senderKeysPath)) {
        fs.rmSync(senderKeysPath, { recursive: true, force: true });
        logger.info(`Cleared sender-keys directory for session: ${this.sessionId}`);
      }

      // Remove sender-key-memory.json if exists
      if (fs.existsSync(senderKeyMemoryPath)) {
        fs.unlinkSync(senderKeyMemoryPath);
        logger.info(`Cleared sender-key-memory for session: ${this.sessionId}`);
      }

      // Also clear from auth state if available
      if (this.authState?.keys) {
        // Clear sender keys from memory
        const keysToRemove = [];
        if (typeof this.authState.keys.get === 'function') {
          // Modern baileys stores keys differently
          logger.info(`Auth state keys structure updated for session: ${this.sessionId}`);
        }
      }

      logger.info(`Sender keys cleared successfully for session: ${this.sessionId}`);
    } catch (error) {
      logger.error(`Failed to clear sender keys: ${error.message}`, {
        sessionId: this.sessionId,
        error: error.stack,
      });
    }
  }

  /**
   * Repair session by clearing problematic data and reconnecting
   * Use this when session has encryption issues
   */
  async repairSession(callbacks = {}) {
    try {
      logger.info(`Starting session repair for: ${this.sessionId}`);

      // Stop all timers
      this.stopPresenceTimer();
      this.stopHealthMonitor();

      // Clear sender keys
      await this.clearSenderKeys();

      // Close current connection gracefully
      if (this.socket) {
        try {
          this.socket.end(undefined);
        } catch (e) {
          // Ignore close errors
        }
        this.socket = null;
      }

      // Wait a moment before reconnecting
      await new Promise(resolve => setTimeout(resolve, 2000));

      // Reinitialize
      this.retryCount = 0;
      this.isReconnecting = false;
      await this.initialize(callbacks);

      logger.info(`Session repair completed for: ${this.sessionId}`);
      return true;
    } catch (error) {
      logger.error(`Session repair failed: ${error.message}`, {
        sessionId: this.sessionId,
        error: error.stack,
      });
      return false;
    }
  }
}

export default WhatsAppClient;
