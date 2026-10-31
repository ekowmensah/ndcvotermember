-- Read-only checks after importing migration 06. Does not expose image bytes.
SELECT v.id,v.voter_id,v.full_name,
 CASE WHEN p.voter_record_id IS NULL THEN 'No photo' ELSE 'Photo saved' END AS photo_status,
 OCTET_LENGTH(p.image_data) AS stored_bytes,p.updated_at,u.name AS uploaded_by
FROM voters v LEFT JOIN voter_photos p ON p.voter_record_id=v.id
LEFT JOIN app_users u ON u.id=p.uploaded_by_user_id
ORDER BY v.id DESC LIMIT 50;

-- Expected: no empty photo records.
SELECT voter_record_id FROM voter_photos WHERE OCTET_LENGTH(image_data)=0;

SELECT a.action_code,a.entity_id AS voter_record_id,u.name AS staff,a.occurred_at
FROM audit_events a LEFT JOIN app_users u ON u.id=a.actor_user_id
WHERE a.action_code IN ('voter.photo_saved','voter.photo_removed')
ORDER BY a.id DESC LIMIT 25;
