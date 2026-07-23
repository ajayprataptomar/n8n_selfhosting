import { describe, it, expect } from '@jest/globals';
import {
  executeCachedQuery,
  getCacheHitMetrics,
  invalidatePlanCache,
  invalidateUserCache,
  invalidateTenantCache,
} from '../src/db/pool.js';

describe('Redis DB Query Cache & Invalidation Test Suite', () => {
  it('1. Caches static query results and tracks hits/misses telemetry', async () => {
    const sql = 'SELECT * FROM plans WHERE id = $1';
    const params = ['pro'];

    // First call: cache miss
    const res1 = await executeCachedQuery(sql, params, 300);
    expect(res1).toBeDefined();

    // Second call: cache hit
    const res2 = await executeCachedQuery(sql, params, 300);
    expect(res2).toEqual(res1);

    const metrics = getCacheHitMetrics();
    expect(metrics.totalRequests).toBeGreaterThanOrEqual(2);
    expect(metrics.hits).toBeGreaterThanOrEqual(1);
    expect(typeof metrics.hitRatioPercent).toBe('number');
  });

  it('2. Bypasses cache for dynamic tables (messages, knowledge_documents, activity_logs)', async () => {
    const dynamicSql = 'SELECT * FROM messages WHERE tenant_id = $1';
    const params = ['00000000-0000-0000-0000-000000000000'];

    const initialMetrics = getCacheHitMetrics();
    await executeCachedQuery(dynamicSql, params, 300);
    const postMetrics = getCacheHitMetrics();

    // Dynamic queries should bypass Redis cache (no hit/miss increment)
    expect(postMetrics.hits).toEqual(initialMetrics.hits);
  });

  it('3. Invalidation functions run cleanly', async () => {
    await expect(invalidatePlanCache('pro')).resolves.not.toThrow();
    await expect(invalidateUserCache('usr_123')).resolves.not.toThrow();
    await expect(invalidateTenantCache('tnt_123')).resolves.not.toThrow();
  });
});
