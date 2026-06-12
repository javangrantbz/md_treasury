-- =============================================================
-- Migration: 012_add_payin_link_to_pos_cart_items
-- Description: Link POS cart items to the pay-in they were
--              settled under, so a cashier can generate a
--              "my sales today" pay-in without double-counting
--              items that have already been paid in.
-- =============================================================

ALTER TABLE pos_cart_items
    ADD COLUMN IF NOT EXISTS pay_in_id VARCHAR(20) NULL DEFAULT NULL AFTER status;

CREATE INDEX IF NOT EXISTS idx_pos_cart_items_pay_in_id ON pos_cart_items(pay_in_id);
