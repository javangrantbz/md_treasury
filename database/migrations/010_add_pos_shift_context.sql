-- =============================================================
-- Migration: 010_add_pos_shift_context
-- Description: Store the selected cashier location cost center
--              and settlement bank account on each POS shift
-- =============================================================

ALTER TABLE pos_shifts
    ADD COLUMN IF NOT EXISTS cost_center_id INT(10) UNSIGNED NULL AFTER register_id,
    ADD COLUMN IF NOT EXISTS bank_account_id INT(10) UNSIGNED NULL AFTER cost_center_id;

CREATE INDEX IF NOT EXISTS idx_pos_shifts_cost_center_id ON pos_shifts(cost_center_id);
CREATE INDEX IF NOT EXISTS idx_pos_shifts_bank_account_id ON pos_shifts(bank_account_id);
