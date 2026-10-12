import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export default {
  // Connection Configuration - Optimized for stability on shared hosting
  connection: {
    maxRetries: parseInt(process.env.MAX_RETRIES || '10', 10),
    reconnectInterval: parseInt(process.env.RECONNECT_INTERVAL || '3000', 10),
    connectTimeout: parseInt(process.env.CONNECT_TIMEOUT || '60000', 10),
    // CRITICAL: Keep-alive interval - 5 seconds for maximum stability
    // This is the Baileys-level WebSocket ping interval
    // 5s is aggressive but prevents connection drops on unstable hosting
    // Most hosting providers have idle timeouts of 30-60 seconds
    keepAliveInterval: parseInt(process.env.KEEPALIVE_INTERVAL || '5000', 10),
    qrTimeout: parseInt(process.env.QR_TIMEOUT || '60000', 10),
    // Query timeout - don't wait too long for responses
    queryTimeout: parseInt(process.env.QUERY_TIMEOUT || '30000', 10),
    // Presence update interval - keeps WhatsApp session alive
    // Sends "available" status periodically to prevent server-side idle timeout
    // 15 seconds: more aggressive to prevent disconnects on shared hosting
    presenceUpdateInterval: parseInt(process.env.PRESENCE_UPDATE_INTERVAL || '15000', 10), // 15 seconds
    // Mark messages as read delay - helps simulate human behavior
    markReadDelay: parseInt(process.env.MARK_READ_DELAY || '1000', 10),
    // Connection monitoring interval - check socket health
    healthCheckInterval: parseInt(process.env.HEALTH_CHECK_INTERVAL || '30000', 10), // 30 seconds
    // Cooldown interval after max retries exhausted (before starting fresh attempts)
    // Prevents rapid reconnection loops while ensuring eventual recovery
    cooldownInterval: parseInt(process.env.COOLDOWN_INTERVAL || '60000', 10), // 1 minute
    // Session keeper interval - scans disk for dropped sessions and restores them
    // This is the safety net that prevents overnight session loss
    // 2 minutes: faster recovery from unexpected disconnects
    sessionKeeperInterval: parseInt(process.env.SESSION_KEEPER_INTERVAL || '120000', 10), // 2 minutes
  },

  // Session Storage
  storage: {
    sessionsPath: process.env.SESSION_STORAGE_PATH || path.join(__dirname, '../../storage/sessions'),
  },

  // Media Configuration
  media: {
    storagePath: process.env.MEDIA_STORAGE_PATH || path.join(__dirname, '../../storage/media'),
    maxFileSize: parseInt(process.env.MAX_FILE_SIZE || '10485760', 10), // 10MB default
    allowedTypes: process.env.ALLOWED_MEDIA_TYPES?.split(',') || [
      'image/jpeg',
      'image/png',
      'image/gif',
      'video/mp4',
      'audio/mpeg',
      'audio/ogg',
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],
  },

  // Browser Configuration
  browser: {
    name: 'Mac OS',
    version: ['10.15.7'],
  },

  // Message Configuration
  message: {
    defaultDelay: 1000, // 1 second delay between messages
    bulkDelay: 2000, // 2 seconds delay for bulk messages
    retryDelay: 5000, // 5 seconds delay before retry
    maxRetries: 3, // Max retries for failed messages
  },
};
