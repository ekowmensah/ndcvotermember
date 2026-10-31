-- Read-only checks after migration 11 and migration 08. No demo records.
SELECT m.id,m.member_number,m.official_membership_id,v.full_name,
 p.profession,p.education_level,p.employment_status,p.job_title,p.employer,
 p.skills,p.languages,p.volunteer_interests,p.availability,p.updated_at
FROM members m JOIN voters v ON v.id=m.voter_record_id
LEFT JOIN member_profiles p ON p.member_id=m.id
ORDER BY v.full_name LIMIT 100;

-- Summary criteria counts use real records only.
SELECT profession,education_level,COUNT(*) AS members
FROM member_profiles GROUP BY profession,education_level ORDER BY profession,education_level;

SELECT COUNT(*) AS members_without_background
FROM members m LEFT JOIN member_profiles p ON p.member_id=m.id WHERE p.member_id IS NULL;

-- Expected: no orphaned profiles.
SELECT p.member_id FROM member_profiles p LEFT JOIN members m ON m.id=p.member_id WHERE m.id IS NULL;

SELECT a.action_code,a.entity_id AS membership_id,u.name AS staff,a.occurred_at
FROM audit_events a LEFT JOIN app_users u ON u.id=a.actor_user_id
WHERE a.action_code IN ('member.profile_saved','member.profile_cleared','member.removed')
ORDER BY a.id DESC LIMIT 25;
