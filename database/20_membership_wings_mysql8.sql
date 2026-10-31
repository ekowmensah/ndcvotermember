-- Import COMPLETE file in phpMyAdmin. Requires existing members/voters/app_users tables.
-- No existing records are changed. No sample enrollments or automatic eligibility.
CREATE TABLE IF NOT EXISTS member_wing_enrollments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 wing_code ENUM('youth','women') NOT NULL,
 member_id BIGINT UNSIGNED NULL,
 voter_record_id BIGINT UNSIGNED NULL,
 member_name_snapshot VARCHAR(255) NOT NULL,
 member_number_snapshot VARCHAR(64) NOT NULL,
 joined_on DATE NOT NULL,
 ended_on DATE NULL,
 end_reason ENUM('left','membership_removed') NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 ended_by_user_id BIGINT UNSIGNED NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 open_flag TINYINT GENERATED ALWAYS AS (CASE WHEN ended_on IS NULL THEN 1 ELSE NULL END) STORED,
 UNIQUE KEY uq_wing_open_person (wing_code,voter_record_id,open_flag),
 KEY ix_wing_directory (wing_code,ended_on,joined_on,id),
 KEY ix_wing_member (member_id,ended_on),
 CONSTRAINT ck_wing_dates CHECK (ended_on IS NULL OR ended_on>=joined_on),
 CONSTRAINT ck_wing_end CHECK ((ended_on IS NULL AND end_reason IS NULL AND ended_by_user_id IS NULL) OR (ended_on IS NOT NULL AND end_reason IS NOT NULL AND ended_by_user_id IS NOT NULL)),
 CONSTRAINT fk_wing_member FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE SET NULL,
 CONSTRAINT fk_wing_person FOREIGN KEY(voter_record_id) REFERENCES voters(id) ON DELETE SET NULL,
 CONSTRAINT fk_wing_creator FOREIGN KEY(created_by_user_id) REFERENCES app_users(id),
 CONSTRAINT fk_wing_ender FOREIGN KEY(ended_by_user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
