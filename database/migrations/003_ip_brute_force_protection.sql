-- =============================================================
-- Migration: 003_ip_brute_force_protection
-- Description: Tracks and blocks suspicious IP addresses
-- =============================================================

CREATE TABLE IF NOT EXISTS ip_login_attempts (
    ip_address    VARCHAR(45) PRIMARY KEY,
    failed_count  INT NOT NULL DEFAULT 0,
    last_attempt  DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    INDEX idx_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
