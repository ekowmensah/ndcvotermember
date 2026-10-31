<?php
declare(strict_types=1);
namespace App\Security;
use App\{Auth,Database,Validation,Crypto,HttpException,View};
final class ExecutiveAccessAdmin
{
 public static function settings(int $user): array {
  Auth::requireRole(['admin']);
  return ExecutiveAccess::ready()?Database::one('SELECT * FROM staff_executive_access WHERE user_id=?',[$user])??[]:[];
 }
 public static function save(array $input): void {
  $actor=Auth::requireRole(['admin']);if(!ExecutiveAccess::ready()){throw new \InvalidArgumentException('Import migration 24 in phpMyAdmin first.');}
  $id=Validation::id($input,'id');$version=Validation::id($input,'version');
  if(AppointmentAccounts::managed($id)){throw new \InvalidArgumentException('Use Manage appointment access to change this account’s per-office permissions.');}
  $revision=(int)Validation::text($input,'access_revision',20);
  if(!StaffIdentity::ready()){throw new \InvalidArgumentException('Import migration 25 first.');}
  $role=Validation::choice($input,'access_role',['viewer','reviewer']);
  $enabled=Validation::choice($input,'enabled',['0','1']);$contacts=isset($input['can_contacts']);$export=isset($input['can_export']);
  $workflow=isset($input['workflow_permissions_present'])?['can_edit_voter_contacts'=>isset($input['can_edit_voter_contacts']),'can_nominate'=>isset($input['can_nominate']),'can_approve_membership'=>isset($input['can_approve_membership'])]:null;
  if($workflow&&(!\App\Membership\BranchWorkflow::ready()||($workflow['can_edit_voter_contacts']&&!$contacts))){throw new \InvalidArgumentException('Branch contact editing requires migration 26 and contact access.');}
  if($workflow&&in_array(true,$workflow,true)&&$role!=='reviewer'){throw new \InvalidArgumentException('Choose Reviewer access before granting contact edits, nominations or membership decisions. Viewers remain read-only.');}
  Database::transaction(function()use($actor,$id,$version,$revision,$role,$enabled,$contacts,$export,$workflow):void{
   $users=Database::all('SELECT id,role,is_active,session_version,member_id FROM app_users ORDER BY id FOR UPDATE');$users=array_column($users,null,'id');$admin=$users[$actor['id']]??null;$target=$users[$id]??null;
   if(!$admin||!$admin['is_active']||$admin['role']!=='admin'||(int)$admin['session_version']!==(int)$actor['session_version']){throw new HttpException(403,'Your permissions changed. Sign in again.');}
   if(!$target||$target['role']!=='viewer'||(int)$target['session_version']!==$version){throw new \InvalidArgumentException('Choose an unchanged Viewer staff account. Refresh the account before retrying.');}
   $member=(int)$target['member_id'];if(!$member){throw new \InvalidArgumentException('Link this account to its member first.');}
   $old=Database::one('SELECT * FROM staff_executive_access WHERE user_id=? FOR UPDATE',[$id]);
   if((int)($old['revision']??0)!==$revision){throw new \InvalidArgumentException('Access settings changed. Refresh before saving.');}
   $person=Database::scalar('SELECT voter_record_id FROM members WHERE id=?',[$member]);
   if(!$person){throw new \InvalidArgumentException('Member record not found.');}
   Database::one('SELECT id FROM voters WHERE id=? FOR UPDATE',[$person]);
   if(!Database::one('SELECT id FROM members WHERE id=? FOR UPDATE',[$member])){throw new \InvalidArgumentException('Membership changed. Refresh before saving.');}
   if(!\App\Executives\OrganizationService::ready()){throw new \InvalidArgumentException('Complete executive migrations through 21 first.');}
   $eligible=Database::scalar("SELECT COUNT(*) FROM executive_appointments a JOIN members m ON m.id=a.member_id JOIN voters v ON v.id=m.voter_record_id WHERE m.id=? AND m.status='active' AND v.deleted_at IS NULL AND v.is_deceased=0 AND a.ended_on IS NULL AND a.cancelled_at IS NULL AND a.starts_on<=UTC_DATE()",[$member]);
   if($enabled==='1'&&!$eligible){throw new \InvalidArgumentException('Enabling access requires an active, nondeceased member with a current executive appointment.');}
   Database::execute('INSERT INTO staff_executive_access(user_id,member_id,access_role,enabled,can_contacts,can_export) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE member_id=VALUES(member_id),access_role=VALUES(access_role),enabled=VALUES(enabled),can_contacts=VALUES(can_contacts),can_export=VALUES(can_export),revision=revision+1',[$id,$member,$role,(int)$enabled,(int)$contacts,(int)$export]);
   Database::execute('UPDATE app_users SET session_version=session_version+1 WHERE id=?',[$id]);
   if($workflow!==null){Database::execute('INSERT INTO executive_workflow_permissions(user_id,can_edit_voter_contacts,can_nominate,can_approve_membership) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE can_edit_voter_contacts=VALUES(can_edit_voter_contacts),can_nominate=VALUES(can_nominate),can_approve_membership=VALUES(can_approve_membership)',[$id,(int)$workflow['can_edit_voter_contacts'],(int)$workflow['can_nominate'],(int)$workflow['can_approve_membership']]);Auth::audit('staff.workflow_permissions_changed','app_user',$id,$workflow,(int)$actor['id']);}
   Auth::audit('staff.executive_access_changed','app_user',$id,['member_id'=>$member,'access_role'=>$role,'enabled'=>(int)$enabled,'contacts'=>$contacts,'export'=>$export],(int)$actor['id']);
  });
 }
 public static function reviews(): void {
  Auth::requireRole(['admin','registrar']);if(!ExecutiveAccess::ready()){throw new \InvalidArgumentException('Import migration 24 first.');}
  $p=max(1,(int)($_GET['p']??1));$total=(int)Database::scalar("SELECT COUNT(*) FROM record_review_flags WHERE status='open'");$last=max(1,(int)ceil($total/25));$p=min($p,$last);$offset=($p-1)*25;
  $rows=Database::all("SELECT f.id,f.voter_record_id,f.note_encrypted,f.created_at,u.name AS actor,v.full_name FROM record_review_flags f JOIN app_users u ON u.id=f.raised_by_user_id LEFT JOIN voters v ON v.id=f.voter_record_id WHERE f.status='open' ORDER BY f.id DESC LIMIT 25 OFFSET $offset");
  View::render('security/reviews/index',compact('rows','total','p','last'),'Record reviews');
 }
 public static function resolve(array $input): void {
  $actor=Auth::requireRole(['admin','registrar']);$id=Validation::id($input,'id');
  Database::transaction(function()use($actor,$id):void{
   if(Database::execute("UPDATE record_review_flags SET status='resolved',resolved_by_user_id=?,resolved_at=UTC_TIMESTAMP(6) WHERE id=? AND status='open'",[$actor['id'],$id])){Auth::audit('record.review_resolved','review_flag',$id);}
  });
 }
}
