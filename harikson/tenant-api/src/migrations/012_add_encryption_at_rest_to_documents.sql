-- Migration 012: Add encryption columns (content_iv, content_tag, key_id) to knowledge_documents
ALTER TABLE knowledge_documents ADD COLUMN IF NOT EXISTS content_iv VARCHAR(64);
ALTER TABLE knowledge_documents ADD COLUMN IF NOT EXISTS content_tag VARCHAR(64);
ALTER TABLE knowledge_documents ADD COLUMN IF NOT EXISTS key_id VARCHAR(32) DEFAULT 'v1';

CREATE INDEX IF NOT EXISTS idx_knowledge_documents_key_id ON knowledge_documents(key_id);
