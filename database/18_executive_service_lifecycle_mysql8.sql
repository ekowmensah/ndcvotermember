-- Executive Phase 3. Import COMPLETE file in phpMyAdmin AFTER migration 17.
-- Preserves all appointments. No sample data or automatic closures.
DELIMITER $$
DROP PROCEDURE IF EXISTS migration18_executive_lifecycle$$
CREATE PROCEDURE migration18_executive_lifecycle()
BEGIN
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name='cancelled_at') THEN
  ALTER TABLE executive_appointments ADD cancelled_at DATETIME(6) NULL;
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name='end_notes') THEN
  ALTER TABLE executive_appointments ADD end_notes VARCHAR(2000) NULL;
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name='ended_by_user_id') THEN
  ALTER TABLE executive_appointments ADD ended_by_user_id BIGINT UNSIGNED NULL,
   ADD CONSTRAINT fk_exec_ended_by FOREIGN KEY(ended_by_user_id) REFERENCES app_users(id);
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name='replaces_appointment_id') THEN
  ALTER TABLE executive_appointments ADD replaces_appointment_id BIGINT UNSIGNED NULL,
   ADD UNIQUE KEY uq_exec_replacement(replaces_appointment_id),
   ADD CONSTRAINT fk_exec_replaced_term FOREIGN KEY(replaces_appointment_id) REFERENCES executive_appointments(id);
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name='open_office' AND generation_expression LIKE '%cancelled_at%') THEN
  ALTER TABLE executive_appointments MODIFY COLUMN open_office TINYINT GENERATED ALWAYS AS (IF(ended_on IS NULL AND cancelled_at IS NULL,1,NULL)) STORED;
 END IF;
 SELECT 'Executive lifecycle ready. Existing appointments retained.' AS result;
END$$
CALL migration18_executive_lifecycle()$$
DROP PROCEDURE IF EXISTS migration18_executive_lifecycle$$
DELIMITER ;
