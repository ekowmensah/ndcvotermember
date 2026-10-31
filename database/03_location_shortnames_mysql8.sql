-- Run ONCE in phpMyAdmin after 02_locations_mysql8.sql. No records are deleted.
-- Existing locations get unique temporary shortnames based on their actual IDs.
-- Replace these with your preferred initials using Locations > Edit.
ALTER TABLE regions ADD shortname VARCHAR(24) NULL,
 ADD created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 ADD updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
ALTER TABLE constituencies ADD shortname VARCHAR(24) NULL,
 ADD created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 ADD updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
ALTER TABLE electoral_areas ADD shortname VARCHAR(24) NULL,
 ADD created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 ADD updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
ALTER TABLE polling_stations ADD shortname VARCHAR(24) NULL,
 ADD created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 ADD updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
UPDATE regions SET shortname=CONCAT('R-',id);
UPDATE constituencies SET shortname=CONCAT('C-',id);
UPDATE electoral_areas SET shortname=CONCAT('EA-',id);
UPDATE polling_stations SET shortname=CONCAT('PS-',id);
ALTER TABLE regions MODIFY shortname VARCHAR(24) NOT NULL, ADD UNIQUE KEY uq_region_shortname(shortname);
ALTER TABLE constituencies MODIFY shortname VARCHAR(24) NOT NULL, ADD UNIQUE KEY uq_constituency_shortname(region_id,shortname);
ALTER TABLE electoral_areas MODIFY shortname VARCHAR(24) NOT NULL, ADD UNIQUE KEY uq_branch_shortname(constituency_id,shortname);
ALTER TABLE polling_stations MODIFY shortname VARCHAR(24) NOT NULL, ADD UNIQUE KEY uq_station_shortname(constituency_id,shortname);
