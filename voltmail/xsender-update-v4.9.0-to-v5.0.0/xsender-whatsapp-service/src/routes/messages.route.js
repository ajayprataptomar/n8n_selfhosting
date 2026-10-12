import express from 'express';
import { body } from 'express-validator';
import validate from '../middleware/validator.middleware.js';
import MessageService from '../services/MessageService.js';
import { successResponse, errorResponse } from '../utils/response.js';
import logger from '../utils/logger.js';

const router = express.Router();

/**
 * POST /messages/send
 * Send message (text, image, video, document, audio)
 * Supports multiple message types based on message content
 *
 * Enterprise Anti-Ban Protection:
 * Pass 'antiBanConfig' object with gateway delay settings to enable:
 * - minDelay: Minimum delay between messages (seconds)
 * - maxDelay: Maximum delay between messages (seconds)
 * - delayAfterCount: Apply extended delay after this many messages
 * - delayAfterDuration: Extended delay duration (seconds)
 * - resetAfterCount: Reset counter after this many messages
 * - simulateTyping: Whether to simulate typing indicator
 */
router.post(
  '/send',
  [
    body('sessionId').notEmpty().withMessage('Session ID is required'),
    body('receiver').notEmpty().withMessage('Receiver is required'),
    body('message').notEmpty().withMessage('Message is required'),
    body('delay').optional().isInt({ min: 0 }).withMessage('Delay must be a positive integer'),
    body('antiBanConfig').optional().isObject().withMessage('antiBanConfig must be an object'),
  ],
  validate,
  async (req, res, next) => {
    try {
      const { sessionId, receiver, message, delay = 0, antiBanConfig = null } = req.body;
      let result;

      // Determine message type and send accordingly
      if (message.text && !message.image && !message.video && !message.document && !message.audio) {
        // Plain text message - pass antiBanConfig for enterprise protection
        result = await MessageService.sendTextMessage(sessionId, receiver, message.text, delay, antiBanConfig);
      } else if (message.image) {
        // Image message
        const imageUrl = message.image.url || message.image;
        const caption = message.caption || '';
        result = await MessageService.sendImageMessage(sessionId, receiver, imageUrl, caption, delay);
      } else if (message.video) {
        // Video message
        const videoUrl = message.video.url || message.video;
        const caption = message.caption || '';
        result = await MessageService.sendVideoMessage(sessionId, receiver, videoUrl, caption, delay);
      } else if (message.document) {
        // Document message with caption support
        const documentUrl = message.document.url || message.document;
        const filename = message.fileName || message.filename || 'document';
        const mimetype = message.mimetype || 'application/pdf';
        const caption = message.caption || '';
        result = await MessageService.sendDocumentMessage(sessionId, receiver, documentUrl, filename, mimetype, caption, delay);
      } else if (message.audio) {
        // Audio message - WhatsApp doesn't support audio captions
        // If caption provided, send as separate text message first
        const audioUrl = message.audio.url || message.audio;
        const caption = message.caption || '';
        result = await MessageService.sendAudioMessage(sessionId, receiver, audioUrl, caption, delay);
      } else if (message.buttons && Array.isArray(message.buttons)) {
        // Button message
        const text = message.text || message.body || '';
        const footer = message.footer || '';
        result = await MessageService.sendButtonMessage(sessionId, receiver, text, message.buttons, footer, delay);
      } else if (message.sections && Array.isArray(message.sections)) {
        // List message
        const title = message.title || '';
        const text = message.text || message.body || '';
        const buttonText = message.buttonText || 'Menu';
        const footer = message.footer || '';
        result = await MessageService.sendListMessage(sessionId, receiver, title, text, buttonText, message.sections, footer, delay);
      } else {
        throw new Error('Invalid message format. Provide text, image, video, document, audio, buttons, or sections');
      }

      return successResponse(res, 200, result.message, {
        messageId: result.messageId,
        timestamp: result.timestamp,
        antiBanStats: result.antiBanStats || null,
      });
    } catch (error) {
      logger.error(`Send message failed: ${error.message}`);
      return errorResponse(res, 500, error.message);
    }
  }
);

/**
 * POST /messages/image
 * Send image message
 */
router.post(
  '/image',
  [
    body('sessionId').notEmpty().withMessage('Session ID is required'),
    body('receiver').notEmpty().withMessage('Receiver is required'),
    body('imageUrl').notEmpty().withMessage('Image URL is required'),
    body('caption').optional().isString(),
    body('delay').optional().isInt({ min: 0 }),
  ],
  validate,
  async (req, res, next) => {
    try {
      const { sessionId, receiver, imageUrl, caption = '', delay = 0 } = req.body;

      const result = await MessageService.sendImageMessage(
        sessionId,
        receiver,
        imageUrl,
        caption,
        delay
      );

      return successResponse(res, 200, result.message, {
        messageId: result.messageId,
        timestamp: result.timestamp,
      });
    } catch (error) {
      logger.error(`Send image failed: ${error.message}`);
      return errorResponse(res, 500, error.message);
    }
  }
);

/**
 * POST /messages/video
 * Send video message
 */
