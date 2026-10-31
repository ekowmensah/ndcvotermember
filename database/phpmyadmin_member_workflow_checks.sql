-- Manual read-only checks after migrations 01–12. No application updates or demo data.
-- Enter an existing voter ID as text (including any leading zeros), then run in phpMyAdmin.
SET @voter_id = '';

-- One shared person: member details refer to this SAME internal voter record.
-- Run before/after a member edit: person_record_id and system member number stay unchanged.
-- Personal values and location links change here immediately after a successful member save.
SELECT v.id AS person_record_id,v.voter_id,v.first_name,v.last_name,v.full_name,v.gender,
 m.id AS membership_record_id,m.member_number,m.official_membership_id,m.status,m.joined_on,
 v.town,rr.name AS residential_region,d.name AS residential_district,
 c.name AS constituency,s.name AS polling_station,s.station_code AS official_station_id,
 b.name AS derived_branch,
 EXISTS(SELECT 1 FROM voter_photos p WHERE p.voter_record_id=v.id) AS has_photo,
 v.updated_at AS person_version,m.updated_at AS membership_version,
 mp.profession,mp.education_level
FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id
LEFT JOIN member_profiles mp ON mp.member_id=m.id
LEFT JOIN residential_regions rr ON rr.id=v.residential_region_id
LEFT JOIN districts d ON d.id=v.residential_district_id
LEFT JOIN constituencies c ON c.id=v.constituency_id
LEFT JOIN polling_stations s ON s.id=v.polling_station_id
LEFT JOIN electoral_areas b ON b.id=s.electoral_area_id
WHERE v.voter_id=@voter_id;

-- Both should return no rows.
SELECT voter_id,COUNT(*) AS duplicate_count FROM voters GROUP BY voter_id HAVING COUNT(*)>1;
SELECT voter_record_id,COUNT(*) AS duplicate_count FROM members GROUP BY voter_record_id HAVING COUNT(*)>1;

-- Inspect aggregate upload counts and the audited member-skip count (no private contact fields).
SELECT i.id,i.filename,i.added_count,i.updated_count,i.skipped_count,
 JSON_UNQUOTE(JSON_EXTRACT(a.change_summary,'$.members_skipped')) AS member_records_skipped,
 i.created_at
FROM voter_imports i LEFT JOIN audit_events a ON a.entity_type='voter_import'
 AND a.entity_id=i.id AND a.action_code='voters.uploaded'
ORDER BY i.id DESC LIMIT 10;

-- Removal preserves the voter row/photo. Re-enrollment uses the same voter row with a new membership ID.
-- Stale/invalid member or old voter-form saves must leave versions and details unchanged.
-- Test writes only through the application using records you intend to change.