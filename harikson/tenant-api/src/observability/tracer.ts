import { AsyncLocalStorage } from 'async_hooks';
import crypto from 'crypto';

export const traceStorage = new AsyncLocalStorage<string>();

export class Tracer {
  static getTraceId(): string {
    return traceStorage.getStore() || 'no-trace-id';
  }

  static runWithTrace<T>(traceId: string | undefined, callback: () => T): T {
    const activeTraceId = traceId || crypto.randomUUID();
    return traceStorage.run(activeTraceId, callback);
  }
}
