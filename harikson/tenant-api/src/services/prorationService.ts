import Stripe from 'stripe';
import { pool, executeTenantQuery, invalidateTenantCache } from '../db/pool.js';
import logger from '../utils/logger.js';

const stripe = new Stripe(process.env.STRIPE_SECRET_KEY || 'sk_test_mock', {
  apiVersion: '2023-10-16' as any,
});

export interface ProrationResult {
  oldPlanPrice: number;
  newPlanPrice: number;
  periodStart: Date;
  periodEnd: Date;
  daysInPeriod: number;
  daysRemaining: number;
  unusedCredit: number;
  newPlanCharge: number;
  netAmount: number;
  currency: string;
  invoiceDescription: string;
  inTrial: boolean;
}

/**
 * Calculate mid-cycle prorated billing for plan changes.
 */
export function calculateProration(
  oldPlanPrice: number,
  newPlanPrice: number,
  periodStart: Date,
  periodEnd: Date,
  now: Date = new Date(),
  inTrial: boolean = false,
  currency: string = 'INR'
): ProrationResult {
  const symbol = currency.toUpperCase() === 'INR' ? '₹' : '$';

  if (inTrial) {
    return {
      oldPlanPrice,
      newPlanPrice,
      periodStart,
      periodEnd,
      daysInPeriod: 30,
      daysRemaining: 30,
      unusedCredit: 0,
      newPlanCharge: 0,
      netAmount: 0,
      currency,
      invoiceDescription: `Prorated plan change: Credit ${symbol}0.00, Charge ${symbol}0.00, Net ${symbol}0.00 (Trial period active)`,
      inTrial: true,
    };
  }

  const msPerDay = 1000 * 60 * 60 * 24;
  const totalMs = Math.max(1, periodEnd.getTime() - periodStart.getTime());
  const remainingMs = Math.max(0, periodEnd.getTime() - now.getTime());

  const daysInPeriod = Math.max(1, Math.round(totalMs / msPerDay));
  const daysRemaining = Math.max(0, Math.round(remainingMs / msPerDay));

  const dailyRateOld = oldPlanPrice / daysInPeriod;
  const unusedCredit = parseFloat((daysRemaining * dailyRateOld).toFixed(2));

  const dailyRateNew = newPlanPrice / daysInPeriod;
  const newPlanCharge = parseFloat((daysRemaining * dailyRateNew).toFixed(2));

  const netAmount = parseFloat((newPlanCharge - unusedCredit).toFixed(2));

  const invoiceDescription = `Prorated plan change: Credit ${symbol}${unusedCredit.toFixed(2)}, Charge ${symbol}${newPlanCharge.toFixed(2)}, Net ${symbol}${netAmount.toFixed(2)}`;

  return {
    oldPlanPrice,
    newPlanPrice,
    periodStart,
    periodEnd,
    daysInPeriod,
    daysRemaining,
    unusedCredit,
    newPlanCharge,
    netAmount,
    currency,
    invoiceDescription,
    inTrial: false,
  };
}

/**
 * Execute mid-cycle plan upgrade or downgrade with prorated billing.
 */
export async function executePlanChange(
  tenantId: string,
  newPlanId: string,
  paymentProvider: string = 'stripe'
) {
  try {
    // 1. Fetch current subscription and target plan
    const subRes = await pool.query(
      `SELECT s.*, p.price as old_price, p.currency, p.name as old_plan_name
       FROM subscriptions s
       JOIN plans p ON p.id = s.plan_id
       WHERE s.tenant_id = $1 AND s.status IN ('active', 'trialing')
       ORDER BY s.created_at DESC LIMIT 1`,
      [tenantId]
    );

    const newPlanRes = await pool.query('SELECT * FROM plans WHERE id = $1', [newPlanId]);
    const newPlan = newPlanRes.rows[0];
    if (!newPlan) throw new Error(`Target plan ${newPlanId} not found`);

    const currentSub = subRes.rows[0];
    const oldPrice = currentSub ? Number(currentSub.old_price) : 0;
    const newPrice = Number(newPlan.price);
    const currency = newPlan.currency || 'INR';

    const periodStart = currentSub?.current_period_start ? new Date(currentSub.current_period_start) : new Date();
    const periodEnd = currentSub?.current_period_end ? new Date(currentSub.current_period_end) : new Date(Date.now() + 30 * 24 * 3600 * 1000);
    const inTrial = currentSub?.status === 'trialing';

    // 2. Calculate proration
    const proration = calculateProration(oldPrice, newPrice, periodStart, periodEnd, new Date(), inTrial, currency);

    // 3. Provider-specific proration execution
    if (paymentProvider === 'stripe' && currentSub?.provider_subscription_id) {
      try {
        const stripeSub = await stripe.subscriptions.retrieve(currentSub.provider_subscription_id);
        const itemId = stripeSub.items.data[0]?.id;

        if (itemId) {
          await stripe.subscriptions.update(currentSub.provider_subscription_id, {
            proration_behavior: 'create_prorations',
            items: [{ id: itemId, price: newPlan.stripe_price_id || newPlanId }],
          });
        }
      } catch (stripeErr: any) {
        logger.warn('Stripe subscription proration update failed, proceeding with manual proration:', stripeErr.message);
      }
    }

    // 4. Record Invoice with Proration Description
    const invoiceNumber = 'INV-PRORATED-' + Math.floor(100000 + Math.random() * 900000);
    await pool.query(
      `INSERT INTO invoices (
        tenant_id, subscription_id, invoice_number, amount, currency, status, 
        tax_amount, total, created_at, paid_at
       )
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, NOW(), NOW())`,
      [
        tenantId,
        currentSub?.id || null,
        invoiceNumber,
        proration.netAmount > 0 ? proration.netAmount : 0,
        currency,
        proration.netAmount > 0 ? 'paid' : 'credited',
        0,
        proration.netAmount > 0 ? proration.netAmount : 0,
      ]
    );

    // 5. Apply Account Credit on Downgrade (netAmount < 0)
    if (proration.netAmount < 0) {
      const creditAmount = Math.abs(proration.netAmount);
      await pool.query(
        `UPDATE tenants 
         SET plan = $1, 
             metadata = COALESCE(metadata, '{}'::jsonb) || jsonb_build_object('account_credit', COALESCE((metadata->>'account_credit')::numeric, 0) + $2),
             updated_at = NOW() 
         WHERE id = $3`,
        [newPlanId.toUpperCase(), creditAmount, tenantId]
      );
    } else {
      await pool.query(
        `UPDATE tenants SET plan = $1, updated_at = NOW() WHERE id = $2`,
        [newPlanId.toUpperCase(), tenantId]
      );
    }

    // Update active subscription record
    if (currentSub?.id) {
      await pool.query(
        `UPDATE subscriptions SET plan_id = $1, amount = $2, updated_at = NOW() WHERE id = $3`,
        [newPlanId, newPrice, currentSub.id]
      );
    }

    await invalidateTenantCache(tenantId);
    logger.info(`🔄 [PRORATION] Tenant ${tenantId} changed plan to ${newPlanId}. ${proration.invoiceDescription}`);

    return proration;
  } catch (err: any) {
    logger.error('Execute plan change error:', err);
    throw err;
  }
}
