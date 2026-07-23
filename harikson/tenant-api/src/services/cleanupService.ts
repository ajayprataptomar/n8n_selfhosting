import { Redis } from 'ioredis';
import { pool } from '../db/pool.js';
import { Logger } from '../observability/logger.js';

export async function exportGDPRData(tenantId: string): Promise<any> {
  try {
    const tenantRes = await pool.query(`SELECT id, name, slug, email, plan, created_at FROM tenants WHERE id = $1`, [tenantId]);
    if (tenantRes.rows.length === 0) return null;

    const usersRes = await pool.query(`SELECT id, email, name, role, created_at FROM users WHERE tenant_id = $1`, [tenantId]);
    const holdsRes = await pool.query(`SELECT id, case_name, description, status, created_at, lifted_at FROM legal_holds WHERE tenant_id = $1 ORDER BY created_at DESC`, [tenantId]);

    const activeHolds = holdsRes.rows.filter((h: any) => h.status === 'active');

    const gdprPackage = {
      tenant: tenantRes.rows[0],
      users: usersRes.rows,
      has_active_legal_hold: activeHolds.length > 0,
      legal_holds: holdsRes.rows,
      retention_disclaimer: "Some data may be retained beyond standard period due to legal hold.",
      exported_at: new Date().toISOString(),
    };

    Logger.info(`📦 [GDPR EXPORT] Export package created for tenant ${tenantId}. Active legal holds: ${activeHolds.length}`);
    return gdprPackage;
  } catch (err: any) {
    Logger.error(`GDPR export failed for tenant ${tenantId}:`, err.message);
    throw err;
  }
}

/**
 * Execute batched deletion query to prevent locking tables during large cleanup runs.
 */
export async function runBatchDelete(
  query: string,
  params: any[] = [],
  batchSize = 10000
): Promise<number> {
  let totalDeleted = 0;
  while (true) {
    const res = await pool.query(query, params);
    const count = res.rowCount || 0;
    totalDeleted += count;
    if (count < batchSize) break;
  }
  return totalDeleted;
}

/**
 * Perform idempotent, batched database cleanup with Redis lock protection,
 * failure tracking, and admin alerts.
 */
