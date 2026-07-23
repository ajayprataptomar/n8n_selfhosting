import { Queue, Worker, Job } from 'bullmq';
import { Redis } from 'ioredis';
import { pool, invalidateTenantCache } from '../db/pool.js';
import { sendDunningNotice } from './email.js';
import logger from '../utils/logger.js';

const redisConnection = new Redis(process.env.REDIS_URL || 'redis://redis:6379', {
  maxRetriesPerRequest: null,
});

export const dunningQueue = new Queue('dunningQueue', { connection: redisConnection });

const DUNNING_MILESTONES = [
  { day: 0, delayMs: 0 },
  { day: 3, delayMs: 3 * 24 * 60 * 60 * 1000 },
  { day: 7, delayMs: 7 * 24 * 60 * 60 * 1000 },
  { day: 14, delayMs: 14 * 24 * 60 * 60 * 1000 },
  { day: 30, delayMs: 30 * 24 * 60 * 60 * 1000 },
];

/**
 * Handle subscription payment failure and initialize 30-day dunning schedule.
 */
export async function triggerSubscriptionDunning(tenantId: string, subscriptionId: string, failureReason: string = 'Card declined'): Promise<void> {
  try {
    // 1. Update subscription & tenant status to past_due
    await pool.query(
      `UPDATE subscriptions 
       SET status = 'past_due', metadata = COALESCE(metadata, '{}'::jsonb) || jsonb_build_object('dunning_stage', 1, 'failure_reason', $1::text) 
       WHERE id = $2`,
      [failureReason, subscriptionId]
    );

    await pool.query(
      `UPDATE tenants 
       SET status = 'past_due', metadata = COALESCE(metadata, '{}'::jsonb) || jsonb_build_object('dunning_stage', 1, 'failure_reason', $1::text) 
       WHERE id = $2`,
      [failureReason, tenantId]
    );

    await invalidateTenantCache(tenantId);

    // 2. Clear any old pending dunning jobs for this tenant
    const existingJobs = await dunningQueue.getJobs(['delayed', 'waiting']);
    for (const job of existingJobs) {
      if (job.data?.tenantId === tenantId) {
        await job.remove().catch(() => {});
      }
    }

    // 3. Schedule all 5 dunning milestones (Days 0, 3, 7, 14, 30)
    for (const milestone of DUNNING_MILESTONES) {
      await dunningQueue.add(
        'process-dunning-milestone',
        {
          tenantId,
          subscriptionId,
          dayStage: milestone.day,
          failureReason,
        },
        {
          delay: milestone.delayMs,
          jobId: `dunning-${tenantId}-day${milestone.day}-${Date.now()}`,
          removeOnComplete: { age: 86400 },
          removeOnFail: { age: 7 * 86400 },
        }
      );
    }

    logger.info(`🚨 [DUNNING STARTED] Tenant ${tenantId} entered dunning workflow due to: ${failureReason}`);
  } catch (err: any) {
    logger.error(`Failed to trigger dunning for tenant ${tenantId}:`, err);
    throw err;
  }
}

/**
 * Process a dunning milestone (Days 0, 3, 7, 14, 30).
 */
export async function handleDunningMilestoneJob(job: Job): Promise<void> {
  const { tenantId, subscriptionId, dayStage } = job.data;

  // Check if tenant is still in past_due status
  const tenantRes = await pool.query('SELECT status, metadata FROM tenants WHERE id = $1', [tenantId]);
  const tenant = tenantRes.rows[0];

  if (!tenant || tenant.status !== 'past_due') {
    logger.info(`⏩ [DUNNING SKIPPED] Tenant ${tenantId} is no longer past_due (status: ${tenant?.status}).`);
    return;
  }

  // Fetch admin user email for email notification
  const userRes = await pool.query(
    `SELECT email FROM users WHERE tenant_id = $1 AND role = 'admin' AND deleted_at IS NULL ORDER BY created_at ASC LIMIT 1`,
    [tenantId]
  );
  const email = userRes.rows[0]?.email;

  logger.info(`📬 [DUNNING DAY ${dayStage}] Processing milestone for tenant ${tenantId}`);

  // Update dunning stage in tenant metadata
  await pool.query(
    `UPDATE tenants 
     SET metadata = COALESCE(metadata, '{}'::jsonb) || jsonb_build_object('dunning_stage', $1::int) 
     WHERE id = $2`,
    [dayStage, tenantId]
  );

  // Send notification email
  if (email) {
    await sendDunningNotice(email, dayStage);
  }

  // Day 14: Restrict chat access (dunning_stage >= 4)
  if (dayStage === 14) {
    logger.warn(`🔒 [DUNNING DAY 14] Grace period expired for tenant ${tenantId}. Restricting AI chat service.`);
  }

  // Day 30: Cancel subscription permanently
  if (dayStage === 30) {
    await pool.query(`UPDATE subscriptions SET status = 'canceled', updated_at = NOW() WHERE id = $1`, [subscriptionId]);
    await pool.query(`UPDATE tenants SET status = 'canceled', updated_at = NOW() WHERE id = $1`, [tenantId]);
    await invalidateTenantCache(tenantId);
    logger.error(`❌ [DUNNING DAY 30] Permanent cancellation executed for tenant ${tenantId}.`);
  }
}

/**
 * Reactivate tenant subscription upon successful payment update.
 */
export async function reactivateTenantSubscription(tenantId: string): Promise<void> {
  try {
    await pool.query(
      `UPDATE tenants 
       SET status = 'active', 
           metadata = COALESCE(metadata, '{}'::jsonb) - 'dunning_stage' - 'failure_reason',
           updated_at = NOW() 
       WHERE id = $1`,
      [tenantId]
    );

    await pool.query(
      `UPDATE subscriptions 
       SET status = 'active', 
           updated_at = NOW() 
       WHERE tenant_id = $1 AND status = 'past_due'`,
      [tenantId]
    );

    // Cancel pending dunning queue jobs
    const jobs = await dunningQueue.getJobs(['delayed', 'waiting']);
    for (const job of jobs) {
      if (job.data?.tenantId === tenantId) {
        await job.remove().catch(() => {});
      }
    }

    await invalidateTenantCache(tenantId);
    logger.info(`🎉 [DUNNING RECOVERY] Tenant ${tenantId} successfully reactivated to active status.`);
  } catch (err: any) {
    logger.error(`Failed to reactivate tenant ${tenantId}:`, err);
    throw err;
  }
}

// Initialize BullMQ Worker for Dunning Schedule
export const dunningWorker = new Worker(
  'dunningQueue',
  async (job: Job) => {
    await handleDunningMilestoneJob(job);
  },
  {
    connection: redisConnection,
    concurrency: 5,
  }
);
