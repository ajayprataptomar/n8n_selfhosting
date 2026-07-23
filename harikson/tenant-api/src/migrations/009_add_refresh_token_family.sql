-- Migration 009: Add refresh_token_family column and performance indexes to refresh_tokens table

DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name='refresh_tokens' AND column_name='refresh_token_family'
    ) THEN
        ALTER TABLE refresh_tokens ADD COLUMN refresh_token_family UUID;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_refresh_tokens_family ON refresh_tokens(refresh_token_family);
CREATE INDEX IF NOT EXISTS idx_refresh_tokens_token ON refresh_tokens(token);
CREATE INDEX IF NOT EXISTS idx_refresh_tokens_created_at ON refresh_tokens(created_at);
