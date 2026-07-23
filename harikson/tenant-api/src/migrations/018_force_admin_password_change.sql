-- Migration 018: Add force_password_change column and require password change for all administrative accounts
ALTER TABLE users ADD COLUMN IF NOT EXISTS force_password_change BOOLEAN DEFAULT FALSE;

-- Force password change on next login for all existing admin, superadmin, and founder accounts
UPDATE users 
SET force_password_change = TRUE 
WHERE role IN ('admin', 'superadmin', 'founder');
