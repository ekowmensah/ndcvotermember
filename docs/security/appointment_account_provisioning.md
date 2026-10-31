# Appointment-linked account provisioning

Import `database/27_appointment_account_provisioning_mysql8.sql` manually through phpMyAdmin after corrected 26 and existing 21/24/25. It creates three new tables only; it does not create accounts, grant access or change existing records. No migration or browser/database suite was executed during development.

## Administrator workflow

1. Appoint a member using the existing form. System access is selected by default for administrators when migration 27 is ready. A registrar can still appoint, but cannot provision login permissions; an administrator must handle access. Old appointment forms without the new controls retain their original behavior.
2. Supply a unique sign-in email only if the member has no existing login. The member link, not name/email similarity, identifies an existing account. Duplicate email for another member fails and rolls the entire appointment/account transaction back. Unchecking system access creates only the appointment.
3. New appointment/account/access grants commit atomically. New accounts receive an unknown random password hash and are inactive until activation; no default/shared password exists. Existing accounts are reused without password resets or automatically changing their manual staff/executive permissions. A reused manually managed account stays under its existing management workflow, and its existing restrictions remain authoritative.
4. A newly provisioned account uses appointment-linked presets. After saving the appointment, the administrator is directed to Appointment-linked access. This page is also linked from Staff accounts > Manage > Executive access. Issue a private activation link and give it to the verified member through an approved private channel. No email/SMS is sent by this implementation.
5. The member opens the link and chooses a 12–128 character password. The token is single-use and expires after 48 hours. A fresh link invalidates the previous unused link. Activation requires CSRF and is limited to 10 attempts per IP per 15-minute window. The member then signs in normally; activation never starts a scheduled office early.

The token is stored only as a SHA-256 hash in the database. Its original is placed in encrypted administrator session state for a one-time receipt, then removed after viewing. The link uses a URL fragment rather than a server query parameter; local activation JavaScript transfers it to the form and removes the fragment from browser history. Without JavaScript the member may paste the 64-character code manually. No raw token is written to audit logs. Link issuance is protected by administrator authorization/recent-password confirmation; a staff account change/revocation invalidates the version-bound link. Previously activated accounts cannot be reset using this issuance path.

## Default presets

| Organization / level | Access | Capability |
| --- | --- | --- |
| Main Party polling station | Reviewer | Nominate nonmember voters at that station |
| Main Party branch | Reviewer | Read contacts, edit nonmember contacts/residential details, nominate in branch |
| Main Party constituency | Reviewer | Decide nominations in constituency |
| Main Party region / national | Viewer | Read current jurisdiction only |
| Youth/Women's Wing offices | Viewer | Read enrolled wing members in current jurisdiction |

Exports remain off. Read-only and workflow boundaries still apply: no general executive editing of names, IDs, electoral assignments, member person details, photos, life status or deletion. Branch contact editing requires both Reviewer and contact permission. Main Party station/branch/constituency nomination rules remain exact-level and explicitly granted. Admin membership approval remains separate.

## Per-office permissions and lifecycle

An administrator can adjust each office's Viewer/Reviewer, enabled state, contact reading and compatible workflow grants from Appointment-linked access. Server checks prevent incompatible level/wing grants and require contact reading for contact editing. Office grants have revision checks; changes revoke sessions and audit atomically. Account-wide manual executive controls are unavailable for preset-managed accounts to avoid mixing two conflicting permission sources. General name/email/status and session revocation remain available in Staff accounts.

Access is derived on each request from enabled grants AND current, noncancelled/nonended appointments matching the account member. Scheduled/ended/cancelled offices contribute no current data or workflow permission. The effective displayed role is Reviewer if any current granted office is Reviewer, otherwise Viewer. That label does not combine jurisdictions for a capability: the actual grant and matching office must authorize EACH target person.

For example, a national Viewer plus branch Reviewer may read people nationally, but contact visibility/editing and nomination remain limited to the granted branch. Two constituency offices with different grants do not share approval rights. Multiple office reads form a union without duplicating people. Ending/replacing/cancelling one role removes its contribution on the next request while preserving other current roles; expected end dates remain advisory. Wing membership/actor membership/life-status restrictions remain enforced.

Scheduled member reassignment disables the previous account's grant; provisioning can attach the office to the new member's existing/new account. Scheduled level/organization changes reconcile capability defaults even when access provisioning is unchecked, preventing a branch contact grant from becoming national contact access. Same-office edits preserve custom grants. Re-election creates a new office grant; it never overwrites the old appointment's history. Existing manually managed accounts are never silently converted into this mode.

## Manual acceptance checklist

1. Import 27. Existing accounts/passwords/roles must remain unchanged. Admin sees system-access controls; registrar does not and cannot forge provisioning POSTs.
2. Appoint a real member without a login. Missing/new conflicting email must fail without creating either appointment or user. With a valid email and confirmation, exactly one inactive account/member link and matching office grant are created.
3. Issue activation. Receipt is shown once. Verify token is hashed in DB, not in audit/query URLs. Wrong/expired/reissued/used token must fail. Too many attempts are throttled. A valid activation sets the password without logging in as that person or revealing it to the administrator.
4. Change/revoke the inactive account after issuing a link: old version-bound link must fail. Issuing a new link works only while never activated; a disabled previously activated account cannot use activation as password reset.
5. Repeat with a scheduled office: account may activate/sign in, but sees no office records or workflow until start. Ending/cancelling it never grants access. No automatic jobs are required for date transitions.
6. Verify each default preset using real correct-level offices. Wing appointments remain scoped Viewers. Exports remain off. All prior outside-jurisdiction/CSRF/stale-edit protections must still hold.
7. Appoint a member who already has an ordinary/manual executive account: same login is reused; role, password, enabled status and existing manual permissions remain unchanged. Appoint a second office for a new preset-managed account: same login and a separate office grant.
8. Mixed scopes: national Viewer + branch Reviewer. National records readable, outside-branch contacts hidden in HTML and branch writes/nominations denied. Contact editing/member approval must not inherit other Viewer jurisdictions. Two constituency grants with only one approval permission must expose/allow decisions only for the granted constituency.
9. Disable/edit a per-office grant in two tabs: stale update fails, old sessions revoked, other offices unaffected. Forged grant for wrong level/wing or a Viewer workflow fails. Account and office IDs must match.
10. End/cancel/replace one office while signed in elsewhere. Its read/capability contribution disappears on next request; other current roles remain. Test scheduled member/level/organization edits without retaining incompatible permissions.
11. Normal manual account creation and nomination approval still work. No user profile/view redesign is included beyond necessary activation/provisioning controls.

Only PHP syntax/static checks were run. Test in Laragon/phpMyAdmin before moving to further changes.
