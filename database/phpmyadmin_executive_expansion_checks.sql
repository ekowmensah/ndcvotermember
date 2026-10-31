-- Optional READ-ONLY checks after migration 19. Run manually in phpMyAdmin.
SELECT t.id,t.name,t.starts_on,t.ends_on,t.is_active,COUNT(a.id) AS assigned_records
FROM executive_leadership_terms t LEFT JOIN executive_appointments a ON a.leadership_term_id=t.id
GROUP BY t.id,t.name,t.starts_on,t.ends_on,t.is_active ORDER BY t.starts_on DESC;
-- Must return zero rows: assigned starts outside their administration.
SELECT a.id,a.starts_on,t.name,t.starts_on AS term_start,t.ends_on AS term_end
FROM executive_appointments a JOIN executive_leadership_terms t ON t.id=a.leadership_term_id
WHERE a.starts_on<t.starts_on OR a.starts_on>t.ends_on;
-- Must return zero rows: recognized entries without completed actual service.
SELECT r.id,r.appointment_id FROM executive_recognitions r JOIN executive_appointments a ON a.id=r.appointment_id
WHERE a.ended_on IS NULL OR a.cancelled_at IS NOT NULL;
SELECT is_published,COUNT(*) AS recognition_entries FROM executive_recognitions GROUP BY is_published;
-- Confirm service identity/dates and snapshots have been retained.
SELECT id,member_name_snapshot,position_name_snapshot,starts_on,ended_on,
 leadership_term_id,leadership_term_name_snapshot,replaces_appointment_id FROM executive_appointments ORDER BY id DESC;
