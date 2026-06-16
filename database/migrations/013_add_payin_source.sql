-- =============================================================
-- Migration: 013_add_payin_source
-- Description: Distinguishes how a pay-in originated so cashiering
--              reporting can separate cashier daily sales pay-ins
--              (generated from POS cash sales) from department
--              pay-ins (a department bringing cash / deposit slips
--              into the treasury, entered via pay-in-new.php).
--                'department' = manually entered department pay-in
--                'pos_sales'  = auto-generated from a cashier's
--                               daily POS cash sales
-- =============================================================

ALTER TABLE pay_ins
    ADD COLUMN IF NOT EXISTS source ENUM('department','pos_sales') NOT NULL DEFAULT 'department' AFTER status;

CREATE INDEX IF NOT EXISTS idx_pay_ins_source ON pay_ins(source);
