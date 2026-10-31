# Leadership terms, Hall of Fame and reports

Import the COMPLETE database/19_executive_terms_recognition_mysql8.sql file in phpMyAdmin after migration 18. Existing appointments remain unassigned to an administration; no records, terms or recognition entries are fabricated. Reimport is resumable and preserves existing data. The app never executes this migration automatically.

## Leadership administrations

Executives → Leadership terms. Admin/registrar can add, edit, deactivate or reactivate a named administration with start/end dates and an optional description. Names are globally unique; dates may overlap other administrations because grouping does not determine office eligibility or vacancies.

An appointment can be grouped during creation/scheduled editing, or from its detail page → Leadership administration → Organize this appointment. Organization may also classify a closed term: it changes only grouping metadata, not original service identity, dates, position, jurisdiction or replacement links. Choose an active administration containing the appointment's start date, or clear the grouping. Existing inactive groupings can be retained. Actual service may finish after the administration's planned end; term dates do not silently close appointments.

Term date edits cannot exclude starts of assigned records. Rename/deactivation never rewrites saved administration-name snapshots. Grouping/reclassification is explicitly audited and version checked. Appointment/history lists show the saved label and filter by stable administration ID, including inactive or unassigned entries. Existing term changes can be reviewed without creating duplicate administrations. No term deletion is offered.

## Curated Hall of Fame

Executives → Hall of Fame. This differs from the factual Hall of service: only deliberately selected completed service receives recognition.

Admin: choose a completed term in Hall of service, open its detail and choose Create / edit recognition. Record a title, citation, optional achievements, recognition date and display order. Save as Draft or Published. Each completed appointment has at most one recognition entry. The source appointment remains fixed and keeps its historical name/office/jurisdiction. Cancelled/current/scheduled appointments cannot be recognized through this workflow.

Published entries are visible to signed-in staff. Drafts and draft management are admin-only; registrar/viewer requests cannot reveal them by modifying status parameters. Return a published entry to Draft to withdraw it from the general Hall of Fame without destroying its editorial record. Role checks, CSRF, source locking, optimistic concurrency and audit protect saves. No public/unauthenticated publishing or external distribution is added.

## Executive reports

Executives → Reports links to the appointments and service-history directories. Apply the existing filters, then choose Print matching results or Download matching CSV. The entire matching set is used, not merely the visible page; no contact values, private notes or photo bytes are exported. Leadership administration, IDs, jurisdiction, appointment type, dates, state and end reason are included.

Print opens a standalone landscape-friendly report with named criteria, row count and UTC generation time. Use Print / Save PDF. Signed-in viewers may print data they can browse. CSV is admin/registrar-only and requested through a CSRF-protected POST. UTF-8 output uses a BOM and formula-looking cell values are prefixed with an apostrophe. Import ID columns as text in spreadsheet software to retain leading zeros; CSV does not carry spreadsheet column types.

Reports use the same allowlisted filtering and ordering logic as the directories within a consistent read transaction. A hard 5,000-row cap rejects oversized reports before download headers or output; refine filters and retry. Cancelled reservations are labelled as not served. Archive reports preserve historical identity; open appointments use current displayed member identity. No saved export files are left in storage.

## Manual acceptance checklist

Use your own records in Laragon/phpMyAdmin. No database/browser tests or migrations have been executed automatically.

1. Import migration 19 and verify zero fabricated administrations/awards and unchanged appointments. Reimport after adding your own entries; confirm no resets. Before import, new features give setup guidance while existing lists/reports continue safely.
2. Create/edit a leadership administration, including duplicate name/date validation and no-op save. Test stale tabs, inactive/reactivated status, permission checks, search and pagination.
3. Assign an existing current, scheduled and completed appointment whose start falls inside the term. Verify saved label, ID filters, unassigned filter and audit. Try dates outside the term; no assignment should be saved.
4. Change an administration's dates to exclude an assigned appointment start: reject atomically. Rename a term and verify old saved labels stay intact. Clear/reassign grouping and check that only metadata/version changes.
5. Create and edit appointments with term selection; check scheduled editing preserves an existing inactive grouping, requires dates to remain in its range or clearing/reclassification, and preserves all previous eligibility/office/handover checks.
6. As admin, draft recognition for a completed term. Verify source identity cannot be changed and duplicates open the same recognition. Publish, view as registrar/viewer, then unpublish and verify it disappears for them. Forged status=all/draft requests must not reveal drafts; editor/POST routes must forbid nonadmins.
7. Validate empty citation/title, future recognition date, invalid order and stale recognition edits. Cancel/back creates no record. Cancelled or open appointments must be rejected as sources. Test escaping of text containing markup.
8. Apply administration, state, jurisdiction, year/review and residential filters in each directory. Print and export from a result page beyond page 1: exported count/records must represent the full matching set in the displayed order.
9. Check a zero-result CSV contains only headers; PDF/print shows a zero state. Check UTF-8 names, leading-zero IDs imported as text, and formula-like text exported safely. No notes/contact/photo content should be present.
10. Verify viewer can print but cannot POST a CSV export. Missing/expired CSRF must fail. More than 5,000 matching records must request narrower filters without partial CSV output.
11. Compare report criteria names and totals with directory chips and filters. Check current versus historical identity, completed/cancelled states and retained leadership labels.
12. Recheck same-day linked handovers, cancellation, history after membership removal, mobile/dark theme, searchable location dropdowns and sidebar active sections.

Automatic verification is syntax/static only. Please report amendments after manual testing.

## Appointment workflow safeguards

- Re-election inserts a new appointment linked to the previous term; replacement closes the old record and creates its successor in one confirmed transaction. Failure rolls back both changes.
- Single-holder offices reject overlapping service within the same position, organizational level and jurisdiction. The agreed linked same-day handover is the only shared-date exception. Different roles may be held concurrently.
- Acting service remains an explicit appointment type and reserves the same office as substantive service.
- New appointments require an active membership and a person not marked deceased. Membership removal is blocked while current or scheduled appointments remain open.
- Historical names, Party IDs, system numbers, position titles and jurisdiction labels use saved snapshots. Administration grouping and recognition cannot rewrite those service identities.
- Full editing is restricted to scheduled appointments before service starts. Current-service amendments are limited; completed/cancelled records cannot be casually rewritten or deleted. Corrections, endings, cancellations, replacements and grouping changes are audited.

During manual acceptance, verify a rejected replacement leaves the previous appointment open and creates no successor; a successful re-election must retain two distinct appointment IDs. Test concurrent roles in different offices, acting-office conflicts, deceased-member rejection and membership removal guards. Rename directory entries and confirm completed service retains its original labels.

Report review corrections: completed-history queries now include expected end dates; print criteria start expanded. Recoverable report validation/database errors return to the appropriate directory with the submitted filters and a message, before any CSV output. Authorization errors still follow the normal access-control response.
