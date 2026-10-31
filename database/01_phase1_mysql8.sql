-- Phase 1: voters, membership and staff sign-in. No sample/demo records.
CREATE TABLE app_users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','registrar','viewer') NOT NULL DEFAULT 'viewer',is_active TINYINT(1) NOT NULL DEFAULT 1,
 session_version INT UNSIGNED NOT NULL DEFAULT 1,last_login_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE app_login_limits (
 attempt_key BINARY(32) PRIMARY KEY,attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 window_started_at DATETIME(6) NOT NULL,locked_until DATETIME(6) NULL
) ENGINE=InnoDB;
CREATE TABLE voters (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,voter_id VARCHAR(64) COLLATE utf8mb4_bin NOT NULL UNIQUE,
 full_name VARCHAR(250) NOT NULL,gender ENUM('unknown','female','male','other') NOT NULL DEFAULT 'unknown',date_of_birth DATE NULL,
 phone_encrypted TEXT NULL,email_encrypted TEXT NULL,address_encrypted TEXT NULL,
 region VARCHAR(160) NULL,constituency VARCHAR(180) NULL,electoral_area VARCHAR(200) NULL,polling_station VARCHAR(250) NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 KEY ix_voter_name(full_name),CONSTRAINT fk_voter_staff FOREIGN KEY(created_by_user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE members (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,voter_record_id BIGINT UNSIGNED NOT NULL UNIQUE,
 member_number VARCHAR(32) NULL UNIQUE,joined_on DATE NOT NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 marked_by_user_id BIGINT UNSIGNED NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_member_voter FOREIGN KEY(voter_record_id) REFERENCES voters(id),
 CONSTRAINT fk_member_staff FOREIGN KEY(marked_by_user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE voter_imports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,filename VARCHAR(255) NOT NULL,
 added_count INT UNSIGNED NOT NULL,updated_count INT UNSIGNED NOT NULL,skipped_count INT UNSIGNED NOT NULL,
 imported_by_user_id BIGINT UNSIGNED NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_import_staff FOREIGN KEY(imported_by_user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE audit_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_uuid CHAR(36) NOT NULL UNIQUE,
 actor_user_id BIGINT UNSIGNED NULL,action_code VARCHAR(100) NOT NULL,entity_type VARCHAR(80) NOT NULL,
 entity_id BIGINT UNSIGNED NULL,ip_address VARBINARY(16) NULL,change_summary JSON NULL,
 occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_audit_staff FOREIGN KEY(actor_user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
