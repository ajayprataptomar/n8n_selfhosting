import logger from '../utils/logger.js';
import SessionManager from '../core/SessionManager.js';
import MessageQueueService from './MessageQueueService.js';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Graceful Shutdown Service
 *
 * Handles proper cleanup when the service is shutting down:
 * - Disconnects all WhatsApp sessions gracefully (preserving credentials)
 * - Persists pending message queue to disk
 * - Waits for in-flight operations to complete
 * - Cleans up resources
 */
class GracefulShutdownService {
  constructor() {
    this.isShuttingDown = false;
    this.shutdownTimeout = 30000; // 30 seconds max shutdown time
    this.pendingQueuePath = path.join(__dirname, '../../storage/pending_queue.json');
  }

  /**
   * Initialize shutdown handlers
   */
  initialize() {
    // Handle various shutdown signals
    process.on('SIGTERM', () => this.handleShutdown('SIGTERM'));
    process.on('SIGINT', () => this.handleShutdown('SIGINT'));

    // Handle uncaught exceptions
    process.on('uncaughtException', async (error) => {
      logger.error('Uncaught exception:', { error: error.message, stack: error.stack });
      await this.handleShutdown('uncaughtException');
    });

    // Handle unhandled promise rejections
    process.on('unhandledRejection', async (reason, promise) => {
      logger.error('Unhandled rejection:', { reason: String(reason) });
      // Don't shutdown on unhandled rejection, just log
    });

    logger.info('Graceful shutdown handlers initialized');
  }

  /**
   * Handle shutdown signal
   */
  async handleShutdown(signal) {
    if (this.isShuttingDown) {
      logger.warn('Shutdown already in progress, ignoring signal:', { signal });
      return;
    }

    this.isShuttingDown = true;
    logger.info(`Received ${signal} signal. Starting graceful shutdown...`);

    // Set a hard timeout for shutdown
    const hardTimeout = setTimeout(() => {
      logger.error('Graceful shutdown timed out, forcing exit');
      process.exit(1);
    }, this.shutdownTimeout);

    try {
      // Step 1: Stop accepting new requests (handled by Express)
      logger.info('Step 1: Stopping new request acceptance...');

      // Step 2: Persist pending messages from queue
      logger.info('Step 2: Persisting pending message queue...');
      await this.persistPendingQueue();

      // Step 3: Disconnect all WhatsApp sessions gracefully
      logger.info('Step 3: Disconnecting WhatsApp sessions...');
      await this.disconnectAllSessions();

      // Step 4: Clean up resources
      logger.info('Step 4: Cleaning up resources...');
      await this.cleanup();

      // Clear the hard timeout
      clearTimeout(hardTimeout);

      logger.info('Graceful shutdown completed successfully');
      process.exit(0);

    } catch (error) {
      clearTimeout(hardTimeout);
      logger.error('Error during graceful shutdown:', { error: error.message });
      process.exit(1);
    }
  }

  /**
   * Persist pending messages to disk
   */
  async persistPendingQueue() {
    try {
      const pending = await MessageQueueService.shutdown();

      if (pending.length > 0) {
        // Ensure storage directory exists
        const storageDir = path.dirname(this.pendingQueuePath);
        if (!fs.existsSync(storageDir)) {
          fs.mkdirSync(storageDir, { recursive: true });
        }

        // Write pending messages to file
        fs.writeFileSync(
          this.pendingQueuePath,
          JSON.stringify(pending, null, 2),
          'utf8'
        );

        logger.info(`Persisted ${pending.length} pending messages to disk`);
      } else {
        // Remove old file if no pending messages
        if (fs.existsSync(this.pendingQueuePath)) {
          fs.unlinkSync(this.pendingQueuePath);
        }
        logger.info('No pending messages to persist');
      }

    } catch (error) {
      logger.error('Failed to persist pending queue:', { error: error.message });
    }
  }

  /**
   * Restore pending messages on startup
   */
  async restorePendingQueue() {
    try {
      if (fs.existsSync(this.pendingQueuePath)) {
        const data = fs.readFileSync(this.pendingQueuePath, 'utf8');
        const pending = JSON.parse(data);

        if (pending.length > 0) {
          const restored = MessageQueueService.restoreMessages(pending);
          logger.info(`Restored ${restored} pending messages from disk`);
        }

        // Remove file after restore
        fs.unlinkSync(this.pendingQueuePath);

      } else {
        logger.debug('No pending queue file found');
      }

    } catch (error) {
      logger.error('Failed to restore pending queue:', { error: error.message });
    }
  }

  /**
   * Disconnect all WhatsApp sessions gracefully
   */
  async disconnectAllSessions() {
    const sessions = SessionManager.getAll();

    if (sessions.length === 0) {
      logger.info('No active sessions to disconnect');
      return;
    }

    logger.info(`Disconnecting ${sessions.length} sessions...`);

    const disconnectPromises = sessions.map(async (sessionId) => {
      try {
        // Use remove instead of delete to preserve session files
        await SessionManager.remove(sessionId);
        logger.info(`Session disconnected: ${sessionId}`);
      } catch (error) {
        logger.error(`Failed to disconnect session ${sessionId}:`, { error: error.message });
      }
    });

    // Wait for all disconnections with a timeout
    await Promise.race([
      Promise.all(disconnectPromises),
      new Promise((resolve) => setTimeout(resolve, 10000)) // 10 second timeout
    ]);

    logger.info('All sessions disconnected');
  }

  /**
   * Clean up resources
   */
  async cleanup() {
    // Any additional cleanup tasks
    logger.info('Cleanup completed');
  }

  /**
   * Check if service is shutting down
   */
  isInShutdown() {
    return this.isShuttingDown;
  }
}

// Export singleton instance
export default new GracefulShutdownService();
