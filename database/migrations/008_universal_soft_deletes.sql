-- =============================================================
-- Migration: 008_universal_soft_deletes
-- Description: Adds deleted_at columns to core entities
-- =============================================================

ALTER TABLE bank_accounts             ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE branches                  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE cost_centers              ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE departments               ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE expenditure_types         ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE registers                 ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE roles                     ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE sub_treasuries            ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE transactions              ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE transaction_items         ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE pay_ins                   ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE register_users            ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE cost_center_bank_accounts ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE department_bank_accounts  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE department_cost_centers   ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE register_bank_accounts    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE user_notes                ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE pos_cart_items            ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

-- Indices for performance
CREATE INDEX IF NOT EXISTS idx_bank_accounts_deleted_at     ON bank_accounts(deleted_at);
CREATE INDEX IF NOT EXISTS idx_cost_centers_deleted_at       ON cost_centers(deleted_at);
CREATE INDEX IF NOT EXISTS idx_departments_deleted_at        ON departments(deleted_at);
CREATE INDEX IF NOT EXISTS idx_transactions_deleted_at       ON transactions(deleted_at);
CREATE INDEX IF NOT EXISTS idx_transaction_items_deleted_at ON transaction_items(deleted_at);
