import axios from 'axios';
import runtimeConfig from '../config/runtime.config.js';
import logger from '../utils/logger.js';

/**
 * Webhook Service - Forwards incoming messages to Laravel
 */
class WebhookService {
  constructor() {
    this.webhookUrl = null;
    this.apiKey = null;
  }

  /**
   * Get Laravel webhook URL
   */
  getWebhookUrl() {
    let domain = runtimeConfig.get('domain');
    if (!domain) {
      return null;
    }

    // Check if domain already has a protocol
    if (domain.startsWith('http://') || domain.startsWith('https://')) {
      // Remove trailing slash if present
      domain = domain.replace(/\/+$/, '');
      return `${domain}/api/whatsapp/node/webhook`;
    }

    // Construct webhook URL with appropriate protocol
    const protocol = domain.includes('localhost') || domain.includes('.test') ? 'http' : 'https';
    return `${protocol}://${domain}/api/whatsapp/node/webhook`;
  }

  /**
   * Get API key for authentication
   */
  getApiKey() {
    return runtimeConfig.get('apiKey') || '';
  }

  /**
   * Forward incoming message to Laravel
   */
  async forwardMessage(sessionId, message) {
    try {
      // Debug logging
      console.log('FORWARD_MESSAGE START:', {
        sessionId,
        fromMe: message.key?.fromMe,
        hasMessage: !!message.message,
        messageStubType: message.messageStubType,
        remoteJid: message.key?.remoteJid
      });

      const webhookUrl = this.getWebhookUrl();
      console.log('WEBHOOK URL:', webhookUrl);

      if (!webhookUrl) {
        logger.warn('Webhook URL not configured, skipping message forward');
        return false;
      }

      // Skip status messages and own messages
      if (message.key?.fromMe) {
        console.log('SKIPPING: fromMe is true');
        logger.debug('Skipping own message', { sessionId, fromMe: true });
        return false;
      }

      // Extract message data
      const messageData = this.extractMessageData(sessionId, message);

      if (!messageData) {
        logger.warn('Could not extract message data - skipping', {
          sessionId,
          hasMessage: !!message.message,
          remoteJid: message.key?.remoteJid,
          messageType: Object.keys(message.message || {}).join(',')
        });
        return false;
      }

      logger.info('Forwarding incoming message to Laravel', {
        sessionId,
        from: messageData.from,
        type: messageData.type,
        webhookUrl
      });

      const response = await axios.post(
        webhookUrl,
        messageData,
        {
          headers: {
            'Content-Type': 'application/json',
            'X-API-Key': this.getApiKey(),
            'X-Session-ID': sessionId,
          },
          timeout: 30000,
        }
      );

      if (response.status === 200) {
        logger.info('Message forwarded successfully', {
          sessionId,
          messageId: messageData.messageId
        });
        return true;
      }

      logger.warn('Message forward returned non-200 status', {
        status: response.status,
        data: response.data
      });
      return false;

    } catch (error) {
      // Log the full error details
      console.error('WEBHOOK FORWARD ERROR:', {
        sessionId,
        errorMessage: error.message,
        errorCode: error.code,
        status: error.response?.status,
        statusText: error.response?.statusText,
        responseData: JSON.stringify(error.response?.data),
        webhookUrl: this.getWebhookUrl()
      });
      logger.error('Failed to forward message to Laravel: ' + error.message);
      return false;
    }
  }

