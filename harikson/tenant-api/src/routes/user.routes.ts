import { Router } from 'express';
import crypto from 'crypto';
import bcrypt from 'bcrypt';
import QRCode from 'qrcode';
import { pool, executeTenantQuery, invalidateUserCache } from '../db/pool.js';
import logger from '../utils/logger.js';
import { validate } from '../middleware/validation.middleware.js';
import { profileUpdateSchema, settingsUpdateSchema } from '../validators/user.schema.js';
import {
  generateTotpSecret,
  generateOtpauthUrl,
  verifyTotpToken,
  generateHashedBackupCodes,
} from '../services/twoFactorService.js';

const router = Router();

// GET /api/user/profile
router.get('/profile', async (req: any, res) => {
  if (!req.user) return res.status(401).json({ error: 'Unauthorized' });

  try {
    const userRes = await pool.query(
      'SELECT id, email, name, role, two_factor_enabled, created_at FROM users WHERE id = $1 AND deleted_at IS NULL',
      [req.user.userId]
    );

    if (userRes.rows.length === 0) {
      return res.status(404).json({ error: 'User not found' });
    }

    res.json({ user: userRes.rows[0] });
  } catch (err: any) {
    logger.error('Fetch user profile error:', err);
    res.status(500).json({ error: 'Failed to fetch user profile' });
  }
});

// PUT /api/user/profile
router.put('/profile', validate(profileUpdateSchema), async (req: any, res) => {
  if (!req.user) return res.status(401).json({ error: 'Unauthorized' });

  const { name } = req.body;
  try {
    const updateRes = await pool.query(
      'UPDATE users SET name = COALESCE($1, name), updated_at = NOW() WHERE id = $2 RETURNING id, email, name, role',
      [name, req.user.userId]
    );

    await invalidateUserCache(req.user.userId);

    res.json({ success: true, user: updateRes.rows[0] });
  } catch (err: any) {
    logger.error('Update user profile error:', err);
    res.status(500).json({ error: 'Failed to update user profile' });
  }
});

// GET /api/user/sessions
router.get('/sessions', async (req: any, res) => {
  if (!req.user) return res.status(401).json({ error: 'Unauthorized' });

  try {
    const sessionsRes = await pool.query(
      `SELECT id, device_name, device_hash, last_ip, country_code, last_used_at, created_at 
       FROM refresh_tokens 
       WHERE user_id = $1 AND expires_at > NOW() 
       ORDER BY last_used_at DESC`,
      [req.user.userId]
    );

    res.json({ sessions: sessionsRes.rows });
  } catch (err: any) {
    logger.error('Fetch user sessions error:', err);
    res.status(500).json({ error: 'Failed to fetch user sessions' });
  }
});

// DELETE /api/user/sessions/:id
router.delete('/sessions/:id', async (req: any, res) => {
  if (!req.user) return res.status(401).json({ error: 'Unauthorized' });

  const { id } = req.params;
  try {
    await pool.query('DELETE FROM refresh_tokens WHERE id = $1 AND user_id = $2', [id, req.user.userId]);
    res.json({ success: true, message: 'Session revoked successfully' });
  } catch (err: any) {
    logger.error('Revoke session error:', err);
    res.status(500).json({ error: 'Failed to revoke session' });
  }
});

// POST /api/user/2fa/setup
router.post('/2fa/setup', async (req: any, res) => {
  if (!req.user) return res.status(401).json({ error: 'Unauthorized' });

  try {
    const userRes = await pool.query('SELECT email FROM users WHERE id = $1', [req.user.userId]);
    const userEmail = userRes.rows[0]?.email || 'user@neuravolt.cloud';

    const secret = generateTotpSecret();
    const otpauthUrl = generateOtpauthUrl(userEmail, secret, 'Neuravolt');
    const qrCodeDataUrl = await QRCode.toDataURL(otpauthUrl);

    await pool.query('UPDATE users SET two_factor_secret_temp = $1 WHERE id = $2', [secret, req.user.userId]);

    res.json({
      secret,
      qrCode: qrCodeDataUrl,
    });
  } catch (err: any) {
    logger.error('2FA setup error:', err);
    res.status(500).json({ error: 'Failed to generate 2FA setup details' });
  }
});

// POST /api/user/2fa/verify
router.post('/2fa/verify', async (req: any, res) => {
  if (!req.user) return res.status(401).json({ error: 'Unauthorized' });
  const { code } = req.body;

  if (!code) return res.status(400).json({ error: 'Verification code is required' });

  try {
    const userRes = await pool.query('SELECT two_factor_secret_temp FROM users WHERE id = $1', [req.user.userId]);
    const tempSecret = userRes.rows[0]?.two_factor_secret_temp;

    if (!tempSecret) {
      return res.status(400).json({ error: 'No 2FA setup session found. Please start setup again.' });
    }

    const isValid = verifyTotpToken(code, tempSecret);
    if (!isValid) {
      return res.status(400).json({ error: 'Invalid verification code' });
    }

    const { rawCodes, hashedCodes } = await generateHashedBackupCodes();

    await pool.query(
      `UPDATE users 
       SET two_factor_enabled = true, 
           two_factor_secret = two_factor_secret_temp, 
           two_factor_secret_temp = NULL,
           two_factor_backup_codes = $1
       WHERE id = $2`,
      [hashedCodes, req.user.userId]
    );

    await invalidateUserCache(req.user.userId);

    res.json({
      success: true,
      message: '2FA enabled successfully',
      backupCodes: rawCodes,
    });
  } catch (err: any) {
    logger.error('2FA verify error:', err);
    res.status(500).json({ error: 'Failed to enable 2FA' });
  }
});

