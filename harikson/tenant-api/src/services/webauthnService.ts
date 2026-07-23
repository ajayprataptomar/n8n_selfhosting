import crypto from 'crypto';
import { pool } from '../db/pool.js';
import logger from '../utils/logger.js';

export interface PasskeyCredential {
  id: string;
  user_id: string;
  credential_id: string;
  public_key: string;
  counter: number;
  device_name: string;
}

/**
 * Generate WebAuthn passkey registration options for user.
 */
export async function generatePasskeyRegistrationOptions(userId: string, email: string) {
  const challenge = crypto.randomBytes(32).toString('base64url');

  return {
    challenge,
    rp: {
      name: 'Neuravolt AI Cloud',
      id: process.env.WEBAUTHN_RP_ID || 'neuravolt.cloud',
    },
    user: {
      id: Buffer.from(userId).toString('base64url'),
      name: email,
      displayName: email.split('@')[0],
    },
    pubKeyCredParams: [
      { alg: -7, type: 'public-key' },  // ES256
      { alg: -257, type: 'public-key' }, // RS256
    ],
    timeout: 60000,
    attestation: 'none',
  };
}

/**
 * Save user passkey credential in DB.
 */
export async function savePasskeyCredential(
  userId: string,
  credentialId: string,
  publicKey: string,
  deviceName: string = 'Security Key'
) {
  const insertRes = await pool.query(
    `INSERT INTO user_passkeys (user_id, credential_id, public_key, device_name, created_at)
     VALUES ($1, $2, $3, $4, NOW())
     RETURNING id, credential_id, device_name, created_at`,
    [userId, credentialId, publicKey, deviceName]
  );
  logger.info(`🔑 Passkey saved for user ${userId}: ${deviceName}`);
  return insertRes.rows[0];
}

/**
 * Generate WebAuthn passkey authentication options.
 */
export async function generatePasskeyAuthOptions(email: string) {
  const userRes = await pool.query('SELECT id FROM users WHERE email = $1 AND deleted_at IS NULL', [email.toLowerCase()]);
  const user = userRes.rows[0];
  if (!user) throw new Error('User not found');

  const passkeysRes = await pool.query('SELECT credential_id FROM user_passkeys WHERE user_id = $1', [user.id]);
  const challenge = crypto.randomBytes(32).toString('base64url');

  return {
    challenge,
    timeout: 60000,
    rpId: process.env.WEBAUTHN_RP_ID || 'neuravolt.cloud',
    allowCredentials: passkeysRes.rows.map((pk) => ({
      id: pk.credential_id,
      type: 'public-key',
    })),
    userVerification: 'preferred',
  };
}
