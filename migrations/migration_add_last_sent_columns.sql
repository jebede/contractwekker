-- Add timestamp columns to track when notifications were actually sent
ALTER TABLE alerts 
ADD COLUMN last_email_sent DATE NULL AFTER updated_at,
ADD COLUMN last_email_early_sent DATE NULL AFTER last_email_sent,
ADD COLUMN last_push_sent DATE NULL AFTER last_email_early_sent,
ADD COLUMN last_push_early_sent DATE NULL AFTER last_push_sent;

-- Add indexes for the new columns
CREATE INDEX idx_last_email_sent ON alerts(last_email_sent);
CREATE INDEX idx_last_email_early_sent ON alerts(last_email_early_sent);
CREATE INDEX idx_last_push_sent ON alerts(last_push_sent);
CREATE INDEX idx_last_push_early_sent ON alerts(last_push_early_sent);

-- Remove redundant boolean columns (replaced by timestamp tracking)
ALTER TABLE alerts 
DROP COLUMN early_reminder_sent,
DROP COLUMN is_sent;