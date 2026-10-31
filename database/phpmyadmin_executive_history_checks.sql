-- Optional read-only phpMyAdmin checks AFTER migration 18.
-- Archive totals (no filters): cancelled reservations excluded.
SELECT COUNT(*) AS completed_terms,COUNT(DISTINCT member_number_snapshot) AS memberships_represented,
 COUNT(DISTINCT position_id) AS positions,COUNT(DISTINCT organization_level) AS levels
FROM executive_appointments WHERE ended_on IS NOT NULL AND cancelled_at IS NULL;
-- Dashboard executive totals without residential filters (active person records only).
SELECT
 COALESCE(SUM(a.ended_on IS NULL AND a.starts_on<=UTC_DATE()),0) AS current_appointments,
 COALESCE(SUM(a.ended_on IS NULL AND a.starts_on>UTC_DATE()),0) AS scheduled_appointments,
 COALESCE(SUM(a.ended_on IS NOT NULL),0) AS completed_terms,
 COALESCE(SUM(a.ended_on IS NULL AND a.starts_on<=UTC_DATE() AND a.expected_ends_on BETWEEN UTC_DATE() AND UTC_DATE()+INTERVAL 30 DAY),0) AS due,
 COALESCE(SUM(a.ended_on IS NULL AND a.starts_on<=UTC_DATE() AND a.expected_ends_on<UTC_DATE()),0) AS overdue
FROM executive_appointments a JOIN voters v ON v.id=a.voter_record_id
WHERE v.deleted_at IS NULL AND a.cancelled_at IS NULL;
-- Current service by level; people are unique within each level, not across levels.
SELECT a.organization_level,COUNT(*) AS appointments,COUNT(DISTINCT a.voter_record_id) AS people
FROM executive_appointments a JOIN voters v ON v.id=a.voter_record_id
WHERE v.deleted_at IS NULL AND a.cancelled_at IS NULL AND a.ended_on IS NULL AND a.starts_on<=UTC_DATE()
GROUP BY a.organization_level;
