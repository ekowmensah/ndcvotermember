# Security Phase 2 — member-linked accounts and executive access

## Setup in phpMyAdmin

Import complete files in order, after existing migrations through 23:

1. `database/24_executive_access_mysql8.sql` — explicit executive access and encrypted reviewer flags.
2. `database/25_staff_member_links_mysql8.sql` — nullable member identity on existing accounts, a unique member/account link and restrictive membership FK.

Both files are replayable for the schema they create. No users, members, demo data or guessed identity matches are inserted. Existing accounts and administrator access remain available. New accounts cannot be created until migration 25 is imported. Executive enablement also requires executive migration 21 readiness.

## Account workflow

- Every NEW login requires a selected existing nondeleted, nondeceased member. One login per member. Ordinary staff roles remain Admin/Registrar/Viewer.
- Existing accounts are a migration exception: they retain access until deliberately linked. Open Administration > Staff accounts > Manage > Member identity to link each, including your own administrator account. Linking signs the account out. No automatic matching by name/email.
- New accounts default to the Executive account type after migration 24. Leave the staff role Viewer. Creation inserts disabled restricted access in the same transaction, so the account never temporarily gains ordinary unrestricted Viewer access.
- After creation, reopen Manage and enable executive access. The saved member identity is authoritative; it cannot independently differ from the executive link. Choose Viewer or Reviewer and explicitly grant contacts/export if needed.
- Ordinary staff is a separate explicit account type. Its role retains existing system-wide permissions. Only administrators can create or configure either account type.
- An executive-linked account must keep the base Viewer role. It remains restricted if executive access is disabled, membership becomes inactive, or all appointments end. It does not fall back to ordinary Viewer permissions.
- Changing member identity disables executive access until reviewed again and increments session_version. Access settings and staff changes also invalidate old sessions. Existing tabs carry versions and cannot overwrite newer account settings.
- Membership removal is blocked while ANY login is linked, including disabled logins. Reassign an incorrectly linked account first; do not remove an actual account-holder's membership casually. Existing account deletion is not introduced here.
- Linked accounts cannot authenticate or continue a session when their person is deleted/deceased or their member link is invalid. Inactive membership still exists for ordinary staff; executive data access requires active membership.

## Scope and roles

Scope is calculated on every request from actual current noncancelled, nonended appointments with starts_on <= UTC_DATE(). Expected end is advisory, not automatic closure. Multiple roles combine their allowed records using EXISTS, never duplicate people.

National main-party appointments cover all undeleted people. Region, constituency, branch and station scopes derive from each target person's actual polling station and its constituency/region/optional branch. Never use residential geography, legacy text or a direct person-branch field for access. Missing branch gives no branch-office access.

Youth/Women appointments additionally require the executive's open enrollment in that wing, and target people must be currently enrolled members of that wing. Main-party appointments can independently grant broader scope. Scheduled/ended/cancelled offices grant nothing. Inactive/deceased/deleted executives receive no data access.

Viewer reads records. Reviewer reads and submits encrypted correction notes; neither can directly edit, enroll, delete, restore, merge, change life status or manage appointments. Flags are limited to 30 per hour per reviewer. Admin/Registrar use Record reviews to open the usual person editor and mark issues resolved. Resolution itself does not modify person data.

## Surfaces and data minimization

Executive accounts use dedicated scoped dashboard counts, paginated name/ID search, basic voter/member profiles and authenticated photos. Contacts, street address and DOB are excluded from SQL projections unless explicitly granted. No decrypted values are retained in URLs or recovery sessions.

All other modules/reports are denied server-side to restricted accounts rather than exposing unrestricted aggregates, histories or filters. Sidebar reflects this allowlist. Repository person lookup and directories have additional scope guards; photo delivery checks scope before retrieving bytes. This phase does not give executive accounts the full management-dashboard charts or full background/history views.

The separately granted executive CSV exports ALL currently permitted people (not only a search page), at most 5,000 rows. It includes identity/gender/party/system IDs only, excludes contacts/DOB, neutralizes spreadsheet formulas and is CSRF-protected and audited. Import ID columns as text to preserve leading zeros. Existing ordinary-staff reports remain unchanged.

## Manual acceptance checks

Only PHP syntax/static inspection was performed during development. Do these using your real records in Laragon/phpMyAdmin; do not add demo records automatically.

1. Import 24 then 25. Existing administrator sign-in still works. Link your existing administrator to the correct member; expect sign-out, then sign back in. Try linking a second login to that same member: reject without changes.
2. Create an Executive-type Viewer account for an actual member. Missing/nonexistent/deceased member or missing member selection must fail; no password is stored in recovery/audit. Before enablement the new account sees zero records, never global Viewer data.
3. Enable for an actual current office holder. Verify all five levels separately where real appointments exist. Directly alter profile/member/photo IDs to records outside the jurisdiction: unavailable. Search for an outside person's name/Party ID: no result. Outside people must not affect counts.
4. Branch scope follows station reassignment immediately on the next request. Residential filters/addresses must never grant electoral scope. Multiple current offices yield the union without duplicate rows.
5. Wing-only office: only currently enrolled people in that wing and jurisdiction appear. A separate Main Party role can additionally grant its own records.
6. End/cancel an appointment or leave only a scheduled appointment: next request yields no access from that office. Inactivate the linked membership or mark the executive deceased: data is unavailable; deceased also invalidates the login. Reversing status never automatically enables disabled access.
7. Toggle contacts off/on: verify phone/email/street/DOB absent/present in HTML accordingly. Photo URLs still require both login and person scope.
8. Viewer cannot flag issues or POST management actions. Reviewer can flag a permitted record but cannot flag an outside record. Admin/Registrar sees Record reviews, corrects using the usual editor, and marks resolved. Verify notes are encrypted and not in audit JSON.
9. Restricted users cannot open executive history, wing directories, legacy reports, uploads, location management, staff management, deleted voters or editor routes even using direct URLs/POSTs. Logout/password change still work.
10. Export denied without explicit grant. With grant, every exported person is in current scope, contacts are absent, >5,000 rows fail with guidance, and audit shows count/actor. CSRF failure must write/download nothing.
11. Disable/relink/revoke account: previous sessions fail on the next request. Relinking disables executive access. Stale settings tabs must fail without reverting newer changes.
12. Attempt membership removal with linked login: reject, including disabled accounts. Verify original voter/member/history remains untouched.

Pause for user testing before the next phase. Absolute session expiry, MFA/recovery, granular ordinary-staff policies and deployment/backup hardening remain outstanding.
