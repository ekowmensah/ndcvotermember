<?php
declare(strict_types=1);
namespace App\Security;
use App\{Auth,Database,Validation,HttpException};
final class StaffIdentity
{
 public static function ready(): bool {
  static $ready=null;return $ready??=((int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='app_users' AND column_name='member_id'")===1);
 }
 public static function choices(): array {
  $options=[''=>'Choose an existing member'];
  foreach(Database::all('SELECT m.id,m.member_number,m.official_membership_id,v.full_name FROM members m JOIN voters v ON v.id=m.voter_record_id WHERE v.deleted_at IS NULL ORDER BY v.full_name,m.id') as $member){$options[$member['id']]=$member['full_name'].' · '.\member_identifier($member).' · record #'.$member['id'];}
  return $options;
 }
 public static function canSignIn(int $user): bool {
  if(!self::ready()){return true;}
  $row=Database::one('SELECT u.member_id,m.id AS found_member,v.is_deceased,v.deleted_at FROM app_users u LEFT JOIN members m ON m.id=u.member_id LEFT JOIN voters v ON v.id=m.voter_record_id WHERE u.id=?',[$user]);
  // Legacy accounts remain available solely for deliberate administrator linking.
  return $row&&(!$row['member_id']||($row['found_member']&&!$row['is_deceased']&&!$row['deleted_at']));
 }
 public static function member(int $id): void {
  $person=Database::scalar('SELECT voter_record_id FROM members WHERE id=?',[$id]);
  if(!$person){throw new \InvalidArgumentException('Choose an existing member for this account.');}
  $voter=Database::one('SELECT id,deleted_at,is_deceased FROM voters WHERE id=? FOR UPDATE',[$person]);
  if(!$voter||$voter['deleted_at']||$voter['is_deceased']||!Database::one('SELECT id FROM members WHERE id=? FOR UPDATE',[$id])){throw new \InvalidArgumentException('Choose a member who is not deleted or marked deceased.');}
 }
 public static function link(array $input): void {
  $actor=Auth::requireRole(['admin']);if(!self::ready()){throw new \InvalidArgumentException('Import migration 25 first.');}
  $id=Validation::id($input,'id');$version=Validation::id($input,'version');$member=Validation::id($input,'member_id');
  Database::transaction(function()use($actor,$id,$version,$member):void{
   $users=array_column(Database::all('SELECT id,role,is_active,session_version,member_id FROM app_users ORDER BY id FOR UPDATE'),null,'id');$admin=$users[$actor['id']]??null;$target=$users[$id]??null;
   if(!$admin||!$admin['is_active']||$admin['role']!=='admin'||(int)$admin['session_version']!==(int)$actor['session_version']){throw new HttpException(403,'Your permissions changed. Sign in again.');}
   if(!$target||(int)$target['session_version']!==$version){throw new \InvalidArgumentException('Account changed. Refresh before linking.');}
   self::member($member);
   if(ExecutiveAccess::ready()&&Database::scalar('SELECT user_id FROM staff_executive_access WHERE user_id=?',[$id])){
    Database::execute('UPDATE staff_executive_access SET member_id=?,enabled=0,revision=revision+1 WHERE user_id=?',[$member,$id]);
   }
   Database::execute('UPDATE app_users SET member_id=?,session_version=session_version+1 WHERE id=?',[$member,$id]);
   Auth::audit('staff.member_linked','app_user',$id,['previous_member_id'=>$target['member_id'],'member_id'=>$member],(int)$actor['id']);
  });
 }
 public static function guardRemoval(int $member): void {
  if(self::ready()&&Database::scalar('SELECT id FROM app_users WHERE member_id=? LIMIT 1',[$member])){throw new \InvalidArgumentException('This member has a login account. Reassign that account to the correct member before removing membership. Disabled accounts also retain their member link.');}
 }
}
