import dotenv from 'dotenv';
dotenv.config();

export default {
  env: process.env.NODE_ENV || 'development',
  host: process.env.HOST || process.env.SERVER_HOST || '127.0.0.1',
  // Support both PORT (cPanel standard) and SERVER_PORT (legacy)
  port: parseInt(process.env.PORT || process.env.SERVER_PORT || '3001', 10),

  // Rate Limiting
  rateLimit: {
    windowMs: parseInt(process.env.RATE_LIMIT_WINDOW || '60000', 10),
    maxRequests: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS || '100', 10),
  },

  // Logging
  logging: {
    level: process.env.LOG_LEVEL || 'info',
    pretty: process.env.LOG_PRETTY === 'true',
  },
};
