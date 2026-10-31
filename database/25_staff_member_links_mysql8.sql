-- Run manually after 24. Existing accounts are retained for administrator linking.
-- Never guess a member from matching names or emails. Safe to replay.
DELIMITER $$
DROP PROCEDURE IF EXISTS migration25_staff_member_links$$
CREATE PROCEDURE migration25_staff_member_links()
BEGIN
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='app_users' AND column_name='member_id') THEN
  ALTER TABLE app_users ADD COLUMN member_id BIGINT UNSIGNED NULL,
   ADD UNIQUE KEY uq_staff_member_identity(member_id),
   ADD CONSTRAINT fk_staff_member_identity FOREIGN KEY(member_id) REFERENCES members(id);
 END IF;
END$$
CALL migration25_staff_member_links()$$
DROP PROCEDURE migration25_staff_member_links$$
DELIMITER ;
