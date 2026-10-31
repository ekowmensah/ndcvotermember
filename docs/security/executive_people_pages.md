# Executive voter/member directories and profiles

ExecutivePeople now supplies the executive-specific lists and profiles under views/security/executive/people/, voters/ and members/. Retired flat executive index/show and duplicate nomination-action fragment were removed after references changed. Ordinary staff pages and public route names remain unchanged.

Directories include private photo thumbnails/placeholders, saved last/first names, gender, canonical station-derived electoral labels, membership/life status and Party ID preferred over highlighted Sys No. under constituency. Members have Region in place of the voter's ID column; the member profile retains voter ID. Current executive role labels appear beneath their respective jurisdiction columns, National beneath Status, in a single visible-page role query; they are plain text rather than links to forbidden management routes.

Search covers name, voter ID, Party ID and Sys No. Filters are collapsible/default closed, with visible removable chips, gender/life/photo/membership or membership-status and scoped constituency/branch/station choices. Sorting/page sizes are server allowlisted; pagination preserves filters. Metrics cover the entire matching set, not the current page. Filters narrow but never expand ExecutiveAccess::condition. Location choices derive only from scoped people, not the global location catalog. Branch remains station-derived. List queries retrieve no encrypted contact/address values or photo bytes.

Profiles distinguish voter identity/nomination from membership identity/joining/background. Shared profile fragments avoid duplicate forms. Member rows open member profiles directly; legacy voter links for members redirect to the canonical member URL after scope validation. Nonmember records alone can show authorized branch edits/station-or-branch nominations. Members remain protected and use correction flags rather than executive personal edits. There are no enrollment/removal/deletion controls here.

Photo metadata prevents broken requests for absent images; image routes remain authenticated/scoped. Contact permission is computed for the individual person and granting office, not just effective account role. Restricted DOB/contact/street columns are NULL in the query and never decrypted/rendered. Residence directory labels and optional saved member background use only the already authorized person. Empty background sections are omitted. No executive/wing historical directories, raw audit events or unscoped reports are embedded.

Pending nomination is shown before offering a new submission. A branch reviewer may see that a nomination exists, but only its owner or an authorized constituency reviewer receives the review link. Notes from another branch submitter are not embedded in the profile. The save endpoint still enforces unique pending nomination, actor scope/person version and authorization. Correction success returns to the person's canonical profile. No private form recovery/browser storage is added.

## Manual verification in Laragon

1. Sign in as executive Viewer and Reviewer. Compare voter/member columns, thumbnails, empty states, dark/light/mobile layouts. Ordinary admin/staff directories must remain unchanged.
2. Search/filter/sort and page using 25/50/100 rows. Counts/chips/pagination must stay consistent. Forged outside-location IDs produce no outside people; dropdowns and totals must not expose other jurisdictions. Missing first/last parts remain Not recorded, never guessed from full name.
3. Open member rows and old voter links for members: canonical member profile, correct Party ID/Sys No., no voter edit/nominate controls. Nonmember profile has only the user's permitted workflows. Verify no direct management/appointment links are exposed by role labels.
4. For national Viewer + branch Reviewer, open an outside-branch person: no phone/email/street/DOB in HTML and no edit/nomination/flag controls unless the specific granting Reviewer office permits them. Repeat by direct IDs and photo URLs outside read scope: unavailable.
5. Nominate a real intended voter: after submission the profile displays Pending approval without another submission form. Another branch submitter cannot open the nomination merely from knowing its ID. Authorized constituency review remains scoped/versioned; self-approval remains blocked.
6. Flag an issue: CSRF/permission checks remain, success returns to the current person profile. Deceased people cannot be nominated/contact-edited. New membership after page load causes stale voter saves to fail and future voter links to redirect to member.
7. Print profiles: helper navigation/forms/actions are omitted. No contact visibility changes when printing.

No database migration is needed. Only PHP syntax/static checks ran; no database/browser tests, demo records or data updates were executed.
