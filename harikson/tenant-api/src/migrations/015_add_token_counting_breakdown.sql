-- Migration 015: Add prompt_tokens, completion_tokens, and context_tokens to messages table
ALTER TABLE messages ADD COLUMN IF NOT EXISTS prompt_tokens INT DEFAULT 0;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS completion_tokens INT DEFAULT 0;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS context_tokens INT DEFAULT 0;

-- Backfill existing records
UPDATE messages 
SET prompt_tokens = COALESCE(tokens_used, 0), 
    completion_tokens = COALESCE(tokens_used, 0)
WHERE (prompt_tokens = 0 OR prompt_tokens IS NULL) AND tokens_used IS NOT NULL;
