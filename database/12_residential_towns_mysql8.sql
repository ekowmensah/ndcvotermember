-- Import the complete file in phpMyAdmin after migrations 09 and 10.
-- Resumable. Uses only real town names already saved on voters; no sample data.
DELIMITER $$
DROP PROCEDURE IF EXISTS migration12_residential_towns$$
CREATE PROCEDURE migration12_residential_towns()
BEGIN
 IF (SELECT COUNT(DISTINCT table_name) FROM information_schema.key_column_usage
     WHERE table_schema=DATABASE() AND referenced_table_name='residential_regions'
      AND ((table_name='districts' AND column_name='region_id')
        OR (table_name='voters' AND column_name='residential_region_id')))<>2 THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Complete migrations 09 and 10 before adding the residential town directory.';
 END IF;
 CREATE TABLE IF NOT EXISTS residential_towns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  residential_region_id BIGINT UNSIGNED NULL,
  district_id BIGINT UNSIGNED NULL,
  region_scope BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(residential_region_id,0)) STORED,
  district_scope BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(district_id,0)) STORED,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_residential_town(name,region_scope,district_scope),
  CONSTRAINT fk_town_residential_region FOREIGN KEY(residential_region_id) REFERENCES residential_regions(id),
  CONSTRAINT fk_town_residential_district FOREIGN KEY(district_id,residential_region_id) REFERENCES districts(id,region_id),
  CONSTRAINT ck_town_district_region CHECK(district_id IS NULL OR residential_region_id IS NOT NULL)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE()
               AND table_name='voters' AND column_name='residential_town_id') THEN
  ALTER TABLE voters ADD residential_town_id BIGINT UNSIGNED NULL;
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.key_column_usage WHERE table_schema=DATABASE()
               AND table_name='voters' AND column_name='residential_town_id'
               AND referenced_table_name='residential_towns') THEN
  ALTER TABLE voters ADD CONSTRAINT fk_voter_residential_town
   FOREIGN KEY(residential_town_id) REFERENCES residential_towns(id);
 END IF;
 INSERT INTO residential_towns(name,residential_region_id,district_id)
 SELECT TRIM(REGEXP_REPLACE(v.town,'[[:space:]]+',' ')),v.residential_region_id,v.residential_district_id
 FROM voters v WHERE v.town IS NOT NULL AND TRIM(REGEXP_REPLACE(v.town,'[[:space:]]+',' '))<>''
 GROUP BY TRIM(REGEXP_REPLACE(v.town,'[[:space:]]+',' ')),v.residential_region_id,v.residential_district_id
 ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(residential_towns.id);
 UPDATE voters v JOIN residential_towns t
  ON t.name=TRIM(REGEXP_REPLACE(v.town,'[[:space:]]+',' '))
   AND t.residential_region_id<=>v.residential_region_id AND t.district_id<=>v.residential_district_id
 SET v.residential_town_id=t.id,v.town=t.name
 WHERE v.residential_town_id IS NULL AND v.town IS NOT NULL AND TRIM(REGEXP_REPLACE(v.town,'[[:space:]]+',' '))<>'';
 SELECT 'Residential towns ready. Existing voter town names have been linked.' AS result;
END$$
CALL migration12_residential_towns()$$
DROP PROCEDURE IF EXISTS migration12_residential_towns$$
DELIMITER ;
