# Executive workspace dashboard

Executive-linked accounts now land on a dedicated dashboard with jurisdiction-only person totals, current/scheduled office cards, a role-aware workbench, nomination queue and account/export links. Ordinary staff dashboard routes remain unchanged.

Voter/member/nonmember/active-member cards use one aggregate query with ExecutiveAccess::condition. Membership coverage is current members / permitted voters, not a historical conversion rate. Members remain a subset of voters. No encrypted contacts, photo blobs or demographic breakdowns are queried for cards.

The dashboard shows up to eight of the user's open offices, distinguishing scheduled/current/unavailable and the effective per-office Viewer/Reviewer label. All qualifying offices still determine access and action eligibility; the display limit does not limit scope. Disabled access, inactive/deceased actor, missing wing enrollment or disabled/missing appointment grants cannot supply usable office actions. Scheduled offices never imply immediate access.

BranchWorkflow::workspaceData reuses the SAME nomination scope as the directory. Pending preview is limited to five authorized records, prioritizing actionable decisions. Decision-ready excludes own submissions, existing memberships and deleted/deceased people. Counts describe visible nomination records; they do not include another constituency's queue. Opening Review still runs the existing authoritative review/save checks. Pending counts may include records needing administrator cleanup; the actionable count does not misrepresent them as eligible.

Workbenches show nomination/contact editing/approval links only for relevant current exact-level Main Party roles and explicit Reviewer permissions. The link opens the existing voter directory; choosing a person determines whether the actual action is permitted. Per-office grants stay local even when another Viewer office has broader read access. Viewers get directory/account access without nomination-management tiles.

The scoped search goes to Voters/Members, preserving existing server-side search enforcement. Export uses the existing CSRF-protected endpoint, explicit grant and 5,000-row limit. Dark/light styling, narrow screens, focus outlines, empty states and print layout are supported with local CSS. No chart fabricates statistics for empty data.

## Manual checks

1. Sign in as an executive-linked Viewer: only permitted people counted; no nomination/edit/approval tiles without grants. Ordinary admin/staff dashboard must retain its current layout.
2. Main Party station Reviewer with nomination permission: nomination tile appears; open a voter to nominate within the station. Branch Reviewer: permitted contact/nomination actions appear. Constituency Reviewer: pending/decision-ready counts match the authorized nomination directory.
3. Verify pending preview excludes outside-jurisdiction records and labels own submissions as awaiting another reviewer. Existing members/deceased/deleted nominees must not inflate decision-ready count. Direct IDs/POSTs remain protected.
4. Mix national Viewer and branch Reviewer roles: national read totals are expected; branch writes stay restricted. Disable/end/cancel roles or set future start dates; next page request must update usable actions and office states without new grants.
5. Check zero records/offices, inactive access, missing migration26, dark theme, mobile and printing manually. No missing optional module should invent data or grant access.
6. Check search, voter/member links, nomination navigation, My account and authorized CSV export. Data refreshes on request; no auto-writing or browser storage of people was introduced.

No migration is needed. Only PHP syntax/static inspection was performed; database/browser verification remains manual in Laragon/phpMyAdmin.
