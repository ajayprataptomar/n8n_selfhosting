import { Router } from 'express';
import bcrypt from 'bcrypt';
import crypto from 'crypto';
import jwt from 'jsonwebtoken';
import { Redis } from 'ioredis';
import { pool, executeTenantQuery } from '../db/pool.js';
import logger from '../utils/logger.js';
import { validate } from '../middleware/validation.middleware.js';
import {
  loginSchema,
  registerSchema,
  forgotPasswordSchema,
  resetPasswordSchema,
} from '../validators/auth.schema.js';
import {
  sendPasswordReset,
  sendWelcomeEmail,
  sendVerificationEmail,
  sendAccountLockoutAlert,
  sendDeviceMismatchAlert,
} from '../services/email.js';
import { verifyTotpToken, verifyBackupCode } from '../services/twoFactorService.js';
import { computeDeviceFingerprint } from '../services/deviceService.js';

const router = Router();
const redis = new Redis(process.env.REDIS_URL || 'redis://redis:6379', {
  retryStrategy: (times) => Math.min(times * 50, 2000),
  maxRetriesPerRequest: 3,
  enableReadyCheck: true,
});

const jwtSecret = process.env.JWT_SECRET || 'dev-jwt-secret-key-change-in-prod';

// Helper: Handle Login Logic
async function handleLogin(req: any, res: any) {
  const { email, password } = req.body;
  try {
    const ip =
      ((req.headers['x-forwarded-for'] as any) || req.socket?.remoteAddress || '')
        .split(',')[0]
        .trim() || '127.0.0.1';
    const key = `ratelimit:login:${ip}`;
    const attempts = await redis.incr(key);
    if (attempts === 1) {
      await redis.expire(key, 60); // 60s sliding window instead of 3600s
    }
    if (attempts > 60) { // 60 attempts/min threshold instead of 5/hr
      return res.status(429).json({
        error: 'Too many login attempts. Rate limit exceeded. Try again in a minute.',
      });
    }

    let userResult = await pool.query(
      'SELECT * FROM users WHERE email = $1 AND tenant_id = $2 AND deleted_at IS NULL',
      [email, req.tenant?.id]
    );
    let user = userResult.rows[0];
    if (!user && req.tenant?.slug === 'neuravolt' && email !== 'admin@harikson.ai') {
      const fallbackResult = await pool.query(
        'SELECT * FROM users WHERE email = $1 AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1',
        [email]
      );
      if (fallbackResult.rows.length > 0) {
        user = fallbackResult.rows[0];
      }
    }

    if (!user) {
      return res.status(401).json({ error: 'Invalid email or password' });
    }

    if (user.locked_until && new Date(user.locked_until) > new Date()) {
      const retryAfter = Math.ceil((new Date(user.locked_until).getTime() - Date.now()) / 1000);
      res.setHeader('Retry-After', String(retryAfter));
      return res.status(429).json({
        error: 'Account is temporarily locked due to multiple failed login attempts.',
        locked: true,
        retryAfter,
        email: user.email,
      });
    }

    let valid = false;
    if (user.password_hash) {
      valid = await bcrypt.compare(password, user.password_hash);
    }

    if (!valid) {
      const failCount = (user.failed_login_attempts || 0) + 1;
      let updateQuery = 'UPDATE users SET failed_login_attempts = $1 WHERE id = $2';
      let queryParams: any[] = [failCount, user.id];

      if (failCount >= 10) {
        const lockoutCount = user.lockout_count || 0;
        let lockoutHours = 1;
        let durationText = '1 hour';
        if (lockoutCount === 1) {
          lockoutHours = 4;
          durationText = '4 hours';
        } else if (lockoutCount >= 2) {
          lockoutHours = 24;
          durationText = '24 hours';
        }

        const unlockToken = crypto.randomBytes(32).toString('hex');
        const unlockTokenHash = crypto.createHash('sha256').update(unlockToken).digest('hex');

        updateQuery = `
          UPDATE users 
          SET failed_login_attempts = $1,
              locked_until = NOW() + INTERVAL '${lockoutHours} hours',
              lockout_count = lockout_count + 1,
              unlock_token = $3
          WHERE id = $2`;
        queryParams = [failCount, user.id, unlockTokenHash];

        const unlockUrl = `https://app.neuravolt.cloud/unlock-account?token=${unlockToken}`;
        sendAccountLockoutAlert(user.email, unlockUrl, durationText).catch((err) =>
          logger.error('[ACCOUNT LOCKOUT ALERT ERROR]:', err.message)
        );
      }

      await pool.query(updateQuery, queryParams);
      return res.status(401).json({ error: 'Invalid email or password' });
    }

    await pool.query(
      'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, lockout_count = 0, unlock_token = NULL WHERE id = $1',
      [user.id]
    );
    await redis.del(key).catch(() => {});

    if (user.email_verified === false) {
      return res.status(403).json({
        error: 'Email verification required. Please check your inbox.',
        requireVerification: true,
        email: user.email,
      });
    }

    if (user.two_factor_enabled) {
      return res.json({
        success: false,
        requires2FA: true,
        userId: user.id,
      });
    }

    let resolvedTenantId = req.tenant?.id || user.tenant_id;
    let resolvedTenantSlug = req.tenant?.slug || 'neuravolt';
    if (user.tenant_id !== req.tenant?.id) {
      const tenantRes = await pool.query('SELECT slug FROM tenants WHERE id = $1', [user.tenant_id]);
      if (tenantRes.rows.length > 0) {
        resolvedTenantId = user.tenant_id;
        resolvedTenantSlug = tenantRes.rows[0].slug;
      }
    }

    const accessToken = jwt.sign({ userId: user.id, role: user.role }, jwtSecret, { expiresIn: '15m' });
    const refreshToken = crypto.randomBytes(64).toString('hex');
    const refreshTokenHash = crypto.createHash('sha256').update(refreshToken).digest('hex');
    const expiresAt = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000);
    const familyId = crypto.randomUUID();
    const fingerprint = computeDeviceFingerprint(req);

    await pool.query(
      `INSERT INTO refresh_tokens (
        token, user_id, tenant_id, expires_at, refresh_token_family,
        device_hash, device_name, last_ip, country_code, last_used_at
       )
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, NOW())`,
      [
        refreshTokenHash,
        user.id,
        resolvedTenantId,
        expiresAt,
        familyId,
        fingerprint.deviceHash,
        fingerprint.deviceName,
        fingerprint.ip,
        fingerprint.countryCode,
      ]
    );

    const host = req.headers.host || '';
    const domainSuffix = host.includes('neuravolt.cloud') ? '; Domain=.neuravolt.cloud' : '';
    const isHttps = req.headers['x-forwarded-proto'] === 'https' || (req.socket as any)?.encrypted;
    const secureFlag = isHttps ? 'Secure;' : '';

    res.setHeader('Set-Cookie', [
      `hk_access_token=${accessToken}; HttpOnly; ${secureFlag} SameSite=Strict; Path=/; Max-Age=${15 * 60}${domainSuffix}`,
      `hk_refresh_token=${refreshToken}; HttpOnly; ${secureFlag} SameSite=Strict; Path=/; Max-Age=${30 * 24 * 60 * 60}${domainSuffix}`,
    ]);

    res.json({
      success: true,
      token: accessToken,
      refreshToken,
      user: {
        id: user.id,
        email: user.email,
        name: user.name,
        role: user.role,
        tenantId: resolvedTenantId,
        tenantSlug: resolvedTenantSlug,
      },
    });
  } catch (err: any) {
    logger.error('Login error:', err);
    res.status(500).json({ error: 'Login failed due to a server error' });
  }
}

