-- =============================================================
-- Migration: 011_add_live_only_tables_to_local
-- Description: Add tables present in live_db.sql but missing from
--              the local treasury_connect database
-- =============================================================

CREATE TABLE IF NOT EXISTS `bank_balances` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bb_uuid` varchar(36) NOT NULL,
  `shift_id` varchar(222) DEFAULT NULL,
  `departmentId` int(10) unsigned NOT NULL,
  `bankId` int(10) unsigned NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `startingCash` decimal(10,2) NOT NULL,
  `uid` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_uid_date` (`uid`,`created_at`),
  KEY `idx_dept` (`departmentId`),
  KEY `idx_bank` (`bankId`),
  KEY `idx_uuid` (`bb_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cash_counts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `shift_id` varchar(36) NOT NULL,
  `uid` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `register_id` int(10) unsigned DEFAULT NULL,
  `expected_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `counted_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `denomination_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON with qty for each denomination' CHECK (json_valid(`denomination_breakdown`)),
  `difference` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'counted - expected',
  `status` enum('cash_on_hand','pay_in') NOT NULL DEFAULT 'cash_on_hand',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_uuid` (`uuid`),
  KEY `idx_shift` (`shift_id`),
  KEY `idx_uid` (`uid`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_favorites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL,
  `activity_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_activity` (`uid`,`activity_id`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shifts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shift_id` varchar(36) NOT NULL,
  `uid` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `register_id` int(10) unsigned DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=ended',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_shift_id` (`shift_id`),
  KEY `idx_uid_status` (`uid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
