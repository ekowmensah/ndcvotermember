# Wing executives — Phase 3

Import the COMPLETE `database/21_wing_executive_scope_mysql8.sql` in the existing database through phpMyAdmin after migrations 17–20. It preserves all records, classifies existing executive appointments as Main Party, and adds no sample appointments. Replay preserves later wing appointments; it does not reset their organization. The application does not run this migration.

## Offices and appointments

Position titles and supported electoral levels remain shared. Define Chairperson once, then appoint separate Main Party, Youth Wing and Women's Wing office holders at the same level/jurisdiction. Each organization has its own single-holder office. Acting appointments reserve the same office as other appointment types within that organization. A member can hold different permitted roles or roles in different organizations.

Open Executive appointments → Appoint executive. Select an existing member, choose Party / wing organization, position, electoral level, jurisdiction and service dates. Search results remain active party members; the saved wing appointment additionally requires active enrollment in the selected wing. Main Party appointments do not require wing enrollment.

Alternatively, open Member profile → Membership wings → Manage wings. Each wing has View wing executives and, where enrollment/eligibility permit, Appoint to wing office. Existing open wing roles link to Manage service. Wing directories also have a Wing executives link.

Wing executive eligibility reuses the recorded rules: Youth ages 18–49 inclusive today AND on the appointment start date; Women's gender Female; active party membership and not deceased. Start cannot precede the current wing enrollment. Stale person/member/wing enrollment versions, invalid or missing enrollment and overlapping offices are rejected inside the appointment transaction. The saved appointment retains the enrollment reference.

## Service lifecycle

Re-election creates a new appointment. Replacement ends the predecessor and creates a successor in one confirmed transaction. Both must use the same organization, title, level and jurisdiction. The agreed exact same-day linked handover remains allowed; unrelated overlaps are blocked. Scheduled editing may correct organization before service begins, but a linked successor must keep its predecessor's organization/office. Current-service amendments cannot reclassify organization, member, office or jurisdiction. Completed/cancelled history stays read-only.

Ending wing enrollment is blocked while its member has a current or scheduled executive appointment in that wing. Close the current term or cancel the scheduled reservation first. Main Party or the other wing's roles do not block leaving this wing. Once roles are closed, an enrollment end date cannot precede any actual service end linked to that enrollment. Party membership removal still requires ALL open executive appointments to be closed before transactional wing closure.

Turning 50 does not automatically end Youth enrollment or executive service. Existing review labels continue to identify eligibility changes, and staff explicitly close service before ending participation. No administrative job silently edits history.

## Read views and reports

Appointments, Hall of service and Hall of Fame display Main Party / Youth Wing / Women's Wing labels and provide an Organization filter inside their collapsible filters. Filter chips, pagination, printing and CSV preserve organization. Reports include an Organization column. Member current-role and historical-service sections, appointment detail and lifecycle pages identify the organization as well.

Existing historical name/title/ID/jurisdiction snapshots and replacement links remain intact. Organization codes have fixed labels; no rename/reclassification control is exposed for completed terms. Wing enrollment IDs link service to its original participation period, even after membership removal. Leadership administrations and curated recognition continue working across all organizations. Dashboard aggregate executive totals include wing appointments; dedicated wing dashboard panels remain the next phase.

Before migration 21, existing Main Party workflows/lists continue operating. Wing appointment controls remain unavailable with import guidance; no page auto-modifies schema.

## Manual acceptance in Laragon/phpMyAdmin

1. Before import, open existing appointments, members, history and reports; verify Main Party behavior and setup guidance. Import 21 and confirm old appointments retain their IDs, identities, dates and Main Party scope. Replay after your own wing appointments exist; confirm they are unchanged.
2. Using your own members, enroll an eligible person in a wing, then appoint through Manage wings. Try without enrollment, with inactive/deceased membership, non-Female Women's member, or missing/out-of-range Youth DOB; reject forged POSTs as well as the form flow.
3. Try a Youth scheduled appointment whose start falls on/after the 50th birthday; reject. Start before wing enrollment must reject. Change/end/rejoin the wing in another tab and submit the old appointment form: reject stale enrollment versions.
4. Appoint the same title/level/location separately in Main Party and both wings: permit. A second open holder in the SAME organization must reject, including acting and scheduled appointments. Different roles should remain possible.
5. End/replace/re-elect in each scope. Confirm separate IDs, atomic rollback on a rejected replacement, predecessor/successor scope consistency and the exact same-day handover exception. Tamper with replacement/successor organization: reject. Cancel a scheduled role and verify its office is freed without served-history metrics.
6. Correct a scheduled appointment into another scope with valid enrollment, checking audit and office conflicts. Started or completed appointments must not offer organization changes.
7. With open wing roles, attempt leaving that wing: block with appointment reference. Close/cancel roles and retry. An end date before a completed role's actual end must reject. Roles in another organization must not alone block leaving this wing.
8. Verify party membership removal still blocks on any open role; after closure it retains wing/service history. Gender correction away from Female follows the existing explicit close-service → end-wing → edit-member process.
9. Check scope labels/filters in all directories, member roles/history, detail, manage and recognition editor. Print/export both full and filtered matching results; verify Organization column/criteria and no private contact or photo exports. Viewer can read/print but cannot appoint/export CSV/manage service.
10. Run `database/phpmyadmin_wing_executive_checks.sql` manually. Use it instead of the older office-overlap checks, which did not distinguish organizations. Check desktop/mobile, dark theme, active sidebar, filter chips, zero states and stale submissions.

Automatic verification is PHP syntax/static only. No database/browser tests or migrations were executed automatically.
