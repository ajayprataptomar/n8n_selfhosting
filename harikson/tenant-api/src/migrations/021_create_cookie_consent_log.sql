-- Migration 021: Create Cookie Consent Audit Log Table for GDPR/ePrivacy Compliance

CREATE TABLE IF NOT EXISTS cookie_consent_log (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    ip_address VARCHAR(255),
    user_agent TEXT,
    consent_json JSONB NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_cookie_consent_log_user_id ON cookie_consent_log(user_id);
CREATE INDEX IF NOT EXISTS idx_cookie_consent_log_created_at ON cookie_consent_log(created_at);
