-- Phase 1: read-only checks in phpMyAdmin. No demo rows or updates.
SELECT 'Voters' AS record_type,COUNT(*) AS records FROM voters
UNION ALL SELECT 'Members',COUNT(*) FROM members
UNION ALL SELECT 'Uploads',COUNT(*) FROM voter_imports;

-- Immediately after the reset, the three counts above should all be zero.
-- The existing administrator login is the only retained application record.
SELECT name,email,role,is_active FROM app_users;

SELECT m.member_number,v.voter_id,v.full_name,m.status,m.joined_on
FROM members m JOIN voters v ON v.id=m.voter_record_id
ORDER BY m.id DESC;

-- Expected: no duplicate voter IDs or more than one membership per voter.
SELECT voter_id,COUNT(*) FROM voters GROUP BY voter_id HAVING COUNT(*)>1;
SELECT voter_record_id,COUNT(*) FROM members GROUP BY voter_record_id HAVING COUNT(*)>1;
