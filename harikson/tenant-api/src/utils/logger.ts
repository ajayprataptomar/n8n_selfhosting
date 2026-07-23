import pino from 'pino';
import { requestContext } from './context.js';

const loggerInstance = pino({
  level: process.env.LOG_LEVEL || 'info',
  mixin() {
    const store = requestContext.getStore();
    if (store && store.req && store.req.id) {
      return { reqId: store.req.id };
    }
    return {};
  },
  redact: {
    paths: [
      'password', 'password_hash', 'token', 'refreshToken',
      'apiKey', 'key', 'secret', 'authorization',
      'email', 'phone', 'content', 'socialLinks'
    ],
    remove: true
  }
});

export default loggerInstance as any;
