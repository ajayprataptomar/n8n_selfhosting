import { describe, it, expect } from '@jest/globals';
import { authenticator } from 'otplib';
import {
  generateTotpSecret,
  generateOtpauthUrl,
  verifyTotpToken,
  generateHashedBackupCodes,
} from '../src/services/twoFactorService.js';

describe('2FA Service - otplib Migration Test Suite', () => {
  it('1. Generates secret using otplib', () => {
    const secret = generateTotpSecret();
    expect(secret).toBeDefined();
    expect(typeof secret).toBe('string');
    expect(secret.length).toBeGreaterThanOrEqual(16);
  });

  it('2. Generates otpauth URL with email and issuer', () => {
    const secret = generateTotpSecret();
    const userEmail = 'user@neuravolt.cloud';
    const otpauthUrl = generateOtpauthUrl(userEmail, secret, 'Neuravolt');

    expect(otpauthUrl).toContain('otpauth://totp/');
    expect(otpauthUrl).toContain('Neuravolt');
    expect(otpauthUrl).toContain(encodeURIComponent(userEmail));
    expect(otpauthUrl).toContain(secret);
  });

  it('3. Verifies valid TOTP token generated for secret', () => {
    const secret = generateTotpSecret();
    const validToken = authenticator.generate(secret);

    const isValid = verifyTotpToken(validToken, secret);
    expect(isValid).toBe(true);
  });

  it('4. Rejects invalid TOTP token', () => {
    const secret = generateTotpSecret();
    const invalidToken = '000000';

    const isValid = verifyTotpToken(invalidToken, secret);
    expect(isValid).toBe(false);
  });

  it('5. Generates 10 bcrypt-hashed backup code records', async () => {
    const { plainCodes, hashedRecords } = await generateHashedBackupCodes();

    expect(plainCodes).toHaveLength(10);
    expect(hashedRecords).toHaveLength(10);
    expect(plainCodes[0]).toHaveLength(8);
    expect(hashedRecords[0].hash).toMatch(/^\$2[ayb]\$/);
    expect(hashedRecords[0].used_at).toBeNull();
  });
});
