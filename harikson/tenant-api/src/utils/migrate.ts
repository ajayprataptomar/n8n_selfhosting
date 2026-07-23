import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import logger from './logger.js';
import pg from 'pg';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export async function runMigrations(pool: pg.Pool) {
  logger.info('⚙️ Running database migrations...');
  try {
    // 1. Ensure migrations_meta exists
    await pool.query(`
      CREATE TABLE IF NOT EXISTS migrations_meta (
        id SERIAL PRIMARY KEY,
        filename VARCHAR(255) UNIQUE NOT NULL,
        checksum VARCHAR(64) NOT NULL,
        executed_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
      )
    `);

    // 2. Read migration files from src/migrations
    const migrationsDir = path.join(__dirname, '..', 'migrations');
    if (!fs.existsSync(migrationsDir)) {
      logger.info('⚠️ Migrations directory not found, skipping migrations.');
      return;
    }

    const files = fs.readdirSync(migrationsDir)
      .filter(f => f.endsWith('.sql'))
      .sort(); // Sort sequentially (e.g. 001, 002, 003)

    // 3. Query already applied migrations
    const { rows } = await pool.query('SELECT filename FROM migrations_meta');
    const applied = new Set(rows.map(r => r.filename));

    // 4. Apply new migrations in order
    for (const file of files) {
      if (applied.has(file)) {
        continue;
      }

      logger.info(`🚀 Applying migration: ${file}...`);
      const filePath = path.join(migrationsDir, file);
      const sql = fs.readFileSync(filePath, 'utf8');

      // Run migration inside a transaction
      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        await client.query(sql);
        await client.query('INSERT INTO migrations_meta (filename, checksum) VALUES ($1, $2)', [file, 'hash']);
        await client.query('COMMIT');
        logger.info(`   ✓ Migration applied successfully: ${file}`);
      } catch (err: any) {
        await client.query('ROLLBACK');
        logger.error(`❌ Migration failed: ${file}. Transaction rolled back.`, err);
        throw err;
      } finally {
        client.release();
      }
    }
    logger.info('🎉 All database migrations are up to date.');
  } catch (err: any) {
    logger.error('❌ Database migration runner failed:', err);
    throw err;
  }
}
