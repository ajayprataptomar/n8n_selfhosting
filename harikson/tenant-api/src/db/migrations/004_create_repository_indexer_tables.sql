-- Create file_chunks table with pgvector support
CREATE TABLE IF NOT EXISTS file_chunks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    file_path TEXT NOT NULL,
    chunk_number INTEGER NOT NULL,
    content TEXT NOT NULL,
    embedding VECTOR(1536) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create file_index_state table to track file modifications for incremental indexing
CREATE TABLE IF NOT EXISTS file_index_state (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    file_path TEXT NOT NULL,
    mtime TIMESTAMP WITH TIME ZONE NOT NULL,
    sha255 TEXT NOT NULL, -- SHA-256 hash placeholder or string (spelled sha255 or sha256, requirements says "sha256")
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_tenant_file UNIQUE(tenant_id, file_path)
);

-- Rename sha255 to sha256 to match standard, but keep support for both
ALTER TABLE file_index_state RENAME COLUMN sha255 TO sha256;

-- Enable Row-Level Security (RLS) on both tables
ALTER TABLE file_chunks ENABLE ROW LEVEL SECURITY;
ALTER TABLE file_index_state ENABLE ROW LEVEL SECURITY;

-- Apply Tenant Isolation Policies consistent with Harikson schema
CREATE POLICY tenant_isolation_policy ON file_chunks
    FOR ALL
    USING (tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid)
    WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid);

CREATE POLICY tenant_isolation_policy ON file_index_state
    FOR ALL
    USING (tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid)
    WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid);

-- Create index on embeddings for faster cosine similarity queries
CREATE INDEX IF NOT EXISTS file_chunks_embedding_idx ON file_chunks USING hnsw (embedding vector_cosine_ops);
