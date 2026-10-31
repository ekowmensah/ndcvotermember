<?php
declare(strict_types=1);
namespace App\Security;
use App\{Auth,Database,Validation,HttpException,View,Crypto};
final class AppointmentAccounts
{
 public const CAPABILITIES=['can_contacts','can_edit_voter_contacts','can_nominate','can_approve_membership'];
 public static function ready(): bool {
  static $ready=null;return $ready??=(StaffIdentity::ready()&&ExecutiveAccess::ready()&&(int)Database::scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('executive_preset_accounts','executive_appointment_access','staff_account_activations')")===3);
 }
 public static function managed(int $user): bool {return self::ready()&&(bool)Database::scalar('SELECT user_id FROM executive_preset_accounts WHERE user_id=?',[$user]);}
 public static function defaults(string $level,string $organization): array {
  $branch=$organization==='main'&&$level==='branch';$station=$organization==='main'&&$level==='polling_station';$const=$organization==='main'&&$level==='constituency';
  return ['access_role'=>$branch||$station||$const?'reviewer':'viewer','can_contacts'=>(int)$branch,'can_edit_voter_contacts'=>(int)$branch,'can_nominate'=>(int)($branch||$station),'can_approve_membership'=>(int)$const];
 }
 public static function currentSql(): string {
  return "a.ended_on IS NULL AND a.cancelled_at IS NULL AND a.starts_on<=UTC_DATE() AND g.enabled=1 AND (a.organization_scope='main' OR EXISTS(SELECT 1 FROM member_wing_enrollments w WHERE w.member_id=a.member_id AND w.wing_code=a.organization_scope AND w.ended_on IS NULL))";
 }
 public static function effective(int $user,bool $lock=false): array {
  $rows=Database::all('SELECT g.access_role,g.can_contacts,g.can_edit_voter_contacts,g.can_nominate,g.can_approve_membership FROM executive_appointment_access g JOIN executive_appointments a ON a.id=g.appointment_id JOIN app_users u ON u.id=g.user_id AND u.member_id=a.member_id WHERE g.user_id=? AND '.self::currentSql().($lock?' FOR UPDATE':''),[$user]);
  $result=['reviewer'=>0,'can_contacts'=>0,'can_edit_voter_contacts'=>0,'can_nominate'=>0,'can_approve_membership'=>0];
  foreach($rows as $row){$result['reviewer']|=(int)($row['access_role']==='reviewer');foreach(self::CAPABILITIES as $cap){if($cap==='can_contacts'||$row['access_role']==='reviewer'){$result[$cap]|=(int)$row[$cap];}}}return $result;
 }
 // Caller owns the appointment transaction. No grants for existing manual accounts.
 public static function provision(int $appointment,array $input): void {
  if(empty($input['enable_system_access'])){return;}
  Auth::requireRole(['admin']);if(!self::ready()){throw new \InvalidArgumentException('Import migration 27 before enabling appointment-linked accounts.');}
  if(!SessionSecurity::recent()){throw new \InvalidArgumentException('Confirm your password before provisioning executive access.');}
  $actor=Auth::user();$admin=Database::one('SELECT role,is_active,session_version FROM app_users WHERE id=? FOR UPDATE',[$actor['id']]);
  if(!$admin||!$admin['is_active']||$admin['role']!=='admin'||(int)$admin['session_version']!==(int)$actor['session_version']){throw new HttpException(403,'Administrator access changed.');}
  $office=Database::one('SELECT a.*,v.full_name FROM executive_appointments a JOIN members m ON m.id=a.member_id JOIN voters v ON v.id=m.voter_record_id WHERE a.id=?',[$appointment])??throw new \InvalidArgumentException('Appointment unavailable.');
  $user=Database::one('SELECT id,role FROM app_users WHERE member_id=? FOR UPDATE',[$office['member_id']]);
  if(!$user){
   $email=Validation::email($input,'account_email');
   Database::execute("INSERT INTO app_users(name,email,password_hash,role,is_active,member_id) VALUES(?,?,?,'viewer',0,?)",[$office['full_name'],$email,password_hash(bin2hex(random_bytes(32)),PASSWORD_ARGON2ID),$office['member_id']]);
   $id=(int)Database::connection()->lastInsertId();
   Database::execute("INSERT INTO staff_executive_access(user_id,member_id,enabled) VALUES(?,?,1)",[$id,$office['member_id']]);
   Database::execute('INSERT INTO executive_preset_accounts(user_id) VALUES(?)',[$id]);
   $user=['id'=>$id,'role'=>'viewer'];Auth::audit('staff.provisioned_from_appointment','app_user',$id,['appointment_id'=>$appointment]);
  }elseif(!self::managed((int)$user['id'])){
   Auth::audit('staff.existing_account_reused','app_user',(int)$user['id'],['appointment_id'=>$appointment,'permissions_preserved'=>true]);return;
  }
  $old=Database::one('SELECT * FROM executive_appointment_access WHERE appointment_id=? FOR UPDATE',[$appointment]);
  // Editing the same office never silently replaces custom grants.
  if($old&&(int)$old['user_id']===(int)$user['id']){return;}
  $grant=self::defaults($office['organization_level'],$office['organization_scope']);
  Database::execute('INSERT INTO executive_appointment_access(appointment_id,user_id,access_role,can_contacts,can_edit_voter_contacts,can_nominate,can_approve_membership) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),access_role=VALUES(access_role),can_contacts=VALUES(can_contacts),can_edit_voter_contacts=VALUES(can_edit_voter_contacts),can_nominate=VALUES(can_nominate),can_approve_membership=VALUES(can_approve_membership),enabled=1,revision=revision+1',[$appointment,$user['id'],$grant['access_role'],$grant['can_contacts'],$grant['can_edit_voter_contacts'],$grant['can_nominate'],$grant['can_approve_membership']]);
  Auth::audit('staff.appointment_preset_assigned','app_user',(int)$user['id'],['appointment_id'=>$appointment,'level'=>$office['organization_level']]);
 }
 public static function page(int $id): void {
  Auth::requireRole(['admin']);if(!self::managed($id)){throw new \InvalidArgumentException('This account uses manually managed access.');}
  $staff=Database::one('SELECT id,name,email,is_active,member_id FROM app_users WHERE id=?',[$id]);
  $rows=Database::all('SELECT g.*,a.position_name_snapshot,a.organization_level,a.organization_scope,a.jurisdiction_snapshot,a.starts_on,a.ended_on,a.cancelled_at FROM executive_appointment_access g JOIN executive_appointments a ON a.id=g.appointment_id WHERE g.user_id=? ORDER BY a.id DESC',[$id]);
  $receipt=null;if(isset($_SESSION['activation_receipt'])){$receipt=json_decode(Crypto::decrypt($_SESSION['activation_receipt']),true);unset($_SESSION['activation_receipt']);if((int)($receipt['user_id']??0)!==$id){$receipt=null;}}
  View::render('security/provisioning/show',compact('staff','rows','receipt'),'Appointment-linked access');
 }
 public static function reconcileEditedGrant(int $appointment,array $before): void {
  if(!self::ready()){return;}
  $row=Database::one('SELECT g.user_id,a.member_id,a.organization_level,a.organization_scope FROM executive_appointment_access g JOIN executive_appointments a ON a.id=g.appointment_id WHERE g.appointment_id=? FOR UPDATE',[$appointment]);
  if(!$row){return;}
  if((int)$row['member_id']!==(int)$before['member_id']){Database::execute('UPDATE executive_appointment_access SET enabled=0,revision=revision+1 WHERE appointment_id=?',[$appointment]);return;}
  if($row['organization_level']!==$before['organization_level']||$row['organization_scope']!==$before['organization_scope']){
   $grant=self::defaults($row['organization_level'],$row['organization_scope']);Database::execute('UPDATE executive_appointment_access SET access_role=?,can_contacts=?,can_edit_voter_contacts=?,can_nominate=?,can_approve_membership=?,revision=revision+1 WHERE appointment_id=?',[$grant['access_role'],$grant['can_contacts'],$grant['can_edit_voter_contacts'],$grant['can_nominate'],$grant['can_approve_membership'],$appointment]);
   Auth::audit('staff.appointment_preset_reconciled','app_user',(int)$row['user_id'],['appointment_id'=>$appointment]);
  }
 }
 public static function saveGrant(array $input): void {
  Auth::requireRole(['admin']);$id=Validation::id($input,'appointment_id');$revision=Validation::id($input,'revision');$role=Validation::choice($input,'access_role',['viewer','reviewer']);
  Database::transaction(function()use($input,$id,$revision,$role):void{
   self::lockAdmin();$row=Database::one('SELECT g.*,a.organization_scope,a.organization_level FROM executive_appointment_access g JOIN executive_appointments a ON a.id=g.appointment_id WHERE g.appointment_id=? FOR UPDATE',[$id]);
   if(!$row||(int)$row['revision']!==$revision){throw new \InvalidArgumentException('Appointment access changed. Refresh before saving.');}
   if((int)$row['user_id']!==Validation::id($input,'id')){throw new \InvalidArgumentException('Account and appointment access do not match. Reopen the account.');}
   $grant=self::defaults($row['organization_level'],$row['organization_scope']);$values=[];
   foreach(self::CAPABILITIES as $cap){$value=isset($input[$cap]);if($value&&($cap!=='can_contacts'&&($role!=='reviewer'||!$grant[$cap]))){throw new \InvalidArgumentException('This capability is not permitted for this office and access role.');}$values[]=(int)$value;}
   if($values[1]&&!$values[0]){throw new \InvalidArgumentException('Contact editing also requires contact access.');}
   Database::execute('UPDATE executive_appointment_access SET access_role=?,enabled=?,can_contacts=?,can_edit_voter_contacts=?,can_nominate=?,can_approve_membership=?,revision=revision+1 WHERE appointment_id=?',[$role,isset($input['enabled'])?1:0,...$values,$id]);
   Database::execute('UPDATE app_users SET session_version=session_version+1 WHERE id=?',[$row['user_id']]);
   Auth::audit('staff.appointment_grants_updated','app_user',(int)$row['user_id'],['appointment_id'=>$id]);
  });
 }
 private static function lockAdmin(): void {
  $actor=Auth::requireRole(['admin']);$row=Database::one('SELECT role,is_active,session_version FROM app_users WHERE id=? FOR UPDATE',[$actor['id']]);
  if(!$row||!$row['is_active']||$row['role']!=='admin'||(int)$row['session_version']!==(int)$actor['session_version']){throw new HttpException(403,'Administrator access changed.');}
 }
 public static function issue(int $id): void {
  Auth::requireRole(['admin']);$token=bin2hex(random_bytes(32));
  Database::transaction(function()use($id,$token):void{
   self::lockAdmin();$row=Database::one('SELECT id,is_active,session_version FROM app_users WHERE id=? FOR UPDATE',[$id]);
   if(!$row||!self::managed($id)||$row['is_active']||Database::scalar('SELECT used_at FROM staff_account_activations WHERE user_id=?',[$id])){throw new \InvalidArgumentException('Activation is only for a new, unactivated appointment-linked account.');}
   Database::execute('INSERT INTO staff_account_activations(user_id,token_hash,session_version,expires_at) VALUES(?,?,?,UTC_TIMESTAMP()+INTERVAL 48 HOUR) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),session_version=VALUES(session_version),expires_at=VALUES(expires_at),used_at=NULL',[$id,hash('sha256',$token,true),$row['session_version']]);
   Auth::audit('staff.activation_issued','app_user',$id);
  });
  $_SESSION['activation_receipt']=Crypto::encrypt(json_encode(['user_id'=>$id,'link'=>\url('activate_account').'#token='.$token],JSON_THROW_ON_ERROR));
 }
 public static function activate(array $input): void {
  if(!self::ready()){throw new \InvalidArgumentException('Activation unavailable.');}
  $limitKey=hash_hmac('sha256','activation:ip:'.($_SERVER['REMOTE_ADDR']??'local'),\App\Config::key(),true);
  if(Database::scalar('SELECT locked_until>UTC_TIMESTAMP() FROM app_login_limits WHERE attempt_key=?',[$limitKey])){throw new \InvalidArgumentException('Too many activation attempts. Try again in 15 minutes.');}
  Database::execute('INSERT INTO app_login_limits(attempt_key,attempt_count,window_started_at) VALUES(?,1,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE attempt_count=IF(window_started_at<UTC_TIMESTAMP(6)-INTERVAL 15 MINUTE,1,LEAST(attempt_count+1,65535)),window_started_at=IF(window_started_at<UTC_TIMESTAMP(6)-INTERVAL 15 MINUTE,UTC_TIMESTAMP(6),window_started_at),locked_until=IF(attempt_count>=10,UTC_TIMESTAMP(6)+INTERVAL 15 MINUTE,NULL)',[$limitKey]);
  $token=Validation::text($input,'activation_token',64);if(!preg_match('/\A[a-f0-9]{64}\z/D',$token)){throw new \InvalidArgumentException('Invalid or expired activation.');}
  $password=$input['password']??null;if(!is_string($password)||strlen($password)<12||strlen($password)>128||$password!==($input['password_confirmation']??null)){throw new \InvalidArgumentException('Passwords must match and contain 12–128 characters.');}
  $hash=password_hash($password,PASSWORD_ARGON2ID);$tokenHash=hash('sha256',$token,true);
  Database::transaction(function()use($hash,$tokenHash):void{
   $lookup=Database::one('SELECT user_id FROM staff_account_activations WHERE token_hash=?',[$tokenHash]);if(!$lookup){throw new \InvalidArgumentException('Invalid or expired activation.');}
   $user=Database::one('SELECT id,is_active,member_id,session_version FROM app_users WHERE id=? FOR UPDATE',[$lookup['user_id']]);
   $activation=Database::one('SELECT used_at,session_version,expires_at>UTC_TIMESTAMP() AS valid FROM staff_account_activations WHERE user_id=? AND token_hash=? FOR UPDATE',[$lookup['user_id'],$tokenHash]);
   if(!$user||$user['is_active']||!$activation||$activation['used_at']||!$activation['valid']||(int)$activation['session_version']!==(int)$user['session_version']||!StaffIdentity::canSignIn((int)$user['id'])){throw new \InvalidArgumentException('Invalid or expired activation.');}
   Database::execute('UPDATE app_users SET password_hash=?,is_active=1,session_version=session_version+1 WHERE id=?',[$hash,$user['id']]);Database::execute('UPDATE staff_account_activations SET used_at=UTC_TIMESTAMP(6) WHERE user_id=?',[$user['id']]);
   Auth::audit('staff.account_activated','app_user',(int)$user['id'],[],(int)$user['id']);
  });
 }
}
