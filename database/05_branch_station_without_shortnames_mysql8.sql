-- Run ONCE in phpMyAdmin after migrations 02, 03 and 04.
-- Branches and polling stations do not use shortnames or initials.
-- Retain legacy column values for compatibility; no records or links are deleted.
-- New branch/station records leave these unused columns NULL.
ALTER TABLE electoral_areas MODIFY shortname VARCHAR(24) NULL DEFAULT NULL;
ALTER TABLE polling_stations MODIFY shortname VARCHAR(24) NULL DEFAULT NULL;
