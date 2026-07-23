import { AsyncLocalStorage } from 'async_hooks';

export interface IRequestContext {
  tenantId?: string;
  userId?: string;
  usePrimaryDb: boolean;
  req?: any;
}

/**
 * RequestContext wraps Node.js built-in AsyncLocalStorage to store request-scoped metadata
 * (tenantId, userId, usePrimaryDb, req) across asynchronous operation boundaries.
 */
export class RequestContext {
  private static asyncLocalStorage = new AsyncLocalStorage<IRequestContext>();

  /**
   * Run a callback within a new RequestContext scope.
   */
  static run<R>(store: IRequestContext, callback: () => R): R {
    return this.asyncLocalStorage.run(store, callback);
  }

  /**
   * Retrieve the current RequestContext store.
   */
  static getStore(): IRequestContext | undefined {
    return this.asyncLocalStorage.getStore();
  }

  /**
   * Helper to check if primary DB should be used in current context.
   */
  static isUsePrimaryDb(): boolean {
    const store = this.getStore();
    return store?.usePrimaryDb ?? false;
  }

  /**
   * Helper to get current tenant ID.
   */
  static getTenantId(): string | undefined {
    return this.getStore()?.tenantId;
  }

  /**
   * Helper to get current user ID.
   */
  static getUserId(): string | undefined {
    return this.getStore()?.userId;
  }

  /**
   * Mutate/update the current context store in-place.
   */
  static update(partial: Partial<IRequestContext>): void {
    const store = this.getStore();
    if (store) {
      Object.assign(store, partial);
    }
  }

  /**
   * Force primary DB usage for remainder of current context.
   */
  static setPrimaryDb(force = true): void {
    const store = this.getStore();
    if (store) {
      store.usePrimaryDb = force;
    }
  }
}

/**
 * Backward compatibility export for existing usages of requestContext.getStore() and requestContext.run().
 */
export const requestContext = {
  getStore: () => RequestContext.getStore(),
  run: <R>(store: IRequestContext, callback: () => R): R => RequestContext.run(store, callback),
  update: (partial: Partial<IRequestContext>) => RequestContext.update(partial),
};
