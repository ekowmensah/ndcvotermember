-- Read-only checks AFTER importing 02_locations_mysql8.sql once.
SELECT s.id,s.name AS polling_station,c.name AS constituency,r.name AS region,
 COALESCE(b.name,'Branch not assigned') AS branch
FROM polling_stations s JOIN constituencies c ON c.id=s.constituency_id
JOIN regions r ON r.id=c.region_id
LEFT JOIN electoral_areas b ON b.id=s.electoral_area_id
ORDER BY r.name,c.name,s.name;

-- These mismatch queries should return no rows.
SELECT s.id,s.name FROM polling_stations s
JOIN electoral_areas b ON b.id=s.electoral_area_id
WHERE s.constituency_id<>b.constituency_id;
SELECT v.id,v.voter_id FROM voters v
JOIN constituencies c ON c.id=v.constituency_id
WHERE v.region_id IS NULL OR v.region_id<>c.region_id;
SELECT v.id,v.voter_id FROM voters v
JOIN polling_stations s ON s.id=v.polling_station_id
WHERE v.constituency_id IS NULL OR v.constituency_id<>s.constituency_id;
