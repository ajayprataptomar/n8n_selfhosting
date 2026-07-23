-- Migration 008: Allow 'canceling' and 'cancelling' statuses in subscriptions status check constraint

DO $$
BEGIN
    -- Drop existing check constraint if it exists (usually named subscriptions_status_check)
    ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_status_check;
    
    -- Add the new updated check constraint
    ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check 
        CHECK (status IN ('active', 'past_due', 'cancelled', 'unpaid', 'paused', 'canceling', 'cancelling'));
END $$;
