# Deleted voters and deceased status

Import the complete files through phpMyAdmin, in order:
1. database/13_deleted_voters_mysql8.sql
2. database/14_deceased_status_mysql8.sql

Both scripts are resumable and retain existing data. The app does not execute migrations automatically.

## Deleted voters

Admin/registrar: open a non-member voter profile and choose Move to Deleted voters. The review page asks for explicit confirmation. Details and photos stay saved; ordinary list, profile, export/import update, enrollment and dashboard paths cannot treat the person as active.

Admin: People → Deleted voters → Review / restore.
- Restore with no matching active record.
- With matching names/IDs, confirm the people are different to keep both.
- Merge into an existing non-member: existing values win, only missing details and photo are transferred. The deleted duplicate is permanently removed after the explicit merge action. No name-based automatic merge.
- Replace: move the chosen existing non-member to Deleted voters and restore the source.
- Permanently delete: expand the section and type the exact voter ID.

Member records cannot be deleted/replaced/merged through this flow. First remove membership through the member profile if that is intended. A matching member can be viewed during restore review, but cannot be replaced or merged into.

Voter IDs remain globally unique and reserved by deleted records. Re-uploading a deleted ID is a preview issue, not a new voter. Restore or permanently delete it before reuse. Name matching uses the database collation and is only a possible-duplicate indicator; it is not an identity guarantee. Different spellings are not detected automatically.

Reviews are bound to person/candidate versions and session tokens. Concurrent changes require a fresh review. Actions use CSRF, permissions, voter-first locks, transactions and audit events without private contact values. Permanent deletion retains audit metadata and dictionary locations; related photo bytes are removed. Restore does not require inventing missing legacy location details; subsequent personal saves still require a valid polling station.

## Deceased status

Open voter/member profile → Update life status / mark deceased. Admin/registrar can record Deceased with an optional date, or correct an accidental status. Death date cannot be future or precede a known birth date. Status lives on the shared voter/person record and reflects in the member profile too.

Membership and photo are preserved; deceased status is independent of active/inactive membership and soft deletion. Existing records default to Not marked deceased, which is not proof that a person is living.

Voter/member lists have Life status filters and deceased badges. Dashboard adds deceased voter/member and not-marked-deceased totals, scoped to the selected residential region/district and linked to filtered lists. Members remain a subset of voters. All total records include deceased people but exclude deleted voters. Deleted people retain their life status if restored.

## Manual verification

Use your own records in Laragon/phpMyAdmin:
- Import both scripts twice and check existing records remain unchanged.
- Delete a non-member; verify list/dashboard/town tags and usage counts exclude it, direct profile/edit/enrollment and CSV update do not reactivate it.
- Restore it and verify photo, IDs, contacts, locations and deceased flag survive.
- Create possible same-name duplicates using your own records; check review does not merge on open/cancel. Check keep-both, missing-detail merge and replacement separately.
- Check conflicting existing values win during merge; a source photo transfers only when the target has none. Member candidates must be protected.
- Keep editors/import previews open during deletion or life-status changes; stale submissions must fail.
- Check old restore reviews fail after another admin changes either record.
- Permanent deletion rejects incorrect confirmation text and cannot target an active voter.
- Registrar can soft delete; only admin can access restore/purge actions. Viewer cannot mutate any lifecycle status.
- Mark voter and member deceased; check badges, date, scope-specific dashboard counts and filtered links. Correct status back with an empty date.
- Unknown death date is allowed; future/before-birth dates are rejected.
- Check zero records and pre-migration pages remain usable.

Only syntax/static checks were run automatically. Database/browser verification is manual.