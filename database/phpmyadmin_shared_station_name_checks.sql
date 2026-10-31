-- Read-only checks AFTER migration 22. Run manually in phpMyAdmin.
-- Must return zero rows: official IDs remain globally unique.
SELECT station_code,COUNT(*) AS stations
FROM polling_stations WHERE station_code IS NOT NULL
GROUP BY station_code HAVING COUNT(*)>1;

-- Shared names are now allowed: rows returned here are NOT errors.
SELECT constituency_id,name,COUNT(*) AS stations,
 GROUP_CONCAT(station_code ORDER BY station_code SEPARATOR ', ') AS official_ids
FROM polling_stations GROUP BY constituency_id,name HAVING COUNT(*)>1;

-- Must return zero rows: no remaining name-only/per-constituency name unique keys.
SELECT index_name,GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columns_in_key
FROM information_schema.statistics
WHERE table_schema=DATABASE() AND table_name='polling_stations' AND non_unique=0 AND index_name<>'PRIMARY'
GROUP BY index_name HAVING columns_in_key IN ('name','constituency_id,name','name,constituency_id');

SHOW INDEX FROM polling_stations;

-- Must return zero rows: linked voters retain their station and constituency.
SELECT v.id,v.polling_station_id,v.constituency_id
FROM voters v LEFT JOIN polling_stations s ON s.id=v.polling_station_id
WHERE v.polling_station_id IS NOT NULL AND (s.id IS NULL OR v.constituency_id<>s.constituency_id);
