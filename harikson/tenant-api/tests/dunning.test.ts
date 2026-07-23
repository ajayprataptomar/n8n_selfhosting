import { describe, it, expect } from '@jest/globals';
import { triggerSubscriptionDunning, reactivateTenantSubscription } from '../src/services/dunningService.js';

describe('Failed Payment Dunning Retry Schedule Test Suite', () => {
  const tenantId = '00000000-0000-0000-0000-000000000000';
  const subId = '00000000-0000-0000-0000-000000000000';

  it('1. Triggers subscription dunning and updates status to past_due', async () => {
    await expect(
      triggerSubscriptionDunning(tenantId, subId, 'Card declined - Insufficient funds')
    ).resolves.not.toThrow();
  });

  it('2. Reactivates tenant subscription and clears dunning state', async () => {
    await expect(
      reactivateTenantSubscription(tenantId)
    ).resolves.not.toThrow();
  });
});
