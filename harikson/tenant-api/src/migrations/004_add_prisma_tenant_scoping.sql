-- Migration 004: Add Prisma Tenant Scoping safely for existing tables if they exist.

DO $$
BEGIN
    -- 1. instances table
    IF EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'instances') THEN
        IF NOT EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'instances' AND column_name = 'tenant_id') THEN
            ALTER TABLE instances ADD COLUMN tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE;
        END IF;
        CREATE INDEX IF NOT EXISTS instances_tenant_id_idx ON instances (tenant_id);
    END IF;

    -- 2. documents table
    IF EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'documents') THEN
        IF NOT EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'documents' AND column_name = 'tenant_id') THEN
            ALTER TABLE documents ADD COLUMN tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE;
        END IF;
        CREATE INDEX IF NOT EXISTS documents_tenant_id_idx ON documents (tenant_id);
    END IF;

    -- 3. fine_tune_jobs table
    IF EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'fine_tune_jobs') THEN
        IF NOT EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'fine_tune_jobs' AND column_name = 'tenant_id') THEN
            ALTER TABLE fine_tune_jobs ADD COLUMN tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE;
        END IF;
        CREATE INDEX IF NOT EXISTS fine_tune_jobs_tenant_id_idx ON fine_tune_jobs (tenant_id);
    END IF;

    -- 4. captured_leads table
    IF EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'captured_leads') THEN
        IF NOT EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'captured_leads' AND column_name = 'tenant_id') THEN
            ALTER TABLE captured_leads ADD COLUMN tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE;
        END IF;
        CREATE INDEX IF NOT EXISTS captured_leads_tenant_id_idx ON captured_leads (tenant_id);
    END IF;

    -- 5. validation_logs table
    IF EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'validation_logs') THEN
        IF NOT EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'validation_logs' AND column_name = 'tenant_id') THEN
            ALTER TABLE validation_logs ADD COLUMN tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE;
        END IF;
        CREATE INDEX IF NOT EXISTS validation_logs_tenant_id_idx ON validation_logs (tenant_id);
    END IF;
END $$;
