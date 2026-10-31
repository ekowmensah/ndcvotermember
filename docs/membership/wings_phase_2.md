# Membership wings — eligibility and participation history

Phase 2 uses migration 20's existing table. No new migration, schema execution or data backfill is needed. Nothing automatically enrolls or ends a member.

## Agreed eligibility

- Youth Wing: 18–49 inclusive. A valid recorded date of birth must establish this age both today and on the submitted enrollment date. Under-18s, members aged 50+, missing dates and future birth dates cannot be newly enrolled.
- Women's Wing: member gender must be Female. Existing protection against saving a non-Female gender while enrolled remains in place.
- Both wings: active party membership and not marked deceased. Previous same-day wing endings must pass before a new enrollment can begin, because wing end dates are inclusive.

When members reach 50, their Youth Wing enrollment remains saved and receives a Needs review flag. Staff must explicitly end it; there is no automatic transition/removal. Birth-date corrections or changed membership/life status also trigger review when the recorded eligibility no longer matches. Ending an enrollment is always allowed even when the member is no longer eligible to join.

## Staff workflow

Open either wing directory and select Eligibility suggestions to find active, nondeceased members who meet its current recorded criteria and are not already enrolled in that wing. Enrollment in the other wing does not exclude them. Search and electoral constituency/branch filters narrow suggestions; nothing is written until the normal reviewed enrollment form is saved. Suggestions exclude people whose previous enrollment ended today, since they cannot yet rejoin.

Needs review shows open enrollments with an eligibility, membership-status or life-status issue. Review filters select open records even if an ended-record filter was also submitted. Open the member's Manage wings form to correct their details or explicitly end enrollment. Correcting an aged-out birth date is appropriate only when the saved date was incorrect; do not change a real date to bypass eligibility.

Member profiles show a recent participation timeline and a Full history link. The complete page filters by wing/enrollment state and paginates 25 records at a time. It preserves enrollment IDs, original saved names/system numbers, actual joining/ending dates, ending reasons and the staff who recorded each action. Historical rows are read-only. Recorded timestamps are separate from effective enrollment dates.

History is linked to the person, so it remains accessible through re-enrollment into party membership. A voter who is no longer a member has a Previous wing participation link. Duplicate-voter merges transfer the person link while preserving saved identity and recorded timestamps; the normal voter merge audit records the surviving person. Purging a person retains unlinked enrollment snapshots under the existing foreign keys, without exposing a new browse-all-history route.

Viewer accounts can browse suggestions, review labels and history. Only admin/registrar can manage enrollment or person details. No contact/address ciphertext or photo bytes are queried for suggestions/history. The new feature CSS lives under assets/css/membership; shared wing directory filters and history entries use nested feature partials.

## Manual acceptance in Laragon/phpMyAdmin

1. Use your own members. Verify ages 17, 18, 49 and 50 around their birthday boundaries. Only 18–49 appear in Youth suggestions. Verify leap-day birth dates around February/March, missing birth date and future/malformed dates if legacy data exists.
2. Try backdating an enrollment to before the member turned 18: reject. Tamper with a POST for an under/over-age member: reject server-side. No partial enrollment/audit write should remain.
3. Open a member's form in one tab, change birth date/status in another and submit the old form: reject as stale. Check existing Female enforcement, confirmation, CSRF and duplicate guards.
4. For an existing Youth enrollee now aged 50+, confirm Needs review on profile, directory and editor. Do not automatically close it. Explicitly end it and verify preserved history. Repeat with missing birth date, inactive membership, deceased status and a legacy non-Female Women's enrollee.
5. Suggestions exclude existing enrollments only for the selected wing: an eligible woman in Youth Wing can still be suggested for Women's Wing and vice versa. An enrollment ended today cannot be suggested for rejoining until tomorrow.
6. Join/end/rejoin and inspect distinct enrollment IDs in the timeline. Confirm original names/numbers remain after member edits. Filter history, navigate more than 25 entries and check the latest-five profile preview.
7. Remove party membership, view history from the voter profile, re-mark as member and verify the new member profile can see prior participation without automatic wing re-enrollment. Merge duplicate nonmember voters through the existing confirmed workflow and verify wing records follow the survivor.
8. Check viewer read access and forbidden editor/POST actions; searchable electoral filters, applied chips, reset, sidebar active state, zero results, mobile display, dark theme and printing.

Only PHP syntax/static checks were run automatically. Runtime/database/browser checks remain manual. Wing executive scope and dashboard statistics are later phases.
