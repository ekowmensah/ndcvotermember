-- Run ONCE in phpMyAdmin after migrations 02 and 03.
-- The official polling station ID is separate from the internal numeric ID.
-- Existing stations keep their records/links; official IDs remain NULL until entered.
-- Text storage preserves leading zeros. No official IDs are fabricated.
ALTER TABLE polling_stations
 ADD station_code VARCHAR(64) NULL AFTER shortname,
 ADD UNIQUE KEY uq_official_polling_station_id(station_code);
