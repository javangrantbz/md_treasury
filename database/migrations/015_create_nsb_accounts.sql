-- =============================================================
-- Migration: 015_create_nsb_accounts
-- Description: Core savings-account store for the NSB module.
--              NSB is a government savings scheme (not card-centric):
--              each account is opened with a customer name, a
--              starting balance and a starting date. Interest is
--              applied once a year at month-varying rates — that
--              engine (rate table + annual postings) is deferred
--              until the client supplies the rates. Card
--              applications (nsb_applications) are a later add-on.
-- Requires existing `branches` and `users` tables.
-- =============================================================

CREATE TABLE IF NOT EXISTS `nsb_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_number` varchar(30) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `starting_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `starting_date` date NOT NULL,
  `status` enum('active','dormant','closed') NOT NULL DEFAULT 'active',
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nsb_account_number` (`account_number`),
  KEY `idx_nsb_accounts_status` (`status`),
  KEY `idx_nsb_accounts_branch` (`branch_id`),
  KEY `idx_nsb_accounts_deleted_at` (`deleted_at`),
  CONSTRAINT `nsb_accounts_branch_fk` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `nsb_accounts_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
