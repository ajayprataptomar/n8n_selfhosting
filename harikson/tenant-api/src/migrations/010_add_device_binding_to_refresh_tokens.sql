-- Migration 010: Add device binding & fingerprinting columns to refresh_tokens
ALTER TABLE refresh_tokens ADD COLUMN IF NOT EXISTS device_hash VARCHAR(64);
ALTER TABLE refresh_tokens ADD COLUMN IF NOT EXISTS device_name VARCHAR(100);
ALTER TABLE refresh_tokens ADD COLUMN IF NOT EXISTS last_ip VARCHAR(45);
ALTER TABLE refresh_tokens ADD COLUMN IF NOT EXISTS last_used_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE refresh_tokens ADD COLUMN IF NOT EXISTS country_code VARCHAR(2) DEFAULT 'IN';
ALTER TABLE refresh_tokens ADD COLUMN IF NOT EXISTS failed_device_attempts INT DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_refresh_tokens_device ON refresh_tokens(device_hash);
CREATE INDEX IF NOT EXISTS idx_refresh_tokens_user_device ON refresh_tokens(user_id, device_hash);
