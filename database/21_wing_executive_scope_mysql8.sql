-- Import COMPLETE file in phpMyAdmin AFTER migration 20 and executive migrations 17–19.
-- Existing appointments remain Main Party appointments. No appointments are created.
DELIMITER $$
DROP PROCEDURE IF EXISTS migration21_wing_executives$$
CREATE PROCEDURE migration21_wing_executives()
BEGIN
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name='organization_scope') THEN
  ALTER TABLE executive_appointments ADD organization_scope ENUM('main','youth','women') NOT NULL DEFAULT 'main';
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name='wing_enrollment_id') THEN
  ALTER TABLE executive_appointments ADD wing_enrollment_id BIGINT UNSIGNED NULL,
   ADD CONSTRAINT fk_exec_wing_enrollment FOREIGN KEY(wing_enrollment_id) REFERENCES member_wing_enrollments(id);
 END IF;
 -- The legacy unique key also supports the position foreign key. Preserve its
 -- supporting index before replacing it with an organization-first unique key.
 IF NOT EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND index_name='ix_exec_position') THEN
  ALTER TABLE executive_appointments ADD KEY ix_exec_position(position_id);
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND index_name='uq_exec_open_office') THEN
  ALTER TABLE executive_appointments ADD UNIQUE KEY uq_exec_open_office(organization_scope,position_id,organization_level,jurisdiction_id,open_office);
 ELSEIF (SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND index_name='uq_exec_open_office')<>'organization_scope,position_id,organization_level,jurisdiction_id,open_office' THEN
  ALTER TABLE executive_appointments DROP INDEX uq_exec_open_office,
   ADD UNIQUE KEY uq_exec_open_office(organization_scope,position_id,organization_level,jurisdiction_id,open_office);
 END IF;
 SELECT 'Wing executive scope ready. Existing appointments retained as Main Party.' AS result;
END$$
CALL migration21_wing_executives()$$
DROP PROCEDURE IF EXISTS migration21_wing_executives$$
DELIMITER ;
