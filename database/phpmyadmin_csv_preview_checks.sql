-- Read-only manual checks after all existing migrations.
-- Run this count query BEFORE previewing a file; note the results.
-- Run it again AFTER preview/refresh/cancel. Counts should be unchanged.
-- Compare values manually: phpMyAdmin may not retain MySQL variables between requests.
SELECT 'Voters' AS record_type,COUNT(*) AS records FROM voters
UNION ALL SELECT 'Members',COUNT(*) FROM members
UNION ALL SELECT 'Polling stations',COUNT(*) FROM polling_stations
UNION ALL SELECT 'Residential towns',COUNT(*) FROM residential_towns
UNION ALL SELECT 'Voter upload history',COUNT(*) FROM voter_imports
UNION ALL SELECT 'Audit events',COUNT(*) FROM audit_events;

-- After EXPLICIT confirmation, compare reviewed counts with these history rows.
SELECT filename,added_count,updated_count,skipped_count,created_at
FROM voter_imports ORDER BY id DESC LIMIT 10;

SELECT occurred_at,action_code,entity_type,entity_id,
 JSON_EXTRACT(change_summary,'$.reviewed') AS reviewed,
 JSON_EXTRACT(change_summary,'$.added') AS added,
 JSON_EXTRACT(change_summary,'$.updated') AS updated,
 JSON_EXTRACT(change_summary,'$.skipped') AS skipped,
 JSON_EXTRACT(change_summary,'$.members_skipped') AS protected_members
FROM audit_events
WHERE action_code IN ('voters.uploaded','polling_stations.uploaded')
ORDER BY id DESC LIMIT 10;

-- These should return no duplicates, including after re-submitting a confirmed form.
SELECT voter_id,COUNT(*) AS duplicate_count FROM voters GROUP BY voter_id HAVING COUNT(*)>1;
SELECT station_code,COUNT(*) AS duplicate_count FROM polling_stations
WHERE station_code IS NOT NULL GROUP BY station_code HAVING COUNT(*)>1;