import logger from '../utils/logger.js';
import { errorResponse } from '../utils/response.js';

/**
 * Global Error Handler Middleware
 */
export const errorHandler = (err, req, res, next) => {
  logger.error('Unhandled error:', {
    error: err.message,
    stack: err.stack,
    path: req.path,
    method: req.method,
  });

  // Baileys specific errors
  if (err.name === 'DisconnectReason' || err.isBoom) {
    return errorResponse(res, 500, 'WhatsApp connection error', {
      details: err.message,
    });
  }

  // Validation errors
  if (err.name === 'ValidationError') {
    return errorResponse(res, 422, 'Validation error', {
      details: err.message,
    });
  }

  // Default error
  const statusCode = err.statusCode || err.status || 500;
  const message = err.message || 'Internal server error';

  return errorResponse(res, statusCode, message);
};

/**
 * 404 Not Found Handler
 */
export const notFoundHandler = (req, res) => {
  return errorResponse(res, 404, `Route not found: ${req.method} ${req.path}`);
};

export default errorHandler;
