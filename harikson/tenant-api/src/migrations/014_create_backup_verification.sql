-- Migration 014: Add backup_verification table & retention tracking
CREATE TABLE IF NOT EXISTS backup_verification (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  backup_filename VARCHAR(255) NOT NULL,
  checksum_sha256 VARCHAR(64) NOT NULL,
  size_bytes BIGINT NOT NULL,
  backup_type VARCHAR(20) DEFAULT 'daily',
  is_valid_pg_dump BOOLEAN DEFAULT false,
  restore_success BOOLEAN DEFAULT false,
  restore_duration_ms INT DEFAULT 0,
  tables_count INT DEFAULT 0,
  rls_policies_verified INT DEFAULT 0,
  error_message TEXT NULL,
  verified_at TIMESTAMPTZ DEFAULT NOW(),
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_backup_verification_created ON backup_verification(created_at DESC);