// POST /register and POST /v1/register
async function handleRegister(req: any, res: any) {
  const { email, password, name, companyName, tenantSlug } = req.body;
  try {
    const existing = await pool.query('SELECT id FROM users WHERE email = $1 AND deleted_at IS NULL', [email]);
    if (existing.rows.length > 0) {
      return res.status(409).json({ error: 'User with this email already exists' });
    }

    let tenantId = req.tenant?.id;
    let finalSlug = req.tenant?.slug || tenantSlug || 'neuravolt';

    if (!tenantId || companyName || tenantSlug) {
      const slugToUse = (tenantSlug || companyName || 'tenant-' + crypto.randomBytes(4).toString('hex'))
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '');

      const existingTenant = await pool.query('SELECT id FROM tenants WHERE slug = $1', [slugToUse]);
      if (existingTenant.rows.length > 0) {
        tenantId = existingTenant.rows[0].id;
      } else {
        const newTenantRes = await pool.query(
          `INSERT INTO tenants (name, slug, status, created_at)
           VALUES ($1, $2, 'active', NOW())
           RETURNING id`,
          [companyName || name + "'s Org", slugToUse]
        );
        tenantId = newTenantRes.rows[0].id;
      }
      finalSlug = slugToUse;
    }

    const passwordHash = await bcrypt.hash(password, 12);
    const verificationToken = crypto.randomBytes(32).toString('hex');
    const verificationTokenHash = crypto.createHash('sha256').update(verificationToken).digest('hex');

    const newUserRes = await pool.query(
      `INSERT INTO users (
        tenant_id, email, password_hash, name, role, email_verified, verification_token, created_at
       )
       VALUES ($1, $2, $3, $4, 'admin', false, $5, NOW())
       RETURNING id, email, name, role, created_at`,
      [tenantId, email, passwordHash, name, verificationTokenHash]
    );

    const user = newUserRes.rows[0];

    // LOW-015: Create trialing subscription record
    const planRes = await pool.query(`SELECT id, trial_days FROM plans ORDER BY price ASC LIMIT 1`);
    const plan = planRes.rows[0];
    const trialDays = plan?.trial_days || 14;

    await pool.query(
      `INSERT INTO subscriptions (
        tenant_id, plan_id, status, current_period_start, current_period_end, amount, currency, created_at
       )
       VALUES ($1, $2, 'trialing', NOW(), NOW() + ($3 || '14')::int * INTERVAL '1 day', 0, 'INR', NOW())`,
      [tenantId, plan?.id || 'starter', trialDays]
    );

    const verifyUrl = `https://app.neuravolt.cloud/verify-email?token=${verificationToken}`;
    sendVerificationEmail(user.email, verifyUrl).catch((err) =>
      logger.error('Failed to send verification email:', err)
    );

    res.status(201).json({
      message: 'Registration successful. Please check your email to verify your account.',
      user: {
        id: user.id,
        email: user.email,
        name: user.name,
        role: user.role,
        tenantId,
        tenantSlug: finalSlug,
      },
    });
  } catch (err: any) {
    logger.error('Registration error:', err);
    res.status(500).json({ error: 'Registration failed due to a server error' });
  }
}

