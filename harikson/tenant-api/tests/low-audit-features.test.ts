import { describe, it, expect } from '@jest/globals';
import { checkTrialExpirations } from '../src/services/trialService.js';
import { setupCustomDomain } from '../src/services/customDomainService.js';
import { generatePasskeyRegistrationOptions, generatePasskeyAuthOptions } from '../src/services/webauthnService.js';

describe('LOW-015, LOW-017, LOW-020, LOW-024 Audit Features Test Suite', () => {
  const tenantId = '00000000-0000-0000-0000-000000000000';
  const userId = '00000000-0000-0000-0000-000000000000';
  const email = 'admin@harikson.ai';

  it('1. LOW-015: Checks trial period expirations and reminders without error', async () => {
    await expect(checkTrialExpirations()).resolves.not.toThrow();
  });

  it('2. LOW-020: Sets up custom domain and performs CNAME verification', async () => {
    const res = await setupCustomDomain(tenantId, 'custom.company.com');
    expect(res).toBeDefined();
    expect(res.message).toContain('DNS');
  });

  it('3. LOW-024: Generates WebAuthn passkey registration options', async () => {
    const options = await generatePasskeyRegistrationOptions(userId, email);
    expect(options.rp.name).toBe('Neuravolt AI Cloud');
    expect(options.user.name).toBe(email);
  });
});
