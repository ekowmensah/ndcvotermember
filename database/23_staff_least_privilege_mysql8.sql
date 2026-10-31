-- Import manually in phpMyAdmin. Does not change existing accounts or records.
-- Safe to replay. Account management uses the existing app_users schema.
ALTER TABLE app_users ALTER COLUMN role SET DEFAULT 'viewer';
