-- Import the COMPLETE file in phpMyAdmin. Preserves all existing records.
-- Voter IDs remain reserved while deleted; restore or permanently delete first.
DELIMITER $$
DROP PROCEDURE IF EXISTS migration13_deleted_voters$$
CREATE PROCEDURE migration13_deleted_voters()
BEGIN
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='voters' AND column_name='deleted_at') THEN
  ALTER TABLE voters ADD deleted_at DATETIME(6) NULL, ADD INDEX ix_voters_deleted(deleted_at,id);
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='voters' AND column_name='deleted_by_user_id') THEN
  ALTER TABLE voters ADD deleted_by_user_id BIGINT UNSIGNED NULL;
 END IF;
 SELECT 'Deleted voters ready. No records have been deleted.' AS result;
END$$
CALL migration13_deleted_voters()$$
DROP PROCEDURE IF EXISTS migration13_deleted_voters$$
DELIMITER ;
