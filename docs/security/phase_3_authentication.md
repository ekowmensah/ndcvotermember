# Security Phase 3 — sessions, confirmation and security events

No database migration is required. Existing app_login_limits and audit_events tables are reused. No credentials, APP_KEY, members or staff are reset. Only missing configuration keys were appended to .env; existing configured values remain intact.

## Defaults

- SESSION_IDLE_MINUTES remains unchanged (default 30).
- SESSION_ABSOLUTE_MINUTES=480: eight hours maximum after sign-in, regardless of activity. Configurable, bounded to 15–1,440 minutes.
- REAUTH_MINUTES=10: password-confirmation window, bounded to 1–30 minutes.
- Authenticated session IDs renew every 15 minutes without changing the CSRF token or extending the absolute deadline.
- Sessions established before authenticated_at was introduced expire on their next request and must sign in again. This is intentional; no account is disabled.
- A new successful sign-in clears old session forms/previews, establishes fresh timestamps and rotates the CSRF token. Password confirmation does not extend absolute lifetime.

## Sensitive actions

Staff creation/edit/deactivation/session revocation, staff-member relinking and executive access settings require recent password confirmation. Staff editors prompt before the administrator fills out the form when confirmation has expired; POST enforcement remains authoritative.

Membership removal, permanent voter deletion, duplicate merge and replacement also require confirmation. Ordinary reads, voter/member edits, soft deletion and restoration keep their existing permission/version/CSRF protections.

Confirmation uses the current signed-in account password and rotates the session ID and CSRF token. It returns only to an internally generated allowlisted GET destination. It never replays a destructive POST. The user must review and submit again; original POST values/passwords/files are never retained. A timeout while filling a sensitive form may require re-entering it. Confirmation is not an approval queue.

Current-password verification for confirmation AND password changes shares account/IP throttling: five failures in 15 minutes block further attempts for 15 minutes. Limit counters are capped to avoid overflow. Successful verification clears the account-specific counter; shared-IP limits still expire normally. There is no unthrottled alternative password-change path.

Password changes lock/recheck the account version and active status, update the password/session version and audit atomically. They keep the current session with a fresh ID/token and invalidate other sessions on their next request. No password, hash, token or form payload is written into audit JSON or session recovery.

## Security activity

Administration > Security activity is administrator-only with server-side category filtering and 25-row pagination. It shows event code, time, actor, IP and report count, never raw audit JSON.

Events cover successful/failed/blocked sign-ins, sign-out, idle/absolute expiry, invalidated sessions, password-confirmation outcomes, password changes, CSRF rejection, denied requests and report generation. Existing staff permission/member-link changes remain audited. Anonymous failed sign-ins do not store submitted emails or passwords. CSV/print events contain report kind/count, not filters or exported records; generation is recorded, not proof of a completed client download/physical print.

Expiry/denial logging is best effort so audit storage failure cannot keep an expired session usable or grant access. Sensitive password/account changes and CSV report generation require their normal audit write; audit failure prevents successful completion. No log retention/deletion job is introduced; backup/retention remains the operations phase.

## Manual checks in Laragon/phpMyAdmin

1. Sign in again after deployment. Confirm no account data was reset. Verify default .env keys without exposing credentials.
2. Open staff Manage. Expect password confirmation before editor when no recent confirmation exists. Correct password returns to editor without saving anything. Wrong password changes nothing.
3. Fail confirmation five times, then verify confirmation and password change are both throttled. Wait for expiry; successful verification works. Check that sign-in throttling remains separate.
4. Confirm, then edit an account. Verify audit entry, stale-form/version protections and session revocation still work. Use a second browser to verify other-session rejection.
5. Review membership removal/purge/merge/replace with a real intended action. Without recent confirmation there must be NO record mutation. After confirmation a fresh review and explicit submission are required; never perform destructive checks against records you intend to retain.
6. Change password through My account. Wrong current password is throttled; mismatched/short replacement changes nothing. A valid change keeps this session, signs other sessions out on next request and emits auth.password_changed with no secrets.
7. For a short manual expiry check, temporarily set SESSION_ABSOLUTE_MINUTES=15 and SESSION_IDLE_MINUTES=5, sign in fresh and use separate idle/active sessions. Active requests must not extend the absolute deadline. Restore your preferred settings afterwards. Check expired POSTs cannot execute.
8. Keep a session active beyond 15 minutes. ID renewal must retain scope and leave the original authenticated_at/absolute deadline unchanged. Existing CSRF forms should remain usable until an explicit confirmation/password change rotates their token.
9. Submit an invalid CSRF token through a controlled manual request: 419, no mutation, security.csrf_rejected. A Viewer/Reviewer direct request to prohibited modules: forbidden, no mutation, security.permission_denied.
10. Generate an authorized executive/wing print or CSV report. Confirm kind/count events and unchanged scope/5,000-row cap/formula protection. Executive jurisdiction CSV keeps its existing separate export event.
11. Security activity must be denied to Viewer/Registrar/executive accounts even using its direct URL; administrators can filter/page it. Review events for secrets: none should appear.

Only PHP syntax and static inspection were run. No browser/application/database suites or migrations were executed.

Remaining security work: backup/restore/deployment isolation and operational health; MFA/recovery and more granular ordinary-staff permissions need further phases. User-facing profile/view-page redesign is explicitly deferred until the security phases are finished.
