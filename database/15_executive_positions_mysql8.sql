-- Executive module, Phase 1. Import the COMPLETE file in phpMyAdmin.
-- Creates an empty directory only; preserves existing data. Safe to reimport.
CREATE TABLE IF NOT EXISTS executive_positions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(160) NOT NULL,
 organization_level ENUM('national','region','constituency','branch') NOT NULL,
 description VARCHAR(1000) NULL,
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_executive_position_level_name(organization_level,name),
 KEY ix_executive_position_listing(is_active,organization_level,display_order,id),
 CONSTRAINT fk_executive_position_creator FOREIGN KEY(created_by_user_id) REFERENCES app_users(id),
 CONSTRAINT ck_executive_position_active CHECK(is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SELECT 'Executive positions ready. Add your own positions through Executives > Positions.' AS result;
