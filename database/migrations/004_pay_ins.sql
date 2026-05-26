-- =============================================================
-- Migration: 004_pay_ins
-- Description: Pay-in tables for cash/cheque receipts from departments
-- =============================================================

CREATE TABLE IF NOT EXISTS pay_ins (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    pay_in_id       VARCHAR(20)     NOT NULL UNIQUE,
    department_id   INT UNSIGNED    NULL,
    department_name VARCHAR(255)    NULL,
    cashier_uid     INT UNSIGNED    NOT NULL,
    pay_in_date     DATE            NOT NULL,
    total_cash      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    total_cheques   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    total_amount    DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    notes           TEXT            NULL,
    bank_slip_path  VARCHAR(500)    NULL,
    status          ENUM('submitted','verified','rejected') NOT NULL DEFAULT 'submitted',
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pay_in_cash (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    pay_in_id   VARCHAR(20)     NOT NULL,
    d_100       INT UNSIGNED    NOT NULL DEFAULT 0,
    d_50        INT UNSIGNED    NOT NULL DEFAULT 0,
    d_20        INT UNSIGNED    NOT NULL DEFAULT 0,
    d_10        INT UNSIGNED    NOT NULL DEFAULT 0,
    d_5         INT UNSIGNED    NOT NULL DEFAULT 0,
    d_2         INT UNSIGNED    NOT NULL DEFAULT 0,
    d_1         INT UNSIGNED    NOT NULL DEFAULT 0,
    c_50        INT UNSIGNED    NOT NULL DEFAULT 0,
    c_25        INT UNSIGNED    NOT NULL DEFAULT 0,
    c_10        INT UNSIGNED    NOT NULL DEFAULT 0,
    c_5         INT UNSIGNED    NOT NULL DEFAULT 0,
    c_1         INT UNSIGNED    NOT NULL DEFAULT 0,
    cash_total  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_pic_pay_in FOREIGN KEY (pay_in_id) REFERENCES pay_ins(pay_in_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pay_in_cheques (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    pay_in_id       VARCHAR(20)     NOT NULL,
    bank_name       VARCHAR(150)    NOT NULL,
    cheque_number   VARCHAR(50)     NOT NULL,
    amount          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_pich_pay_in FOREIGN KEY (pay_in_id) REFERENCES pay_ins(pay_in_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
