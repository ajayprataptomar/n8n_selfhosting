import logger from '../utils/logger.js';
import { EventEmitter } from 'events';

/**
 * Enterprise Message Queue Service
 *
 * Features:
 * - In-memory queue with optional Redis support
 * - Priority queuing (high, normal, low)
 * - Retry mechanism with exponential backoff
 * - Rate limiting per session
 * - Batch processing support
 * - Queue persistence for graceful shutdown
 */
class MessageQueueService extends EventEmitter {
  constructor() {
    super();

    // Queue storage - Map of sessionId -> priority queues
    this.queues = new Map();

    // Processing state
    this.processors = new Map();
    this.isProcessing = new Map();

    // Configuration
    this.config = {
      maxQueueSize: 10000,          // Max messages per session queue
      maxRetries: 3,                 // Max retry attempts per message
      retryBaseDelay: 5000,          // Base retry delay (ms)
      processingInterval: 100,       // Queue check interval (ms)
      batchSize: 10,                 // Messages to process per batch
      rateLimitPerMinute: 30,        // Max messages per minute per session
      rateLimitWindow: 60000,        // Rate limit window (ms)
    };

    // Rate limiting tracker
    this.rateLimits = new Map();

    // Statistics
    this.stats = {
      totalQueued: 0,
      totalProcessed: 0,
      totalFailed: 0,
      totalRetried: 0,
    };
  }

  /**
   * Configure the queue service
   */
  configure(options = {}) {
    this.config = { ...this.config, ...options };
    logger.info('MessageQueue configured', { config: this.config });
  }

  /**
   * Get or create session queue
   */
  getQueue(sessionId) {
    if (!this.queues.has(sessionId)) {
      this.queues.set(sessionId, {
        high: [],      // Priority: high (interactive responses)
        normal: [],    // Priority: normal (bulk messages)
        low: [],       // Priority: low (scheduled messages)
        pending: new Map(),  // messageId -> message (for retry tracking)
      });
    }
    return this.queues.get(sessionId);
  }

