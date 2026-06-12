-- =============================================================
-- Migration: 007_parsed_user_agents
-- Description: Adds columns for parsed user agent components
-- =============================================================

ALTER TABLE audit_logs 
    ADD COLUMN IF NOT EXISTS ua_browser VARCHAR(50) NULL AFTER user_agent,
    ADD COLUMN IF NOT EXISTS ua_os VARCHAR(50) NULL AFTER ua_browser,
    ADD COLUMN IF NOT EXISTS ua_device VARCHAR(50) NULL AFTER ua_os;

-- Index for easier filtering/reporting
CREATE INDEX IF NOT EXISTS idx_ua_browser ON audit_logs(ua_browser);
CREATE INDEX IF NOT EXISTS idx_ua_os ON audit_logs(ua_os);
