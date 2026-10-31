-- Import COMPLETE file in phpMyAdmin AFTER migration 15.
-- One title, multiple applicable levels. Original executive_positions is retained.
CREATE TABLE IF NOT EXISTS executive_position_titles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(160) NOT NULL UNIQUE,
 description VARCHAR(1000) NULL,
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_exec_title_creator FOREIGN KEY(created_by_user_id) REFERENCES app_users(id),
 CONSTRAINT ck_exec_title_active CHECK(is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS executive_position_levels (
 position_id BIGINT UNSIGNED NOT NULL,
 organization_level ENUM('national','region','constituency','branch','polling_station') NOT NULL,
 PRIMARY KEY(position_id,organization_level),
 CONSTRAINT fk_exec_level_title FOREIGN KEY(position_id) REFERENCES executive_position_titles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS executive_position_legacy_map (
 legacy_position_id BIGINT UNSIGNED PRIMARY KEY,
 position_id BIGINT UNSIGNED NOT NULL,
 CONSTRAINT fk_exec_legacy_title FOREIGN KEY(position_id) REFERENCES executive_position_titles(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS app_schema_migrations (
 migration_key VARCHAR(100) PRIMARY KEY,
 completed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB;
DELIMITER $$
DROP PROCEDURE IF EXISTS migration16_executive_levels$$
CREATE PROCEDURE migration16_executive_levels()
BEGIN
 DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
 START TRANSACTION;
 IF NOT EXISTS(SELECT 1 FROM app_schema_migrations WHERE migration_key='16_executive_position_levels') THEN
  -- Preserve the earliest ID/title/description/creator per equivalent name.
  -- Active if any old level was active; use the lowest display order.
  INSERT INTO executive_position_titles(id,name,description,display_order,is_active,created_by_user_id,created_at,updated_at)
  SELECT p.id,p.name,p.description,g.sort_order,g.active,p.created_by_user_id,p.created_at,CURRENT_TIMESTAMP(6)
  FROM executive_positions p JOIN
   (SELECT name,MIN(id) AS canonical_id,MIN(display_order) AS sort_order,MAX(is_active) AS active FROM executive_positions GROUP BY name) g
   ON p.id=g.canonical_id;
  INSERT INTO executive_position_legacy_map(legacy_position_id,position_id)
  SELECT p.id,t.id FROM executive_positions p JOIN executive_position_titles t ON t.name=p.name;
  INSERT INTO executive_position_levels(position_id,organization_level)
  SELECT DISTINCT m.position_id,p.organization_level FROM executive_positions p JOIN executive_position_legacy_map m ON m.legacy_position_id=p.id;
  INSERT INTO app_schema_migrations(migration_key) VALUES('16_executive_position_levels');
 END IF;
 COMMIT;
 SELECT 'Positions now support multiple levels. Original rows retained; review applicable levels, including Polling station.' AS result;
END$$
CALL migration16_executive_levels()$$
DROP PROCEDURE IF EXISTS migration16_executive_levels$$
DELIMITER ;
