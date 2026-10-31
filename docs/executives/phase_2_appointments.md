# Executives — Phase 2: appointments

Phase 3 is now implemented. Apply migration 18 and use phase_3_service_lifecycle.md for the current end/replace/cancel workflows; the phase limits below describe the original Phase 2 checkpoint.

Import the COMPLETE database/17_executive_appointments_mysql8.sql file in phpMyAdmin after migration 16. Previous person/lifecycle migrations (including 08, 13 and 14) must also be applied. No migration runs in the app; no demo records are supplied.

## Delivered workflow

Executives → Executive appointments → Appoint executive.

1. Find an existing active member by name, Party ID, Sys No. or voter ID. Search returns at most 25 eligible matches; refine when needed. A member profile also provides Appoint to office in Executive appointments.
2. Choose an active position, one of its enabled levels, then its electoral jurisdiction. Select the exact region/constituency/branch/station as required. National has no local jurisdiction. Polling stations do not need branch assignment and are selected directly under constituency.
3. Choose elected, appointed or acting; enter start date and optional expected end/notes. Save appointment is the only create action.
4. Browse Current executives, Scheduled or All open appointments. Search and collapsible filters work across the full matching set with 25-row pagination. Click a position to read appointment details/notes and its original snapshots.

The directory uses current names/Party IDs for identifying existing members. Appointment detail preserves the original member name, party/system IDs, position title and jurisdiction. Updates to a member's Party ID do not rewrite the original snapshot. Photo queries fetch only metadata and use the existing authenticated photo route.

## Rules and phase limits

- Only admin/registrar creates; viewer can browse and read. Eligible members must exist, be active, not deleted and not marked deceased. Service cannot start before the selected membership joined date.
- Member and position versions are checked when saving. Opening, searching, selecting or cancelling creates nothing.
- One open holder per position, level and exact jurisdiction, including acting appointments. A future appointment reserves that office immediately. Existing ended service dates cannot overlap the new term (start must be after the previous actual end).
- Different positions/jurisdictions are independent offices. The same member may receive multiple appointments; no invented eligibility restrictions on home address or voter polling station are applied.
- Expected end is a reminder, not an actual end. Passing it does not remove an executive. Current means started and not explicitly ended; scheduled means future start. All dates use UTC.
- Ending, replacement, cancellation and appointment editing/history UI are Phase 3; no such actions are implemented in this phase. Saved appointments therefore remain open during Phase 2. Use your own test records and review the form before saving.
- Open appointments prevent removing membership. Position levels in use cannot be removed. Inactivating a title stops new appointments but preserves existing office holders. Making membership inactive or marking a person deceased preserves the appointment and flags the member in the directory; ending it follows in Phase 3.
- No voter, membership or background data is duplicated or overwritten. All writes are transactional, CSRF/role protected, audited and protected by position locking plus a unique open-office database key.
- Geography uses electoral directories, never residential locations. Jurisdiction labels are captured when appointed; later name corrections do not rewrite original appointment labels.

## Manual acceptance checklist

Use your own records in Laragon/phpMyAdmin. No database/browser tests have been run automatically.

1. Before migration, open appointments: setup guidance, no server error. Import 17 and verify empty states. Reimport and confirm nothing resets.
2. Viewer: browse list/detail; add page and POST must be forbidden. Admin/registrar may create.
3. Search using name, leading-zero Party ID, Sys No. and voter ID. Confirm only eligible members appear. Missing/removed/inactive/deceased members must fail server-side even with a crafted POST.
4. Select a position supporting all levels. Verify the level choices follow that position; switch to a restricted position and ensure unsupported selection clears.
5. Appoint at National (no local fields), Region, Constituency, Branch and Polling station using your own offices/members. Check saved snapshots/IDs and profile links.
6. Choose a polling station without a branch: it must work. Cross-region constituencies/cross-constituency branches or stations, unsupported levels, missing jurisdiction and extra irrelevant locations must fail.
7. Create one current appointment. Attempt another member and an acting appointment in the same office: both must fail. Same title at a different jurisdiction/level must work. Different positions for one member must work.
8. Create a future-start appointment. Confirm Scheduled and All open show it, Current excludes it, and a duplicate reservation is blocked.
9. Check missing/invalid start, date before membership began, and expected end before start are rejected. Unknown expected end is allowed. A past expected end displays review needed without silently closing.
10. Open a form, change the member's life status/name/Party ID or edit/deactivate the position in another tab, then submit the old form. It must fail and reload current versions. Check changed entered values are recovered where applicable.
11. Submit the same appointment twice or use two tabs concurrently. Exactly one office holder should be saved. Inspect audit_events: successful creation only, with no note/contact payload.
12. Attempt to remove membership with an open appointment; it must fail without deleting member/profile/photo. Attempt to remove an in-use level from its position; it must fail. Renaming a title must preserve appointment snapshots.
13. Mark an appointed member deceased or membership inactive: preserve the appointment, display the flag and exclude them from new-appointment candidates. Existing Party ID correction should display the new ID in the directory while detail retains original snapshot.
14. Check directory search, all filters, chips, pagination and member-profile roles. Review notes on appointment detail. Check empty, mobile/dark-theme and no-JavaScript server validation.
15. Cancel or navigate back without saving: verify no appointment, membership or audit creation.

PHP and JavaScript syntax checks are allowed and were run. Please report amendments before requesting Phase 3.
