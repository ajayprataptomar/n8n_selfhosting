import logger from './logger.js';

/**
 * Retry utility with exponential backoff
 */
export const retryWithBackoff = async (fn, options = {}) => {
  const {
    maxRetries = 3,
    initialDelay = 1000,
    maxDelay = 30000,
    factor = 2,
    onRetry = null,
  } = options;

  let lastError;
  let delay = initialDelay;

  for (let attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      return await fn();
    } catch (error) {
      lastError = error;

      if (attempt === maxRetries) {
        logger.error(`Max retries (${maxRetries}) reached. Giving up.`, {
          error: error.message,
        });
        throw error;
      }

      logger.warn(`Attempt ${attempt} failed. Retrying in ${delay}ms...`, {
        error: error.message,
      });

      if (onRetry) {
        await onRetry(attempt, error);
      }

      await sleep(delay);

      // Exponential backoff with max limit
      delay = Math.min(delay * factor, maxDelay);
    }
  }

  throw lastError;
};

/**
 * Sleep for specified milliseconds
 * @param {number} ms - Milliseconds to sleep
 * @returns {Promise}
 */
export const sleep = (ms) => {
  return new Promise((resolve) => setTimeout(resolve, ms));
};

/**
 * Delay execution
 * @param {number} ms - Milliseconds to delay
 * @returns {Promise}
 */
export const delay = async (ms) => {
  if (ms > 0) {
    await sleep(ms);
  }
};

export default {
  retryWithBackoff,
  sleep,
  delay,
};
