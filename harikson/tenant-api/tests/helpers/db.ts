import { pool } from '../../src/db/pool.js';
import { setTenantContext, clearTenantContext } from '../../src/utils/context.js';

export async function resetTestDatabase(): Promise<void> {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');
    await client.query('TRUNCATE TABLE messages, conversations, invoices, subscriptions, users, tenants CASCADE');
    await client.query('COMMIT');
  } catch (err) {
    await client.query('ROLLBACK');
    throw err;
  } finally {
    client.release();
  }
}

export { setTenantContext, clearTenantContext };
