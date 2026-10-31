-- Manual phpMyAdmin import after 24/25. No permission grants or sample data.
CREATE TABLE IF NOT EXISTS executive_workflow_permissions (
 user_id BIGINT UNSIGNED PRIMARY KEY,
 can_edit_voter_contacts TINYINT(1) NOT NULL DEFAULT 0,
 can_nominate TINYINT(1) NOT NULL DEFAULT 0,
 can_approve_membership TINYINT(1) NOT NULL DEFAULT 0,
 CONSTRAINT fk_workflow_staff FOREIGN KEY(user_id) REFERENCES app_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS membership_nominations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 voter_record_id BIGINT UNSIGNED NULL,
 voter_name_snapshot VARCHAR(250) NOT NULL,
 constituency_id_snapshot BIGINT UNSIGNED NOT NULL,
 nominated_by_user_id BIGINT UNSIGNED NOT NULL,
 status ENUM('pending','approved','rejected','superseded') NOT NULL DEFAULT 'pending',
 note_encrypted TEXT NULL,
 decision_note_encrypted TEXT NULL,
 decided_by_user_id BIGINT UNSIGNED NULL,
 membership_id BIGINT UNSIGNED NULL,
 revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 decided_at DATETIME(6) NULL,
 -- Generate from status only: voter_record_id must support ON DELETE SET NULL.
 pending_flag TINYINT GENERATED ALWAYS AS (IF(status='pending',1,NULL)) STORED,
 UNIQUE KEY uq_nomination_pending_voter(voter_record_id,pending_flag),
 KEY ix_nomination_status(status,id),
 CONSTRAINT fk_nomination_person FOREIGN KEY(voter_record_id) REFERENCES voters(id) ON DELETE SET NULL,
 CONSTRAINT fk_nomination_staff FOREIGN KEY(nominated_by_user_id) REFERENCES app_users(id),
 CONSTRAINT fk_nomination_decider FOREIGN KEY(decided_by_user_id) REFERENCES app_users(id),
 CONSTRAINT fk_nomination_membership FOREIGN KEY(membership_id) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade a previously created version without deleting rows. A failed CREATE
-- needs no cleanup: the CREATE above simply succeeds on the next import.
DELIMITER $$
DROP PROCEDURE IF EXISTS migration26_nomination_pending_key$$
CREATE PROCEDURE migration26_nomination_pending_key()
BEGIN
 IF EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='membership_nominations' AND column_name='pending_person') THEN
  IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='membership_nominations' AND column_name='pending_flag') THEN
   ALTER TABLE membership_nominations ADD COLUMN pending_flag TINYINT GENERATED ALWAYS AS (IF(status='pending',1,NULL)) STORED;
  END IF;
  IF NOT EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='membership_nominations' AND index_name='uq_nomination_pending_voter') THEN
   ALTER TABLE membership_nominations ADD UNIQUE KEY uq_nomination_pending_voter(voter_record_id,pending_flag);
  END IF;
  IF EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='membership_nominations' AND index_name='uq_pending_member_nomination') THEN
   ALTER TABLE membership_nominations DROP INDEX uq_pending_member_nomination;
  END IF;
  ALTER TABLE membership_nominations DROP COLUMN pending_person;
 END IF;
END$$
CALL migration26_nomination_pending_key()$$
DROP PROCEDURE migration26_nomination_pending_key$$
DELIMITER ;
