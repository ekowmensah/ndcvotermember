# Branch voter updates and membership nominations

Reviewer-only amendment: ALL executive contact edits, membership nominations and constituency decisions require access_role=reviewer AND the corresponding explicit grant AND current appointment jurisdiction. Viewer remains read-only even if an older grant is still stored. Selecting Viewer with new workflow grants is rejected; clear grants when changing back to Viewer. Admin approval remains available. For polling-station nomination, select Reviewer and enable can_nominate on the account, then sign in again. No new migration is needed for this role enforcement.

Polling-station amendment: explicitly granted nomination permission now works for CURRENT Main Party polling-station executives as well as branch executives. Station executives can nominate only nonmembers linked to their own station, including a station with no branch assigned. Branch executives retain their own-branch scope. This amendment changes nomination controls, POST authorization and the nominator's own history scope together; it does not extend contact editing or approval to station executives. No additional migration is needed beyond corrected migration 26.

To use: administrator opens Staff accounts > Manage > Executive-linked access, enables the account and Nominate nonmember voters in own branch or polling station, then saves. The executive signs in again, opens Voters > View record and uses Nominate as a potential member. If absent, check current appointment level/organization/start/end/cancellation, active party membership, nomination grant and that the target is a nonmember at the correct station. Scheduled/ended/wing-only appointments do not qualify. Manually verify nomination is denied for another station and for current members; constituency approval scope remains unchanged.

Import `database/26_branch_membership_workflow_mysql8.sql` manually through phpMyAdmin after migrations 24/25. No users, nominations, memberships or permission grants are inserted. Complete the existing residential and executive migrations first. No automatic database/browser tests were run.

If the original import failed with #1215, reimport the complete corrected file; do not drop either table or disable foreign-key checks. The original pending_person expression referenced voter_record_id, which conflicts with MySQL's restriction on SET NULL for the base column of a stored generated column. The corrected definition generates pending_flag from status alone and uniquely indexes (voter_record_id,pending_flag). Pending records use flag 1; completed records use NULL, allowing history while blocking two pending nominations for the same voter. The file also upgrades a previously created old definition without deleting records and is resumable. See [MySQL 8.0 foreign-key restrictions](https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html).

## Grant access

Administration > Staff accounts > Manage > Executive-linked access now includes three independent permissions:

- Edit nonmember voter contacts/residential details in own branch. Also enable contact access.
- Nominate nonmember voters in own branch.
- Approve/reject membership nominations in own constituency.

Saving remains administrator-only, requires recent password confirmation, checks access/account revisions, revokes old sessions and audits grants. Old open settings forms without workflow_permissions_present preserve existing grants. No permission is enabled automatically by the migration or by appointment.

The first two require a CURRENT Main Party branch appointment; approval requires a CURRENT Main Party constituency appointment. Higher-level, scheduled, ended, acting-at-another-level and wing-only appointments do not silently grant these extra capabilities. Acting appointments at the correct level are permitted. Existing wing read scope is unchanged; nonmember voters cannot satisfy wing-member scope. An administrator can approve across constituencies; registrars do not receive nomination approval by default.

## Branch workflow

Open a permitted voter profile. Authorized executives see Update contact / residential details and/or Nominate as a potential member.

The dedicated editor changes only phone, email, encrypted street address and canonical residential region/district/town. It reuses searchable residential selections, dynamic Add town and district popular-town tags. Dictionary counts shown to executives are scoped to their permitted people. Town creation happens only inside successful saves. Names, IDs, gender/DOB, electoral assignment, photos, life status and membership are not writable through this endpoint, even if extra fields are submitted. Existing members must use the member workflow and cannot be edited here.

Voters must still belong to the executive's branch via their actual station; legacy voter branch text/residential geography never grant permission. Missing/unassigned station branch grants no branch editing. Save locks/rechecks staff permissions/session version, actor membership/appointments, target person/station and person version. Deceased/deleted people and existing members cannot be nominated/edited through this flow. Errors roll back all updates, including new town records; passwords/contact values are not stored in audit payloads or session recovery.

A nomination records the voter snapshot, actor, original constituency and optional encrypted note. It changes neither membership status nor the voter identity/version and consumes no member number. One pending nomination per voter is enforced in both code and a generated-column unique key. Rejected voters may be nominated again; history remains. Opening/cancelling forms never writes a nomination.

