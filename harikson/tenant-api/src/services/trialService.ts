import { pool, invalidateTenantCache } from '../db/pool.js';
import { sendDunningNotice } from './email.js';
import logger from '../utils/logger.js';

/**
 * Daily cron worker logic checking trial period expirations and sending email reminders.
 */
export async function checkTrialExpirations(): Promise<void> {
  try {
    const now = new Date();

    // 1. Fetch all active trialing subscriptions
    const trialingRes = await pool.query(
      `SELECT s.id as subscription_id, s.tenant_id, s.current_period_end, s.plan_id, u.email
       FROM subscriptions s
       JOIN users u ON u.tenant_id = s.tenant_id AND u.role = 'admin'
       WHERE s.status = 'trialing' AND u.deleted_at IS NULL`
    );

    for (const sub of trialingRes.rows) {
      const periodEnd = new Date(sub.current_period_end);
      const diffMs = periodEnd.getTime() - now.getTime();
      const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

      if (diffDays <= 0) {
        // Trial Expired -> Auto-downgrade to Free / Starter plan or mark past_due
        const freePlanRes = await pool.query(`SELECT id FROM plans WHERE price = 0 LIMIT 1`);
        const fallbackPlanId = freePlanRes.rows[0]?.id || 'starter';

        await pool.query(
          `UPDATE subscriptions SET status = 'past_due', plan_id = $1, updated_at = NOW() WHERE id = $2`,
          [fallbackPlanId, sub.subscription_id]
        );
        await pool.query(
          `UPDATE tenants SET status = 'past_due', plan = $1, updated_at = NOW() WHERE id = $2`,
          [fallbackPlanId.toUpperCase(), sub.tenant_id]
        );

        await invalidateTenantCache(sub.tenant_id);
        logger.info(`⏰ [TRIAL EXPIRED] Tenant ${sub.tenant_id} trial ended. Downgraded/moved to past_due.`);
      } else if (diffDays === 7 || diffDays === 3 || diffDays === 1) {
        // Send email reminder
        await sendDunningNotice(sub.email, diffDays);
        logger.info(`📬 [TRIAL REMINDER] Sent ${diffDays}-day trial expiry notice to ${sub.email}`);
      }
    }
  } catch (err: any) {
    logger.error('Failed checking trial expirations:', err);
  }
}
