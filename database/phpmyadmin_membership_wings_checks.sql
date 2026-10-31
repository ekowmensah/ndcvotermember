-- Read-only checks after migration 20. Run manually in phpMyAdmin.
SELECT wing_code,
 SUM(ended_on IS NULL) AS enrolled,
 SUM(ended_on IS NOT NULL) AS ended_enrollments
FROM member_wing_enrollments GROUP BY wing_code;

-- Must return zero rows: multiple open enrollments for one person/wing.
SELECT voter_record_id,wing_code,COUNT(*) AS open_count
FROM member_wing_enrollments
WHERE ended_on IS NULL AND voter_record_id IS NOT NULL
GROUP BY voter_record_id,wing_code HAVING COUNT(*)>1;

-- Must return zero rows: active enrollment without its party membership.
SELECT w.id,w.wing_code,w.member_id,w.voter_record_id
FROM member_wing_enrollments w LEFT JOIN members m ON m.id=w.member_id
WHERE w.ended_on IS NULL AND (m.id IS NULL OR m.voter_record_id<>w.voter_record_id OR w.voter_record_id IS NULL);

-- Review any existing Women's Wing enrollments that do not meet the Female rule.
-- This is read-only: correct the member or end the enrollment in the application.
SELECT w.id,w.member_id,v.full_name,v.gender
FROM member_wing_enrollments w JOIN voters v ON v.id=w.voter_record_id
WHERE w.wing_code='women' AND w.ended_on IS NULL AND v.gender<>'female';

-- Existing Youth Wing enrollments needing age review. No automatic endings.
SELECT w.id,w.member_id,v.full_name,
 TIMESTAMPDIFF(YEAR,v.date_of_birth,UTC_DATE()) AS age
FROM member_wing_enrollments w JOIN voters v ON v.id=w.voter_record_id
WHERE w.wing_code='youth' AND w.ended_on IS NULL
 AND (v.date_of_birth IS NULL OR v.date_of_birth>UTC_DATE()
 OR TIMESTAMPDIFF(YEAR,v.date_of_birth,UTC_DATE()) NOT BETWEEN 18 AND 49);

-- Inspect your own enrollment and ending audit records, newest first.
SELECT id,action_code,entity_id,actor_user_id,occurred_at,change_summary
FROM audit_events WHERE entity_type='wing_enrollment'
ORDER BY id DESC LIMIT 50;
