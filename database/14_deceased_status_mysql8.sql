-- Run the COMPLETE file in phpMyAdmin. No people or sample data are added.
DELIMITER $$
DROP PROCEDURE IF EXISTS migration14_deceased_status$$
CREATE PROCEDURE migration14_deceased_status()
BEGIN
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='voters' AND column_name='is_deceased') THEN
  ALTER TABLE voters ADD is_deceased TINYINT(1) NOT NULL DEFAULT 0;
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='voters' AND column_name='date_of_death') THEN
  ALTER TABLE voters ADD date_of_death DATE NULL;
 END IF;
 SELECT 'Deceased status ready. Existing records remain not marked deceased.' AS result;
END$$
CALL migration14_deceased_status()$$
DROP PROCEDURE IF EXISTS migration14_deceased_status$$
DELIMITER ;
