-- Read-only checks after migrations 03 through 05. No demo data or changes.
SELECT 'Regions' AS location_type,id,name,shortname,created_at,updated_at FROM regions
UNION ALL SELECT 'Constituencies',id,name,shortname,created_at,updated_at FROM constituencies
UNION ALL SELECT 'Branches',id,name,NULL,created_at,updated_at FROM electoral_areas
UNION ALL SELECT 'Polling stations',id,name,NULL,created_at,updated_at FROM polling_stations
ORDER BY location_type,name;

-- All duplicate/missing-shortname queries below should return no rows.
SELECT shortname,COUNT(*) FROM regions GROUP BY shortname HAVING COUNT(*)>1;
SELECT region_id,shortname,COUNT(*) FROM constituencies GROUP BY region_id,shortname HAVING COUNT(*)>1;
SELECT 'region' AS location_type,id FROM regions WHERE shortname IS NULL OR TRIM(shortname)=''
UNION ALL SELECT 'constituency',id FROM constituencies WHERE shortname IS NULL OR TRIM(shortname)='';

SELECT a.action_code,a.entity_type,a.entity_id,u.name AS staff,a.occurred_at,a.change_summary
FROM audit_events a LEFT JOIN app_users u ON u.id=a.actor_user_id
WHERE a.action_code IN ('location.created','location.updated','station.branch_assigned')
ORDER BY a.id DESC LIMIT 25;
