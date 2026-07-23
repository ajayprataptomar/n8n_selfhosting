-- Migration 020: Add Progressive Account Lockout Columns

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS failed_login_attempts INTEGER DEFAULT 0,
ADD COLUMN IF NOT EXISTS locked_until TIMESTAMPTZ,
ADD COLUMN IF NOT EXISTS lockout_count INTEGER DEFAULT 0,
ADD COLUMN IF NOT EXISTS unlock_token VARCHAR(255);

-- Index for fast token and locked status queries
CREATE INDEX IF NOT EXISTS idx_users_locked_until ON users(locked_until);
CREATE INDEX IF NOT EXISTS idx_users_unlock_token ON users(unlock_token);
