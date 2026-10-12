import axios from 'axios';
import { EventEmitter } from 'events';
import runtimeConfig from '../config/runtime.config.js';
import logger from '../utils/logger.js';

/**
 * Enterprise Webhook Event Service
 *
 * Features:
 * - Event-based architecture (similar to WML approach)
 * - Event filtering by type
 * - Batch event delivery
 * - Retry mechanism with exponential backoff
 * - Event queue for offline handling
 */
class WebhookEventService extends EventEmitter {
  constructor() {
    super();

    // Event types supported
    this.EVENT_TYPES = {
      MESSAGE_RECEIVED: 'message.received',
      MESSAGE_SENT: 'message.sent',
      MESSAGE_DELIVERED: 'message.delivered',
      MESSAGE_READ: 'message.read',
      MESSAGE_FAILED: 'message.failed',
      SESSION_CONNECTED: 'session.connected',
      SESSION_DISCONNECTED: 'session.disconnected',
      SESSION_QR: 'session.qr',
      SESSION_LOGGED_OUT: 'session.logged_out',
      PRESENCE_UPDATE: 'presence.update',
      GROUP_UPDATE: 'group.update',
    };

    // Configuration
    this.config = {
      maxRetries: 3,
      retryBaseDelay: 1000,
      batchSize: 10,
      batchTimeout: 5000, // Flush batch after 5 seconds
      timeout: 30000,
    };

    // Event queue for batching
    this.eventQueue = [];
    this.batchTimer = null;

    // Event filters per webhook (allows subscribing to specific events)
    this.webhookFilters = new Map();

    // Statistics
    this.stats = {
      eventsEmitted: 0,
      eventsDelivered: 0,
      eventsFailed: 0,
      eventsRetried: 0,
    };
  }

  /**
   * Configure event filters for a webhook
   *
   * @param {string} webhookId - Unique webhook identifier
   * @param {Array<string>} allowedEvents - Array of event types to allow
   */
  setEventFilter(webhookId, allowedEvents) {
    this.webhookFilters.set(webhookId, new Set(allowedEvents));
    logger.info(`Event filter set for webhook: ${webhookId}`, { events: allowedEvents });
  }

  /**
   * Check if event should be delivered to webhook
   */
  shouldDeliverEvent(webhookId, eventType) {
    const filter = this.webhookFilters.get(webhookId);

    // No filter = allow all events
    if (!filter) {
      return true;
    }

    // Check if event type is in allowed list
    return filter.has(eventType) || filter.has('*');
  }

