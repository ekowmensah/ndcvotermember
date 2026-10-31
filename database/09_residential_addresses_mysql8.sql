-- Run ONCE in phpMyAdmin after migration 02 (regions must exist).
-- Preserves existing encrypted street addresses and all electoral locations.
CREATE TABLE districts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 region_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(180) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_district_name(region_id,name), UNIQUE KEY uq_district_region(id,region_id),
 CONSTRAINT fk_district_region FOREIGN KEY(region_id) REFERENCES regions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE voters
 ADD town VARCHAR(180) NULL,
 ADD residential_region_id BIGINT UNSIGNED NULL,
 ADD residential_district_id BIGINT UNSIGNED NULL,
 ADD KEY ix_voter_town(town),
 ADD KEY ix_voter_residential_region(residential_region_id),
 ADD CONSTRAINT fk_voter_residential_region FOREIGN KEY(residential_region_id) REFERENCES regions(id),
 ADD CONSTRAINT fk_voter_residential_district FOREIGN KEY(residential_district_id,residential_region_id) REFERENCES districts(id,region_id),
 ADD CONSTRAINT ck_residential_district_region CHECK(residential_district_id IS NULL OR residential_region_id IS NOT NULL);
