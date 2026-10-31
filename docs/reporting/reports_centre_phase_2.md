# Reports Centre — operational reports

Three reports were added to the existing centre. No new migration or records are created. Existing nomination migrations through corrected 26 and executive migration 21 must be ready for those modules; unavailable reports return setup guidance to the hub rather than a redirect loop. PHP syntax/static checks only were run.

## Membership nominations

Outcome, submitter, submission time, decider, decision time, elapsed days to decision and pending age. Recorded timestamps are UTC and timing is elapsed calendar time, not working days. Filter by outcome, electoral location and submission/decision date basis. Decided-date ranges naturally omit undecided rows. Approved, rejected and enrolled separately remain distinct outcomes; repeated submissions are nomination records, not distinct people.

Admin/registrar can report nomination records; this does not give registrars approval powers. Ordinary Viewers cannot open nomination reports. Executive reports reuse the exact BranchWorkflow nomination scope: authorized reviewers see their own current branch/station submissions and/or their granted constituency review scope. Viewer-only or disabled executive accounts do not acquire reporting rights. Notes/contact contents are never selected/exported. Authorized ordinary staff may see archived/unavailable-person nomination snapshots; executive scope excludes unavailable/outside people. Geography uses the current station, not the submission snapshot.

## Data quality

One row per permitted person with multiple missing-information labels: name parts, photo, Party ID for existing members, polling station, station branch, residential location, phone and email. Filter to one issue or any issue, members/nonmembers, geography and voter creation dates. Missing optional details are review opportunities rather than invalid records. Residential checks apply when the residential/town modules are ready; names are never guessed.

Phone/email completeness is evaluated ONLY where the current user's per-person contact read grant permits it. The query uses presence tests, never selects encrypted/plain contact values. A broad Viewer office does not expand a narrow branch contact grant for this report. Unknown contact completeness outside a grant is not labelled missing. No raw DOB, contact, street, photo or notes appear in any output.

Preview-only Open record links follow the existing voter/member routes and permissions; members go through member workflow. These links are not CSV data or print actions, and reports perform no corrections or merges. Counts describe people, not total issues; one person may have several labels.

## Current executive directory

Current started, noncancelled and nonended offices, including acting roles and expected-end review. Filters cover Main/Youth/Women organization, office level, appointment type, office geography and service start dates. Future reservations are excluded. Expected end is advisory; overdue offices remain current until explicitly ended.

Names/Party ID/Sys No. use current linked records with saved snapshot fallback; office title and jurisdiction preserve saved labels. Membership/life flags describe current linked records. No contact/notes or service corrections are exposed by this report. Appointment count differs from linked-person count; NULL/unlinked people are excluded from distinct linked-person totals.

ExecutiveAccess::appointmentCondition enforces scope using the OFFICE jurisdiction, not where the office-holder lives or votes. Main Party national/region/constituency/branch/station actors see only offices at or below their granted geography. Branch scope includes polling-station offices currently assigned to that branch. Station scope includes only that station's offices. Wing-only actors see offices in their own wing and jurisdiction with active wing participation. Preset-managed actor grants must be enabled/current; ended/cancelled/scheduled offices grant nothing. This never expands general voter/member profile access for a holder whose office is visible but whose person record is outside the user's voter read scope; there is no restricted-user management link.

## Shared behavior

Preview, full print and CSV use the same filtered query in a read transaction, 25/50/100 preview pagination and 5,000 full-output-row cap. Each report has matching full-set metrics, visible date/scope criteria and explanatory notice. CSV still requires explicit executive export or admin/registrar, CSRF and formula neutralization. Existing print policy/authentication and metadata-only generation audit remain. Existing voter/member/location reports and reportData naming contract are preserved.

## Manual checks in Laragon/phpMyAdmin

1. Open the hub and all permitted report kinds. Check the original three still render and the former reportData collision does not recur. Check missing-module guidance returns safely to hub.
2. Nomination outcomes and date bases: compare approved/rejected/enrolled-separately/pending counts with saved rows. Verify elapsed-day labels, undecided date filtering and independent count of submissions rather than distinct people. Notes must be absent.
3. Executive nomination scope: own branch/station submissions and authorized constituency queue only; changing station/branch/constituency or ending the actor's role must change access on next request. Ordinary Viewer denied; registrar report access must not add nomination decision controls.
4. Quality: verify multiple issues share one row; Party ID missing applies to members only; unassigned station branches remain optional. Open record follows editor permissions. Per-person restricted contacts are never flagged merely because hidden, and contact ciphertext/content is absent from HTML/CSV/print.
5. Mixed national Viewer + branch Reviewer/contact grant: national quality records may be visible for noncontact issues; phone/email absence is evaluated only inside the granted contact jurisdiction.
6. Current directory: exclude scheduled/ended/cancelled, retain acting/overdue. Scope follows office jurisdiction even when its holder lives elsewhere. Check main/wing boundaries, downward-only office visibility and station branch reassignment. Direct profile/management access for outside-scope holders remains denied.
7. Confirm preview totals, print and CSV match when data is unchanged. Filters persist across pages; wrong IDs cannot widen scope. Preview-only links do not become CSV columns. Cap/CSRF/export checks remain unchanged.
8. Confirm no data/schema updates or personal notes/contact values are written by these read reports, except existing metadata-only report-generation audits.
