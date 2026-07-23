-- Migration 006: Add success_count and error_count, drop success_rate and error_rate columns on agents table if exists.

DO $$
BEGIN
    IF EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'agents') THEN
        IF NOT EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'agents' AND column_name = 'success_count') THEN
            ALTER TABLE agents ADD COLUMN success_count INT DEFAULT 0;
        END IF;
        
        IF NOT EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'agents' AND column_name = 'error_count') THEN
            ALTER TABLE agents ADD COLUMN error_count INT DEFAULT 0;
        END IF;

        IF EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'agents' AND column_name = 'success_rate') THEN
            ALTER TABLE agents DROP COLUMN success_rate;
        END IF;

        IF EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'agents' AND column_name = 'error_rate') THEN
            ALTER TABLE agents DROP COLUMN error_rate;
        END IF;
    END IF;
END $$;
