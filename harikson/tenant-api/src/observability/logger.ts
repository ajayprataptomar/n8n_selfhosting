import { Tracer } from './tracer.js';

export class Logger {
  static info(message: string, args: any = {}) {
    console.log(
      JSON.stringify({
        timestamp: new Date().toISOString(),
        level: 'INFO',
        traceId: Tracer.getTraceId(),
        message,
        ...args,
      })
    );
  }

  static warn(message: string, args: any = {}) {
    console.warn(
      JSON.stringify({
        timestamp: new Date().toISOString(),
        level: 'WARN',
        traceId: Tracer.getTraceId(),
        message,
        ...args,
      })
    );
  }

  static error(message: string, error: any, args: any = {}) {
    console.error(
      JSON.stringify({
        timestamp: new Date().toISOString(),
        level: 'ERROR',
        traceId: Tracer.getTraceId(),
        message,
        error: error?.message || String(error),
        stack: error?.stack,
        ...args,
      })
    );
  }
}
