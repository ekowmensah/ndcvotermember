-- Read-only checks after migration 07. No sample records or updates.
SELECT id,voter_id,first_name,last_name,full_name FROM voters ORDER BY id DESC LIMIT 50;

-- Expected: no mismatches for voters with both name parts saved.
SELECT id,voter_id,first_name,last_name,full_name FROM voters
WHERE first_name IS NOT NULL AND last_name IS NOT NULL
 AND BINARY full_name<>BINARY CONCAT(first_name,' ',last_name);

-- Run before opening enrollment, after cancelling, then after Save membership.
-- Opening/cancelling should leave counts unchanged; saving adds one membership.
SELECT COUNT(*) AS voter_count FROM voters;
SELECT COUNT(*) AS member_count FROM members;
SELECT m.id,m.member_number,v.voter_id,v.full_name,m.joined_on,m.status
FROM members m JOIN voters v ON v.id=m.voter_record_id ORDER BY m.id DESC LIMIT 25;

-- Expected: no duplicate memberships per voter.
SELECT voter_record_id,COUNT(*) FROM members GROUP BY voter_record_id HAVING COUNT(*)>1;
