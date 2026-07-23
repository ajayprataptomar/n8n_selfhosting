-- Migration 025: Hybrid Search (Full-Text TSVector + Vector Embeddings)
ALTER TABLE knowledge_documents 
ADD COLUMN IF NOT EXISTS tsv TSVECTOR;

-- Update existing records to populate tsvector
UPDATE knowledge_documents 
SET tsv = to_tsvector('english', COALESCE(title, '') || ' ' || COALESCE(content, ''))
WHERE tsv IS NULL;

-- Create GIN index for fast text search
CREATE INDEX IF NOT EXISTS idx_knowledge_docs_tsv ON knowledge_documents USING GIN(tsv);

-- Create automatic trigger to update tsvector on insert/update
CREATE OR REPLACE FUNCTION knowledge_docs_tsv_trigger() RETURNS trigger AS $$
begin
  new.tsv := to_tsvector('english', COALESCE(new.title, '') || ' ' || COALESCE(new.content, ''));
  return new;
end
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_knowledge_docs_tsv ON knowledge_documents;
CREATE TRIGGER trg_knowledge_docs_tsv
BEFORE INSERT OR UPDATE ON knowledge_documents
FOR EACH ROW EXECUTE FUNCTION knowledge_docs_tsv_trigger();
