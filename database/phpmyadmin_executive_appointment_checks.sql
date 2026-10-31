-- After migration 21, use phpmyadmin_wing_executive_checks.sql instead (offices are separated by organization).
-- Read-only optional checks for phpMyAdmin AFTER migration 18.
-- These queries do not add, modify or delete records.
SELECT id,name,is_active FROM executive_position_titles ORDER BY display_order,name;
SELECT a.id,a.member_id,a.position_name_snapshot,a.organization_level,a.jurisdiction_snapshot,
 a.starts_on,a.expected_ends_on,a.ended_on,
 CASE WHEN a.cancelled_at IS NOT NULL THEN 'cancelled' WHEN a.ended_on IS NOT NULL THEN 'ended' WHEN a.starts_on>UTC_DATE() THEN 'scheduled' ELSE 'current' END AS service_state
FROM executive_appointments a ORDER BY a.id DESC;
-- Should return zero rows: multiple open holders of one office.
SELECT position_id,organization_level,jurisdiction_id,COUNT(*) AS holders
FROM executive_appointments WHERE ended_on IS NULL AND cancelled_at IS NULL
GROUP BY position_id,organization_level,jurisdiction_id HAVING COUNT(*)>1;
-- Should return zero rows: open appointments have lost their member/person link.
SELECT id,member_id,voter_record_id FROM executive_appointments
WHERE ended_on IS NULL AND cancelled_at IS NULL AND (member_id IS NULL OR voter_record_id IS NULL);
-- Review flags: these need human follow-up, not automatic deletion.
SELECT a.id,v.full_name,m.status,v.is_deceased,a.expected_ends_on
FROM executive_appointments a JOIN members m ON m.id=a.member_id JOIN voters v ON v.id=a.voter_record_id
WHERE a.ended_on IS NULL AND a.cancelled_at IS NULL AND (m.status<>'active' OR v.is_deceased=1 OR a.expected_ends_on<UTC_DATE());
