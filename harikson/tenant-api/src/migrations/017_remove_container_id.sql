-- Migration 005: Remove container_id field from instances table
DO $$
BEGIN
    IF EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'instances') THEN
        IF EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'instances' AND column_name = 'container_id') THEN
            ALTER TABLE instances DROP COLUMN container_id;
        END IF;
    END IF;
END $$;
