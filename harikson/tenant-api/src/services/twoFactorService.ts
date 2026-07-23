import bcrypt from 'bcrypt';
import crypto from 'crypto';
import { authenticator } from 'otplib';
import { pool } from '../db/pool.js';
import { Logger } from '../observability/logger.js';

// Configure otplib authenticator options (window of ±1 step = 30 seconds drift tolerance)
authenticator.options = { window: 1 };

export interface BackupCodeRecord {
  hash: string;
  used_at: string | null;
}

/**
 * Generate base32 secret for TOTP using otplib.
 */
export function generateTotpSecret(): string {
  return authenticator.generateSecret();
}

/**
 * Generate OTPAuth URI string for QR code generation using otplib.
 */
export function generateOtpauthUrl(email: string, secret: string, serviceName: string = 'Neuravolt'): string {
  return authenticator.keyuri(email, serviceName, secret);
}

/**
 * Verify input TOTP code against base32 secret using otplib.
 */
export function verifyTotpToken(code: string, secret: string): boolean {
  try {
    return authenticator.verify({ token: code, secret });
  } catch (err) {
    return false;
  }
}

/**
 * Generate 10 crypto-random 8-character alphanumeric backup codes.
 * Returns plain codes for single-display to user and bcrypt-hashed records for DB storage.
 */
export async function generateHashedBackupCodes(): Promise<{
  plainCodes: string[];
  hashedRecords: BackupCodeRecord[];
}> {
  const plainCodes: string[] = [];
  const hashedRecords: BackupCodeRecord[] = [];

  for (let i = 0; i < 10; i++) {
    // Generate 8-character alphanumeric code
    const code = crypto.randomBytes(4).toString('hex').toUpperCase();
    plainCodes.push(code);

    const hash = await bcrypt.hash(code, 10);
    hashedRecords.push({
      hash,
      used_at: null,
    });
  }

  return { plainCodes, hashedRecords };
}

/**
 * Verify backup code input against bcrypt-hashed records.
 * Marks code as used (used_at = NOW()) if valid and unused.
 * Increments failed_attempts and locks account after 5 failures.
 */
export async function verifyBackupCode(
  userId: string,
  inputCode: string
): Promise<{
  success: boolean;
  error?: string;
  locked?: boolean;
  attemptsLeft?: number;
}> {
  const client = await pool.connect();
  try {
    const userRes = await client.query(
      `SELECT id, email, two_factor_backup_codes, failed_2fa_attempts, locked_until 
       FROM users WHERE id = $1`,
      [userId]
    );

    if (userRes.rows.length === 0) {
      return { success: false, error: 'User not found' };
    }

    const user = userRes.rows[0];

    // 1. Check Lock Status
    if (user.locked_until && new Date(user.locked_until) > new Date()) {
      const lockMinutes = Math.ceil(
        (new Date(user.locked_until).getTime() - Date.now()) / (60 * 1000)
      );
      return {
        success: false,
        locked: true,
        error: `Account is temporarily locked due to 5 failed 2FA attempts. Try again in ${lockMinutes} minutes.`,
      };
    }

    const rawCodes = user.two_factor_backup_codes || [];
    let records: BackupCodeRecord[] = [];
    if (Array.isArray(rawCodes)) {
      records = rawCodes.map((r: any) =>
        typeof r === 'string' ? { hash: r, used_at: null } : r
      );
    }

    let matchIndex = -1;
    const cleanInput = inputCode.trim().toUpperCase();

    // 2. Compare Input Code Against Hashes
    for (let i = 0; i < records.length; i++) {
      const isMatch = await bcrypt.compare(cleanInput, records[i].hash);
      if (isMatch) {
        matchIndex = i;
        break;
      }
    }

    // 3. Match Evaluation
    if (matchIndex !== -1) {
      const record = records[matchIndex];
      if (record.used_at !== null) {
        return {
          success: false,
          error: 'This backup code has already been used. Please enter an unused code.',
        };
      }

      // Mark code as used
      records[matchIndex].used_at = new Date().toISOString();
      await client.query(
        `UPDATE users 
         SET two_factor_backup_codes = $1::jsonb, 
             failed_2fa_attempts = 0, 
             locked_until = NULL 
         WHERE id = $2`,
        [JSON.stringify(records), userId]
      );

      Logger.info(`[2FA] Backup code successfully verified and marked used for user ${userId}`);
      return { success: true };
    }

    // 4. Failed Attempt Counter & Lockout
    const newFailCount = (user.failed_2fa_attempts || 0) + 1;
    let lockTime: Date | null = null;
    if (newFailCount >= 5) {
      lockTime = new Date(Date.now() + 15 * 60 * 1000); // 15 minute lock
      Logger.warn(`🚨 [2FA Lockout] User ${userId} locked for 15 minutes after 5 failed 2FA attempts.`);
    }

    await client.query(
      `UPDATE users 
       SET failed_2fa_attempts = $1, 
           locked_until = $2 
       WHERE id = $3`,
      [newFailCount, lockTime, userId]
    );

    return {
      success: false,
      error: newFailCount >= 5
        ? 'Account temporarily locked for 15 minutes due to 5 failed 2FA attempts.'
        : 'Invalid verification or backup code.',
      attemptsLeft: Math.max(0, 5 - newFailCount),
    };

  } finally {
    client.release();
  }
}
