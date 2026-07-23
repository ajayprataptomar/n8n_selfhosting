-- Migration 019: Add Email Verification Columns and Grandfather Existing Users

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(255),
ADD COLUMN IF NOT EXISTS email_verification_sent_at TIMESTAMPTZ;

-- Index for fast token lookup
CREATE INDEX IF NOT EXISTS idx_users_email_verification_token ON users(email_verification_token);

-- Grandfather existing users so existing accounts remain fully operational
UPDATE users 
SET email_verified = TRUE 
WHERE email_verified IS FALSE OR email_verified IS NULL;
