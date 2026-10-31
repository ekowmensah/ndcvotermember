-- After migration 21, use phpmyadmin_wing_executive_checks.sql instead (offices are separated by organization).
-- Read-only checks for phpMyAdmin AFTER migration 18. Run manually.
SELECT id,member_name_snapshot,position_name_snapshot,organization_level,jurisdiction_snapshot,
 starts_on,expected_ends_on,ended_on,end_reason,cancelled_at,replaces_appointment_id,open_office
FROM executive_appointments ORDER BY id DESC;
-- Must return zero rows: more than one open holder for one office.
SELECT position_id,organization_level,jurisdiction_id,COUNT(*) AS holders
FROM executive_appointments WHERE ended_on IS NULL AND cancelled_at IS NULL
GROUP BY position_id,organization_level,jurisdiction_id HAVING COUNT(*)>1;
-- Must return zero rows: cancelled entries incorrectly retain the office or actual service end.
SELECT id FROM executive_appointments WHERE cancelled_at IS NOT NULL AND (open_office IS NOT NULL OR ended_on IS NOT NULL);
-- Must return zero rows: overlapping, unclosed or mismatched replacement chains.
SELECT successor.id,successor.replaces_appointment_id
FROM executive_appointments successor JOIN executive_appointments previous ON previous.id=successor.replaces_appointment_id
WHERE previous.ended_on IS NULL OR previous.cancelled_at IS NOT NULL
 OR successor.starts_on<previous.ended_on OR successor.position_id<>previous.position_id
 OR successor.organization_level<>previous.organization_level OR successor.jurisdiction_id<>previous.jurisdiction_id;
-- Must return zero rows: overlapping non-cancelled terms in an office.
SELECT a.id AS first_term,b.id AS second_term
FROM executive_appointments a JOIN executive_appointments b
 ON a.id<b.id AND a.position_id=b.position_id AND a.organization_level=b.organization_level AND a.jurisdiction_id=b.jurisdiction_id
WHERE a.cancelled_at IS NULL AND b.cancelled_at IS NULL
 AND a.starts_on<=COALESCE(b.ended_on,'9999-12-31') AND b.starts_on<=COALESCE(a.ended_on,'9999-12-31')
 AND NOT (COALESCE(b.replaces_appointment_id,0)=a.id AND a.ended_on IS NOT NULL AND a.ended_on=b.starts_on)
 AND NOT (COALESCE(a.replaces_appointment_id,0)=b.id AND b.ended_on IS NOT NULL AND b.ended_on=a.starts_on);

