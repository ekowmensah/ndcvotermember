-- Read-only checks after migration 04. No sample records or updates.
SELECT s.id AS system_id,s.station_code AS official_polling_station_id,
 s.name,c.name AS constituency,r.name AS region,
 COALESCE(b.name,'Branch not assigned') AS branch
FROM polling_stations s JOIN constituencies c ON c.id=s.constituency_id
JOIN regions r ON r.id=c.region_id
LEFT JOIN electoral_areas b ON b.id=s.electoral_area_id
ORDER BY r.name,c.name,s.name;

-- Outstanding real IDs to enter; migration leaves existing official IDs blank.
SELECT id,name FROM polling_stations WHERE station_code IS NULL;

-- Expected: no duplicate official IDs.
SELECT station_code,COUNT(*) FROM polling_stations WHERE station_code IS NOT NULL
GROUP BY station_code HAVING COUNT(*)>1;

SELECT v.voter_id,v.full_name,s.id AS station_system_id,
 s.station_code AS official_polling_station_id,s.name AS polling_station
FROM voters v JOIN polling_stations s ON s.id=v.polling_station_id
ORDER BY v.id DESC LIMIT 50;
