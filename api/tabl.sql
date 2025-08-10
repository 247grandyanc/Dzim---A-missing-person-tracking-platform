-- Enable strict mode for better data integrity
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Core Tables
-- --------------------------------------------------------

CREATE TABLE admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id)
);

-- Admin sessions table
CREATE TABLE `admin_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL COMMENT 'SHA256 hash of token',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `token` (`token`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `admin_sessions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL COMMENT 'bcrypt encrypted',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Encrypted with AES-256',
  `country` varchar(2) NOT NULL DEFAULT 'GH' COMMENT 'ISO country code',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Last login IP',
   last_login TIMESTAMP NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  verification_token VARCHAR(64),
  status ENUM('ACTIVE', 'SUSPENDED') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- First create subscription_plans
CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `searches_per_month` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_popular` tinyint(1) NOT NULL DEFAULT 0,
  `features` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subscriptions
CREATE TABLE `subscription` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending',
  `payment_reference` varchar(100) DEFAULT NULL,
  `paystack_reference` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `payment_currency` varchar(3) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `paystack_data` json DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `plan_id` (`plan_id`),
  CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;;

-- --------------------------------------------------------

CREATE TABLE `search_history` (
  `search_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL for guest searches',
  `query_type` enum('NAME','PHONE','IMAGE') NOT NULL,
  `query_text` varchar(255) DEFAULT NULL COMMENT 'Encrypted if sensitive',
  `image_hash` varchar(64) DEFAULT NULL COMMENT 'SHA-256 of biometric vector',
  `is_deep_search` tinyint(1) NOT NULL DEFAULT 0,
  `confidence_score` float DEFAULT NULL COMMENT 'NULL for basic searches',
  `result_count` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) NOT NULL,
  PRIMARY KEY (`search_id`),
  KEY `user_id` (`user_id`),
  KEY `query_type` (`query_type`),
  CONSTRAINT `search_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `search_results` (
  `result_id` int(11) NOT NULL AUTO_INCREMENT,
  `search_id` int(11) NOT NULL,
  `platform` enum('FACEBOOK','TWITTER','INSTAGRAM','LINKEDIN','OTHERS') NOT NULL,
  `profile_url` varchar(512) NOT NULL COMMENT 'Encrypted URL',
  `match_score` float DEFAULT NULL COMMENT '0-1 for image searches',
  `is_verified` tinyint(1) DEFAULT 0 COMMENT 'Platform verification',
  `flagged` tinyint(1) DEFAULT 0 COMMENT 'Marked suspicious by admin',
  PRIMARY KEY (`result_id`),
  KEY `search_id` (`search_id`),
  CONSTRAINT `search_results_ibfk_1` FOREIGN KEY (`search_id`)
  REFERENCES `search_history` (`search_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------


CREATE TABLE social_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARBINARY(255) NOT NULL COMMENT 'AES encrypted',
    platform VARCHAR(20) NOT NULL,
    profile_url VARCHAR(512) NOT NULL,
    image_hash VARCHAR(64),
    vector_id INT COMMENT 'Reference to biometric_vectors',
    INDEX (name),
    INDEX (image_hash),
    KEY `profile_id` (`profile_id`),
  CONSTRAINT `social_profiles_ibfk_1` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`profile_id`) ON DELETE CASCADE
);

