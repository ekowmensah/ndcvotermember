-- Read-only checks after migration 12. No sample data or updates.
SELECT t.id,t.name,r.name AS residential_region,d.name AS district,COUNT(v.id) AS voters
FROM residential_towns t LEFT JOIN residential_regions r ON r.id=t.residential_region_id
LEFT JOIN districts d ON d.id=t.district_id LEFT JOIN voters v ON v.residential_town_id=t.id
GROUP BY t.id,t.name,r.name,d.name ORDER BY t.name,r.name,d.name;

-- Expected: no duplicates within the same residential region/district scope.
SELECT name,region_scope,district_scope,COUNT(*) FROM residential_towns
GROUP BY name,region_scope,district_scope HAVING COUNT(*)>1;

-- Expected: no voter/town location mismatches and no unlinked nonblank town names.
SELECT v.id,v.voter_id FROM voters v JOIN residential_towns t ON t.id=v.residential_town_id
WHERE NOT(t.residential_region_id<=>v.residential_region_id)
 OR NOT(t.district_id<=>v.residential_district_id) OR t.name<>v.town;
SELECT id,voter_id,town FROM voters WHERE town IS NOT NULL
 AND TRIM(town)<>'' AND residential_town_id IS NULL;
