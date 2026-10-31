-- Import in phpMyAdmin after migration 08. Optional background information only.
-- No sample data. Existing memberships, identifiers, photos and voters are unchanged.
CREATE TABLE IF NOT EXISTS member_profiles (
 member_id BIGINT UNSIGNED PRIMARY KEY,
 profession VARCHAR(150) NULL,
 education_level ENUM('none','primary','junior_secondary','senior_secondary','vocational','diploma','bachelors','masters','doctorate','other') NULL,
 employment_status ENUM('employed','self_employed','unemployed','student','retired','other') NULL,
 job_title VARCHAR(150) NULL,
 employer VARCHAR(180) NULL,
 skills VARCHAR(500) NULL,
 languages VARCHAR(250) NULL,
 volunteer_interests VARCHAR(500) NULL,
 availability ENUM('flexible','weekdays','weekends','evenings','unavailable') NULL,
 updated_by_user_id BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 KEY ix_profile_profession(profession),
 KEY ix_profile_education(education_level),
 KEY ix_profile_employment(employment_status),
 KEY ix_profile_availability(availability),
 CONSTRAINT fk_profile_membership FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE,
 CONSTRAINT fk_profile_staff FOREIGN KEY(updated_by_user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
