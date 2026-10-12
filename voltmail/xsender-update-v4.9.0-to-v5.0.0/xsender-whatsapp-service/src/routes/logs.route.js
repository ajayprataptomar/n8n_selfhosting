import { Router } from 'express';
import { successResponse, errorResponse } from '../utils/response.js';
import logger, { getLogs, getLogStats, clearLogBuffer } from '../utils/logger.js';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const router = Router();

// For ES modules, we need to get __dirname differently
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * GET /logs
 * Get recent logs from memory buffer
 * Query params:
 *   - level: Filter by minimum level (error, warn, info, debug, all)
 *   - lines: Number of lines to return (default: 100, max: 1000)
 */
router.get('/', (req, res) => {
  try {
    const requestedLevel = (req.query.level || 'info').toLowerCase();
    const lines = Math.min(parseInt(req.query.lines) || 100, 1000);

    // Get logs from the logger buffer
    const result = getLogs(requestedLevel, lines);
    const stats = getLogStats();

    return successResponse(res, 200, 'Logs retrieved successfully', {
      logs: result,
      count: result.length,
      total: stats.bufferSize,
      filters: {
        level: requestedLevel,
        lines,
      },
    });
  } catch (err) {
    logger.error('Failed to retrieve logs:', { error: err.message });
    return errorResponse(res, 500, 'Failed to retrieve logs');
  }
});

/**
 * GET /logs/file
 * Read logs from log file (if available)
 * Useful for persistent log viewing
 */
router.get('/file', (req, res) => {
  try {
    const lines = Math.min(parseInt(req.query.lines) || 100, 500);

    // Try common PM2 log locations
    const possiblePaths = [
      path.join(process.env.HOME || '', '.pm2', 'logs', 'xsender-whatsapp-out.log'),
      path.join(process.env.HOME || '', '.pm2', 'logs', 'xsender-out.log'),
      path.join(__dirname, '../../logs/app.log'),
    ];

    let logContent = null;
    let usedPath = null;

    for (const logPath of possiblePaths) {
      if (fs.existsSync(logPath)) {
        try {
          const content = fs.readFileSync(logPath, 'utf8');
          const logLines = content.split('\n').filter(line => line.trim());
          logContent = logLines.slice(-lines);
          usedPath = logPath;
          break;
        } catch (e) {
          // Continue to next path
        }
      }
    }

    if (!logContent) {
      return errorResponse(res, 404, 'Log file not found. Logs may only be available in memory buffer.');
    }

    // Parse JSON logs if possible
    const parsedLogs = logContent.map(line => {
      try {
        return JSON.parse(line);
      } catch {
        return { message: line, level: 'info', timestamp: Date.now() };
      }
    });

    return successResponse(res, 200, 'File logs retrieved', {
      logs: parsedLogs,
      count: parsedLogs.length,
      source: usedPath,
    });
  } catch (err) {
    logger.error('Failed to read log file:', { error: err.message });
    return errorResponse(res, 500, 'Failed to read log file');
  }
});

/**
 * DELETE /logs/clear
 * Clear the in-memory log buffer
 */
router.delete('/clear', (req, res) => {
  const count = clearLogBuffer();

  logger.info('Log buffer cleared', { previousCount: count });

  return successResponse(res, 200, 'Log buffer cleared', {
    clearedCount: count,
  });
});

/**
 * GET /logs/stats
 * Get log statistics
 */
router.get('/stats', (req, res) => {
  const stats = getLogStats();
  return successResponse(res, 200, 'Log statistics', stats);
});

export default router;
