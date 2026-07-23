import { pool } from '../../src/db/pool.js';
import crypto from 'crypto';

export async function createTestTenant(overrides: Record<string, any> = {}) {
  const slug = overrides.slug || `tenant-${crypto.randomBytes(4).toString('hex')}`;
  const name = overrides.name || `Test Tenant ${slug}`;
  const plan = overrides.plan || 'STARTER';

  const res = await pool.query(
    `INSERT INTO tenants (name, slug, plan, status)
     VALUES ($1, $2, $3, 'active')
     RETURNING *`,
    [name, slug, plan]
  );
  return res.rows[0];
}

export async function createTestUser(tenantId: string, overrides: Record<string, any> = {}) {
  const email = overrides.email || `user-${crypto.randomBytes(4).toString('hex')}@neuravolt.cloud`;
  const role = overrides.role || 'user';
  const passwordHash = '$2b$10$abcdefghijklmnopqrstuu'; // Dummy hash for testing

  const res = await pool.query(
    `INSERT INTO users (tenant_id, email, password_hash, role)
     VALUES ($1, $2, $3, $4)
     RETURNING *`,
    [tenantId, email, passwordHash, role]
  );
  return res.rows[0];
}