router.post(
  '/video',
  [
    body('sessionId').notEmpty().withMessage('Session ID is required'),
    body('receiver').notEmpty().withMessage('Receiver is required'),
    body('videoUrl').notEmpty().withMessage('Video URL is required'),
    body('caption').optional().isString(),
    body('delay').optional().isInt({ min: 0 }),
  ],
  validate,
  async (req, res, next) => {
    try {
      const { sessionId, receiver, videoUrl, caption = '', delay = 0 } = req.body;

      const result = await MessageService.sendVideoMessage(
        sessionId,
        receiver,
        videoUrl,
        caption,
        delay
      );

      return successResponse(res, 200, result.message, {
        messageId: result.messageId,
        timestamp: result.timestamp,
      });
    } catch (error) {
      logger.error(`Send video failed: ${error.message}`);
      return errorResponse(res, 500, error.message);
    }
  }
);

/**
 * POST /messages/document
 * Send document message with optional caption
 */
router.post(
  '/document',
  [
    body('sessionId').notEmpty().withMessage('Session ID is required'),
    body('receiver').notEmpty().withMessage('Receiver is required'),
    body('documentUrl').notEmpty().withMessage('Document URL is required'),
    body('filename').notEmpty().withMessage('Filename is required'),
    body('mimetype').optional().isString(),
    body('caption').optional().isString(),
    body('delay').optional().isInt({ min: 0 }),
  ],
  validate,
  async (req, res, next) => {
    try {
      const { sessionId, receiver, documentUrl, filename, mimetype = 'application/pdf', caption = '', delay = 0 } = req.body;

      const result = await MessageService.sendDocumentMessage(
        sessionId,
        receiver,
        documentUrl,
        filename,
        mimetype,
        caption,
        delay
      );

      return successResponse(res, 200, result.message, {
        messageId: result.messageId,
        timestamp: result.timestamp,
      });
    } catch (error) {
      logger.error(`Send document failed: ${error.message}`);
      return errorResponse(res, 500, error.message);
    }
  }
);

/**
 * POST /messages/button
 * Send button message
 */
router.post(
  '/button',
  [
    body('sessionId').notEmpty().withMessage('Session ID is required'),
    body('receiver').notEmpty().withMessage('Receiver is required'),
    body('text').notEmpty().withMessage('Text is required'),
    body('buttons').isArray({ min: 1 }).withMessage('Buttons array is required'),
    body('footer').optional().isString(),
    body('delay').optional().isInt({ min: 0 }),
  ],
  validate,
  async (req, res, next) => {
    try {
      const { sessionId, receiver, text, buttons, footer = '', delay = 0 } = req.body;

      const result = await MessageService.sendButtonMessage(
        sessionId,
        receiver,
        text,
        buttons,
        footer,
        delay
      );

      return successResponse(res, 200, result.message, {
        messageId: result.messageId,
        timestamp: result.timestamp,
      });
    } catch (error) {
      logger.error(`Send button message failed: ${error.message}`);
      return errorResponse(res, 500, error.message);
    }
  }
);

/**
 * POST /messages/list
 * Send list/template message
 */
router.post(
  '/list',
  [
    body('sessionId').notEmpty().withMessage('Session ID is required'),
    body('receiver').notEmpty().withMessage('Receiver is required'),
    body('title').notEmpty().withMessage('Title is required'),
    body('text').notEmpty().withMessage('Text is required'),
    body('buttonText').notEmpty().withMessage('Button text is required'),
    body('sections').isArray({ min: 1 }).withMessage('Sections array is required'),
    body('footer').optional().isString(),
    body('delay').optional().isInt({ min: 0 }),
  ],
  validate,
  async (req, res, next) => {
    try {
      const { sessionId, receiver, title, text, buttonText, sections, footer = '', delay = 0 } = req.body;

      const result = await MessageService.sendListMessage(
        sessionId,
        receiver,
        title,
        text,
        buttonText,
        sections,
        footer,
        delay
      );

      return successResponse(res, 200, result.message, {
        messageId: result.messageId,
        timestamp: result.timestamp,
      });
    } catch (error) {
      logger.error(`Send list message failed: ${error.message}`);
      return errorResponse(res, 500, error.message);
    }
  }
);

/**
 * POST /messages/bulk
 * Send bulk messages
 */
router.post(
  '/bulk',
  [
    body('sessionId').notEmpty().withMessage('Session ID is required'),
    body('messages').isArray({ min: 1 }).withMessage('Messages array is required'),
  ],
  validate,
  async (req, res, next) => {
    try {
      const { sessionId, messages } = req.body;

      const result = await MessageService.sendBulkMessages(sessionId, messages);

      const statusCode = result.failed === messages.length ? 500 : 200;

      return successResponse(res, statusCode, result.message, {
        sent: result.sent,
        failed: result.failed,
        results: result.results,
        errors: result.errors,
      });
    } catch (error) {
      logger.error(`Send bulk messages failed: ${error.message}`);
      return errorResponse(res, 500, error.message);
    }
  }
);

/**
 * POST /messages/check
 * Check if number exists on WhatsApp
 */
router.post(
  '/check',
  [
    body('sessionId').notEmpty().withMessage('Session ID is required'),
    body('number').notEmpty().withMessage('Number is required'),
  ],
  validate,
  async (req, res, next) => {
    try {
      const { sessionId, number } = req.body;

      const result = await MessageService.checkNumberExists(sessionId, number);

      return successResponse(res, 200, 'Number checked successfully', {
        exists: result.exists,
        jid: result.jid,
      });
    } catch (error) {
      logger.error(`Check number failed: ${error.message}`);
      return errorResponse(res, 500, error.message);
    }
  }
);

export default router;
