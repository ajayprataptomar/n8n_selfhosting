import pg from 'pg';
import { RequestContext, requestContext } from '../utils/context.js';
import logger from '../utils/logger.js';
import { traceQuery } from '../utils/queryLogger.js';

const { Pool } = pg;

export { RequestContext, requestContext };

export const pool = new Pool({
  connectionString:
    process.env.DATABASE_URL ||
    'postgresql://neuravolt:neuravolt_dev_pwd@postgres:5432/neuravolt',
  max: 20,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 5000,
});

export const readPool = new Pool({
  connectionString:
    process.env.DATABASE_READ_URL ||
    process.env.DATABASE_URL ||
    'postgresql://neuravolt:neuravolt_dev_pwd@postgres:5432/neuravolt',
  max: 20,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 5000,
});

// Wrap pool query functions for tracing
const wrapPoolQuery = (p: pg.Pool, name: string) => {
  const originalQuery = p.query;
  // @ts-ignore
  p.query = function (text: any, params: any, callback: any) {
    return traceQuery(logger, name, text, originalQuery, p, Array.from(arguments));
  };
};

wrapPoolQuery(pool, 'PrimaryPool');
wrapPoolQuery(readPool, 'ReadReplicaPool');

// Log any pool errors to prevent crashes, letting pg pool reconnect on demand
pool.on('error', (err) => {
  logger.error('Unexpected DB pool error:', err);
});

readPool.on('error', (err) => {
  logger.error('Unexpected DB read pool error:', err);
});

export async function checkDbHealth(): Promise<boolean> {
  try {
    const client = await pool.connect();
    try {
      await client.query('SELECT 1');
      return true;
    } finally {
      client.release();
    }
  } catch (err) {
    logger.error('Database health check failed:', err);
    return false;
  }
}

// Helper: Acquire a verified clean connection from the pool
export async function connectWithValidation(useReplica = false): Promise<pg.PoolClient> {
  let client: pg.PoolClient | undefined;
  let retries = 3;

  const store = RequestContext.getStore();
  const req = store?.req;
  const usePrimaryDb = RequestContext.isUsePrimaryDb() || req?.usePrimaryDb || false;

  let shouldReadFromReplica = useReplica;

  if (usePrimaryDb) {
    shouldReadFromReplica = false;
  } else if (req && req.method === 'GET') {
    const isCriticalPath =
      req.path?.startsWith('/api/auth') ||
      req.path?.startsWith('/api/billing') ||
      req.path?.startsWith('/api/subscription') ||
      req.path?.startsWith('/api/invoices') ||
      req.path?.startsWith('/api/user/security') ||
      req.path?.startsWith('/api/user/2fa');

    if (!isCriticalPath && !usePrimaryDb) {
      shouldReadFromReplica = true;
    }
  }

  const targetPool = shouldReadFromReplica ? readPool : pool;
  let delay = 500;
  while (retries > 0) {
    try {
      client = await targetPool.connect();

      // Wrap client query function for tracing
      const originalClientQuery = client.query;
      // @ts-ignore
      client.query = function (text: any, params: any, callback: any) {
        return traceQuery(logger, 'Client', text, originalClientQuery, client, Array.from(arguments));
      };

      const valRes = await client.query(
        "SELECT current_setting('app.current_tenant', true) AS tenant"
      );
      const currentTenant = valRes.rows[0]?.tenant;
      if (currentTenant && currentTenant.trim() !== '') {
        throw new Error(
          `Connection pollution detected: app.current_tenant is already set to "${currentTenant}"`
        );
      }
      return client;
    } catch (err: any) {
      if (err.message.includes('unrecognized configuration parameter')) {
        if (client) return client;
      }
      try {
        if (client) client.release(true); // Discard on error
      } catch (releaseErr) {
        // Ignored
      }
      retries--;
      if (retries === 0) {
        throw new Error(`Failed to acquire a clean database connection: ${err.message}`);
      }
      logger.warn(`Database connection failed: ${err.message}. Retrying in ${delay}ms...`);
      await new Promise((resolve) => setTimeout(resolve, delay));
      delay *= 2;
    }
  }
  throw new Error('Failed to acquire a clean database connection');
}

export async function executeTenantQuery<T>(
  tenantId: string,
  callback: (client: pg.PoolClient) => Promise<T>,
  useReplica = false
): Promise<T> {
  const isPrimaryForced = RequestContext.isUsePrimaryDb();
  const effectiveUseReplica = isPrimaryForced ? false : useReplica;

  const client = await connectWithValidation(effectiveUseReplica);
  let contextSet = false;
  try {
    // Set RLS context on the connection
    await client.query("SELECT set_config('app.current_tenant', $1, false)", [
      tenantId,
    ]);
    contextSet = true;

    // Assert tenant context is set correctly
    await client.query('SELECT assert_tenant_context()');

    // Run the queries
    const result = await callback(client);
    return result;
  } catch (err) {
    throw err;
  } finally {
    if (contextSet) {
      try {
        // Clear context to prevent leakage to subsequent checkouts of this connection
        await client.query(
          "SELECT set_config('app.current_tenant', '', false)"
        );
        client.release();
      } catch (resetErr) {
        logger.error(
          '[DB FATAL] Failed to reset tenant context, destroying connection:',
          resetErr
        );
        // Mark connection as bad and discard it from pool
        client.release(true);
      }
    } else {
      client.release();
    }
  }
}

