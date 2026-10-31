<?php
declare(strict_types=1);
namespace App\Security;
use App\{Auth,Config,Database,HttpException,View};
final class SessionSecurity
{
 public static function activity(array $input): void {
  Auth::requireRole(['admin']);
  $category=\App\Validation::choice(['category'=>$input['category']??'all'],'category',['all','auth','security','staff','report']);
  $where="(a.action_code LIKE 'auth.%' OR a.action_code LIKE 'security.%' OR a.action_code LIKE 'staff.%' OR a.action_code LIKE 'report.%' OR a.action_code='executive.records_exported')";$params=[];
  if($category!=='all'){$where.=' AND a.action_code LIKE ?';$params[]=$category.'.%';}
  $total=(int)Database::scalar('SELECT COUNT(*) FROM audit_events a WHERE '.$where,$params);
  $last=max(1,(int)ceil($total/25));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*25;
  $rows=Database::all("SELECT a.action_code,a.occurred_at,a.entity_id,u.name AS actor,INET6_NTOA(a.ip_address) AS ip,JSON_UNQUOTE(JSON_EXTRACT(a.change_summary,'$.records')) AS record_count FROM audit_events a LEFT JOIN app_users u ON u.id=a.actor_user_id WHERE $where ORDER BY a.id DESC LIMIT 25 OFFSET $offset",$params);
  View::render('security/activity/index',compact('rows','category','total','last','p'),'Security activity');
 }
 public static function event(string $action,?int $actor=null,array $summary=[]): void {
  // Logging must not leave a revoked/expired session usable if logging itself fails.
  try{Database::execute('INSERT INTO audit_events(event_uuid,actor_user_id,action_code,entity_type,entity_id,ip_address,change_summary) VALUES(UUID(),?,?,\'security\',?,?,?)',[$actor,$action,$actor,inet_pton($_SERVER['REMOTE_ADDR']??'127.0.0.1')?:null,json_encode($summary,JSON_THROW_ON_ERROR)]);}
  catch(\Throwable $error){error_log('Security event could not be recorded: '.$action);}
 }
 public static function verifyPassword(mixed $password): void {
  $user=Auth::requireLogin();$id=(int)$user['id'];
  $keys=[hash_hmac('sha256','confirm:user:'.$id,Config::key(),true),hash_hmac('sha256','confirm:ip:'.($_SERVER['REMOTE_ADDR']??'local'),Config::key(),true)];
  foreach($keys as $key){if(Database::scalar('SELECT locked_until>UTC_TIMESTAMP() FROM app_login_limits WHERE attempt_key=?',[$key])){self::event('auth.confirmation_blocked',$id);throw new \InvalidArgumentException('Too many password attempts. Try again in 15 minutes.');}}
  $hash=Database::scalar('SELECT password_hash FROM app_users WHERE id=?',[$id]);
  if(!is_string($password)||strlen($password)>128||!password_verify($password,(string)$hash)){
   foreach($keys as $key){Database::execute('INSERT INTO app_login_limits(attempt_key,attempt_count,window_started_at) VALUES(?,1,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE attempt_count=IF(window_started_at<UTC_TIMESTAMP(6)-INTERVAL 15 MINUTE,1,LEAST(attempt_count+1,65535)),window_started_at=IF(window_started_at<UTC_TIMESTAMP(6)-INTERVAL 15 MINUTE,UTC_TIMESTAMP(6),window_started_at),locked_until=IF(attempt_count>=5,UTC_TIMESTAMP(6)+INTERVAL 15 MINUTE,NULL)',[$key]);}
   self::event('auth.confirmation_failed',$id);throw new \InvalidArgumentException('Your current password is incorrect.');
  }
  Database::execute('DELETE FROM app_login_limits WHERE attempt_key=?',[$keys[0]]);
 }
 public static function recent(): bool {
  $window=max(1,min(30,(int)Config::get('REAUTH_MINUTES','10')))*60;
  return isset($_SESSION['password_confirmed_at'])&&time()-(int)$_SESSION['password_confirmed_at']<$window;
 }
 public static function guard(string $action,array $input): void {
  $target=null;
  if(in_array($action,['executive_appointment_create','executive_appointment_update'],true)&&!empty($input['enable_system_access'])){
   Auth::requireRole(['admin']);$params=[];foreach(['member_id','edit_id','replace_id'] as $key){if(!empty($input[$key])){$params[$key]=\App\Validation::id($input,$key);}}$target=['page'=>'executive_appointment_add','params'=>$params];
  }elseif(in_array($action,['appointment_activation_issue','appointment_grant_save'],true)){
   Auth::requireRole(['admin']);$target=['page'=>'executive_account','params'=>['id'=>\App\Validation::id($input,'id')]];
  }
  if(in_array($action,['staff_save','staff_member_link','executive_access_save'],true)){
   Auth::requireRole(['admin']);$target=['page'=>'staff_edit','params'=>empty($input['id'])?[]:['id'=>\App\Validation::id($input,'id')]];
  }elseif($action==='member_remove'){
   Auth::requireRole(['admin','registrar']);$target=['page'=>'member','params'=>['id'=>\App\Validation::id($input,'membership_id')]];
  }elseif($action==='membership_decide'){
   Auth::requireLogin();if(!Auth::admin()&&!\App\Membership\BranchWorkflow::permitted('can_approve_membership')){throw new HttpException(403,'Membership approval access is required.');}
   $target=['page'=>'membership_nomination','params'=>['id'=>\App\Validation::id($input,'id')]];
  }elseif($action==='voter_trash'&&in_array($input['mode']??'',['purge','merge','replace'],true)){
   Auth::requireRole(['admin']);$target=['page'=>'voter_restore','params'=>['id'=>\App\Validation::id($input,'id')]];
  }
  if(!$target||self::recent()){return;}
  $_SESSION['reauth_return']=$target;
  \flash('info','Confirm your password, then review and submit the action again. No changes have been made. Entered form values are not retained.');
  \redirect('security_confirm');
 }
 public static function confirm(): never {
  try{
   self::verifyPassword($_POST['current_password']??null);
   session_regenerate_id(true);$_SESSION['_token']=bin2hex(random_bytes(32));$_SESSION['password_confirmed_at']=time();
   self::event('auth.password_confirmed',(int)Auth::user()['id']);
  }catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());\redirect('security_confirm');}
  $target=$_SESSION['reauth_return']??['page'=>'account','params'=>[]];unset($_SESSION['reauth_return']);
  // Only internally generated, allowlisted GET destinations. Never replay a POST.
  if(!in_array($target['page']??'',['staff_edit','member','voter_restore','account','membership_nomination','executive_appointment_add','executive_account'],true)){$target=['page'=>'account','params'=>[]];}
  \flash('success','Password confirmed. Review the form and submit your intended action.');\redirect($target['page'],$target['params']);
 }
 public static function changePassword(array $input): void {
  self::verifyPassword($input['current_password']??null);$user=Auth::user();$id=(int)$user['id'];
  $password=$input['password']??null;
  if(!is_string($password)||strlen($password)<12||strlen($password)>128||$password!==($input['password_confirmation']??null)){throw new \InvalidArgumentException('Passwords must match and contain 12–128 characters.');}
  $hash=password_hash($password,PASSWORD_ARGON2ID);
  Database::transaction(function()use($id,$hash,$user):void{
   $row=Database::one('SELECT session_version,is_active FROM app_users WHERE id=? FOR UPDATE',[$id]);
   if(!$row||!$row['is_active']||(int)$row['session_version']!==(int)$user['session_version']){throw new HttpException(403,'Your account changed. Sign in again.');}
   Database::execute('UPDATE app_users SET password_hash=?,session_version=session_version+1 WHERE id=?',[$hash,$id]);
   Auth::audit('auth.password_changed','app_user',$id,[], $id);
  });
  $_SESSION['session_version']=(int)$user['session_version']+1;
  session_regenerate_id(true);$_SESSION['_token']=bin2hex(random_bytes(32));$_SESSION['password_confirmed_at']=time();
  Auth::forgetCachedUser();
 }
}
