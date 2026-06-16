-- =============================================================
-- Migration: 016_add_pos_soft_delete_columns
-- Description: Adds deleted_at soft-delete columns to pos_shifts
--              and pos_transactions. Migration 008 added deleted_at
--              to most core tables but missed these two, so the
--              pay-in shifts query (api/pay-in/shifts.php) filtering
--              `s.deleted_at IS NULL` / `t.deleted_at IS NULL` fails
--              on environments where the columns were never added
--              by hand (e.g. live: SQLSTATE[42S22] Unknown column
--              's.deleted_at'). This makes the columns reproducible.
-- =============================================================

ALTER TABLE pos_shifts        ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE pos_transactions  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

CREATE INDEX IF NOT EXISTS idx_pos_shifts_deleted_at       ON pos_shifts(deleted_at);
CREATE INDEX IF NOT EXISTS idx_pos_transactions_deleted_at ON pos_transactions(deleted_at);
