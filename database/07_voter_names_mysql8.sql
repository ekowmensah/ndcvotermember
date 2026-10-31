-- Run ONCE in phpMyAdmin. Preserves existing full names without guessing name parts.
ALTER TABLE voters
 ADD first_name VARCHAR(120) NULL AFTER voter_id,
 ADD last_name VARCHAR(120) NULL AFTER first_name;
-- Existing full_name-only voters can have their first/last names completed on edit.
