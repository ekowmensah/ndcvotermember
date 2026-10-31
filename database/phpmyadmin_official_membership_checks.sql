-- Read-only checks after migration 08. No demo records or modifications.
SELECT m.id AS system_id,m.member_number AS system_member_number,
 m.official_membership_id,v.voter_id,v.full_name,m.status,m.joined_on,m.updated_at
FROM members m JOIN voters v ON v.id=m.voter_record_id ORDER BY m.id DESC LIMIT 50;

-- Expected: no duplicate official party IDs or empty strings (blank means NULL).
SELECT official_membership_id,COUNT(*) FROM members
WHERE official_membership_id IS NOT NULL GROUP BY official_membership_id HAVING COUNT(*)>1;
SELECT id FROM members WHERE official_membership_id IS NOT NULL AND TRIM(official_membership_id)='';

SELECT COUNT(*) AS missing_official_ids FROM members WHERE official_membership_id IS NULL;
SELECT a.action_code,a.entity_id AS membership_id,u.name AS staff,a.occurred_at,a.change_summary
FROM audit_events a LEFT JOIN app_users u ON u.id=a.actor_user_id
WHERE a.action_code IN ('member.marked','member.updated','member.removed')
ORDER BY a.id DESC LIMIT 25;