  /**
   * Emit an event (adds to queue for delivery)
   *
   * @param {string} eventType - Event type from EVENT_TYPES
   * @param {string} sessionId - Session ID
   * @param {object} data - Event data
   */
  emitEvent(eventType, sessionId, data) {
    const event = {
      id: `${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
      type: eventType,
      sessionId,
      data,
      timestamp: new Date().toISOString(),
      attempts: 0,
    };

    this.eventQueue.push(event);
    this.stats.eventsEmitted++;

    // Emit locally for internal listeners
    this.emit(eventType, event);
    this.emit('event', event);

    logger.debug(`Event queued: ${eventType}`, { sessionId, eventId: event.id });

    // Start batch timer if not running
    this.scheduleBatchDelivery();

    // If queue is full, deliver immediately
    if (this.eventQueue.length >= this.config.batchSize) {
      this.deliverBatch();
    }
  }

  /**
   * Schedule batch delivery
   */
  scheduleBatchDelivery() {
    if (this.batchTimer) {
      return;
    }

    this.batchTimer = setTimeout(() => {
      this.deliverBatch();
    }, this.config.batchTimeout);
  }

  /**
   * Deliver batched events
   */
  async deliverBatch() {
    // Clear timer
    if (this.batchTimer) {
      clearTimeout(this.batchTimer);
      this.batchTimer = null;
    }

    if (this.eventQueue.length === 0) {
      return;
    }

    // Get events to deliver
    const events = this.eventQueue.splice(0, this.config.batchSize);

    // Get webhook URL
    const webhookUrl = this.getWebhookUrl();
    if (!webhookUrl) {
      logger.warn('Webhook URL not configured, events dropped', { count: events.length });
      return;
    }

    // Deliver each event
    for (const event of events) {
      await this.deliverEvent(event, webhookUrl);
    }
  }

  /**
   * Deliver single event with retry
   */
  async deliverEvent(event, webhookUrl, attempt = 1) {
    try {
      const response = await axios.post(
        webhookUrl,
        {
          event: event.type,
          sessionId: event.sessionId,
          data: event.data,
          timestamp: event.timestamp,
          eventId: event.id,
        },
        {
          headers: {
            'Content-Type': 'application/json',
            'X-API-Key': this.getApiKey(),
            'X-Event-Type': event.type,
            'X-Session-ID': event.sessionId,
          },
          timeout: this.config.timeout,
        }
      );

      if (response.status === 200) {
        this.stats.eventsDelivered++;
        logger.debug(`Event delivered: ${event.type}`, { eventId: event.id });
        return true;
      }

      throw new Error(`Unexpected status: ${response.status}`);

    } catch (error) {
      logger.warn(`Event delivery failed (attempt ${attempt}):`, {
        eventId: event.id,
        eventType: event.type,
        error: error.message,
      });

      // Retry if attempts remaining
      if (attempt < this.config.maxRetries) {
        this.stats.eventsRetried++;
        const delay = this.config.retryBaseDelay * Math.pow(2, attempt - 1);

        await new Promise(resolve => setTimeout(resolve, delay));
        return this.deliverEvent(event, webhookUrl, attempt + 1);
      }

      this.stats.eventsFailed++;
      logger.error(`Event delivery failed permanently:`, {
        eventId: event.id,
        eventType: event.type,
      });

      return false;
    }
  }

  /**
   * Get Laravel webhook URL
   */
  getWebhookUrl() {
    let domain = runtimeConfig.get('domain');
    if (!domain) {
      return null;
    }

    if (domain.startsWith('http://') || domain.startsWith('https://')) {
      domain = domain.replace(/\/+$/, '');
      return `${domain}/api/whatsapp/node/webhook`;
    }

    const protocol = domain.includes('localhost') || domain.includes('.test') ? 'http' : 'https';
    return `${protocol}://${domain}/api/whatsapp/node/webhook`;
  }

  /**
   * Get API key
   */
  getApiKey() {
    return runtimeConfig.get('apiKey') || '';
  }

  /**
   * Emit message received event
   */
  onMessageReceived(sessionId, message, extractedData) {
    this.emitEvent(this.EVENT_TYPES.MESSAGE_RECEIVED, sessionId, {
      messageId: extractedData.messageId,
      from: extractedData.from,
      type: extractedData.type,
      text: extractedData.text,
      caption: extractedData.caption,
      isGroup: extractedData.isGroup,
      pushName: extractedData.pushName,
      timestamp: extractedData.timestamp,
    });
  }

  /**
   * Emit message sent event
   */
  onMessageSent(sessionId, receiver, messageId, type = 'text') {
    this.emitEvent(this.EVENT_TYPES.MESSAGE_SENT, sessionId, {
      messageId,
      receiver,
      type,
    });
  }

  /**
   * Emit message failed event
   */
  onMessageFailed(sessionId, receiver, error, type = 'text') {
    this.emitEvent(this.EVENT_TYPES.MESSAGE_FAILED, sessionId, {
      receiver,
      type,
      error: error.message || error,
    });
  }

  /**
   * Emit session connected event
   */
  onSessionConnected(sessionId, user) {
    this.emitEvent(this.EVENT_TYPES.SESSION_CONNECTED, sessionId, {
      user: user ? {
        id: user.id,
        name: user.name,
        phone: user.id?.split(':')[0],
      } : null,
    });
  }

  /**
   * Emit session disconnected event
   */
  onSessionDisconnected(sessionId, reason = null) {
    this.emitEvent(this.EVENT_TYPES.SESSION_DISCONNECTED, sessionId, {
      reason,
    });
  }

  /**
   * Emit session QR event
   */
  onSessionQR(sessionId) {
    this.emitEvent(this.EVENT_TYPES.SESSION_QR, sessionId, {});
  }

  /**
   * Emit session logged out event
   */
  onSessionLoggedOut(sessionId) {
    this.emitEvent(this.EVENT_TYPES.SESSION_LOGGED_OUT, sessionId, {});
  }

  /**
   * Get statistics
   */
  getStats() {
    return {
      ...this.stats,
      queueLength: this.eventQueue.length,
      webhookFilters: this.webhookFilters.size,
    };
  }

  /**
   * Flush all pending events immediately
   */
  async flush() {
    while (this.eventQueue.length > 0) {
      await this.deliverBatch();
    }
  }

  /**
   * Shutdown service
   */
  async shutdown() {
    // Deliver remaining events
    await this.flush();

    // Clear timer
    if (this.batchTimer) {
      clearTimeout(this.batchTimer);
      this.batchTimer = null;
    }

    logger.info('WebhookEventService shutdown complete', { stats: this.stats });
  }
}

// Export singleton instance
export default new WebhookEventService();
