-- Import COMPLETE file in phpMyAdmin AFTER location migrations 02–05.
-- Allows shared station names. Preserves all records, IDs, branch assignments and voter links.
DELIMITER $$
DROP PROCEDURE IF EXISTS migration22_station_names$$
CREATE PROCEDURE migration22_station_names()
BEGIN
 DECLARE name_index VARCHAR(64) DEFAULT NULL;
 IF NOT EXISTS(
  SELECT 1 FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='polling_stations' AND non_unique=0
  GROUP BY index_name HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index)='station_code' AND SUM(sub_part IS NOT NULL)=0
 ) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Import migration 04 first: the full official Station ID must have a global unique index.';
 END IF;
 -- Preserve the constituency foreign-key support and name lookup performance.
 IF NOT EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='polling_stations' AND index_name='ix_station_constituency_name') THEN
  ALTER TABLE polling_stations ADD KEY ix_station_constituency_name(constituency_id,name);
 END IF;
 name_indexes: LOOP
  SELECT MIN(index_name) INTO name_index FROM (
   SELECT index_name FROM information_schema.statistics
   WHERE table_schema=DATABASE() AND table_name='polling_stations' AND non_unique=0 AND index_name<>'PRIMARY'
   GROUP BY index_name HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) IN ('name','constituency_id,name','name,constituency_id')
  ) AS unique_names;
  IF name_index IS NULL THEN LEAVE name_indexes; END IF;
  SET @station_name_ddl=CONCAT('ALTER TABLE polling_stations DROP INDEX `',REPLACE(name_index,'`','``'),'`');
  PREPARE station_name_stmt FROM @station_name_ddl;
  EXECUTE station_name_stmt;
  DEALLOCATE PREPARE station_name_stmt;
 END LOOP;
 SET @station_name_ddl=NULL;
 SELECT 'Shared station names enabled. Official Station IDs remain globally unique. No records changed.' AS result;
END$$
CALL migration22_station_names()$$
DROP PROCEDURE IF EXISTS migration22_station_names$$
DELIMITER ;