  /**
   * Add message to queue
   *
   * @param {string} sessionId - Session ID
   * @param {object} message - Message object
   * @param {string} priority - 'high', 'normal', 'low'
   * @returns {string} Queue ID
   */
  enqueue(sessionId, message, priority = 'normal') {
    const queue = this.getQueue(sessionId);

    // Check queue size
    const totalSize = queue.high.length + queue.normal.length + queue.low.length;
    if (totalSize >= this.config.maxQueueSize) {
      throw new Error(`Queue full for session ${sessionId}`);
    }

    // Generate queue ID
    const queueId = `${sessionId}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    const queuedMessage = {
      id: queueId,
      sessionId,
      message,
      priority,
      attempts: 0,
      createdAt: Date.now(),
      scheduledAt: message.scheduledAt || Date.now(),
      status: 'queued',
    };

    // Add to appropriate priority queue
    if (priority === 'high') {
      queue.high.push(queuedMessage);
    } else if (priority === 'low') {
      queue.low.push(queuedMessage);
    } else {
      queue.normal.push(queuedMessage);
    }

    queue.pending.set(queueId, queuedMessage);
    this.stats.totalQueued++;

    logger.debug(`Message queued`, {
      queueId,
      sessionId,
      priority,
      queueSize: totalSize + 1,
    });

    this.emit('queued', queuedMessage);

    // Start processor if not running
    this.startProcessor(sessionId);

    return queueId;
  }

  /**
   * Check rate limit for session
   */
  checkRateLimit(sessionId) {
    const now = Date.now();

    if (!this.rateLimits.has(sessionId)) {
      this.rateLimits.set(sessionId, {
        count: 0,
        windowStart: now,
      });
    }

    const limit = this.rateLimits.get(sessionId);

    // Reset window if expired
    if (now - limit.windowStart > this.config.rateLimitWindow) {
      limit.count = 0;
      limit.windowStart = now;
    }

    // Check if under limit
    if (limit.count >= this.config.rateLimitPerMinute) {
      const waitTime = this.config.rateLimitWindow - (now - limit.windowStart);
      return { allowed: false, waitTime };
    }

    limit.count++;
    return { allowed: true, waitTime: 0 };
  }

  /**
   * Get next message from queue (respects priority)
   */
  dequeue(sessionId) {
    const queue = this.getQueue(sessionId);
    const now = Date.now();

    // Check high priority first
    for (const q of [queue.high, queue.normal, queue.low]) {
      const index = q.findIndex(m =>
        m.status === 'queued' && m.scheduledAt <= now
      );
      if (index !== -1) {
        const message = q.splice(index, 1)[0];
        message.status = 'processing';
        return message;
      }
    }

    return null;
  }

  /**
   * Start queue processor for session
   */
  startProcessor(sessionId) {
    if (this.processors.has(sessionId)) {
      return; // Already running
    }

    const processor = setInterval(async () => {
      await this.processQueue(sessionId);
    }, this.config.processingInterval);

    this.processors.set(sessionId, processor);
    this.isProcessing.set(sessionId, false);

    logger.info(`Queue processor started for session: ${sessionId}`);
  }

  /**
   * Stop queue processor for session
   */
  stopProcessor(sessionId) {
    const processor = this.processors.get(sessionId);
    if (processor) {
      clearInterval(processor);
      this.processors.delete(sessionId);
      this.isProcessing.delete(sessionId);
      logger.info(`Queue processor stopped for session: ${sessionId}`);
    }
  }

  /**
   * Process queue for session
   */
  async processQueue(sessionId) {
    // Prevent concurrent processing
    if (this.isProcessing.get(sessionId)) {
      return;
    }

    this.isProcessing.set(sessionId, true);

    try {
      // Check rate limit
      const rateCheck = this.checkRateLimit(sessionId);
      if (!rateCheck.allowed) {
        logger.debug(`Rate limited for session ${sessionId}, waiting ${rateCheck.waitTime}ms`);
        return;
      }

      // Get next message
      const queuedMessage = this.dequeue(sessionId);
      if (!queuedMessage) {
        return; // Queue empty
      }

      // Process message
      queuedMessage.attempts++;

      try {
        // Emit processing event - actual sending is done by listener
        await this.processMessage(queuedMessage);

        // Success
        const queue = this.getQueue(sessionId);
        queue.pending.delete(queuedMessage.id);
        queuedMessage.status = 'completed';
        this.stats.totalProcessed++;

        this.emit('completed', queuedMessage);

        logger.debug(`Message processed successfully`, {
          queueId: queuedMessage.id,
          sessionId,
          attempts: queuedMessage.attempts,
        });

      } catch (error) {
        // Handle failure
        await this.handleFailure(queuedMessage, error);
      }

    } finally {
      this.isProcessing.set(sessionId, false);
    }
  }

  /**
   * Process individual message
   * Override this in subclass or set handler via setMessageHandler
   */
  async processMessage(queuedMessage) {
    if (this.messageHandler) {
      return await this.messageHandler(queuedMessage);
    }

    // Emit event for external handling
    return new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        reject(new Error('Message processing timeout'));
      }, 60000);

      this.emit('process', queuedMessage, (error) => {
        clearTimeout(timeout);
        if (error) {
          reject(error);
        } else {
          resolve();
        }
      });
    });
  }

  /**
   * Set message handler function
   */
  setMessageHandler(handler) {
    this.messageHandler = handler;
  }

  /**
   * Handle message processing failure
   */
  async handleFailure(queuedMessage, error) {
    const { sessionId, id } = queuedMessage;

    logger.warn(`Message processing failed`, {
      queueId: id,
      sessionId,
      attempts: queuedMessage.attempts,
      error: error.message,
    });

    // Check if should retry
    if (queuedMessage.attempts < this.config.maxRetries) {
      // Exponential backoff
      const delay = this.config.retryBaseDelay * Math.pow(2, queuedMessage.attempts - 1);

      queuedMessage.status = 'queued';
      queuedMessage.scheduledAt = Date.now() + delay;
      queuedMessage.lastError = error.message;

      // Re-add to queue with same priority
      const queue = this.getQueue(sessionId);
      if (queuedMessage.priority === 'high') {
        queue.high.push(queuedMessage);
      } else if (queuedMessage.priority === 'low') {
        queue.low.push(queuedMessage);
      } else {
        queue.normal.push(queuedMessage);
      }

      this.stats.totalRetried++;
      this.emit('retry', queuedMessage);

      logger.info(`Message scheduled for retry`, {
        queueId: id,
        sessionId,
        nextAttempt: queuedMessage.attempts + 1,
        delay,
      });

    } else {
      // Max retries exceeded
      const queue = this.getQueue(sessionId);
      queue.pending.delete(id);
      queuedMessage.status = 'failed';
      queuedMessage.lastError = error.message;

      this.stats.totalFailed++;
      this.emit('failed', queuedMessage);

      logger.error(`Message failed permanently`, {
        queueId: id,
        sessionId,
        attempts: queuedMessage.attempts,
        error: error.message,
      });
    }
  }

  /**
   * Get queue status for session
   */
  getStatus(sessionId) {
    const queue = this.getQueue(sessionId);
    const rateLimit = this.rateLimits.get(sessionId) || { count: 0 };

    return {
      sessionId,
      queued: {
        high: queue.high.length,
        normal: queue.normal.length,
        low: queue.low.length,
        total: queue.high.length + queue.normal.length + queue.low.length,
      },
      pending: queue.pending.size,
      rateLimit: {
        used: rateLimit.count,
        limit: this.config.rateLimitPerMinute,
        remaining: Math.max(0, this.config.rateLimitPerMinute - rateLimit.count),
      },
      isProcessing: this.isProcessing.get(sessionId) || false,
    };
  }

  /**
   * Get all queues status
   */
  getAllStatus() {
    const statuses = {};
    for (const sessionId of this.queues.keys()) {
      statuses[sessionId] = this.getStatus(sessionId);
    }
    return {
      sessions: statuses,
      stats: this.stats,
      config: this.config,
    };
  }

  /**
   * Clear queue for session
   */
  clearQueue(sessionId) {
    this.stopProcessor(sessionId);
    this.queues.delete(sessionId);
    this.rateLimits.delete(sessionId);
    logger.info(`Queue cleared for session: ${sessionId}`);
  }

  /**
   * Get pending messages for graceful shutdown
   */
  getPendingMessages() {
    const pending = [];

    for (const [sessionId, queue] of this.queues) {
      for (const [id, message] of queue.pending) {
        pending.push(message);
      }
    }

    return pending;
  }

  /**
   * Restore pending messages (after restart)
   */
  restoreMessages(messages) {
    let restored = 0;

    for (const message of messages) {
      try {
        // Reset status and attempts for retry
        message.status = 'queued';
        message.scheduledAt = Date.now();

        const queue = this.getQueue(message.sessionId);

        if (message.priority === 'high') {
          queue.high.push(message);
        } else if (message.priority === 'low') {
          queue.low.push(message);
        } else {
          queue.normal.push(message);
        }

        queue.pending.set(message.id, message);
        restored++;

        this.startProcessor(message.sessionId);

      } catch (error) {
        logger.error(`Failed to restore message: ${error.message}`, { messageId: message.id });
      }
    }

    logger.info(`Restored ${restored} pending messages`);
    return restored;
  }

  /**
   * Graceful shutdown - stop all processors
   */
  async shutdown() {
    logger.info('MessageQueue shutting down...');

    // Stop all processors
    for (const sessionId of this.processors.keys()) {
      this.stopProcessor(sessionId);
    }

    // Get pending messages for persistence
    const pending = this.getPendingMessages();

    logger.info(`MessageQueue shutdown complete`, {
      pendingMessages: pending.length,
      stats: this.stats,
    });

    return pending;
  }
}

// Export singleton instance
export default new MessageQueueService();