export async function executeDatabaseCleanup(force = false): Promise<{
  success: boolean;
  deleted: Record<string, number>;
  skipped?: boolean;
  reason?: string;
}> {
  const redis = new Redis(process.env.REDIS_URL || 'redis://localhost:6379', {
    maxRetriesPerRequest: 3,
  });
  const lockKey = 'cleanup:lock';
  const now = Date.now();

  try {
    // 1. Lock Mechanism
    if (!force) {
      const existingLock = await redis.get(lockKey);
      if (existingLock) {
        const lockTime = parseInt(existingLock, 10);
        const lockAge = now - lockTime;

        // If lock is valid (< 2 hours old) -> skip
        if (lockAge < 2 * 3600 * 1000) {
          Logger.info(
            `[Cleanup] Concurrent cleanup run locked (Age: ${Math.round(lockAge / 1000)}s). Skipping.`
          );
          await redis.quit();
          return { success: true, deleted: {}, skipped: true, reason: 'Locked by another process' };
        } else {
          Logger.warn(`[Cleanup] Stale lock detected (${Math.round(lockAge / 3600000)}h old). Resetting lock.`);
          await redis.del(lockKey);
        }
      }

      // Try acquiring 1-hour lock
      const acquired = await redis.set(lockKey, now.toString(), 'EX', 3600, 'NX');
      if (!acquired) {
        Logger.info('[Cleanup] Failed to acquire Redis lock. Skipping.');
        await redis.quit();
        return { success: true, deleted: {}, skipped: true, reason: 'Failed to acquire lock' };
      }
    }

    Logger.info('[Cleanup] Starting database cleanup & data retention rules...');
    const deletedCounts: Record<string, number> = {};

    // 2. Perform Idempotent Batched Deletions
    // A. Activity Logs (> 90 days)
    deletedCounts.activity_logs = await runBatchDelete(`
      DELETE FROM activity_logs 
      WHERE id IN (
        SELECT id FROM activity_logs 
        WHERE created_at < NOW() - INTERVAL '90 days' 
        LIMIT 10000
      )
    `);

    // B. Expired / Revoked User Sessions
    deletedCounts.user_sessions = await runBatchDelete(`
      DELETE FROM user_sessions 
      WHERE id IN (
        SELECT id FROM user_sessions 
        WHERE expires_at < NOW() OR revoked_at IS NOT NULL 
        LIMIT 10000
      )
    `);

    // C. Invoices (> 7 years)
    deletedCounts.invoices = await runBatchDelete(`
      DELETE FROM invoices 
      WHERE id IN (
        SELECT id FROM invoices 
        WHERE created_at < NOW() - INTERVAL '2555 days' 
        LIMIT 10000
      )
    `);

    // D. Tenant-specific conversation retention (Check active legal hold)
    let totalConvsDeleted = 0;
    const activeTenants = await pool.query(
      `SELECT id, plan, retention_overrides FROM tenants WHERE status = 'active' AND deleted_at IS NULL`
    );
    for (const tenantRow of activeTenants.rows) {
      const tId = tenantRow.id;

      // 1. Check if tenant has active legal hold
      const legalHoldCheck = await pool.query(
        `SELECT COUNT(*)::int as count FROM legal_holds WHERE tenant_id = $1 AND status = 'active'`,
        [tId]
      );
      if (legalHoldCheck.rows[0]?.count > 0) {
        Logger.info(`⚖️ [LEGAL HOLD] Retention skipped for tenant ${tId} — legal hold active`);
        continue;
      }

      const plan = (tenantRow.plan || 'starter').toLowerCase();
      const overrides = tenantRow.retention_overrides || {};

      let days = 365;
      if (plan === 'pro' || plan === 'professional') {
        days = 730;
      } else if (plan === 'enterprise') {
        days = overrides.conversations_days ? parseInt(overrides.conversations_days, 10) : null;
      }

      if (days !== null) {
        const count = await runBatchDelete(
          `DELETE FROM conversations 
           WHERE id IN (
             SELECT id FROM conversations 
             WHERE tenant_id = $1 AND updated_at < NOW() - $2 * INTERVAL '1 day' 
             LIMIT 10000
           )`,
          [tId, days]
        );
        totalConvsDeleted += count;
      }
    }
    deletedCounts.tenant_conversations = totalConvsDeleted;

    // E. Hard delete soft-deleted records (> 30 days) — Exclude tenants under active legal hold
    const retentionDays = parseInt(process.env.HARD_DELETE_AFTER_DAYS || '30', 10);

    const softDeletedTenants = await pool.query(
      `SELECT id FROM tenants WHERE deleted_at < NOW() - $1 * INTERVAL '1 day'`,
      [retentionDays]
    );
    for (const tRow of softDeletedTenants.rows) {
      const legalHoldCheck = await pool.query(
        `SELECT COUNT(*)::int as count FROM legal_holds WHERE tenant_id = $1 AND status = 'active'`,
        [tRow.id]
      );
      if (legalHoldCheck.rows[0]?.count > 0) {
        Logger.info(`⚖️ [LEGAL HOLD] Hard delete skipped for tenant ${tRow.id} — legal hold active`);
        continue;
      }

      await exportGDPRData(tRow.id).catch((err) =>
        Logger.error(`GDPR export error for tenant ${tRow.id}:`, err)
      );
    }

    deletedCounts.tenants = await runBatchDelete(
      `DELETE FROM tenants WHERE id IN (
        SELECT id FROM tenants 
        WHERE deleted_at < NOW() - $1 * INTERVAL '1 day' 
        AND id NOT IN (SELECT tenant_id FROM legal_holds WHERE status = 'active')
        LIMIT 10000
      )`,
      [retentionDays]
    );

    deletedCounts.users = await runBatchDelete(
      `DELETE FROM users WHERE id IN (
        SELECT id FROM users 
        WHERE deleted_at < NOW() - $1 * INTERVAL '1 day' 
        AND tenant_id NOT IN (SELECT tenant_id FROM legal_holds WHERE status = 'active')
        LIMIT 10000
      )`,
      [retentionDays]
    );

    deletedCounts.conversations = await runBatchDelete(
      `DELETE FROM conversations WHERE id IN (
        SELECT id FROM conversations 
        WHERE deleted_at < NOW() - $1 * INTERVAL '1 day' 
        AND tenant_id NOT IN (SELECT tenant_id FROM legal_holds WHERE status = 'active')
        LIMIT 10000
      )`,
      [retentionDays]
    );

    deletedCounts.messages = await runBatchDelete(
      `DELETE FROM messages WHERE id IN (
        SELECT id FROM messages 
        WHERE deleted_at < NOW() - $1 * INTERVAL '1 day' 
        AND tenant_id NOT IN (SELECT tenant_id FROM legal_holds WHERE status = 'active')
        LIMIT 10000
      )`,
      [retentionDays]
    );

    // F. Downgrade grace period checks
    const expiredGraceTenants = await pool.query(
      `SELECT id, plan, downgrade_grace_ends FROM tenants WHERE downgrade_grace_ends < NOW() AND status = 'active'`
    );

    for (const tRow of expiredGraceTenants.rows) {
      const tId = tRow.id;
      const planId = tRow.plan.toLowerCase();

      const planRes = await pool.query('SELECT agent_limit FROM plans WHERE id = $1', [planId]);
      if (planRes.rows.length > 0) {
        const agentLimit = planRes.rows[0].agent_limit;
        if (agentLimit !== -1) {
          const activeAgents = await pool.query(
            `SELECT id FROM agents WHERE tenant_id = $1 AND status = 'active' ORDER BY created_at ASC`,
            [tId]
          );
          if (activeAgents.rows.length > agentLimit) {
            const extraAgentIds = activeAgents.rows.slice(agentLimit).map((r) => r.id);
            await pool.query(`UPDATE agents SET status = 'disabled' WHERE id = ANY($1)`, [extraAgentIds]);
            Logger.info(`[Cleanup] Disabled ${extraAgentIds.length} extra agents for tenant ${tId}`);
          }
        }

        const graceEnds = new Date(tRow.downgrade_grace_ends);
        const autoSuspendTime = new Date(graceEnds.getTime() + 14 * 24 * 60 * 60 * 1000);
        if (new Date() > autoSuspendTime) {
          await pool.query(`UPDATE tenants SET status = 'suspended' WHERE id = $1`, [tId]);
          Logger.info(`[Cleanup] Auto-suspended tenant ${tId} due to unresolved plan limit violations for 14 days.`);
        }
      }
    }

    // 3. Reset Failure Count & Record Last Successful Run
    await redis.set('cleanup:last_run', Date.now().toString());
    await redis.set('cleanup:consecutive_failures', '0');
    await redis.del(lockKey);
    await redis.quit();

    Logger.info('[Cleanup] Database cleanup completed successfully:', JSON.stringify(deletedCounts));
    return { success: true, deleted: deletedCounts };

  } catch (err: any) {
    Logger.error('❌ [Cleanup Error] Database cleanup execution failed:', err);

    try {
      const failCount = await redis.incr('cleanup:consecutive_failures');
      await redis.set('cleanup:failed_at', new Date().toISOString());
      await redis.del(lockKey);

      if (failCount >= 3) {
        Logger.error(
          `🚨 [ALERT] Database cleanup job has failed ${failCount} consecutive times! Investigation required.`,
          err
        );
      }
      await redis.quit();
    } catch (rErr) {}

    return {
      success: false,
      deleted: {},
      reason: err.message || String(err),
    };
  }
}
