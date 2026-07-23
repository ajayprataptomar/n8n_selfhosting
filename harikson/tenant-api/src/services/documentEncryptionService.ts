import crypto from 'crypto';
import { Redis } from 'ioredis';
import { pool } from '../db/pool.js';
import { Logger } from '../observability/logger.js';

const redis = new Redis({
  host: process.env.REDIS_HOST || '127.0.0.1',
  port: parseInt(process.env.REDIS_PORT || '6379'),
  password: process.env.REDIS_PASSWORD || undefined,
  lazyConnect: true,
});

redis.connect().catch(() => {});

/**
 * Validates that TENANT_MASTER_KEY is present and meets security standards (at least 32 characters).
 * Fails fast with a fatal exception if unconfigured.
 */
export function validateMasterKeyConfig(): string {
  const masterKey = process.env.TENANT_MASTER_KEY;
  if (!masterKey || masterKey.length < 32) {
    throw new Error('FATAL: TENANT_MASTER_KEY must be set and at least 32 characters');
  }
  return masterKey;
}

// Execute module load-time configuration validation
validateMasterKeyConfig();

export interface EncryptedPayload {
  encryptedContent: string;
  iv: string;
  authTag: string;
  keyId: string;
}

/**
 * Derive a 256-bit key from the tenant master key and document ID using PBKDF2 (100,000 iterations).
 */
function deriveDocumentKey(documentId: string, keyId: string = 'v1'): Buffer {
  const masterKey = validateMasterKeyConfig();
  const salt = `${documentId}:${keyId}`;
  return crypto.pbkdf2Sync(masterKey, salt, 100000, 32, 'sha256');
}

/**
 * Encrypt document content at rest using AES-256-GCM and PBKDF2 key derivation.
 */
export function encryptDocumentContent(
  documentId: string,
  plainText: string,
  keyId: string = 'v1'
): EncryptedPayload {
  const derivedKey = deriveDocumentKey(documentId, keyId);
  const iv = crypto.randomBytes(12); // 96-bit IV for GCM
  const cipher = crypto.createCipheriv('aes-256-gcm', derivedKey, iv);

  let encrypted = cipher.update(plainText, 'utf8', 'hex');
  encrypted += cipher.final('hex');
  const authTag = cipher.getAuthTag().toString('hex');

  return {
    encryptedContent: encrypted,
    iv: iv.toString('hex'),
    authTag,
    keyId,
  };
}

/**
 * Decrypt document content from AES-256-GCM.
 */
export function decryptDocumentContent(
  documentId: string,
  encryptedContent: string,
  ivHex: string,
  authTagHex: string,
  keyId: string = 'v1'
): string {
  const derivedKey = deriveDocumentKey(documentId, keyId);
  const decipher = crypto.createDecipheriv(
    'aes-256-gcm',
    derivedKey,
    Buffer.from(ivHex, 'hex')
  );
  decipher.setAuthTag(Buffer.from(authTagHex, 'hex'));

  let decrypted = decipher.update(encryptedContent, 'hex', 'utf8');
  decrypted += decipher.final('utf8');
  return decrypted;
}

/**
 * Decrypt document content with Redis caching (TTL: 1 hour) to avoid CPU-intensive re-decryption.
 */
export async function decryptDocumentContentWithCache(
  documentId: string,
  encryptedContent: string,
  ivHex: string,
  authTagHex: string,
  keyId: string = 'v1'
): Promise<string> {
  const cacheKey = `cache:doc:${documentId}`;

  try {
    const cached = await redis.get(cacheKey);
    if (cached) {
      return cached;
    }
  } catch (err) {
    Logger.warn('Redis cache get error in document decryption:', (err as any).message);
  }

  // Fallback to plain text if not encrypted yet (legacy records)
  if (!ivHex || !authTagHex) {
    return encryptedContent;
  }

  const decrypted = decryptDocumentContent(
    documentId,
    encryptedContent,
    ivHex,
    authTagHex,
    keyId
  );

  try {
    await redis.set(cacheKey, decrypted, 'EX', 3600); // 1 hour TTL
  } catch (err) {
    Logger.warn('Redis cache set error in document decryption:', (err as any).message);
  }

  return decrypted;
}

/**
 * Background Key Rotation: Re-encrypt documents in batches of 100 with a new master key version.
 */
export async function rotateDocumentKeys(
  newKeyId: string = 'v2',
  batchSize: number = 100
): Promise<{ processed: number; remaining: number }> {
  const client = await pool.connect();
  try {
    const docsRes = await client.query(
      `SELECT id, content, content_iv, content_tag, key_id 
       FROM knowledge_documents 
       WHERE key_id IS NULL OR key_id != $1 
       LIMIT $2`,
      [newKeyId, batchSize]
    );

    let count = 0;
    for (const doc of docsRes.rows) {
      let plainText = doc.content;

      // Decrypt if previously encrypted
      if (doc.content_iv && doc.content_tag) {
        plainText = decryptDocumentContent(
          doc.id,
          doc.content,
          doc.content_iv,
          doc.content_tag,
          doc.key_id || 'v1'
        );
      }

      // Re-encrypt with new key version
      const { encryptedContent, iv, authTag } = encryptDocumentContent(
        doc.id,
        plainText,
        newKeyId
      );

      await client.query(
        `UPDATE knowledge_documents 
         SET content = $1, content_iv = $2, content_tag = $3, key_id = $4 
         WHERE id = $5`,
        [encryptedContent, iv, authTag, newKeyId, doc.id]
      );

      // Invalidate Redis cache
      await redis.del(`cache:doc:${doc.id}`).catch(() => {});
      count++;
    }

    const remainingRes = await client.query(
      `SELECT COUNT(*)::int as count FROM knowledge_documents WHERE key_id IS NULL OR key_id != $1`,
      [newKeyId]
    );

    const remaining = remainingRes.rows[0]?.count || 0;
    Logger.info(
      `🔄 [KEY ROTATION] Processed ${count} documents with key version '${newKeyId}'. Remaining un-rotated: ${remaining}`
    );

    return { processed: count, remaining };
  } finally {
    client.release();
  }
}
