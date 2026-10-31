-- Run ONCE in phpMyAdmin. Existing system IDs and member numbers stay unchanged.
-- No official party IDs are fabricated. Text storage preserves leading zeros.
ALTER TABLE members
 ADD official_membership_id VARCHAR(64) NULL AFTER member_number,
 ADD updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 ADD UNIQUE KEY uq_official_membership_id(official_membership_id);