router.post('/register', validate(registerSchema), handleRegister);
router.post('/v1/register', validate(registerSchema), handleRegister);

router.post('/login', validate(loginSchema), handleLogin);
router.post('/v1/login', validate(loginSchema), handleLogin);

// POST /login/2fa - Complete 2FA Login
router.post('/login/2fa', async (req, res) => {
  const { userId, code } = req.body;
  if (!userId || !code) {
    return res.status(400).json({ error: 'userId and 2FA code are required' });
  }

  try {
    const userRes = await pool.query('SELECT * FROM users WHERE id = $1 AND deleted_at IS NULL', [userId]);
    const user = userRes.rows[0];

    if (!user || !user.two_factor_enabled || !user.two_factor_secret) {
      return res.status(400).json({ error: '2FA is not enabled for this user' });
    }

    let isCodeValid = verifyTotpToken(code, user.two_factor_secret);
    if (!isCodeValid) {
      const isBackupValid = await verifyBackupCode(userId, code);
      if (isBackupValid) {
        isCodeValid = true;
      }
    }

    if (!isCodeValid) {
      return res.status(401).json({ error: 'Invalid 2FA code' });
    }

    const accessToken = jwt.sign({ userId: user.id, role: user.role }, jwtSecret, { expiresIn: '15m' });
    const refreshToken = crypto.randomBytes(64).toString('hex');
    const refreshTokenHash = crypto.createHash('sha256').update(refreshToken).digest('hex');
    const expiresAt = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000);
    const familyId = crypto.randomUUID();
    const fingerprint = computeDeviceFingerprint(req);

    await pool.query(
      `INSERT INTO refresh_tokens (
        token, user_id, tenant_id, expires_at, refresh_token_family,
        device_hash, device_name, last_ip, country_code, last_used_at
       )
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, NOW())`,
      [
        refreshTokenHash,
        user.id,
        user.tenant_id,
        expiresAt,
        familyId,
        fingerprint.deviceHash,
        fingerprint.deviceName,
        fingerprint.ip,
        fingerprint.countryCode,
      ]
    );

    const host = req.headers.host || '';
    const domainSuffix = host.includes('neuravolt.cloud') ? '; Domain=.neuravolt.cloud' : '';
    const isHttps = req.headers['x-forwarded-proto'] === 'https' || (req.socket as any)?.encrypted;
    const secureFlag = isHttps ? 'Secure;' : '';

    res.setHeader('Set-Cookie', [
      `hk_access_token=${accessToken}; HttpOnly; ${secureFlag} SameSite=Strict; Path=/; Max-Age=${15 * 60}${domainSuffix}`,
      `hk_refresh_token=${refreshToken}; HttpOnly; ${secureFlag} SameSite=Strict; Path=/; Max-Age=${30 * 24 * 60 * 60}${domainSuffix}`,
    ]);

    res.json({
      success: true,
      token: accessToken,
      refreshToken,
      user: {
        id: user.id,
        email: user.email,
        name: user.name,
        role: user.role,
        tenantId: user.tenant_id,
      },
    });
  } catch (err: any) {
    logger.error('2FA Login error:', err);
    res.status(500).json({ error: '2FA authentication failed' });
  }
});

