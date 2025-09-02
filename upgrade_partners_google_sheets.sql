-- SQL script to add required columns to partners table
-- and remove google_spreadsheet_id from global settings

-- Add new columns to partners table for individual Google Sheets configuration
ALTER TABLE partners 
ADD COLUMN google_spreadsheet_id VARCHAR(255) NULL COMMENT 'Partner-specific Google Spreadsheet ID' AFTER google_sheet_name,
ADD COLUMN google_service_account_json TEXT NULL COMMENT 'Partner-specific Google Service Account JSON credentials' AFTER google_spreadsheet_id;

-- Remove google_spreadsheet_id from global settings since it's now partner-specific
DELETE FROM settings WHERE setting_key = 'google_spreadsheet_id';

-- Update existing partners to use the previous global spreadsheet ID if it exists
-- (You may need to manually set this for existing partners)
-- UPDATE partners SET google_spreadsheet_id = 'YOUR_PREVIOUS_GLOBAL_SPREADSHEET_ID' WHERE google_sheet_name IS NOT NULL AND google_sheet_name != '';

-- Optional: Add index for better performance
CREATE INDEX idx_partners_google_spreadsheet_id ON partners(google_spreadsheet_id);