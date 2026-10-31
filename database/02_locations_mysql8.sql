-- Run ONCE in phpMyAdmin, with the existing application database selected.
-- Preserves all voters, members and staff. No sample data.
CREATE TABLE regions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(160) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE constituencies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 region_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(180) NOT NULL,
 UNIQUE KEY uq_constituency(region_id,name), UNIQUE KEY uq_constituency_parent(id,region_id),
 FOREIGN KEY(region_id) REFERENCES regions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE electoral_areas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 constituency_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(200) NOT NULL,
 UNIQUE KEY uq_branch(constituency_id,name), UNIQUE KEY uq_branch_parent(id,constituency_id),
 FOREIGN KEY(constituency_id) REFERENCES constituencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE polling_stations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 constituency_id BIGINT UNSIGNED NOT NULL,
 electoral_area_id BIGINT UNSIGNED NULL,
 name VARCHAR(250) NOT NULL,
 UNIQUE KEY uq_station(constituency_id,name), UNIQUE KEY uq_station_parent(id,constituency_id),
 FOREIGN KEY(constituency_id) REFERENCES constituencies(id),
 FOREIGN KEY(electoral_area_id,constituency_id) REFERENCES electoral_areas(id,constituency_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE voters
 ADD constituency_id BIGINT UNSIGNED NULL,
 ADD electoral_area_id BIGINT UNSIGNED NULL,
 ADD polling_station_id BIGINT UNSIGNED NULL,
 ADD region_id BIGINT UNSIGNED NULL,
 ADD FOREIGN KEY(region_id) REFERENCES regions(id),
 ADD FOREIGN KEY(constituency_id,region_id) REFERENCES constituencies(id,region_id),
 ADD FOREIGN KEY(electoral_area_id,constituency_id) REFERENCES electoral_areas(id,constituency_id),
 ADD FOREIGN KEY(polling_station_id,constituency_id) REFERENCES polling_stations(id,constituency_id),
 ADD CONSTRAINT ck_voter_location_parents CHECK (
  (constituency_id IS NULL OR region_id IS NOT NULL) AND
  (electoral_area_id IS NULL OR constituency_id IS NOT NULL) AND
  (polling_station_id IS NULL OR constituency_id IS NOT NULL)
 );
-- Existing text locations remain visible. Edit a voter to link it to the new directory.
