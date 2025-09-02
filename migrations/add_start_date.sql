-- Add start_date column to alerts table
ALTER TABLE alerts 
ADD COLUMN start_date DATE NULL AFTER alert_period;

-- Add index for start_date for better query performance
ALTER TABLE alerts 
ADD INDEX idx_start_date (start_date);