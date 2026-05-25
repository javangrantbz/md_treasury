-- =============================================================
-- Migration: 002_data_integrity_soft_deletes
-- Description: Adds deleted_at columns for soft delete support
-- =============================================================

ALTER TABLE users           ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE suppliers       ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE customers       ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE expense_entries ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
