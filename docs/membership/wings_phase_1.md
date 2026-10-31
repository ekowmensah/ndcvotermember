# Membership wings — Phase 1

Import the complete `database/20_membership_wings_mysql8.sql` into the existing application database in phpMyAdmin after migrations through 19. Reimporting the same complete file leaves existing data intact. No application page runs the migration. No members or wing enrollments are fabricated.

## Everyday use

1. Open Members, find an existing member and open their profile.
2. In Membership wings choose Manage wings.
3. Choose Youth Wing or Women's Wing, enter the enrollment date, confirm eligibility and click Save enrollment. Opening the form or cancelling does not enroll anyone.
4. A person may be enrolled in both wings. They retain the same member profile, Party ID, personal details and polling station.
5. Use Membership wings in the sidebar for each directory. Search by name, Party ID or Sys No.; collapse/expand filters for enrollment state, constituency and branch. Members also has a wing/not-enrolled filter.
6. To leave, open Manage wings, enter the actual end date, confirm and choose End enrollment. Rejoining creates a new enrollment after the previous inclusive end date; it does not overwrite the old one.

Admins and registrars manage wing enrollment. Viewers can read directories and member-profile wing labels, but cannot access the editor or save. Only active members not marked deceased can join. Women's Wing additionally requires gender to be recorded as Female; male, other and unknown are rejected by both the form and the locked server save. Youth Wing is unchanged. Existing wing enrollment does not automatically end when someone is marked deceased or membership becomes inactive; those conditions remain visible in the directory for explicit review. Youth age thresholds remain pending agreed rules. No age or gender value automatically enrolls a member.

Electoral constituency and branch derive from the person's current polling station, not residential address or a separately editable wing location. Directory records show current person details while available, with enrollment snapshots as a fallback. Ended enrollments are records, not additional active members; the directory count is labelled enrollments.

Removing party membership closes active wing enrollments within the same transaction before deleting the membership. Historical enrollment records remain, and re-enrollment into the party does not silently rejoin any wing. Existing executive membership-removal restrictions still apply. Party membership start dates cannot be moved beyond a recorded wing enrollment start. An active Women's Wing enrollment blocks saving a non-Female gender; end the enrollment first when correcting gender away from Female. Existing mismatches are flagged in the wing editor, never automatically removed. Version checks and person-first/member-second locks prevent stale forms and concurrent duplicate enrollments. A database unique key independently permits only one open enrollment per wing/person. All joins and endings are audited; no destructive wing-history action is exposed.

## Manual acceptance

- Before importing 20, member pages/directories retain setup guidance without querying the missing enrollment table. After import, both wing directories should be empty until you add your own enrollments. Reimport must preserve those entries.
- Open/cancel an enrollment form: no writes. Enroll an eligible member in one wing, then both. Confirm one profile/Party ID and correct profile labels, directories and Members filters (including Not enrolled in any wing).
- Try duplicate and stale-tab submissions, invalid/future dates, dates before party membership, and missing confirmation. No duplicate/partial changes should occur.
- Verify inactive/deceased people cannot join; existing enrollments remain visible and can be ended. Viewer access to the editor and forged save requests must be forbidden. Expired CSRF must fail normally.
- End enrollment, inspect the Ended enrollments directory, then rejoin after its end date. Check a new enrollment ID and preserved earlier record. Same-day rejoining is blocked because wing enrollment end dates are inclusive.
- Assign/change the polling station's branch and confirm wing directory labels/filters follow it. No wing-specific branch editor should exist.
- Remove membership with active wings: confirm automatic closure and retained ended records. Re-mark as member: no automatic wing enrollment. A blocked executive membership removal must leave both party and wing enrollments unchanged.
- Try moving the party membership join date later than a wing start; expect the whole member save to roll back. Check search, filter chips, reset, pagination, mobile layout and sidebar expansion.

Only PHP syntax/static verification is performed automatically. Run runtime/database checks manually in Laragon/phpMyAdmin. Eligibility suggestions, detailed participation timelines, wing executive scope and dashboard statistics remain later phases.

Women's Wing manual acceptance: enroll a Female active member successfully; attempt Male, Other and Unknown and verify the form blocks enrollment and forged POSTs are also rejected. Try changing a currently enrolled Female member to another gender: the whole member save must roll back. End the wing enrollment, then retry the gender correction. Existing non-Female enrollments must remain visible for review and must still be endable. Youth Wing enrollment must retain its existing behavior.

Phase 2 supersedes the pending Youth age policy above: ages 18–49 inclusive are now required. See docs/membership/wings_phase_2.md for eligibility suggestions, age-review flags and participation history. No additional migration beyond 20.
