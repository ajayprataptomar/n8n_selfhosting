export const HARIKSON_ERRORS = {
  MODEL_LOAD_ERROR: {
    message: 'Harikson is initializing. Please try again in a moment.',
    action: 'Please wait 30 seconds and refresh.',
  },
  RATE_LIMIT: {
    message: 'Harikson is processing many requests. Please wait a moment.',
    action: 'Try again in a few seconds.',
  },
  CONTEXT_LENGTH: {
    message: 'This conversation is getting long. Consider starting a new chat.',
    action: 'Click "New Chat" to start fresh.',
  },
  GENERIC_ERROR: {
    message: 'Harikson encountered an issue. Our team has been notified.',
    action: 'Please try again later or contact support.',
  },
} as const;
