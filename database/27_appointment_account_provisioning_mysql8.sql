-- Manual phpMyAdmin import after corrected 26. No existing accounts are changed.
CREATE TABLE IF NOT EXISTS executive_preset_accounts (
 user_id BIGINT UNSIGNED PRIMARY KEY,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_preset_account_user FOREIGN KEY(user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS executive_appointment_access (
 appointment_id BIGINT UNSIGNED PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 access_role ENUM('viewer','reviewer') NOT NULL DEFAULT 'viewer',
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 can_contacts TINYINT(1) NOT NULL DEFAULT 0,
 can_edit_voter_contacts TINYINT(1) NOT NULL DEFAULT 0,
 can_nominate TINYINT(1) NOT NULL DEFAULT 0,
 can_approve_membership TINYINT(1) NOT NULL DEFAULT 0,
 revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
 KEY ix_grant_user(user_id),
 CONSTRAINT fk_appointment_grant_office FOREIGN KEY(appointment_id) REFERENCES executive_appointments(id),
 CONSTRAINT fk_appointment_grant_user FOREIGN KEY(user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS staff_account_activations (
 user_id BIGINT UNSIGNED PRIMARY KEY,
 token_hash BINARY(32) NOT NULL UNIQUE,
 session_version INT UNSIGNED NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 used_at DATETIME(6) NULL,
 CONSTRAINT fk_activation_user FOREIGN KEY(user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
