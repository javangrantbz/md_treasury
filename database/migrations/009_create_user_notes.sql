-- =============================================================
-- Migration: 009_create_user_notes
-- Description: Creates the user_notes table used by the profile
--              notes feature (api/profile/notes/*). This table
--              was missing from live_db.sql and never created on
--              live, which broke the soft-delete migration (008)
--              and the notes feature itself. deleted_at is
--              included up front so 008 is a no-op once this runs.
-- =============================================================

CREATE TABLE IF NOT EXISTS `user_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