// Replication Lag Monitoring
async function checkReplicationLag() {
  try {
    const res = await readPool.query(`
      SELECT 
        CASE 
          WHEN pg_is_in_recovery() THEN 
            COALESCE(EXTRACT(EPOCH FROM (now() - pg_last_xact_replay_timestamp())), 0)
          ELSE 0 
        END AS lag_seconds
    `);
    const lag = parseFloat(res.rows[0]?.lag_seconds || '0');
    if (lag > 5) {
      logger.error(`🚨 [ALERT] Replication lag is too high: ${lag} seconds!`);
    }
  } catch (err: any) {
    if (!err.message.includes('pg_last_xact_replay_timestamp') && !err.message.includes('pg_is_in_recovery')) {
      logger.error('Failed to check replication lag:', err);
    }
  }
}
setInterval(checkReplicationLag, 5000);

import crypto from 'crypto';
import { Redis } from 'ioredis';

const redis = new Redis(process.env.REDIS_URL || 'redis://redis:6379', {
  retryStrategy: (times) => Math.min(times * 50, 2000),
  maxRetriesPerRequest: 3,
  enableReadyCheck: true,
});

redis.on('error', (err) => logger.error('Pool Redis error:', err.message));

// Cache hits and misses metrics telemetry
let cacheHits = 0;
let cacheMisses = 0;

export function getCacheHitMetrics() {
  const total = cacheHits + cacheMisses;
  const ratio = total > 0 ? parseFloat(((cacheHits / total) * 100).toFixed(2)) : 0;
  return {
    hits: cacheHits,
    misses: cacheMisses,
    totalRequests: total,
    hitRatioPercent: ratio,
  };
}

// Periodically log cache hit/miss ratio metrics every 60s
setInterval(() => {
  const metrics = getCacheHitMetrics();
  if (metrics.totalRequests > 0) {
    logger.info(
      `📊 [CACHE TELEMETRY] Redis DB Query Cache - Hits: ${metrics.hits}, Misses: ${metrics.misses}, Hit Ratio: ${metrics.hitRatioPercent}%`
    );
  }
}, 60000);

/**
 * Execute query with Redis caching wrapper.
 * Caches plan details (300s), tenant configs (60s), user profiles (120s), and active subscriptions (60s).
 * Does NOT cache chat messages, documents, or activity logs.
 */
export async function executeCachedQuery<T = any>(
  sql: string,
  params: any[] = [],
  ttlSeconds = 300,
  tenantId?: string
): Promise<T> {
  const lowerSql = sql.toLowerCase();

  // Safety Rule 5: Don't cache dynamic tables
  if (
    lowerSql.includes('messages') ||
    lowerSql.includes('knowledge_documents') ||
    lowerSql.includes('document_embeddings') ||
    lowerSql.includes('activity_logs') ||
    lowerSql.includes('insert into') ||
    lowerSql.includes('update ') ||
    lowerSql.includes('delete ')
  ) {
    if (tenantId) {
      return executeTenantQuery(tenantId, (client) => client.query(sql, params).then((res) => res as unknown as T));
    }
    const res = await pool.query(sql, params);
    return res as unknown as T;
  }

  const queryHash = crypto
    .createHash('sha256')
    .update(`${sql}:${JSON.stringify(params)}`)
    .digest('hex')
    .substring(0, 16);

  const cacheKey = `query:${tenantId ? tenantId + ':' : ''}${queryHash}`;

  try {
    const cached = await redis.get(cacheKey);
    if (cached) {
      cacheHits++;
      return JSON.parse(cached);
    }
  } catch (err: any) {
    logger.warn('Redis cache get error, falling back to DB:', err.message);
  }

  cacheMisses++;
  let result: any;
  if (tenantId) {
    result = await executeTenantQuery(tenantId, (client) => client.query(sql, params));
  } else {
    result = await pool.query(sql, params);
  }

  try {
    await redis.setex(cacheKey, ttlSeconds, JSON.stringify(result));
  } catch (err: any) {
    logger.warn('Redis cache setex error:', err.message);
  }

  return result as T;
}

// Invalidation Helpers
export async function invalidatePlanCache(planId?: string): Promise<void> {
  try {
    const keys = await redis.keys('query:*plan*');
    if (keys.length > 0) {
      await redis.del(...keys);
    }
    if (planId) {
      await redis.del(`plan:${planId}`);
    }
    logger.info(`[CACHE INVALIDATED] Plan cache cleared.`);
  } catch (err: any) {
    logger.error('Failed to invalidate plan cache:', err.message);
  }
}

export async function invalidateUserCache(userId: string): Promise<void> {
  try {
    const keys = await redis.keys(`query:*user*${userId}*`);
    const directKey = `user:${userId}`;
    if (keys.length > 0) {
      await redis.del(...keys);
    }
    await redis.del(directKey);
    logger.info(`[CACHE INVALIDATED] User cache cleared for user ${userId}.`);
  } catch (err: any) {
    logger.error('Failed to invalidate user cache:', err.message);
  }
}

export async function invalidateTenantCache(tenantId: string): Promise<void> {
  try {
    const keys = await redis.keys(`query:${tenantId}:*`);
    if (keys.length > 0) {
      await redis.del(...keys);
    }
    await redis.del(`tenant:${tenantId}`);
    logger.info(`[CACHE INVALIDATED] Tenant cache cleared for tenant ${tenantId}.`);
  } catch (err: any) {
    logger.error('Failed to invalidate tenant cache:', err.message);
  }
}

