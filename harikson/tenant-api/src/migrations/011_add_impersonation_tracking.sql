-- Migration 011: Add impersonation audit columns to activity_logs & ai_activity
ALTER TABLE activity_logs ADD COLUMN IF NOT EXISTS impersonated_by UUID NULL;
ALTER TABLE activity_logs ADD COLUMN IF NOT EXISTS is_impersonation BOOLEAN DEFAULT false;

ALTER TABLE ai_activity ADD COLUMN IF NOT EXISTS impersonated_by UUID NULL;
ALTER TABLE ai_activity ADD COLUMN IF NOT EXISTS is_impersonation BOOLEAN DEFAULT false;

CREATE INDEX IF NOT EXISTS idx_activity_logs_impersonation ON activity_logs(is_impersonation) WHERE is_impersonation = true;
CREATE INDEX IF NOT EXISTS idx_ai_activity_impersonation ON ai_activity(is_impersonation) WHERE is_impersonation = true;
