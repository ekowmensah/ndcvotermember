-- Read-only checks AFTER migration 21. Run manually in phpMyAdmin.
-- Use this file in place of the older appointment/lifecycle office-conflict checks.
SELECT id,organization_scope,wing_enrollment_id,member_name_snapshot,position_name_snapshot,
 organization_level,jurisdiction_snapshot,starts_on,ended_on,cancelled_at,replaces_appointment_id
FROM executive_appointments ORDER BY id DESC;

-- Must return zero rows: duplicate open offices within the SAME organization.
SELECT organization_scope,position_id,organization_level,jurisdiction_id,COUNT(*) AS holders
FROM executive_appointments WHERE ended_on IS NULL AND cancelled_at IS NULL
GROUP BY organization_scope,position_id,organization_level,jurisdiction_id HAVING COUNT(*)>1;

-- Must return zero rows: invalid replacement chain, including organization changes.
SELECT successor.id,successor.replaces_appointment_id
FROM executive_appointments successor JOIN executive_appointments previous ON previous.id=successor.replaces_appointment_id
WHERE previous.ended_on IS NULL OR previous.cancelled_at IS NOT NULL
 OR successor.starts_on<previous.ended_on OR successor.position_id<>previous.position_id
 OR successor.organization_level<>previous.organization_level OR successor.jurisdiction_id<>previous.jurisdiction_id
 OR successor.organization_scope<>previous.organization_scope;

-- Must return zero rows: overlapping non-cancelled terms in the SAME office.
SELECT a.id AS first_term,b.id AS second_term,a.organization_scope
FROM executive_appointments a JOIN executive_appointments b
 ON a.id<b.id AND a.organization_scope=b.organization_scope AND a.position_id=b.position_id
 AND a.organization_level=b.organization_level AND a.jurisdiction_id=b.jurisdiction_id
WHERE a.cancelled_at IS NULL AND b.cancelled_at IS NULL
 AND a.starts_on<=COALESCE(b.ended_on,'9999-12-31') AND b.starts_on<=COALESCE(a.ended_on,'9999-12-31')
 AND NOT (COALESCE(b.replaces_appointment_id,0)=a.id AND a.ended_on=b.starts_on)
 AND NOT (COALESCE(a.replaces_appointment_id,0)=b.id AND b.ended_on=a.starts_on);

-- Must return zero rows: wing service outside its recorded wing participation period.
SELECT a.id,a.organization_scope,a.wing_enrollment_id
FROM executive_appointments a LEFT JOIN member_wing_enrollments w ON w.id=a.wing_enrollment_id
WHERE a.organization_scope<>'main' AND a.cancelled_at IS NULL
 AND (w.id IS NULL OR w.wing_code<>a.organization_scope OR a.starts_on<w.joined_on
 OR (w.ended_on IS NOT NULL AND (a.ended_on IS NULL OR a.ended_on>w.ended_on)));

-- Must return zero rows: wrong member/person enrollment link while both links remain present.
SELECT a.id,a.wing_enrollment_id
FROM executive_appointments a JOIN member_wing_enrollments w ON w.id=a.wing_enrollment_id
WHERE a.organization_scope<>'main'
 AND ((a.member_id IS NOT NULL AND w.member_id IS NOT NULL AND a.member_id<>w.member_id)
 OR (a.voter_record_id IS NOT NULL AND w.voter_record_id IS NOT NULL AND a.voter_record_id<>w.voter_record_id));

-- Must return zero rows: cancelled entries retaining office reservation/actual service end.
SELECT id FROM executive_appointments WHERE cancelled_at IS NOT NULL AND (open_office IS NOT NULL OR ended_on IS NOT NULL);

-- Inspect the expanded unique office key.
SHOW INDEX FROM executive_appointments WHERE Key_name='uq_exec_open_office';
