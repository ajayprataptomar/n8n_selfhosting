-- Create task_plans table to save multi-agent workflows
CREATE TABLE IF NOT EXISTS task_plans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    conversation_id UUID NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    plan JSONB NOT NULL, -- Array of steps: [{ step: 1, agent: 'Harikson Planner', task: '...', status: '...', result: '...' }]
    status TEXT NOT NULL, -- 'pending' | 'running' | 'completed' | 'failed'
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Enable Row-Level Security
ALTER TABLE task_plans ENABLE ROW LEVEL SECURITY;

-- Apply Tenant Isolation Policy
CREATE POLICY tenant_isolation_policy ON task_plans
    FOR ALL
    USING (tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid)
    WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid);
