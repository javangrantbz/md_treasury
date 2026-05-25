-- =============================================================
-- Migration: 001_government_grade_security
-- Description: Adds session tracking and enhances audit logs
-- =============================================================

-- Add session tracking to users
ALTER TABLE users ADD COLUMN IF NOT EXISTS current_session_id VARCHAR(255) NULL AFTER last_login_at;

-- Enhance audit_logs table
ALTER TABLE audit_logs 
    ADD COLUMN IF NOT EXISTS event_type VARCHAR(50) NOT NULL DEFAULT 'DATA_CHANGE' AFTER user_id,
    ADD COLUMN IF NOT EXISTS event_action VARCHAR(50) NOT NULL AFTER event_type,
    ADD COLUMN IF NOT EXISTS old_values LONGTEXT NULL AFTER entity_id,
    ADD COLUMN IF NOT EXISTS new_values LONGTEXT NULL AFTER old_values,
    ADD COLUMN IF NOT EXISTS user_agent VARCHAR(255) NULL AFTER ip_address;

-- If 'action' column exists, we migrate it to event_action and drop it (optional/safe)
-- This is a simple migration so we assume fresh or incremental
