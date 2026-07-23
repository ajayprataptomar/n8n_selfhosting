-- Migration 007: Create missing conversation_summaries and ai_activity tables, indexes, and RLS policies

-- 1. Create conversation_summaries table
CREATE TABLE IF NOT EXISTS conversation_summaries (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    conversation_id UUID NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    summary TEXT NOT NULL,
    message_count INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Enable RLS for conversation_summaries
ALTER TABLE conversation_summaries ENABLE ROW LEVEL SECURITY;
ALTER TABLE conversation_summaries FORCE ROW LEVEL SECURITY;

-- Apply Tenant Isolation Policy for conversation_summaries
DROP POLICY IF EXISTS tenant_isolation_policy ON conversation_summaries;
CREATE POLICY tenant_isolation_policy ON conversation_summaries
    FOR ALL
    USING (tenant_id = current_setting('app.current_tenant', true)::uuid)
    WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid);


-- 2. Create ai_activity table
CREATE TABLE IF NOT EXISTS ai_activity (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    model VARCHAR(100) NOT NULL,
    tokens_in INTEGER DEFAULT 0,
    tokens_out INTEGER DEFAULT 0,
    latency_ms INTEGER,
    gpu_percent DECIMAL(5,2),
    request_path VARCHAR(255),
    status_code INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Add index on tenant_id, created_at for admin queries
CREATE INDEX IF NOT EXISTS idx_ai_activity_tenant_created_at ON ai_activity (tenant_id, created_at DESC);

-- Enable RLS for ai_activity
ALTER TABLE ai_activity ENABLE ROW LEVEL SECURITY;
ALTER TABLE ai_activity FORCE ROW LEVEL SECURITY;

-- Apply Tenant Isolation Policy for ai_activity
DROP POLICY IF EXISTS tenant_isolation_policy ON ai_activity;
CREATE POLICY tenant_isolation_policy ON ai_activity
    FOR ALL
    USING (tenant_id = current_setting('app.current_tenant', true)::uuid)
    WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid);
