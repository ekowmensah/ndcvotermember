-- Read-only dashboard checks. Run manually in phpMyAdmin after migrations 01–12.
-- Optional: replace NULL with a real residential region/district ID.
-- These are residential IDs, not electoral IDs. A district must belong to the selected region.
SET @region_id = NULL;
SET @district_id = NULL;

SELECT COUNT(*) AS voters, COUNT(m.id) AS members,
 COALESCE(SUM(m.status='active'),0) AS active_members,
 COALESCE(SUM(m.status='inactive'),0) AS inactive_members,
 COALESCE(SUM(m.id IS NULL),0) AS voters_without_membership,
 COALESCE(SUM(v.created_at>=DATE_FORMAT(UTC_DATE(),'%Y-%m-01')
  AND v.created_at<DATE_FORMAT(UTC_DATE()+INTERVAL 1 MONTH,'%Y-%m-01')),0) AS voters_created_this_month,
 COALESCE(SUM(m.created_at>=DATE_FORMAT(UTC_DATE(),'%Y-%m-01')
  AND m.created_at<DATE_FORMAT(UTC_DATE()+INTERVAL 1 MONTH,'%Y-%m-01')),0) AS current_memberships_created_this_month
FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id
WHERE (@region_id IS NULL OR v.residential_region_id=@region_id)
 AND (@district_id IS NULL OR v.residential_district_id=@district_id);

-- Exact district-scoped town counts; no district selection deliberately returns no tags.
SELECT t.id,t.name,COUNT(*) AS voters,COUNT(m.id) AS members
FROM voters v JOIN residential_towns t ON t.id=v.residential_town_id
LEFT JOIN members m ON m.voter_record_id=v.id
WHERE @district_id IS NOT NULL AND v.residential_district_id=@district_id
 AND (@region_id IS NULL OR v.residential_region_id=@region_id)
GROUP BY t.id,t.name ORDER BY voters DESC,t.name,t.id LIMIT 15;

-- Top MEMBER towns can rank differently from top VOTER towns.
SELECT t.id,t.name,COUNT(*) AS members
FROM voters v JOIN residential_towns t ON t.id=v.residential_town_id
JOIN members m ON m.voter_record_id=v.id
WHERE @district_id IS NOT NULL AND v.residential_district_id=@district_id
 AND (@region_id IS NULL OR v.residential_region_id=@region_id)
GROUP BY t.id,t.name ORDER BY members DESC,t.name,t.id LIMIT 15;

-- Missing values match the dashboard follow-up links; optional fields are not errors.
SELECT COALESCE(SUM(v.phone_encrypted IS NULL OR v.phone_encrypted=''),0) AS missing_phone,
 COALESCE(SUM(v.date_of_birth IS NULL),0) AS missing_birth_date,
 COALESCE(SUM(v.residential_district_id IS NULL),0) AS missing_residential_district,
 COALESCE(SUM(v.residential_town_id IS NULL),0) AS missing_saved_town,
 COALESCE(SUM(v.polling_station_id IS NULL),0) AS missing_polling_station,
 COALESCE(SUM(ps.electoral_area_id IS NULL),0) AS missing_derived_branch,
 COALESCE(SUM(NOT EXISTS(SELECT 1 FROM voter_photos vp WHERE vp.voter_record_id=v.id)),0) AS missing_photo
FROM voters v LEFT JOIN polling_stations ps ON ps.id=v.polling_station_id
WHERE (@region_id IS NULL OR v.residential_region_id=@region_id)
 AND (@district_id IS NULL OR v.residential_district_id=@district_id);

SELECT v.gender,COUNT(*) AS voters,COUNT(m.id) AS members
FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id
WHERE (@region_id IS NULL OR v.residential_region_id=@region_id)
 AND (@district_id IS NULL OR v.residential_district_id=@district_id)
GROUP BY v.gender ORDER BY voters DESC,v.gender;

-- Membership identity: voters = members + nonmembers; members = active + inactive.
-- Activity figures represent current records created in each month, not a historic audit.
SELECT DATE_FORMAT(v.created_at,'%Y-%m') AS created_month,COUNT(*) AS voters
FROM voters v
WHERE v.created_at>=DATE_FORMAT(UTC_DATE()-INTERVAL 5 MONTH,'%Y-%m-01')
 AND v.created_at<DATE_FORMAT(UTC_DATE()+INTERVAL 1 MONTH,'%Y-%m-01')
 AND (@region_id IS NULL OR v.residential_region_id=@region_id)
 AND (@district_id IS NULL OR v.residential_district_id=@district_id)
GROUP BY created_month ORDER BY created_month;

SELECT DATE_FORMAT(m.created_at,'%Y-%m') AS created_month,COUNT(*) AS members
FROM members m JOIN voters v ON v.id=m.voter_record_id
WHERE m.created_at>=DATE_FORMAT(UTC_DATE()-INTERVAL 5 MONTH,'%Y-%m-01')
 AND m.created_at<DATE_FORMAT(UTC_DATE()+INTERVAL 1 MONTH,'%Y-%m-01')
 AND (@region_id IS NULL OR v.residential_region_id=@region_id)
 AND (@district_id IS NULL OR v.residential_district_id=@district_id)
GROUP BY created_month ORDER BY created_month;