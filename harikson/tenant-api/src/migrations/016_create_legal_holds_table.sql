-- Migration 016: Add legal_holds table and immutable audit trail
CREATE TABLE IF NOT EXISTS legal_holds (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  case_name VARCHAR(255) NOT NULL,
  description TEXT,
  created_by UUID REFERENCES users(id) ON DELETE SET NULL,
  created_by_admin_email VARCHAR(255),
  status VARCHAR(20) DEFAULT 'active', -- 'active' | 'lifted'
  expires_at TIMESTAMPTZ NULL,
  lifted_at TIMESTAMPTZ NULL,
  lifted_by_admin_email VARCHAR(255) NULL,
  lift_reason TEXT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_legal_holds_tenant_status ON legal_holds(tenant_id, status);

-- Immutable audit log for legal holds (append-only)
CREATE TABLE IF NOT EXISTS legal_hold_audit_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  legal_hold_id UUID REFERENCES legal_holds(id) ON DELETE CASCADE,
  tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  action VARCHAR(50) NOT NULL, -- 'LEGAL_HOLD_CREATED', 'LEGAL_HOLD_LIFTED'
  admin_id VARCHAR(255),
  admin_email VARCHAR(255),
  case_name VARCHAR(255),
  reason TEXT,
  metadata JSONB DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_legal_hold_audit_logs_tenant ON legal_hold_audit_logs(tenant_id);