// POST /refresh - Refresh Token
async function handleRefresh(req: any, res: any) {
  const refreshToken = req.body?.refreshToken || req.cookies?.hk_refresh_token;
  if (!refreshToken) {
    return res.status(401).json({ error: 'Refresh token missing' });
  }

  try {
    const refreshTokenHash = crypto.createHash('sha256').update(refreshToken).digest('hex');
    const tokenRes = await pool.query('SELECT * FROM refresh_tokens WHERE token = $1', [refreshTokenHash]);
    const rtRecord = tokenRes.rows[0];

    if (!rtRecord) {
      return res.status(401).json({ error: 'Invalid refresh token' });
    }

    if (new Date(rtRecord.expires_at) < new Date()) {
      await pool.query('DELETE FROM refresh_tokens WHERE id = $1', [rtRecord.id]);
      return res.status(401).json({ error: 'Refresh token expired. Please log in again.' });
    }

    // Fingerprint Mismatch Check
    const currentFingerprint = computeDeviceFingerprint(req);
    if (rtRecord.device_hash && rtRecord.device_hash !== currentFingerprint.deviceHash) {
      await pool.query('DELETE FROM refresh_tokens WHERE refresh_token_family = $1', [
        rtRecord.refresh_token_family,
      ]);
      const host = req.headers.host || '';
      const domainSuffix = host.includes('neuravolt.cloud') ? '; Domain=.neuravolt.cloud' : '';
      res.setHeader('Set-Cookie', [
        `hk_access_token=; HttpOnly; SameSite=Strict; Path=/; Max-Age=0${domainSuffix}`,
        `hk_refresh_token=; HttpOnly; SameSite=Strict; Path=/; Max-Age=0${domainSuffix}`,
      ]);

      const userRes = await pool.query('SELECT email FROM users WHERE id = $1', [rtRecord.user_id]);
      if (userRes.rows[0]?.email) {
        sendDeviceMismatchAlert(userRes.rows[0].email, currentFingerprint.ip, currentFingerprint.deviceName).catch(
          (err) => logger.error('Failed to send device mismatch email:', err)
        );
      }

      return res.status(403).json({ error: 'Device mismatch detected. Please log in again.' });
    }

    const userRes = await pool.query('SELECT * FROM users WHERE id = $1 AND deleted_at IS NULL', [rtRecord.user_id]);
    const user = userRes.rows[0];
    if (!user) {
      return res.status(401).json({ error: 'User no longer exists' });
    }

    const newAccessToken = jwt.sign({ userId: user.id, role: user.role }, jwtSecret, { expiresIn: '15m' });
    const newRefreshToken = crypto.randomBytes(64).toString('hex');
    const newRefreshTokenHash = crypto.createHash('sha256').update(newRefreshToken).digest('hex');

    await pool.query('DELETE FROM refresh_tokens WHERE id = $1', [rtRecord.id]);
    await pool.query(
      `INSERT INTO refresh_tokens (
        token, user_id, tenant_id, expires_at, refresh_token_family,
        device_hash, device_name, last_ip, country_code, last_used_at
       )
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, NOW())`,
      [
        newRefreshTokenHash,
        user.id,
        user.tenant_id,
        new Date(Date.now() + 30 * 24 * 60 * 60 * 1000),
        rtRecord.refresh_token_family,
        currentFingerprint.deviceHash,
        currentFingerprint.deviceName,
        currentFingerprint.ip,
        currentFingerprint.countryCode,
      ]
    );

    const host = req.headers.host || '';
    const domainSuffix = host.includes('neuravolt.cloud') ? '; Domain=.neuravolt.cloud' : '';
    const isHttps = req.headers['x-forwarded-proto'] === 'https' || (req.socket as any)?.encrypted;
    const secureFlag = isHttps ? 'Secure;' : '';

    res.setHeader('Set-Cookie', [
      `hk_access_token=${newAccessToken}; HttpOnly; ${secureFlag} SameSite=Strict; Path=/; Max-Age=${15 * 60}${domainSuffix}`,
      `hk_refresh_token=${newRefreshToken}; HttpOnly; ${secureFlag} SameSite=Strict; Path=/; Max-Age=${30 * 24 * 60 * 60}${domainSuffix}`,
    ]);

    res.json({
      success: true,
      token: newAccessToken,
      refreshToken: newRefreshToken,
    });
  } catch (err: any) {
    logger.error('Token refresh error:', err);
    res.status(500).json({ error: 'Token refresh failed' });
  }
}

