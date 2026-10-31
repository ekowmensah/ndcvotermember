-- Run ONCE in phpMyAdmin. Adds optional photos without changing existing voters.
-- Images are private database records, served only through authenticated PHP.
CREATE TABLE voter_photos (
 voter_record_id BIGINT UNSIGNED PRIMARY KEY,
 image_data MEDIUMBLOB NOT NULL,
 uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_photo_voter FOREIGN KEY(voter_record_id) REFERENCES voters(id) ON DELETE CASCADE,
 CONSTRAINT fk_photo_staff FOREIGN KEY(uploaded_by_user_id) REFERENCES app_users(id)
) ENGINE=InnoDB;
