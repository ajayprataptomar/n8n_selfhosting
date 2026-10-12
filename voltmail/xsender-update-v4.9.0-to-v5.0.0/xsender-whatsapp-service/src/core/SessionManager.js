import WhatsAppClient from './WhatsAppClient.js';
import logger from '../utils/logger.js';
import fs from 'fs';
import path from 'path';
import baileysConfig from '../config/baileys.config.js';
import WebhookService from '../services/WebhookService.js';

/**
 * Session Manager - Manages multiple WhatsApp sessions
 *
 * Enterprise features:
 * - Automatic session restoration from disk
 * - Periodic session keeper to recover dropped sessions
 * - Never permanently lose sessions (only explicit logout removes them)
 */
class SessionManager {
  constructor() {
    this.sessions = new Map();
    this.retries = new Map();
    // Session keeper timer — periodically restores sessions that fell out of memory
    this.sessionKeeperTimer = null;
  }

  /**
   * Check if session exists
   */
  exists(sessionId) {
    return this.sessions.has(sessionId);
  }

  /**
   * Get session
   */
  get(sessionId) {
    return this.sessions.get(sessionId) || null;
  }

  /**
   * Create new session
   */
  async create(sessionId, callbacks = {}) {
    try {
      if (this.exists(sessionId)) {
        throw new Error('Session already exists');
      }

      const client = new WhatsAppClient(sessionId);

      // Create message handler that forwards to Laravel
      const messageHandler = WebhookService.createMessageHandler(sessionId);

      // Initialize with callbacks
      await client.initialize({
        onQR: (qr) => {
          logger.info(`QR generated for session: ${sessionId}`);
          // Notify Laravel that QR is being shown
          WebhookService.notifySessionStatus(sessionId, 'qr', null);
          if (callbacks.onQR) {
            callbacks.onQR(qr);
          }
        },
        onConnected: (user) => {
          logger.info(`Session connected: ${sessionId}`, { user });
          // Notify Laravel that session is connected
          WebhookService.notifySessionStatus(sessionId, 'connected', user);
          if (callbacks.onConnected) {
            callbacks.onConnected(user);
          }
        },
        onDisconnected: (shouldReconnect, isLoggedOut = false) => {
          // Notify Laravel that session is disconnected
          const status = shouldReconnect ? 'disconnected' : (isLoggedOut ? 'logged_out' : 'disconnected');
          WebhookService.notifySessionStatus(sessionId, status, null);

          if (!shouldReconnect) {
            if (isLoggedOut) {
              // Only delete session files if explicitly logged out by the user
              // This is the ONLY case where we permanently remove a session
              this.delete(sessionId, true);
            }
            // For non-logout disconnects (connectionReplaced after max retries, etc.):
            // Do NOT remove from memory. The session keeper will attempt recovery.
            // This prevents the "session lost overnight" problem.
          }
          if (callbacks.onDisconnected) {
            callbacks.onDisconnected(shouldReconnect);
          }
        },
        // Always attach message handler for incoming messages
        onMessage: async (msg) => {
          // Forward to Laravel webhook
          await messageHandler(msg);
          // Also call custom callback if provided
          if (callbacks.onMessage) {
            callbacks.onMessage(msg);
          }
        },
      });

      this.sessions.set(sessionId, client);
      this.retries.delete(sessionId);

      logger.info(`Session created: ${sessionId}`);
      return client;
    } catch (error) {
      logger.error(`Failed to create session: ${error.message}`, { sessionId });
      throw error;
    }
  }

  /**
   * Delete session - removes from memory and optionally deletes session files
   * @param {string} sessionId - Session ID to delete
   * @param {boolean} deleteFiles - Whether to delete session files (default: true for manual deletion)
   */
  async delete(sessionId, deleteFiles = true) {
    try {
      const client = this.get(sessionId);

      if (client) {
        if (deleteFiles) {
          // Full logout - removes session files
          await client.logout();
        } else {
          // Just disconnect - keep session files for reconnection
          await client.disconnect();
        }
        this.sessions.delete(sessionId);
        this.retries.delete(sessionId);
        logger.info(`Session deleted: ${sessionId}`, { filesDeleted: deleteFiles });
      }
    } catch (error) {
      logger.error(`Failed to delete session: ${error.message}`, { sessionId });
      throw error;
    }
  }

  /**
   * Remove session from memory without deleting files
   * Used when session disconnects but we want to preserve credentials for reconnection
   */
  async remove(sessionId) {
    try {
      const client = this.get(sessionId);

      if (client) {
        // Just stop timers and close socket, don't delete files
        client.stopPresenceTimer();
        if (client.socket) {
          try {
            client.socket.end(undefined);
          } catch (e) {
            // Ignore close errors
          }
          client.socket = null;
        }
        this.sessions.delete(sessionId);
        this.retries.delete(sessionId);
        logger.info(`Session removed from memory (files preserved): ${sessionId}`);
      }
    } catch (error) {
      logger.error(`Failed to remove session: ${error.message}`, { sessionId });
    }
  }

  /**
   * Get session status
   */
  getStatus(sessionId) {
    const client = this.get(sessionId);

    if (!client) {
      return { status: 'disconnected', isSession: false };
    }

    return client.getStatus();
  }

  /**
   * Get all sessions
   */
  getAll() {
    return Array.from(this.sessions.keys());
  }