// POST /api/user/2fa/disable
router.post('/2fa/disable', async (req: any, res) => {
  if (!req.user) return res.status(401).json({ error: 'Unauthorized' });

  try {
    await pool.query(
      `UPDATE users 
       SET two_factor_enabled = false, 
           two_factor_secret = NULL, 
           two_factor_secret_temp = NULL,
           two_factor_backup_codes = NULL
       WHERE id = $1`,
      [req.user.userId]
    );

    await invalidateUserCache(req.user.userId);

    res.json({ success: true, message: '2FA disabled successfully' });
  } catch (err: any) {
    logger.error('2FA disable error:', err);
    res.status(500).json({ error: 'Failed to disable 2FA' });
  }
});

// API Keys Endpoints
router.get('/api-keys', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });

  try {
    const keysRes = await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        'SELECT id, name, key_prefix, scopes, created_at, last_used_at, status FROM tenant_api_keys WHERE status = \'active\' ORDER BY created_at DESC'
      )
    );

    res.json({ apiKeys: keysRes.rows });
  } catch (err: any) {
    logger.error('Fetch API keys error:', err);
    res.status(500).json({ error: 'Failed to fetch API keys' });
  }
});

router.post('/api-keys', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });

  const { name, scopes } = req.body;
  const rawKey = 'hk_live_' + crypto.randomBytes(24).toString('hex');
  const prefix = rawKey.substring(0, 12);
  const keyHash = crypto.createHash('sha256').update(rawKey).digest('hex');

  try {
    const insertRes = await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        `INSERT INTO tenant_api_keys (tenant_id, user_id, name, key_hash, key_prefix, scopes, status, created_at)
         VALUES ($1, $2, $3, $4, $5, $6, 'active', NOW())
         RETURNING id, name, key_prefix, scopes, created_at`,
        [req.tenant.id, req.user?.userId || null, name || 'API Key', keyHash, prefix, JSON.stringify(scopes || ['read'])]
      )
    );

    res.status(201).json({
      apiKey: insertRes.rows[0],
      secretKey: rawKey,
    });
  } catch (err: any) {
    logger.error('Create API key error:', err);
    res.status(500).json({ error: 'Failed to create API key' });
  }
});

router.delete('/api-keys/:id', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });

  const { id } = req.params;
  try {
    await executeTenantQuery(req.tenant.id, (client) =>
      client.query('UPDATE tenant_api_keys SET status = \'revoked\', revoked_at = NOW() WHERE id = $1 AND tenant_id = $2', [
        id,
        req.tenant.id,
      ])
    );

    res.json({ success: true, message: 'API key revoked successfully' });
  } catch (err: any) {
    logger.error('Revoke API key error:', err);
    res.status(500).json({ error: 'Failed to revoke API key' });
  }
});

import { setupCustomDomain } from '../services/customDomainService.js';
import { generatePasskeyRegistrationOptions, savePasskeyCredential } from '../services/webauthnService.js';

// LOW-020: Setup custom domain and verify DNS CNAME record
router.post('/custom-domain', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });

  const { domain } = req.body;
  if (!domain) return res.status(400).json({ error: 'Domain is required' });

  try {
    const result = await setupCustomDomain(req.tenant.id, domain);
    res.json(result);
  } catch (err: any) {
    logger.error('Setup custom domain error:', err);
    res.status(500).json({ error: 'Failed to setup custom domain' });
  }
});

// LOW-024: WebAuthn Passkey Registration Options
router.post('/passkeys/generate-options', async (req: any, res) => {
  if (!req.user) return res.status(401).json({ error: 'Unauthorized' });

  try {
    const userRes = await pool.query('SELECT email FROM users WHERE id = $1', [req.user.userId]);
    const email = userRes.rows[0]?.email || 'user@neuravolt.cloud';

    const options = await generatePasskeyRegistrationOptions(req.user.userId, email);
    res.json(options);
  } catch (err: any) {
    logger.error('Generate passkey registration options error:', err);
    res.status(500).json({ error: 'Failed to generate passkey options' });
  }
});

// LOW-024: WebAuthn Passkey Registration Verification & Storage
router.post('/passkeys/verify-registration', async (req: any, res) => {
  if (!req.user) return res.status(401).json({ error: 'Unauthorized' });

  const { credentialId, publicKey, deviceName = 'Security Key' } = req.body;
  if (!credentialId || !publicKey) {
    return res.status(400).json({ error: 'credentialId and publicKey are required' });
  }

  try {
    const passkey = await savePasskeyCredential(req.user.userId, credentialId, publicKey, deviceName);
    res.json({ success: true, message: 'Passkey registered successfully', passkey });
  } catch (err: any) {
    logger.error('Verify passkey registration error:', err);
    res.status(500).json({ error: 'Failed to register passkey' });
  }
});

export default router;
