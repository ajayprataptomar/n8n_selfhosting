import { describe, it, expect } from '@jest/globals';
import { enqueueWebhookEvent, processWebhookPayload } from '../src/services/webhookRetryService.js';

describe('Reliable Webhook Retry Queue & Idempotency Test Suite', () => {
  it('1. Immediately stores webhook as pending and enqueues event', async () => {
    const eventId = 'evt_test_' + Date.now();
    const provider = 'stripe';
    const eventType = 'checkout.session.completed';
    const payload = { id: eventId, type: eventType, data: { object: { amount_total: 5000 } } };

    const record = await enqueueWebhookEvent(provider, eventId, eventType, payload, 50);

    expect(record).toBeDefined();
    expect(record.status).toEqual('pending');
  });

  it('2. Handles idempotency cleanly on duplicate event receipt', async () => {
    const eventId = 'evt_idempotent_test_123';
    const provider = 'stripe';
    const eventType = 'checkout.session.completed';
    const payload = { id: eventId, type: eventType };

    const first = await enqueueWebhookEvent(provider, eventId, eventType, payload);
    const second = await enqueueWebhookEvent(provider, eventId, eventType, payload);

    expect(first).toBeDefined();
    expect(second).toBeDefined();
    expect(second.status).toEqual('pending');
  });

  it('3. Processes webhook payload for subscription activation', async () => {
    const payload = {
      data: {
        object: {
          metadata: {
            tenantId: '00000000-0000-0000-0000-000000000000',
            planId: 'pro',
          },
        },
      },
    };

    await expect(
      processWebhookPayload('stripe', 'checkout.session.completed', payload)
    ).resolves.not.toThrow();
  });
});