router.post('/refresh', handleRefresh);
router.post('/v1/refresh', handleRefresh);

// POST /logout
async function handleLogout(req: any, res: any) {
  const refreshToken = req.body?.refreshToken || req.cookies?.hk_refresh_token;
  if (refreshToken) {
    const refreshTokenHash = crypto.createHash('sha256').update(refreshToken).digest('hex');
    await pool.query('DELETE FROM refresh_tokens WHERE token = $1', [refreshTokenHash]).catch(() => {});
  }

  const host = req.headers.host || '';
  const domainSuffix = host.includes('neuravolt.cloud') ? '; Domain=.neuravolt.cloud' : '';
  res.setHeader('Set-Cookie', [
    `hk_access_token=; HttpOnly; SameSite=Strict; Path=/; Max-Age=0${domainSuffix}`,
    `hk_refresh_token=; HttpOnly; SameSite=Strict; Path=/; Max-Age=0${domainSuffix}`,
  ]);

  res.json({ success: true, message: 'Logged out successfully' });
}

router.post('/logout', handleLogout);
router.post('/v1/logout', handleLogout);

// POST /forgot-password
async function handleForgotPassword(req: any, res: any) {
  const { email } = req.body;
  try {
    const userRes = await pool.query('SELECT * FROM users WHERE email = $1 AND deleted_at IS NULL', [email]);
    if (userRes.rows.length === 0) {
      return res.json({ message: 'If that email exists, a password reset link has been sent.' });
    }

    const resetToken = crypto.randomBytes(32).toString('hex');
    const resetTokenHash = crypto.createHash('sha256').update(resetToken).digest('hex');
    const expiresAt = new Date(Date.now() + 60 * 60 * 1000); // 1 hour

    await pool.query(
      'UPDATE users SET password_reset_token = $1, password_reset_expires = $2 WHERE id = $3',
      [resetTokenHash, expiresAt, userRes.rows[0].id]
    );

    const resetUrl = `https://app.neuravolt.cloud/reset-password?token=${resetToken}`;
    sendPasswordReset(email, resetUrl).catch((err) => logger.error('Failed to send password reset email:', err));

    res.json({ message: 'If that email exists, a password reset link has been sent.' });
  } catch (err: any) {
    logger.error('Forgot password error:', err);
    res.status(500).json({ error: 'Password reset request failed' });
  }
}

