-- Read-only checks after migrations 09 and 10; street addresses are not displayed.
SELECT v.id,v.voter_id,v.full_name,v.town,
 rr.name AS residential_region,d.name AS residential_district,
 er.name AS electoral_region,c.name AS constituency
FROM voters v LEFT JOIN residential_regions rr ON rr.id=v.residential_region_id
LEFT JOIN districts d ON d.id=v.residential_district_id
LEFT JOIN regions er ON er.id=v.region_id
LEFT JOIN constituencies c ON c.id=v.constituency_id
ORDER BY rr.name,d.name,v.town,v.full_name LIMIT 100;

-- Expected: no mismatches or duplicate districts within one region.
SELECT v.id,v.voter_id FROM voters v JOIN districts d ON d.id=v.residential_district_id
WHERE v.residential_region_id IS NULL OR v.residential_region_id<>d.region_id;
SELECT region_id,name,COUNT(*) FROM districts GROUP BY region_id,name HAVING COUNT(*)>1;

SELECT d.id,d.name,r.name AS region FROM districts d
JOIN residential_regions r ON r.id=d.region_id ORDER BY r.name,d.name;

-- Both residential parent FKs must point to residential_regions.
SELECT table_name,constraint_name,referenced_table_name
FROM information_schema.key_column_usage WHERE table_schema=DATABASE()
 AND ((table_name='districts' AND column_name='region_id')
   OR (table_name='voters' AND column_name='residential_region_id'))
 AND referenced_table_name IS NOT NULL;
