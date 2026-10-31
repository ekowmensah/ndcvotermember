-- Manual read-only directory checks after migrations 01–12.
-- Set these to real IDs to compare directory counts and linked people; NULL means all.
SET @residential_region_id = NULL;
SET @district_id = NULL;
SET @constituency_id = NULL;
SET @branch_id = NULL;
SET @station_id = NULL;

-- People overview. Members are included in the voter total; these counts span all matching rows.
SELECT COUNT(*) AS voters,COUNT(m.id) AS members,
 COALESCE(SUM(m.id IS NULL),0) AS nonmembers,
 COALESCE(SUM(m.status='active'),0) AS active_members,
 COALESCE(SUM(m.status='inactive'),0) AS inactive_members,
 COALESCE(SUM(EXISTS(SELECT 1 FROM voter_photos p WHERE p.voter_record_id=v.id)),0) AS with_photo
FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id
LEFT JOIN polling_stations s ON s.id=v.polling_station_id
WHERE (@residential_region_id IS NULL OR v.residential_region_id=@residential_region_id)
 AND (@district_id IS NULL OR v.residential_district_id=@district_id)
 AND (@constituency_id IS NULL OR v.constituency_id=@constituency_id)
 AND (@branch_id IS NULL OR s.electoral_area_id=@branch_id)
 AND (@station_id IS NULL OR v.polling_station_id=@station_id);

-- Independent residential usage. Unlinked legacy address text is not counted.
SELECT r.id,r.name,
 (SELECT COUNT(*) FROM districts d WHERE d.region_id=r.id) AS districts,
 (SELECT COUNT(*) FROM residential_towns t WHERE t.residential_region_id=r.id) AS towns,
 (SELECT COUNT(*) FROM voters v WHERE v.residential_region_id=r.id) AS voters,
 (SELECT COUNT(*) FROM voters v JOIN members m ON m.voter_record_id=v.id WHERE v.residential_region_id=r.id) AS members
FROM residential_regions r
WHERE @residential_region_id IS NULL OR r.id=@residential_region_id ORDER BY r.name,r.id;

SELECT d.id,d.name,r.name AS residential_region,
 (SELECT COUNT(*) FROM residential_towns t WHERE t.district_id=d.id) AS towns,
 (SELECT COUNT(*) FROM voters v WHERE v.residential_district_id=d.id) AS voters,
 (SELECT COUNT(*) FROM voters v JOIN members m ON m.voter_record_id=v.id WHERE v.residential_district_id=d.id) AS members
FROM districts d JOIN residential_regions r ON r.id=d.region_id
WHERE (@residential_region_id IS NULL OR d.region_id=@residential_region_id)
 AND (@district_id IS NULL OR d.id=@district_id) ORDER BY d.name,d.id;

-- Branch totals derive from stations; never from a direct voter-to-branch field.
SELECT b.id,b.name,
 (SELECT COUNT(*) FROM polling_stations s WHERE s.electoral_area_id=b.id) AS assigned_stations,
 (SELECT COUNT(*) FROM voters v JOIN polling_stations s ON s.id=v.polling_station_id WHERE s.electoral_area_id=b.id) AS voters,
 (SELECT COUNT(*) FROM voters v JOIN members m ON m.voter_record_id=v.id JOIN polling_stations s ON s.id=v.polling_station_id WHERE s.electoral_area_id=b.id) AS members
FROM electoral_areas b
WHERE (@constituency_id IS NULL OR b.constituency_id=@constituency_id)
 AND (@branch_id IS NULL OR b.id=@branch_id) ORDER BY b.name,b.id;

SELECT COUNT(*) AS stations,
 COALESCE(SUM(electoral_area_id IS NULL),0) AS without_branch,
 COALESCE(SUM(electoral_area_id IS NOT NULL),0) AS with_branch,
 COALESCE(SUM(station_code IS NULL),0) AS missing_official_id
FROM polling_stations WHERE @constituency_id IS NULL OR constituency_id=@constituency_id;

-- Compare page sorting without decrypting private contact fields.
SELECT v.id,v.voter_id,v.last_name,v.first_name,m.member_number
FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id
ORDER BY v.last_name IS NULL,v.last_name,v.first_name,v.id LIMIT 25;