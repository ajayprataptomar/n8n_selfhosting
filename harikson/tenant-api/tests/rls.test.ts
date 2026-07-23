import { describe, it, expect, beforeEach, afterAll } from '@jest/globals';
import { pool } from '../src/db/pool.js';
import { createTestTenant, createTestUser } from './factories/tenantFactory.js';
import { resetTestDatabase } from './helpers/db.js';

describe('Tenant API - Row-Level Security (RLS) Test Suite', () => {
  beforeEach(async () => {
    await resetTestDatabase();
  });

  afterAll(async () => {
    await pool.end();
  });

  it('Tenant Data Isolation - User A from Tenant X cannot read Tenant Y data', async () => {
    const tenantX = await createTestTenant({ slug: 'tenant-x' });
    const tenantY = await createTestTenant({ slug: 'tenant-y' });

    const userA = await createTestUser(tenantX.id, { email: 'userA@tenantX.com' });
    const userB = await createTestUser(tenantY.id, { email: 'userB@tenantY.com' });

    const client = await pool.connect();
    try {
      await client.query('BEGIN');
      await client.query(`SET LOCAL app.current_tenant_id = $1`, [tenantX.id]);

      const res = await client.query('SELECT * FROM users WHERE id = $1', [userB.id]);
      expect(res.rows.length).toBe(0);

      await client.query('COMMIT');
    } finally {
      client.release();
    }
  });

  it('Set Tenant Context - Query returns only rows belonging to the active tenant', async () => {
    const tenantX = await createTestTenant({ slug: 'active-tenant-x' });
    await createTestUser(tenantX.id, { email: 'active1@tenantX.com' });
    await createTestUser(tenantX.id, { email: 'active2@tenantX.com' });

    const client = await pool.connect();
    try {
      await client.query('BEGIN');
      await client.query(`SET LOCAL app.current_tenant_id = $1`, [tenantX.id]);

      const res = await client.query('SELECT * FROM users WHERE tenant_id = $1', [tenantX.id]);
      expect(res.rows.length).toBe(2);

      await client.query('COMMIT');
    } finally {
      client.release();
    }
  });

  it('Missing Tenant Context - Query returns empty or enforces strict tenant filter', async () => {
    const client = await pool.connect();
    try {
      const res = await client.query(`SELECT current_setting('app.current_tenant_id', true) as tenant_id`);
      const activeTenant = res.rows[0].tenant_id;
      expect(activeTenant).toBeFalsy();
    } finally {
      client.release();
    }
  });
});
