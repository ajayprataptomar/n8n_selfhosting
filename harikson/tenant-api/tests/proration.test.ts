import { describe, it, expect } from '@jest/globals';
import { calculateProration } from '../src/services/prorationService.js';

describe('Prorated Billing & Mid-Cycle Plan Change Test Suite', () => {
  const periodStart = new Date('2026-07-01T00:00:00Z');
  const periodEnd = new Date('2026-07-31T00:00:00Z'); // 30 days
  const now = new Date('2026-07-16T00:00:00Z');      // 15 days remaining (half cycle)

  it('1. Calculates mid-cycle upgrade correctly (Starter $100 -> Pro $200)', () => {
    const oldPrice = 100;
    const newPrice = 200;

    const proration = calculateProration(oldPrice, newPrice, periodStart, periodEnd, now, false, 'INR');

    // 15 days remaining out of 30
    // Unused credit = $100 * (15/30) = $50
    // New charge = $200 * (15/30) = $100
    // Net amount = $100 - $50 = $50
    expect(proration.daysRemaining).toBe(15);
    expect(proration.unusedCredit).toBe(50);
    expect(proration.newPlanCharge).toBe(100);
    expect(proration.netAmount).toBe(50);
    expect(proration.invoiceDescription).toContain('Prorated plan change: Credit ₹50.00, Charge ₹100.00, Net ₹50.00');
  });

  it('2. Calculates mid-cycle downgrade correctly (Enterprise $300 -> Pro $100)', () => {
    const oldPrice = 300;
    const newPrice = 100;

    const proration = calculateProration(oldPrice, newPrice, periodStart, periodEnd, now, false, 'USD');

    // Unused credit = $300 * (15/30) = $150
    // New charge = $100 * (15/30) = $50
    // Net amount = $50 - $150 = -$100 (account credit)
    expect(proration.unusedCredit).toBe(150);
    expect(proration.newPlanCharge).toBe(50);
    expect(proration.netAmount).toBe(-100);
    expect(proration.invoiceDescription).toContain('Credit $150.00, Charge $50.00, Net -$100.00');
  });

  it('3. Handles trial period upgrades without extra charge', () => {
    const proration = calculateProration(0, 200, periodStart, periodEnd, now, true, 'INR');

    expect(proration.inTrial).toBe(true);
    expect(proration.netAmount).toBe(0);
    expect(proration.invoiceDescription).toContain('Trial period active');
  });
});
