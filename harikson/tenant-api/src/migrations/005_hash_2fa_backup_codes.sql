-- Migration 005: Convert two_factor_backup_codes to JSONB array with bcrypt hashes and used_at timestamps
-- Format: [{ "hash": "$2b$10$...", "used_at": null }]

ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_backup_codes_json JSONB DEFAULT '[]'::jsonb;
ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_2fa_attempts INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until TIMESTAMPTZ DEFAULT NULL;

-- Reset invalid legacy unhashed backup codes
UPDATE users SET two_factor_backup_codes_json = '[]'::jsonb WHERE two_factor_backup_codes IS NOT NULL;

-- Swap columns
ALTER TABLE users DROP COLUMN IF EXISTS two_factor_backup_codes;
ALTER TABLE users RENAME COLUMN two_factor_backup_codes_json TO two_factor_backup_codes;
