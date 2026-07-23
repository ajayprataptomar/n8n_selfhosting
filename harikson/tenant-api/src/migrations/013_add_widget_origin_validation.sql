-- Migration 013: Add widget_allowed_origins & widget_secret to tenants table
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS widget_allowed_origins TEXT[] DEFAULT '{}';
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS widget_secret VARCHAR(64);

-- Create widget analytics table
CREATE TABLE IF NOT EXISTS widget_analytics (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  origin VARCHAR(255) NOT NULL,
  messages_sent INT DEFAULT 0,
  sessions_count INT DEFAULT 0,
  unique_users_count INT DEFAULT 0,
  last_activity_at TIMESTAMPTZ DEFAULT NOW(),
  created_at TIMESTAMPTZ DEFAULT NOW(),
  CONSTRAINT uq_widget_analytics_tenant_origin UNIQUE(tenant_id, origin)
);

CREATE INDEX IF NOT EXISTS idx_widget_analytics_tenant ON widget_analytics(tenant_id);
