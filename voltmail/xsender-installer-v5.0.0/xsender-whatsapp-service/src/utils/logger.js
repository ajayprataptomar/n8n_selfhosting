import pino from 'pino';
import serverConfig from '../config/server.config.js';

// In-memory log buffer for API access
const logBuffer = [];
const MAX_BUFFER_SIZE = 1000;

// Level mappings for pino (numeric levels)
const LEVEL_NAMES = {
  10: 'trace',
  20: 'debug',
  30: 'info',
  40: 'warn',
  50: 'error',
  60: 'fatal',
};

/**
 * Add log entry to buffer
 * @param {Object} logEntry
 */
function addLogToBuffer(logEntry) {
  try {
    const entry = typeof logEntry === 'string' ? JSON.parse(logEntry) : logEntry;
    logBuffer.push({
      level: LEVEL_NAMES[entry.level] || 'info',
      message: entry.msg || entry.message || '',
      timestamp: entry.time || Date.now(),
      ...entry,
    });

    // Keep buffer size manageable
    while (logBuffer.length > MAX_BUFFER_SIZE) {
      logBuffer.shift();
    }
  } catch {
    // Ignore parse errors for non-JSON logs
  }
}

/**
 * Get logs from buffer
 * @param {string} level - Minimum level filter
 * @param {number} lines - Number of lines to return
 * @returns {Array}
 */
export function getLogs(level = 'info', lines = 100) {
  const LEVEL_NUMBERS = {
    trace: 10,
    debug: 20,
    info: 30,
    warn: 40,
    error: 50,
    fatal: 60,
    all: 0,
  };

  const minLevel = LEVEL_NUMBERS[level] ?? LEVEL_NUMBERS.info;
  const filteredLogs =
    level === 'all'
      ? logBuffer
      : logBuffer.filter(log => {
          const logLevel = LEVEL_NUMBERS[log.level] || 30;
          return logLevel >= minLevel;
        });

  return filteredLogs.slice(-Math.min(lines, 1000));
}

/**
 * Get log buffer statistics
 * @returns {Object}
 */
export function getLogStats() {
  const stats = { bufferSize: logBuffer.length, maxBufferSize: MAX_BUFFER_SIZE, levels: {} };
  logBuffer.forEach(log => {
    const level = log.level || 'info';
    stats.levels[level] = (stats.levels[level] || 0) + 1;
  });
  return stats;
}

/**
 * Clear log buffer
 * @returns {number} - Count of cleared logs
 */
export function clearLogBuffer() {
  const count = logBuffer.length;
  logBuffer.length = 0;
  return count;
}

// Create a custom destination that buffers logs
const bufferDestination = {
  write(chunk) {
    // Buffer the log entry
    addLogToBuffer(chunk);
    // Also write to stdout
    process.stdout.write(chunk);
  },
};

// Create the logger
const logger = pino(
  {
    level: serverConfig.logging.level,
    timestamp: () => `,"time":${Date.now()}`,
  },
  serverConfig.logging.pretty
    ? pino.transport({
        targets: [
          {
            target: 'pino-pretty',
            options: {
              colorize: true,
              translateTime: 'yyyy-mm-dd HH:MM:ss',
              ignore: 'pid,hostname',
            },
            level: serverConfig.logging.level,
          },
        ],
      })
    : bufferDestination
);

// For pretty mode, add a hook to capture logs
if (serverConfig.logging.pretty) {
  const originalChild = logger.child.bind(logger);
  logger.child = function (bindings) {
    const child = originalChild(bindings);
    return child;
  };

  // Wrap log methods to capture to buffer in pretty mode
  ['trace', 'debug', 'info', 'warn', 'error', 'fatal'].forEach(level => {
    const original = logger[level].bind(logger);
    logger[level] = function (...args) {
      // Add to buffer
      const entry = {
        level,
        message: typeof args[0] === 'string' ? args[0] : args[0]?.msg || JSON.stringify(args[0]),
        timestamp: Date.now(),
      };
      logBuffer.push(entry);
      while (logBuffer.length > MAX_BUFFER_SIZE) {
        logBuffer.shift();
      }

      // Call original
      return original(...args);
    };
  });
}

export default logger;
