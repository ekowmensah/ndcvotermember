-- Import this complete file in phpMyAdmin after migration 09.
-- Safe to rerun after a partial or completed migration. No reset or record deletion.
-- Existing residential region names/IDs are never overwritten.
DELIMITER $$
DROP PROCEDURE IF EXISTS migration10_separate_residential_regions$$
CREATE PROCEDURE migration10_separate_residential_regions()
BEGIN
 DECLARE district_parent VARCHAR(64);
 DECLARE voter_parent VARCHAR(64);
 DECLARE district_fk VARCHAR(64);
 DECLARE voter_fk VARCHAR(64);

 IF (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema=DATABASE() AND table_name='voters'
      AND column_name IN ('town','residential_region_id','residential_district_id'))<>3
    OR (SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema=DATABASE() AND table_name='districts')<>1 THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Migration 09 is incomplete. Select the application database and complete 09 first.';
 END IF;

 -- Discover direct parent links by column, supporting old and new constraint names.
 IF (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=DATABASE()
      AND table_name='districts' AND column_name='region_id' AND referenced_column_name='id')<>1
    OR (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=DATABASE()
      AND table_name='voters' AND column_name='residential_region_id' AND referenced_column_name='id'
      AND referenced_table_name IN ('regions','residential_regions'))<>1 THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected one direct region foreign key per residential parent column. Run phpmyadmin_address_checks.sql.';
 END IF;
 SELECT constraint_name,referenced_table_name INTO district_fk,district_parent
 FROM information_schema.key_column_usage WHERE table_schema=DATABASE()
  AND table_name='districts' AND column_name='region_id' AND referenced_column_name='id';
 SELECT constraint_name,referenced_table_name INTO voter_fk,voter_parent
 FROM information_schema.key_column_usage WHERE table_schema=DATABASE()
  AND table_name='voters' AND column_name='residential_region_id' AND referenced_column_name='id'
  AND referenced_table_name IN ('regions','residential_regions');
 IF district_parent IS NULL OR district_parent NOT IN ('regions','residential_regions')
    OR voter_parent IS NULL OR voter_parent NOT IN ('regions','residential_regions') THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unexpected or missing residential foreign keys. Run phpmyadmin_address_checks.sql and share the results.';
 END IF;

 CREATE TABLE IF NOT EXISTS residential_regions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL UNIQUE,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 IF (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema=DATABASE() AND table_name='residential_regions'
      AND column_name IN ('id','name','created_at','updated_at'))<>4 THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Existing residential_regions has an unexpected structure. Share SHOW CREATE TABLE residential_regions.';
 END IF;

 -- Copy only parents still referenced through electoral regions.
 -- A completed side of the migration never reads renamed electoral records again.
 IF EXISTS(
  SELECT 1 FROM regions r
  LEFT JOIN residential_regions by_id ON by_id.id=r.id
  LEFT JOIN residential_regions by_name ON by_name.name=r.name
  WHERE ((district_parent='regions' AND EXISTS(SELECT 1 FROM districts d WHERE d.region_id=r.id))
      OR (voter_parent='regions' AND EXISTS(SELECT 1 FROM voters v WHERE v.residential_region_id=r.id)))
   AND ((by_id.id IS NOT NULL AND by_id.name<>r.name)
     OR (by_name.id IS NOT NULL AND by_name.id<>r.id))
 ) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Existing residential region IDs/names conflict with pending links. No names overwritten. Share both region directories and foreign-key results.';
 END IF;

 INSERT INTO residential_regions(id,name)
 SELECT r.id,r.name FROM regions r
 WHERE ((district_parent='regions' AND EXISTS(SELECT 1 FROM districts d WHERE d.region_id=r.id))
     OR (voter_parent='regions' AND EXISTS(SELECT 1 FROM voters v WHERE v.residential_region_id=r.id)))
  AND NOT EXISTS(SELECT 1 FROM residential_regions existing_region WHERE existing_region.id=r.id);

 IF district_parent='regions' THEN
  -- Use a distinct name; reusing a dropped name in the same ALTER can raise #1826.
  SET @migration10_sql=CONCAT('ALTER TABLE `districts` DROP FOREIGN KEY `',
   REPLACE(district_fk,'`','``'),
   '`, ADD CONSTRAINT `fk_district_residential_region_v10` FOREIGN KEY (`region_id`) REFERENCES `residential_regions` (`id`)');
  PREPARE migration10_statement FROM @migration10_sql;
  EXECUTE migration10_statement;
  DEALLOCATE PREPARE migration10_statement;
 END IF;
 IF voter_parent='regions' THEN
  SET @migration10_sql=CONCAT('ALTER TABLE `voters` DROP FOREIGN KEY `',
   REPLACE(voter_fk,'`','``'),
   '`, ADD CONSTRAINT `fk_voter_residential_region_v10` FOREIGN KEY (`residential_region_id`) REFERENCES `residential_regions` (`id`)');
  PREPARE migration10_statement FROM @migration10_sql;
  EXECUTE migration10_statement;
  DEALLOCATE PREPARE migration10_statement;
 END IF;

 SELECT 'Residential region separation complete. Existing records and links preserved.' AS result;
 SELECT table_name,constraint_name,referenced_table_name
 FROM information_schema.key_column_usage WHERE table_schema=DATABASE()
  AND ((table_name='districts' AND column_name='region_id')
    OR (table_name='voters' AND column_name='residential_region_id'))
  AND referenced_table_name IS NOT NULL;
END$$
CALL migration10_separate_residential_regions()$$
DROP PROCEDURE IF EXISTS migration10_separate_residential_regions$$
DELIMITER ;
