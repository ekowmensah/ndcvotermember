-- Import manually after 21 and 23. No accounts or demo data are created.
CREATE TABLE IF NOT EXISTS staff_executive_access (
 user_id BIGINT UNSIGNED PRIMARY KEY,
 member_id BIGINT UNSIGNED NULL,
 access_role ENUM('viewer','reviewer') NOT NULL DEFAULT 'viewer',
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 can_contacts TINYINT(1) NOT NULL DEFAULT 0,
 can_export TINYINT(1) NOT NULL DEFAULT 0,
 revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_staff_scope_user FOREIGN KEY(user_id) REFERENCES app_users(id),
 CONSTRAINT fk_staff_scope_member FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS record_review_flags (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 voter_record_id BIGINT UNSIGNED NULL,
 raised_by_user_id BIGINT UNSIGNED NOT NULL,
 note_encrypted TEXT NOT NULL,
 status ENUM('open','resolved') NOT NULL DEFAULT 'open',
 resolved_by_user_id BIGINT UNSIGNED NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 resolved_at DATETIME(6) NULL,
 KEY ix_review_status(status,id),
 CONSTRAINT fk_review_person FOREIGN KEY(voter_record_id) REFERENCES voters(id) ON DELETE SET NULL,
 CONSTRAINT fk_review_actor FOREIGN KEY(raised_by_user_id) REFERENCES app_users(id),
 CONSTRAINT fk_review_resolver FOREIGN KEY(resolved_by_user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
