-- Migration 024: Add trial days to plans, custom domain to tenants, and user_passkeys for WebAuthn
ALTER TABLE plans ADD COLUMN IF NOT EXISTS trial_days INTEGER DEFAULT 14;

ALTER TABLE tenants 
ADD COLUMN IF NOT EXISTS custom_domain VARCHAR(255) UNIQUE,
ADD COLUMN IF NOT EXISTS custom_domain_verified BOOLEAN DEFAULT false,
ADD COLUMN IF NOT EXISTS custom_domain_ssl_status VARCHAR(50) DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS user_passkeys (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    credential_id TEXT UNIQUE NOT NULL,
    public_key TEXT NOT NULL,
    counter BIGINT DEFAULT 0,
    transports JSONB,
    device_name VARCHAR(255) DEFAULT 'Security Key',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    last_used_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_user_passkeys_user_id ON user_passkeys(user_id);