  /**
   * Extract relevant message data from Baileys message
   */
  extractMessageData(sessionId, message) {
    try {
      // Detailed logging for debugging
      console.log('EXTRACT_MESSAGE_DATA START:', {
        sessionId,
        hasKey: !!message.key,
        hasMessage: !!message.message,
        messageStubType: message.messageStubType,
        fromMe: message.key?.fromMe
      });

      const key = message.key;
      const remoteJid = key?.remoteJid;

      // Skip broadcast and status messages
      if (!remoteJid || remoteJid === 'status@broadcast') {
        console.log('SKIPPING: No remoteJid or status broadcast');
        return null;
      }

      // Extract phone number from JID or senderPn (for @lid format messages)
      let from;
      if (remoteJid.endsWith('@lid')) {
        // For linked identifier format, use senderPn which contains the actual phone
        const senderPn = key?.senderPn || key?.participant;
        from = senderPn ? senderPn.replace('@s.whatsapp.net', '').replace('@g.us', '') : null;
        if (!from) {
          console.log('SKIPPING: Could not extract phone from @lid', { remoteJid, senderPn });
          logger.warn('Could not extract phone from @lid format message', { remoteJid, senderPn });
          return null;
        }
        console.log('EXTRACTED FROM @lid:', { from, senderPn });
      } else {
        from = remoteJid.replace('@s.whatsapp.net', '').replace('@g.us', '');
        console.log('EXTRACTED FROM standard:', { from, remoteJid });
      }

      // Determine message type and content
      const messageContent = message.message;
      if (!messageContent) {
        // Log why message is being skipped
        console.log('MESSAGE CONTENT NULL:', {
          from,
          remoteJid,
          hasMessage: !!message.message,
          messageKeys: message.message ? Object.keys(message.message) : [],
          fullMessage: JSON.stringify(message).substring(0, 500)
        });
        return null;
      }

      console.log('MESSAGE CONTENT EXISTS:', {
        from,
        contentKeys: Object.keys(messageContent)
      });

      let type = 'unknown';
      let text = null;
      let caption = null;
      let mediaUrl = null;
      let mediaKey = null;
      let mimetype = null;
      let filename = null;

      // Text message
      if (messageContent.conversation) {
        type = 'text';
        text = messageContent.conversation;
      } else if (messageContent.extendedTextMessage) {
        type = 'text';
        text = messageContent.extendedTextMessage.text;
      }
      // Image message
      else if (messageContent.imageMessage) {
        type = 'image';
        caption = messageContent.imageMessage.caption;
        mimetype = messageContent.imageMessage.mimetype;
        mediaKey = messageContent.imageMessage.mediaKey;
      }
      // Video message
      else if (messageContent.videoMessage) {
        type = 'video';
        caption = messageContent.videoMessage.caption;
        mimetype = messageContent.videoMessage.mimetype;
        mediaKey = messageContent.videoMessage.mediaKey;
      }
      // Audio message
      else if (messageContent.audioMessage) {
        type = 'audio';
        mimetype = messageContent.audioMessage.mimetype;
        mediaKey = messageContent.audioMessage.mediaKey;
      }
      // Document message
      else if (messageContent.documentMessage) {
        type = 'document';
        caption = messageContent.documentMessage.caption;
        mimetype = messageContent.documentMessage.mimetype;
        filename = messageContent.documentMessage.fileName;
        mediaKey = messageContent.documentMessage.mediaKey;
      }
      // Sticker message
      else if (messageContent.stickerMessage) {
        type = 'sticker';
        mimetype = messageContent.stickerMessage.mimetype;
        mediaKey = messageContent.stickerMessage.mediaKey;
      }
      // Location message
      else if (messageContent.locationMessage) {
        type = 'location';
        text = JSON.stringify({
          latitude: messageContent.locationMessage.degreesLatitude,
          longitude: messageContent.locationMessage.degreesLongitude,
          name: messageContent.locationMessage.name,
          address: messageContent.locationMessage.address,
        });
      }
      // Contact message
      else if (messageContent.contactMessage) {
        type = 'contact';
        text = messageContent.contactMessage.vcard;
      }

      // Get push name (contact name as saved by sender)
      const pushName = message.pushName || null;

      // Get timestamp
      const timestamp = message.messageTimestamp
        ? (typeof message.messageTimestamp === 'object'
          ? message.messageTimestamp.low
          : message.messageTimestamp)
        : Math.floor(Date.now() / 1000);

      return {
        sessionId,
        messageId: key.id,
        from,
        pushName,
        type,
        text: text || caption || null,
        caption,
        mimetype,
        filename,
        mediaKey: mediaKey ? Buffer.from(mediaKey).toString('base64') : null,
        isGroup: remoteJid.endsWith('@g.us'),
        timestamp,
        rawMessage: message,
      };

    } catch (error) {
      logger.error('Failed to extract message data', {
        sessionId,
        error: error.message
      });
      return null;
    }
  }

