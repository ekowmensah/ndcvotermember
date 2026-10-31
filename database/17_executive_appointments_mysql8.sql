-- Executive Phase 2. Import COMPLETE file in phpMyAdmin AFTER migration 16.
-- No sample records. Reimport preserves appointments.
CREATE TABLE IF NOT EXISTS executive_appointments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 member_id BIGINT UNSIGNED NULL,
 voter_record_id BIGINT UNSIGNED NULL,
 position_id BIGINT UNSIGNED NOT NULL,
 organization_level ENUM('national','region','constituency','branch','polling_station') NOT NULL,
 jurisdiction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 region_id BIGINT UNSIGNED NULL,
 constituency_id BIGINT UNSIGNED NULL,
 branch_id BIGINT UNSIGNED NULL,
 polling_station_id BIGINT UNSIGNED NULL,
 member_name_snapshot VARCHAR(250) NOT NULL,
 member_number_snapshot VARCHAR(32) NOT NULL,
 party_id_snapshot VARCHAR(64) NULL,
 position_name_snapshot VARCHAR(160) NOT NULL,
 jurisdiction_snapshot VARCHAR(1000) NOT NULL,
 appointment_type ENUM('elected','appointed','acting') NOT NULL,
 starts_on DATE NOT NULL,
 expected_ends_on DATE NULL,
 ended_on DATE NULL,
 end_reason VARCHAR(100) NULL,
 notes VARCHAR(2000) NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 open_office TINYINT GENERATED ALWAYS AS (IF(ended_on IS NULL,1,NULL)) STORED,
 UNIQUE KEY uq_exec_open_office(position_id,organization_level,jurisdiction_id,open_office),
 KEY ix_exec_member(member_id,starts_on,id),
 KEY ix_exec_person(voter_record_id),
 KEY ix_exec_directory(ended_on,organization_level,starts_on,id),
 CONSTRAINT fk_exec_appointment_member FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE SET NULL,
 CONSTRAINT fk_exec_appointment_person FOREIGN KEY(voter_record_id) REFERENCES voters(id) ON DELETE SET NULL,
 CONSTRAINT fk_exec_appointment_position FOREIGN KEY(position_id) REFERENCES executive_position_titles(id),
 CONSTRAINT fk_exec_appointment_creator FOREIGN KEY(created_by_user_id) REFERENCES app_users(id),
 CONSTRAINT ck_exec_appointment_dates CHECK((expected_ends_on IS NULL OR expected_ends_on>=starts_on) AND (ended_on IS NULL OR ended_on>=starts_on)),
 CONSTRAINT ck_exec_appointment_scope CHECK(
  (organization_level='national' AND jurisdiction_id=0 AND region_id IS NULL AND constituency_id IS NULL AND branch_id IS NULL AND polling_station_id IS NULL) OR
  (organization_level='region' AND jurisdiction_id=region_id AND region_id IS NOT NULL AND constituency_id IS NULL AND branch_id IS NULL AND polling_station_id IS NULL) OR
  (organization_level='constituency' AND jurisdiction_id=constituency_id AND region_id IS NOT NULL AND constituency_id IS NOT NULL AND branch_id IS NULL AND polling_station_id IS NULL) OR
  (organization_level='branch' AND jurisdiction_id=branch_id AND region_id IS NOT NULL AND constituency_id IS NOT NULL AND branch_id IS NOT NULL AND polling_station_id IS NULL) OR
  (organization_level='polling_station' AND jurisdiction_id=polling_station_id AND region_id IS NOT NULL AND constituency_id IS NOT NULL AND branch_id IS NULL AND polling_station_id IS NOT NULL)
 )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SELECT 'Executive appointments ready. No appointments were added.' AS result;
