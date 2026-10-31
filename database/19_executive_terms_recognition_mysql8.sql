-- Import COMPLETE file in phpMyAdmin AFTER migration 18. No demo data.
CREATE TABLE IF NOT EXISTS executive_leadership_terms (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(160) NOT NULL UNIQUE,
 starts_on DATE NOT NULL,ends_on DATE NOT NULL,
 description VARCHAR(1000) NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT ck_exec_term_dates CHECK(ends_on>=starts_on),
 CONSTRAINT fk_exec_term_creator FOREIGN KEY(created_by_user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS executive_recognitions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 appointment_id BIGINT UNSIGNED NOT NULL UNIQUE,
 title VARCHAR(160) NOT NULL,citation VARCHAR(2000) NOT NULL,achievements VARCHAR(4000) NULL,
 recognized_on DATE NOT NULL,display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 is_published TINYINT(1) NOT NULL DEFAULT 0,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_exec_recognition_service FOREIGN KEY(appointment_id) REFERENCES executive_appointments(id),
 CONSTRAINT fk_exec_recognition_creator FOREIGN KEY(created_by_user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELIMITER $$
DROP PROCEDURE IF EXISTS migration19_exec_expansion$$
CREATE PROCEDURE migration19_exec_expansion()
BEGIN
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name='leadership_term_id') THEN
  ALTER TABLE executive_appointments ADD leadership_term_id BIGINT UNSIGNED NULL,
   ADD CONSTRAINT fk_exec_appointment_term FOREIGN KEY(leadership_term_id) REFERENCES executive_leadership_terms(id);
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name='leadership_term_name_snapshot') THEN
  ALTER TABLE executive_appointments ADD leadership_term_name_snapshot VARCHAR(160) NULL;
 END IF;
 SELECT 'Leadership terms and recognition ready. Existing appointments unchanged.' AS result;
END$$
CALL migration19_exec_expansion()$$
DROP PROCEDURE IF EXISTS migration19_exec_expansion$$
DELIMITER ;