CREATE TABLE `profiles` (
  `profile_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varbinary(255) NOT NULL COMMENT 'AES encrypted',
  `email` varbinary(255) DEFAULT NULL COMMENT 'AES encrypted',
  `address` text DEFAULT NULL,
  `image_hash` varchar(64) DEFAULT NULL COMMENT 'SHA-256 of profile image',
  `vector_id` int(11) DEFAULT NULL COMMENT 'Reference to biometric_vectors',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`profile_id`),
  KEY `name` (`name`),
  KEY `image_hash` (`image_hash`),
  KEY `vector_id` (`vector_id`),
  CONSTRAINT `profiles_ibfk_1` FOREIGN KEY (`vector_id`) REFERENCES `biometric_vectors` (`vector_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `biometric_vectors` (
  `vector_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'If registered user uploaded',
  `vector_hash` varchar(64) NOT NULL COMMENT 'SHA-256 of feature vector',
  `vector_data` blob NOT NULL COMMENT 'Encrypted facial features',
  `source` enum('UPLOAD','SOCIAL_SCRAPE') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vector_id`),
  UNIQUE KEY `vector_hash` (`vector_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `biometric_vectors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `profile_connections` (
  `connection_id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_id1` int(11) NOT NULL,
  `profile_id2` int(11) NOT NULL,
  `connection_type` enum('family','friend','colleague','other') NOT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`connection_id`),
  UNIQUE KEY `unique_connection` (`profile_id1`,`profile_id2`),
  KEY `profile_id2` (`profile_id2`),
  CONSTRAINT `profile_connections_ibfk_1` FOREIGN KEY (`profile_id1`) REFERENCES `profiles` (`profile_id`) ON DELETE CASCADE,
  CONSTRAINT `profile_connections_ibfk_2` FOREIGN KEY (`profile_id2`) REFERENCES `profiles` (`profile_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Admin & System Tables
-- --------------------------------------------------------

CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('SUPER_ADMIN','MODERATOR','ANALYST') NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `admin_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL COMMENT 'SHA-256 hashed session token',
  `ip_address` varchar(45) NOT NULL COMMENT 'Supports both IPv4 and IPv6',
  `user_agent` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `token` (`token`),
  KEY `admin_id` (`admin_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `admin_sessions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL COMMENT 'e.g., "BAN_USER"',
  `target_id` int(11) DEFAULT NULL COMMENT 'User/search affected',
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Initial Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('REQUIRE_GH_IP', '1'),
('BIOMETRICS_ENABLED', '1'),
('MAX_FREE_SEARCHES', '5');




-- --------------------------------------------------------
-- Ads Placement System
-- --------------------------------------------------------

CREATE TABLE `ad_campaigns` (
  `campaign_id` int(11) NOT NULL AUTO_INCREMENT,
  `advertiser_name` varchar(100) NOT NULL,
  `target_country` varchar(2) DEFAULT NULL COMMENT 'NULL for global',
  `image_url` varchar(255) NOT NULL,
  `destination_url` varchar(255) NOT NULL,
  `budget` decimal(10,2) NOT NULL,
  `clicks_remaining` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `start_date` timestamp NOT NULL,
  `end_date` timestamp NOT NULL,
  PRIMARY KEY (`campaign_id`),
  KEY `target_country` (`target_country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ad_placements` (
  `placement_id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `placement_type` enum('SIDEBAR','INLINE_RESULTS','FOOTER') NOT NULL,
  `weight` int(11) NOT NULL DEFAULT 100 COMMENT 'Relative priority',
  PRIMARY KEY (`placement_id`),
  KEY `campaign_id` (`campaign_id`),
  CONSTRAINT `ad_placements_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `ad_campaigns` (`campaign_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ad_impressions` (
  `impression_id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `page_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`impression_id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ad_impressions_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `ad_campaigns` (`campaign_id`),
  CONSTRAINT `ad_impressions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Transaction Tracking (Beyond Paystack)
-- --------------------------------------------------------

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `paystack_reference` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'GHS',
  `status` enum('PENDING','SUCCESS','FAILED','REFUNDED') NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`transaction_id`),
  KEY `user_id` (`user_id`),
  KEY `subscription_id` (`subscription_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`subscription_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- IP Management (Blocking/Geo-Restrictions)
-- --------------------------------------------------------

CREATE TABLE `deep_search_logs` (
  `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'User who performed the search',
  `search_type` enum('text','image','biometric','reverse_image','phone','email') NOT NULL,
  `query_value` varchar(512) DEFAULT NULL COMMENT 'The actual search query or image filename',
  `query_hash` varchar(64) DEFAULT NULL COMMENT 'SHA256 hash of sensitive queries for deduplication',
  `is_deep_search` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Flag for deep vs basic search',
  `result_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of results returned',
  `match_confidence_avg` float DEFAULT NULL COMMENT 'Average confidence score of results',
  `matched_vectors` json DEFAULT NULL COMMENT 'Array of matched vector IDs for image searches',
  `subscription_used` int(11) DEFAULT NULL COMMENT 'Subscription ID that was charged',
  `credits_used` int(11) NOT NULL DEFAULT 1 COMMENT 'Number of credits consumed',
  `processing_time` float DEFAULT NULL COMMENT 'Time in seconds the search took',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `device_fingerprint` varchar(128) DEFAULT NULL COMMENT 'Client-side device fingerprint',
  `request_metadata` json DEFAULT NULL COMMENT 'Additional request headers/metadata',
  `country_code` varchar(2) DEFAULT NULL COMMENT 'Geolocation from IP',
  `is_tor` tinyint(1) DEFAULT 0 COMMENT 'Flag for TOR network usage',
  `flagged` tinyint(1) DEFAULT 0 COMMENT 'Marked for review by admins',
  `flag_reason` enum('high_confidence','celebrity','law_enforcement','many_results','other') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_search_type` (`search_type`),
  KEY `idx_created` (`created_at`),
  KEY `idx_query_hash` (`query_hash`),
  KEY `idx_flagged` (`flagged`),
  CONSTRAINT `fk_deep_search_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs of all deep search activities';

-- Add partition by month for large datasets
ALTER TABLE `deep_search_logs` PARTITION BY RANGE (UNIX_TIMESTAMP(`created_at`)) (
    PARTITION p2023_01 VALUES LESS THAN (UNIX_TIMESTAMP('2023-02-01 00:00:00')),
    PARTITION p2023_02 VALUES LESS THAN (UNIX_TIMESTAMP('2023-03-01 00:00:00')),
    PARTITION p_current VALUES LESS THAN MAXVALUE
);


CREATE TABLE `error_reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `error_type` varchar(50) NOT NULL COMMENT 'Type/category of error',
  `error_message` text NOT NULL COMMENT 'Detailed error description',
  `error_file` varchar(255) DEFAULT NULL COMMENT 'File where error occurred',
  `error_line` int(11) DEFAULT NULL COMMENT 'Line number where error occurred',
  `error_code` varchar(50) DEFAULT NULL COMMENT 'Error code if available',
  `request_url` varchar(255) DEFAULT NULL COMMENT 'URL where error occurred',
  `user_id` int(11) DEFAULT NULL COMMENT 'User who encountered the error (if logged in)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of requester',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'User agent/browser info',
  `stack_trace` text DEFAULT NULL COMMENT 'Full error stack trace',
  `additional_data` text DEFAULT NULL COMMENT 'JSON-encoded additional data',
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=unresolved, 1=resolved',
  `resolved_by` int(11) DEFAULT NULL COMMENT 'Admin who resolved it',
  `resolved_at` datetime DEFAULT NULL COMMENT 'When it was resolved',
  `resolution_notes` text DEFAULT NULL COMMENT 'How it was resolved',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`),
  KEY `is_resolved` (`is_resolved`),
  KEY `error_type` (`error_type`),
  KEY `created_at` (`created_at`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `ip_blocks` (
  `block_id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_range` varchar(45) NOT NULL COMMENT 'e.g., 192.168.1.0/24',
  `reason` enum('ABUSE','GEO_BLOCK','MANUAL_BAN') NOT NULL,
  `admin_id` int(11) DEFAULT NULL COMMENT 'Who blocked it',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'NULL = permanent',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`block_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `ip_blocks_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Missing Persons System Tables
CREATE TABLE `missing_persons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('missing','found') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `home_name` varchar(50) NOT NULL COMMENT 'Common Ghanaian home name',
  `age` int(11) DEFAULT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `height` varchar(20) DEFAULT NULL COMMENT 'e.g. 5ft 8in or 173cm',
  `last_seen_location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `reporter_id` int(11) DEFAULT NULL COMMENT 'User who reported',
  `reporter_name` varchar(100) DEFAULT NULL,
  `reporter_contact` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `status` enum('active','resolved','removed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `type_status` (`type`,`status`),
  KEY `home_name` (`home_name`),
  FULLTEXT KEY `fulltext_search` (`full_name`,`home_name`,`description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `missing_person_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `missing_id` int(11) NOT NULL COMMENT 'ID of missing person report',
  `found_id` int(11) NOT NULL COMMENT 'ID of found person report',
  `confidence_score` float NOT NULL COMMENT '0-1 confidence in match',
  `matched_by` enum('system','admin','user') NOT NULL,
  `matched_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_match` (`missing_id`,`found_id`),
  KEY `found_id` (`found_id`),
  CONSTRAINT `missing_person_matches_ibfk_1` FOREIGN KEY (`missing_id`) REFERENCES `missing_persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `missing_person_matches_ibfk_2` FOREIGN KEY (`found_id`) REFERENCES `missing_persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `missing_person_searches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `search_type` enum('missing','found') NOT NULL,
  `search_terms` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `missing_person_searches_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reward system tables
CREATE TABLE `missing_person_rewards` (
  `reward_id` int(11) NOT NULL AUTO_INCREMENT,
  `missing_person_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User offering the reward',
  `amount` decimal(10,2) NOT NULL COMMENT 'Reward amount in GHC',
  `platform_fee` decimal(10,2) NOT NULL COMMENT 'Platform fee in GHC',
  `total_amount` decimal(10,2) NOT NULL COMMENT 'Amount + fee',
  `status` enum('pending','active','claimed','refunded') NOT NULL DEFAULT 'pending',
  `paystack_reference` varchar(100) DEFAULT NULL,
  `payment_status` enum('unpaid','paid','failed') NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`reward_id`),
  KEY `missing_person_id` (`missing_person_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `missing_person_rewards_ibfk_1` FOREIGN KEY (`missing_person_id`) REFERENCES `missing_persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `missing_person_rewards_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reward_claims` (
  `claim_id` int(11) NOT NULL AUTO_INCREMENT,
  `reward_id` int(11) NOT NULL,
  `claimer_id` int(11) NOT NULL COMMENT 'User claiming the reward',
  `message` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `admin_id` int(11) DEFAULT NULL COMMENT 'Admin who processed',
  `paystack_transfer_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`claim_id`),
  KEY `reward_id` (`reward_id`),
  KEY `claimer_id` (`claimer_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `reward_claims_ibfk_1` FOREIGN KEY (`reward_id`) REFERENCES `missing_person_rewards` (`reward_id`) ON DELETE CASCADE,
  CONSTRAINT `reward_claims_ibfk_2` FOREIGN KEY (`claimer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `reward_claims_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add reward flag to missing persons table
ALTER TABLE `missing_persons` 
ADD COLUMN `has_reward` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`,
ADD COLUMN `reward_amount` decimal(10,2) DEFAULT NULL AFTER `has_reward`;

CREATE TABLE `allowed_countries` (
  `country_code` varchar(2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Initial setup: Only Ghana allowed
INSERT INTO `allowed_countries` (`country_code`, `is_active`) VALUES ('GH', 1);

-- --------------------------------------------------------
-- Error Reporting
-- --------------------------------------------------------

CREATE TABLE `error_logs` (
  `error_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `error_code` varchar(50) NOT NULL COMMENT 'e.g., "PAYMENT_FAILED"',
  `message` text NOT NULL,
  `stack_trace` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`error_id`),
  KEY `user_id` (`user_id`),
  KEY `resolved` (`resolved`),
  CONSTRAINT `error_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `api_key` varchar(64) NOT NULL COMMENT 'SHA256 hash of the key',
  `permissions` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add status to ad campaigns
ALTER TABLE `ad_campaigns` 
ADD COLUMN `status` ENUM('PENDING','ACTIVE','REJECTED') NOT NULL DEFAULT 'PENDING',
ADD COLUMN `approved_by` INT NULL AFTER `status`,
ADD COLUMN `approved_at` TIMESTAMP NULL AFTER `approved_by`,
ADD COLUMN `rejected_by` INT NULL AFTER `approved_at`,
ADD COLUMN `rejected_at` TIMESTAMP NULL AFTER `rejected_by`,
ADD CONSTRAINT `fk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `admin_users` (`admin_id`),
ADD CONSTRAINT `fk_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `admin_users` (`admin_id`);

-- Add clicks and CPC to impressions
ALTER TABLE `ad_impressions` 
ADD COLUMN `clicked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `page_url`,
ADD COLUMN `cpc` DECIMAL(10,2) NULL AFTER `clicked`;

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission` (`role`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(255) NOT NULL,
  `requests` int(11) NOT NULL DEFAULT 1,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cache_key` (`cache_key`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_subscription_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    purpose_of_search TEXT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    relation_with_person VARCHAR(255) NOT NULL,
    organization_name VARCHAR(255),
    security_question_1 VARCHAR(255) NOT NULL,
    security_answer_1 VARCHAR(255) NOT NULL,
    security_question_2 VARCHAR(255) NOT NULL,
    security_answer_2 VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);


-- --------------------------------------------------------
-- New System Settings (Add to existing table)
-- --------------------------------------------------------

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('ADS_ENABLED', '1'),
('MAX_ADS_PER_PAGE', '3'),
('ERROR_REPORTING_EMAIL', 'dev@personsearch.com');

-- --------------------------------------------------------
-- Indexes & Optimizations
-- --------------------------------------------------------

CREATE INDEX `idx_search_confidence` ON `search_history` (`confidence_score`);
CREATE INDEX `idx_subscription_status` ON `subscriptions` (`status`);
CREATE FULLTEXT INDEX `idx_encrypted_query` ON `search_history` (`query_text`) COMMENT 'For encrypted partial match';

COMMIT;

