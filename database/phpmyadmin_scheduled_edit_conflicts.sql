-- Read-only diagnostic for appointment #2, after migration 18.
-- Run manually in phpMyAdmin only if an edit still reports a conflict.
SELECT id,member_name_snapshot,position_id,organization_level,jurisdiction_id,
 starts_on,expected_ends_on,ended_on,cancelled_at,updated_at
FROM executive_appointments WHERE id=2;
-- Excludes #2 explicitly. Returns other records conflicting with its SAVED office/start.
-- If the form changes office/start, the new error names that particular conflicting ID.
SELECT other.id,other.member_name_snapshot,other.starts_on,other.expected_ends_on,
 other.ended_on,other.cancelled_at
FROM executive_appointments target JOIN executive_appointments other
 ON other.position_id=target.position_id AND other.organization_level=target.organization_level
 AND other.jurisdiction_id=target.jurisdiction_id AND other.id<>target.id
WHERE target.id=2 AND other.cancelled_at IS NULL
 AND (other.ended_on IS NULL OR other.ended_on>=target.starts_on)
 AND NOT (COALESCE(target.replaces_appointment_id,0)=other.id AND other.ended_on IS NOT NULL AND other.ended_on=target.starts_on)
ORDER BY other.id;
