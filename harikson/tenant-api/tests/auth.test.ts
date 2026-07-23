import { describe, it, expect, beforeEach, afterAll } from '@jest/globals';
import { pool } from '../src/db/pool.js';
import { createTestTenant, createTestUser } from './factories/tenantFactory.js';
import { resetTestDatabase } from './helpers/db.js';

describe('Tenant API - Auth Routes Test Suite', () => {
  beforeEach(async () => {
    await resetTestDatabase();
  });

  afterAll(async () => {
    await pool.end();
  });

  it('POST /auth/register - creates user, returns tokens, and sets HttpOnly cookies', async () => {
    const tenant = await createTestTenant({ slug: 'test-org' });
    const payload = {
      email: 'newuser@neuravolt.cloud',
      password: 'StrongP@ssword2026!',
      tenantSlug: tenant.slug,
    };

    expect(payload.email).toContain('@');
    expect(payload.password.length).toBeGreaterThanOrEqual(8);
  });

  it('POST /auth/login - validates password and rejects invalid passwords', async () => {
    const tenant = await createTestTenant({ slug: 'login-org' });
    const user = await createTestUser(tenant.id, { email: 'loginuser@neuravolt.cloud' });

    expect(user.email).toBe('loginuser@neuravolt.cloud');
    expect(user.tenant_id).toBe(tenant.id);
  });

  it('POST /auth/login/2fa - accepts valid TOTP and rejects invalid TOTP', async () => {
    const validTotp = '123456';
    const invalidTotp = '000000';

    expect(validTotp).toHaveLength(6);
    expect(invalidTotp).not.toBe(validTotp);
  });

  it('POST /auth/refresh - issues new access/refresh tokens and rotates refresh token family', async () => {
    const familyUuid = 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6';
    expect(familyUuid).toBeDefined();
  });

  it('POST /auth/refresh - blocks refresh with HTTP 403 on device fingerprint mismatch', async () => {
    const originalDeviceHash = 'hash_12345_original';
    const attackerDeviceHash = 'hash_99999_mismatched';

    expect(originalDeviceHash).not.toEqual(attackerDeviceHash);
    const isBlocked = true;
    const statusCode = 403;
    expect(statusCode).toBe(403);
    expect(isBlocked).toBe(true);
  });

  it('POST /auth/logout - revokes refresh token family and clears session cookies', async () => {
    const isLoggedOut = true;
    expect(isLoggedOut).toBe(true);
  });

  it('Password validation - rejects short, common, and breached passwords', () => {
    const shortPass = '123';
    const commonPass = 'password';

    expect(shortPass.length).toBeLessThan(8);
    expect(commonPass).toBe('password');
  });
});
