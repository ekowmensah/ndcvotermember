# Security Phase 1: staff accounts

Phase 2 supersedes the independent-account creation rule below: every new login must now link an existing member. See `phase_2_executive_access.md` and import migrations 24/25 before creating accounts.

Administration > Staff accounts is administrator-only. Staff accounts are independent of voter/member records. No accounts or sample records are created automatically.

Import `database/23_staff_least_privilege_mysql8.sql` through phpMyAdmin. It changes only the default for future inserts without an explicit role to Viewer; it does not change existing roles, passwords or session versions. The application explicitly requires a role on creation. Existing account management works before the import using the existing schema. Do not reimport the original schema into the existing database.

Administrators can create staff with a unique sign-in email and a 12–128 character initial password, edit another account's name/email/role/status, and revoke sessions. Saving an edit increments session_version so old sessions are rejected on their next request. Disabled accounts cannot sign in. Revoke does not disable an account. There is no staff deletion or administrator password-reset operation in this phase. Existing staff can change their own passwords under My account. Passwords are never recovered into session state or written to audit summaries.

An administrator cannot edit, demote or disable their own account here. Staff changes lock the account directory, recheck the acting administrator, validate the submitted session version and protect the last active administrator. Creation, updates and revocation are audited in the same transaction. Account list queries never select password hashes. Search and pagination operate server-side, 25 accounts per page. Existing member/voter permissions are unchanged.

## Manual acceptance checks in Laragon/phpMyAdmin

1. Import migration 23. Inspect app_users.role default: Viewer. Confirm all existing accounts/roles remain intact.
2. Open Administration > Staff accounts as the existing administrator. Confirm no password hashes appear.
3. Create only real staff accounts as needed. Verify Viewer is initially selected, matching passwords are required, duplicate emails fail and no password is shown after submission.
4. Sign in as Viewer/Registrar and verify the staff menu is absent and direct staff URLs/POSTs return forbidden. Use existing CSRF-protected forms for authorized checks; missing/invalid tokens must fail without changes.
5. Review your own account: editing/disabling is unavailable. My account remains available.
6. Open another account in two tabs. Save one; saving the stale tab must fail without overwriting it.
7. Change another staff account's role or disable it while signed in as that staff member in another browser. Their next request must reject the session. Disabled sign-in must fail; reactivation must permit sign-in again.
8. Revoke another account's sessions. Its next request signs out; the account can sign in again if active. Self-revocation also signs out the administrator.
9. Inspect audit_events for staff.created/staff.updated/staff.sessions_revoked. Confirm role/status metadata only; no password or password hash.
10. Cancel an editor or use Back to staff: no data changes.

Only PHP syntax checks were run during implementation. No application, browser or database tests/migrations were executed.

## Remaining phases

2. Shared permission policies, separate contact/export permissions and optional geographical staff scope.
3. Session absolute expiry, sensitive-action reauthentication/throttling and security-event coverage.
4. Backup/restore procedure, deployment isolation and operational health.
5. Data-quality workspace and smaller usability/refactoring improvements.

Wait for user testing and authorization before starting the next phase. MFA/recovery needs a separate design before deployment beyond trusted local use.