  /**
   * Restore sessions on startup
   */
  async restoreSessions() {
    try {
      const sessionsPath = baileysConfig.storage.sessionsPath;

      if (!fs.existsSync(sessionsPath)) {
        fs.mkdirSync(sessionsPath, { recursive: true });
        // Sync empty sessions with Laravel
        await this.syncWithLaravel();
        return;
      }

      const files = fs.readdirSync(sessionsPath);

      for (const file of files) {
        // Only restore md_ prefixed directories
        if (!file.startsWith('md_') || file.endsWith('_store.json')) {
          continue;
        }

        const sessionId = file.replace('md_', '');
        const sessionPath = path.join(sessionsPath, file);

        // Check if it's a directory with creds.json
        const credsPath = path.join(sessionPath, 'creds.json');
        if (fs.existsSync(credsPath) && fs.statSync(sessionPath).isDirectory()) {
          try {
            logger.info(`Restoring session: ${sessionId}`);
            await this.create(sessionId);
          } catch (error) {
            logger.error(`Failed to restore session ${sessionId}: ${error.message}`);
          }
        }
      }

      logger.info(`Sessions restored. Active: ${this.sessions.size}`);

      // Sync all session statuses with Laravel after restore
      await this.syncWithLaravel();

      // Start the session keeper to prevent overnight session loss
      this.startSessionKeeper();
    } catch (error) {
      logger.error(`Failed to restore sessions: ${error.message}`);
    }
  }

  /**
   * Session Keeper — Enterprise session persistence
   *
   * Periodically scans session files on disk and restores any that have
   * fallen out of memory due to disconnect + max retry exhaustion.
   * This is the safety net that prevents overnight session loss.
   *
   * Also checks in-memory sessions that are disconnected and attempts
   * to reconnect them if they have valid credentials.
   */
  startSessionKeeper() {
    if (this.sessionKeeperTimer) {
      clearInterval(this.sessionKeeperTimer);
    }

    const interval = baileysConfig.connection.sessionKeeperInterval || 300000; // 5 minutes default

    logger.info(`Session keeper started (interval: ${interval / 1000}s)`);

    this.sessionKeeperTimer = setInterval(async () => {
      try {
        await this.runSessionKeeper();
      } catch (error) {
        logger.error(`Session keeper error: ${error.message}`);
      }
    }, interval);
  }

  /**
   * Run a single session keeper cycle
   */
  async runSessionKeeper() {
    const sessionsPath = baileysConfig.storage.sessionsPath;

    if (!fs.existsSync(sessionsPath)) {
      return;
    }

    const files = fs.readdirSync(sessionsPath);
    let restored = 0;
    let reconnected = 0;

    for (const file of files) {
      if (!file.startsWith('md_') || file.endsWith('_store.json')) {
        continue;
      }

      const sessionId = file.replace('md_', '');
      const credsPath = path.join(sessionsPath, file, 'creds.json');

      if (!fs.existsSync(credsPath)) {
        continue;
      }

      // Check if session exists in memory
      const client = this.sessions.get(sessionId);

      if (!client) {
        // Session has valid creds on disk but is NOT in memory — restore it
        logger.info(`[SessionKeeper] Restoring dropped session: ${sessionId}`);
        try {
          await this.create(sessionId);
          restored++;
        } catch (error) {
          // "Session already exists" can happen in race conditions — ignore
          if (!error.message.includes('already exists')) {
            logger.error(`[SessionKeeper] Failed to restore session ${sessionId}: ${error.message}`);
          }
        }
      } else if (!client.socket || !client.socket.user) {
        // Session is in memory but disconnected — attempt reconnection
        if (!client.isReconnecting && client.savedCallbacks) {
          logger.info(`[SessionKeeper] Reconnecting unhealthy session: ${sessionId}`);
          try {
            client.retryCount = 0;
            client.consecutiveFailures = 0;
            client.isReconnecting = false;
            await client.initialize(client.savedCallbacks);
            reconnected++;
          } catch (error) {
            logger.error(`[SessionKeeper] Failed to reconnect session ${sessionId}: ${error.message}`);
          }
        }
      }
    }

    if (restored > 0 || reconnected > 0) {
      logger.info(`[SessionKeeper] Cycle complete — restored: ${restored}, reconnected: ${reconnected}`);
      // Sync with Laravel after changes
      await this.syncWithLaravel();
    }
  }

  /**
   * Sync all session statuses with Laravel
   * This ensures Laravel database is consistent with actual session states
   */
  async syncWithLaravel() {
    try {
      const sessions = [];

      for (const [sessionId, client] of this.sessions) {
        const status = client.getStatus();
        sessions.push({
          id: sessionId,
          status: status.isSession ? 'connected' : 'disconnected',
          user: status.user || null,
        });
      }

      logger.info(`Syncing ${sessions.length} sessions with Laravel`);
      await WebhookService.syncSessionsWithLaravel(sessions);
    } catch (error) {
      logger.error(`Failed to sync sessions with Laravel: ${error.message}`);
    }
  }

  /**
   * Cleanup all sessions
   */
  async cleanup() {
    logger.info('Cleaning up all sessions...');

    // Stop session keeper
    if (this.sessionKeeperTimer) {
      clearInterval(this.sessionKeeperTimer);
      this.sessionKeeperTimer = null;
    }

    // Sessions will be cleaned up automatically on process exit
    logger.info('All sessions cleaned up');
  }
}

// Export singleton instance
export default new SessionManager();
