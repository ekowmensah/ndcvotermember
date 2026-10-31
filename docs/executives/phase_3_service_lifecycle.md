# Executives — Phase 3: service lifecycle and member timeline

Import the COMPLETE database/18_executive_service_lifecycle_mysql8.sql through phpMyAdmin after migration 17. It retains appointments, adds closure/cancellation metadata and successor links, and updates the open-office unique key to release cancelled reservations. Reimport is resumable and does not close or seed records. The app never executes migrations automatically.

## Where to use it

Executives → Executive appointments → click a position → End / manage service. Scheduled appointments show Manage / cancel reservation. Member profiles also link to Manage service and show the executive service timeline.

### End an appointment

Record the actual last service day and reason (term completed, resigned, replaced, removed, deceased, other), optional end notes, and confirm. Other requires an explanation. The actual date must be on/after start and no later than today. Saving closes the appointment immediately and preserves it as read-only history. A linked successor may start on the same actual end date as its immediate predecessor. This is an intentional handover; unrelated terms may not overlap.

Ending a role does not delete membership, photos or other roles. The Deceased end reason does not mark the person deceased; update life status separately when appropriate. Existing expected end dates remain advisory and never automatically close roles.

### Replace or re-elect

From a started open appointment, choose Replace office holder / new term. Search/select the successor, or the same member for re-election. Position, level and jurisdiction are fixed to the original office. Record the previous actual end, successor start/type/expected end, optional notes and confirmation.

Both the prior closure and new term save in one transaction. If eligibility, dates, scope, versions or uniqueness checks fail, the old term remains open and no new appointment/audit is committed. The new term points to the previous appointment; both detail pages link to each other. Successors require an active supported position and eligible member. Reactivate an inactive title before replacement. Future actual end dates are unsupported; replacements are processed after the predecessor's service has ended.

### Cancel a reservation

Only appointments whose start is after today can be cancelled. Enter an explanation and confirm. Cancellation frees the office, records who/when/why, leaves actual ended_on NULL and is labelled Cancelled — not served. It is not a completed service term. An appointment that has started must be ended instead. Cancel a scheduled reservation before assigning its office to a different person.

### Correct an open appointment

The correction section allows appointment type, expected end and notes to change with confirmation, concurrency checks and audit. Identity, position, jurisdiction and start date remain unchanged. Closed terms/cancellations are read-only and have no reopen/delete action in this phase. Corrections to those historical fields are outside this phase; report any required amendment after testing.

### History and membership

The directory now includes Completed terms and Cancelled reservations tabs. Closed rows use original identity snapshots. Appointment detail displays end/cancellation notes, actor, preserved identifiers and predecessor/successor links.

Member service timelines show ten closed records per page, labelled separately for actual service and cancellation. The timeline uses the underlying person ID, so it includes older memberships if the person is later re-enrolled. Removing membership is allowed only after all open roles/reservations are ended/cancelled. Foreign keys preserve snapshots if membership or voter records are later removed. Positions may drop unused levels after closure; original appointment level/title labels remain unchanged.

Permissions remain admin/registrar for mutations, viewer read-only. CSRF, explicit confirmation, versions, ordered voter/member/position locks and transactional audit cover lifecycle writes. End notes and appointment notes are not copied into audit summaries.

## Manual acceptance checklist

Use your own records in Laragon/phpMyAdmin. No app/database/browser tests run automatically.

1. Before migration 18, check setup guidance; existing membership-removal and in-use-level protections still apply. Import the complete file and verify current appointments remain unchanged. Reimport after operations and verify no resets.
2. End a current term with a valid actual day/reason. Check it leaves Current, appears in Completed terms/member timeline, retains details/photos/membership and records actor/date/reason.
3. Reject missing confirmation, future actual end, end before start and Other without notes. Cancel/back must not write. End a deceased/inactive member's role without deleting their member record or changing life status.
4. Replace with a different member, then test re-election of the same member in another term. Check distinct IDs, original snapshots, same office and bidirectional term links. Original term closes only on successful save.
5. Attempt a replacement with a start before the predecessor ends or with an unrelated overlapping term, an ineligible/deceased successor, unsupported/inactive position, modified office IDs, changed member versions or stale source version. Confirm the previous term stays open and no new row/audit persists.
6. If predecessor ends today, the linked successor may start today or later. If predecessor ended yesterday, a successor may start today. A future successor remains Scheduled and reserves the office; actual old closure takes effect immediately on save.
7. Cancel a future reservation with an explanation. Check it appears as Cancelled — not served, has no actual end date, releases the office and no longer blocks membership removal or level changes. Repeated cancellation must not write twice.
8. Try cancelling a started term or ending a future reservation: reject. Try replacing a future reservation: direct to cancellation instead.
9. Correct open type/expected end/notes; verify audit and unchanged identity/scope/start. Blank optional values clear. An unchanged save should not add an audit. Closed terms must have no editing/mutating UI and forged POSTs must fail.
10. Open manage/replacement in two tabs, change in one and submit the other. Exactly one valid closure/replacement should commit. Refresh the source detail and inspect both appointments and audit_events.
11. End/cancel all roles, remove membership, then re-enroll the same voter. Check prior closed records remain on the person's timeline and no old membership ID affects the new one.
12. Check history pagination, current/scheduled/open/completed/cancelled filters and chips, snapshot searches, source/successor links and viewer permissions. Check closed appointments remain accessible after membership removal and don't display broken photos for deleted voters to viewers.
13. Verify no demo data, no automatic lifecycle changes and no unrequested schema execution. Broad Hall of service presentation, recognition and dashboard expansions remain Phase 4, pending feedback.

Syntax/static checks only are automated. Report amendments before proceeding to the next phase.

Duplicate nonmember voter merges also transfer their person-linked executive history to the surviving voter before removing the duplicate. Snapshots and predecessor/successor links remain unchanged. Manually check this after ending roles and removing membership; permanent voter deletion retains appointment snapshots with a NULL person link.

## Amendment: full scheduled editing

Open a future appointment → Edit scheduled appointment (also linked directly from Scheduled and Manage pages). Before service starts, admin/registrar may correct the member, position, supported level, jurisdiction, start/type/expected end and notes. This updates the same row and audits changes; it does not cancel/recreate the record.

The original reservation must still be open and future at save time. Its new start can be today or future, not past. Choosing today makes it current and thereafter restricts edits to type/expected end/notes. A successor linked to a previous term must retain its predecessor's office and start on or after that term's actual end. Current/closed/cancelled terms cannot use scheduled editing.

Manual checks: change date/member/office and verify the same appointment ID remains; check the old office becomes free only after successful save; attempt an occupied office, invalid jurisdiction, deceased/inactive member, past/new-before-membership date and expected-before-start date and verify the original row remains unchanged. Check two edit tabs, concurrent cancellation, selection/recovery context, no-op audit behavior, viewer denial and successor-chain restrictions. No new migration required.
Same-day handover amendment: manually verify creating a linked replacement on the actual end date succeeds, and moving a scheduled successor to that date succeeds on the same ID. Starting earlier, changing predecessor office or colliding with any unrelated term must fail atomically. An ordinary unlinked appointment still requires a start after earlier actual service. No migration is required.
