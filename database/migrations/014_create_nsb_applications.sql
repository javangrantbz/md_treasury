-- =============================================================
-- Migration: 014_create_nsb_applications
-- Description: Creates the nsb_applications table that backs the
--              National Savings Bank (NSB) module — card/account
--              applications and their approval workflow. This is
--              the only table the NSB code reads/writes; the
--              customers, cards and ledger screens are views over
--              it. Requires the existing `branches` and `users`
--              tables (foreign keys).
-- =============================================================

CREATE TABLE IF NOT EXISTS `nsb_applications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(255) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `account_number` varchar(20) NOT NULL,
  `card_type` enum('atm','debit','credit') NOT NULL DEFAULT 'debit',
  `branch_id` bigint(20) unsigned NOT NULL,
  `status` enum('pending','processing','approved','rejected','printed','delivered','void') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `branch_id` (`branch_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `nsb_applications_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `nsb_applications_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
