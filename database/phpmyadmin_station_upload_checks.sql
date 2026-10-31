-- Read-only checks in phpMyAdmin after an upload; requires location migrations 02–05.
-- Set to the actual selected constituency's internal ID; NULL lists all.
SET @constituency_id = NULL;

SELECT s.id AS system_id,s.station_code AS official_station_id,s.name,
 c.name AS constituency,r.name AS region,s.electoral_area_id,b.name AS branch,
 (SELECT COUNT(*) FROM voters v WHERE v.polling_station_id=s.id) AS linked_voters
FROM polling_stations s JOIN constituencies c ON c.id=s.constituency_id
JOIN regions r ON r.id=c.region_id
LEFT JOIN electoral_areas b ON b.id=s.electoral_area_id
WHERE @constituency_id IS NULL OR s.constituency_id=@constituency_id
ORDER BY s.id DESC LIMIT 100;

-- No duplicates should be returned.
SELECT station_code,COUNT(*) AS duplicates FROM polling_stations
WHERE station_code IS NOT NULL GROUP BY station_code HAVING COUNT(*)>1;
SELECT constituency_id,name,COUNT(*) AS duplicates FROM polling_stations
GROUP BY constituency_id,name HAVING COUNT(*)>1;

-- One creation audit per newly created station. Re-uploading exact matches adds no creation events.
SELECT s.id,s.station_code,s.name,COUNT(a.id) AS creation_events
FROM polling_stations s JOIN audit_events a
 ON a.entity_type='polling_station' AND a.entity_id=s.id AND a.action_code='location.created'
WHERE (@constituency_id IS NULL OR s.constituency_id=@constituency_id)
 AND JSON_UNQUOTE(JSON_EXTRACT(a.change_summary,'$.origin'))='csv'
GROUP BY s.id,s.station_code,s.name ORDER BY s.id DESC LIMIT 100;

SELECT a.occurred_at,u.name AS uploaded_by,
 JSON_UNQUOTE(JSON_EXTRACT(a.change_summary,'$.filename')) AS filename,
 JSON_EXTRACT(a.change_summary,'$.added') AS added,
 JSON_EXTRACT(a.change_summary,'$.skipped') AS skipped,
 a.entity_id AS constituency_id
FROM audit_events a LEFT JOIN app_users u ON u.id=a.actor_user_id
WHERE a.action_code='polling_stations.uploaded'
 AND (@constituency_id IS NULL OR a.entity_id=@constituency_id)
ORDER BY a.id DESC LIMIT 10;

-- Compare counts/links before and after an intentionally invalid file: they must not change.
-- Successful all-skipped uploads produce a summary event but no new station rows.