## Review and approval

Membership review > Nominations offers pending/approved/rejected/enrolled-separately views with pagination. A branch nominator can see their own records while the person remains in their current branch. Authorized constituency executives see records in their CURRENT constituency. Admins see all records, including archived/unavailable people. Nominations follow the person's current station constituency, not the submission snapshot, so the previous constituency cannot approve after a move.

Review shows saved/current identity and encrypted notes only within scope. An administrator or an explicitly authorized constituency executive reviews the current voter and chooses:

- Approve and save membership: select joined date, active/inactive status and optional Party ID. The system number is generated only on successful approval.
- Reject: a decision note is required; voter remains unchanged. Admins may reject stale nominations for unavailable/deleted/deceased people to clear the queue.

Recent password confirmation is required; the user must review and resubmit after confirmation, never automatic POST replay. A nominator cannot approve/reject their own nomination even when they also hold a constituency office. An independent authorized reviewer is required.

Approval checks current scope and actor eligibility, pending state/revision, current voter version, no existing membership and an undeleted/nondeceased target under transaction/locks. Shared EnrollmentWriter is used by ordinary explicit Save membership and nomination approval so number/Party ID/profile/version/audit behavior stays consistent. Membership and decision commit together; duplicate Party IDs or stale tabs leave the nomination pending and create no membership. No executive ability to remove membership is granted.

Ordinary administrator/registrar enrollment outside the nomination workflow marks any pending nomination Enrolled separately in the same transaction. It is not misrepresented as approval by the constituency reviewer. Historical membership links become NULL if membership is later removed; the nomination decision/snapshot remains. Purged voters also retain nomination history via nullable FKs; unavailable pending records can be rejected by an administrator. No bulk approval, email notification or automatic conversion is introduced.

## Manual acceptance checklist

1. Import migration 26. Verify existing accounts, data and grants are unchanged. Configure only real intended users. Contact editing without contact access must be rejected.
2. Sign in as a branch executive with no new grants: read-only behavior remains. Grant just nomination, then just contact edit; each capability must remain independent.
3. Edit a real nonmember's contact/residence in the correct branch. Confirm electoral fields/identity/DOB/photo/membership remain unchanged. Confirm towns/search/tags work, region/district scope is validated and new names persist only on successful save.
4. Modify form target IDs/submit extra identity/electoral fields: outside-branch requests must fail; protected fields must never change. Existing members, deceased/deleted people and unassigned-branch voters must not be writable.
5. Submit the same nomination from two tabs: exactly one pending row and no membership/number. Blank optional note is allowed. Opening/cancelling the form writes nothing.
6. Review as a constituency executive without the approval grant: forbidden. With grant and correct current constituency: permitted. National/region-only or branch-only appointment with an inappropriate grant must not bypass the exact-level rule. Admin can review globally; registrar cannot approve by default.
7. Move a voter's station or reassign the station branch/constituency using authorized existing location flows before submission/approval. Recheck boundaries: old branch/constituency loses access and new authorized constituency receives it. Appointment ending/inactivation/death or permission revocation must invalidate later saves.
8. Open two edit/decision tabs. Save in one, then submit the other: stale person/nomination versions must fail atomically. Try duplicate Party ID: no partial membership/decision. Review and explicitly resubmit after password confirmation.
9. Approve with valid joined/status/Party ID: one member and one approved nomination, preferred Party ID preserved as text, voter editor now routes to member workflow. Retrying cannot create another membership. Contact/profile data remains intact.
10. Reject with/without note: note required; no membership created. Renomination creates a new pending record while retaining rejection history. A branch+constituency dual-office user cannot decide their own nomination.
11. Enroll a pending nominee through normal Save membership: nomination becomes Enrolled separately. Verify no duplicate membership or number from subsequent approval attempts.
12. Inspect audit_events: grant/nominate/contact-edit/approve/reject/supersede events exist without plaintext contact/note values. Notes in membership_nominations are encrypted. CSRF failure must not write anything.

User view-page redesign remains deferred until the security/operations phases are finished. These pages add only the controls required for this authorized workflow.