  /**
   * Create message handler for a session
   */
  createMessageHandler(sessionId) {
    return async (message) => {
      await this.forwardMessage(sessionId, message);
    };
  }

  /**
   * Get Laravel session status webhook URL
   */
  getSessionStatusUrl() {
    let domain = runtimeConfig.get('domain');
    if (!domain) {
      return null;
    }

    // Remove trailing slash if present
    domain = domain.replace(/\/+$/, '');

    // Add protocol if missing
    if (!domain.startsWith('http://') && !domain.startsWith('https://')) {
      const protocol = domain.includes('localhost') || domain.includes('.test') ? 'http' : 'https';
      domain = `${protocol}://${domain}`;
    }

    return `${domain}/api/whatsapp/session/status`;
  }

  /**
   * Notify Laravel about session status change
   * Called when session connects, disconnects, or logs out
   *
   * @param {string} sessionId - Session/Gateway name
   * @param {string} status - 'connected', 'disconnected', 'logged_out', 'qr'
   * @param {object|null} user - WhatsApp user info when connected
   */
  async notifySessionStatus(sessionId, status, user = null) {
    try {
      const webhookUrl = this.getSessionStatusUrl();

      if (!webhookUrl) {
        logger.warn('Session status webhook URL not configured');
        return false;
      }

      logger.info('Notifying Laravel about session status change', {
        sessionId,
        status,
        user: user?.id || null,
        webhookUrl
      });

      const response = await axios.post(
        webhookUrl,
        {
          sessionId,
          status,
          user,
          timestamp: new Date().toISOString(),
        },
        {
          headers: {
            'Content-Type': 'application/json',
            'X-API-Key': this.getApiKey(),
          },
          timeout: 10000,
        }
      );

      if (response.status === 200) {
        logger.info('Session status notification sent successfully', {
          sessionId,
          status,
          laravelStatus: response.data?.data?.status
        });
        return true;
      }

      logger.warn('Session status notification returned non-200', {
        status: response.status,
        data: response.data
      });
      return false;

    } catch (error) {
      logger.error('Failed to notify Laravel about session status', {
        sessionId,
        status,
        error: error.message,
        response: error.response?.data
      });
      return false;
    }
  }

  /**
   * Sync all sessions with Laravel on startup
   *
   * @param {Array} sessions - Array of session objects with id and status
   */
  async syncSessionsWithLaravel(sessions) {
    try {
      let domain = runtimeConfig.get('domain');
      if (!domain) {
        logger.warn('Domain not configured, cannot sync sessions');
        return false;
      }

      // Remove trailing slash if present
      domain = domain.replace(/\/+$/, '');

      // Add protocol if missing
      if (!domain.startsWith('http://') && !domain.startsWith('https://')) {
        const protocol = domain.includes('localhost') || domain.includes('.test') ? 'http' : 'https';
        domain = `${protocol}://${domain}`;
      }

      const syncUrl = `${domain}/api/whatsapp/session/sync`;

      logger.info('Syncing sessions with Laravel', {
        sessionCount: sessions.length,
        syncUrl
      });

      const response = await axios.post(
        syncUrl,
        { sessions },
        {
          headers: {
            'Content-Type': 'application/json',
            'X-API-Key': this.getApiKey(),
          },
          timeout: 30000,
        }
      );

      if (response.status === 200) {
        logger.info('Sessions synced with Laravel', {
          total: response.data?.data?.total,
          updated: response.data?.data?.updated
        });
        return true;
      }

      return false;

    } catch (error) {
      logger.error('Failed to sync sessions with Laravel', {
        error: error.message
      });
      return false;
    }
  }
}

export default new WebhookService();