router.post('/forgot-password', validate(forgotPasswordSchema), handleForgotPassword);
router.post('/v1/forgot-password', validate(forgotPasswordSchema), handleForgotPassword);

// POST /reset-password
async function handleResetPassword(req: any, res: any) {
  const { token, newPassword } = req.body;
  try {
    const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
    const userRes = await pool.query(
      'SELECT * FROM users WHERE password_reset_token = $1 AND password_reset_expires > NOW() AND deleted_at IS NULL',
      [tokenHash]
    );

    if (userRes.rows.length === 0) {
      return res.status(400).json({ error: 'Invalid or expired password reset token' });
    }

    const user = userRes.rows[0];
    const passwordHash = await bcrypt.hash(newPassword, 12);

    await pool.query(
      'UPDATE users SET password_hash = $1, password_reset_token = NULL, password_reset_expires = NULL WHERE id = $2',
      [passwordHash, user.id]
    );

    await pool.query('DELETE FROM refresh_tokens WHERE user_id = $1', [user.id]);

    res.json({ message: 'Password has been reset successfully. Please log in with your new password.' });
  } catch (err: any) {
    logger.error('Reset password error:', err);
    res.status(500).json({ error: 'Password reset failed' });
  }
}

router.post('/reset-password', validate(resetPasswordSchema), handleResetPassword);
router.post('/v1/reset-password', validate(resetPasswordSchema), handleResetPassword);

// GET /verify-email
router.get('/verify-email', async (req, res) => {
  const { token } = req.query;
  if (!token || typeof token !== 'string') {
    return res.status(400).json({ error: 'Verification token is required' });
  }

  try {
    const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
    const userRes = await pool.query(
      'SELECT * FROM users WHERE verification_token = $1 AND deleted_at IS NULL',
      [tokenHash]
    );

    if (userRes.rows.length === 0) {
      return res.status(400).json({ error: 'Invalid or expired verification token' });
    }

    const user = userRes.rows[0];
    await pool.query('UPDATE users SET email_verified = true, verification_token = NULL WHERE id = $1', [
      user.id,
    ]);

    sendWelcomeEmail(user.email, user.name).catch((err) => logger.error('Failed to send welcome email:', err));

    res.json({ message: 'Email verified successfully. You can now log in.' });
  } catch (err: any) {
    logger.error('Email verification error:', err);
    res.status(500).json({ error: 'Email verification failed' });
  }
});

import { generatePasskeyAuthOptions } from '../services/webauthnService.js';

// LOW-017: OAuth Google Login Redirect
router.get('/google', (req, res) => {
  const redirectUrl = `https://accounts.google.com/o/oauth2/v2/auth?client_id=${process.env.GOOGLE_CLIENT_ID || 'mock_google_id'}&redirect_uri=https://app.neuravolt.cloud/login?sso=google&response_type=code&scope=email%20profile`;
  res.redirect(redirectUrl);
});

// LOW-017: OAuth Microsoft Login Redirect
router.get('/microsoft', (req, res) => {
  const redirectUrl = `https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=${process.env.MICROSOFT_CLIENT_ID || 'mock_ms_id'}&redirect_uri=https://app.neuravolt.cloud/login?sso=microsoft&response_type=code&scope=openid%20email%20profile`;
  res.redirect(redirectUrl);
});

// LOW-024: WebAuthn Passkey Login Options
router.post('/passkeys/generate-options', async (req, res) => {
  const { email } = req.body;
  if (!email) return res.status(400).json({ error: 'Email is required for passkey login' });

  try {
    const options = await generatePasskeyAuthOptions(email);
    res.json(options);
  } catch (err: any) {
    logger.error('Passkey auth options error:', err);
    res.status(400).json({ error: err.message || 'Failed to generate passkey authentication options' });
  }
});

export default router;
