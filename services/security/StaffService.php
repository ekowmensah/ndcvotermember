<?php
declare(strict_types=1);
namespace App\Security;
use App\{Auth,Database,HttpException,Validation};

final class StaffService
{
 public const ROLES=['viewer'=>'Viewer','registrar'=>'Registrar','admin'=>'Administrator'];
 public static function listing(array $input): array {
  Auth::requireRole(['admin']);
  $q=Validation::text($input,'q',190,false)??'';
  $p=max(1,(int)($input['p']??1));$params=[];$where='';
  if($q!==''){$where=' WHERE name LIKE ? OR email LIKE ?';$params=['%'.$q.'%','%'.$q.'%'];}
  $total=(int)Database::scalar('SELECT COUNT(*) FROM app_users'.$where,$params);
  $last=max(1,(int)ceil($total/25));$p=min($p,$last);$offset=($p-1)*25;
  $accessColumns=ExecutiveAccess::ready()?',ea.access_role AS executive_role,ea.enabled AS executive_enabled':',NULL AS executive_role,NULL AS executive_enabled';
  $accessJoin=ExecutiveAccess::ready()?' LEFT JOIN staff_executive_access ea ON ea.user_id=u.id':'';
  $rows=Database::all('SELECT u.id,u.name,u.email,u.role,u.is_active,u.session_version,u.last_login_at,u.created_at'.$accessColumns.' FROM app_users u'.$accessJoin.$where.' ORDER BY u.name,u.id LIMIT 25 OFFSET '.$offset,$params);
  if($rows&&AppointmentAccounts::ready()){
   $ids=array_column($rows,'id');$marks=implode(',',array_fill(0,count($ids),'?'));
   $effective=Database::all("SELECT p.user_id,COALESCE(MAX(CASE WHEN u.member_id=a.member_id AND ".AppointmentAccounts::currentSql()." THEN g.access_role='reviewer' ELSE 0 END),0) AS reviewer FROM executive_preset_accounts p JOIN app_users u ON u.id=p.user_id LEFT JOIN executive_appointment_access g ON g.user_id=p.user_id LEFT JOIN executive_appointments a ON a.id=g.appointment_id WHERE p.user_id IN ($marks) GROUP BY p.user_id",$ids);
   $byUser=array_column($effective,'reviewer','user_id');foreach($rows as &$row){if(array_key_exists($row['id'],$byUser)){$row['executive_role']=$byUser[$row['id']]?'reviewer':'viewer';}}unset($row);
  }
  return compact('rows','q','total','p','last');
 }
 public static function editor(?int $id): array {
  Auth::requireRole(['admin']);
  $staff=$id?Database::one('SELECT id,name,email,role,is_active,session_version'.(StaffIdentity::ready()?',member_id':',NULL AS member_id').' FROM app_users WHERE id=?',[$id]):null;
  if($id&&!$staff){throw new HttpException(404,'Staff account not found.');}
  $executiveAccount=$id&&ExecutiveAccess::ready()?Database::one('SELECT * FROM staff_executive_access WHERE user_id=?',[$id]):null;
  if($executiveAccount&&AppointmentAccounts::managed($id)){$effective=AppointmentAccounts::effective($id);$executiveAccount['access_role']=$effective['reviewer']?'reviewer':'viewer';}
  return ['staff'=>$staff,'roles'=>self::ROLES,'executiveAccount'=>$executiveAccount];
 }
 public static function save(array $input): void {
  $actor=Auth::requireRole(['admin']);
  $mode=Validation::choice($input,'mode',['create','update','revoke']);
  $id=$mode==='create'?null:Validation::id($input,'id');
  $version=$id?Validation::id($input,'version'):null;
  $name=$mode!=='revoke'?Validation::text($input,'name',150):null;
  $email=$mode!=='revoke'?Validation::email($input,'email'):null;
  $submittedRole=$mode!=='revoke'?Validation::choice($input,'role',array_merge(array_keys(self::ROLES),$mode==='create'?['executive_viewer','executive_reviewer']:[])):null;
  $executiveRole=$submittedRole==='executive_reviewer'?'reviewer':'viewer';
  $role=in_array($submittedRole,['executive_viewer','executive_reviewer'],true)?'viewer':$submittedRole;
  $active=$mode!=='revoke'?Validation::choice($input,'is_active',['0','1']):null;
  $password=$input['password']??'';
  if(!is_string($password)){throw new \InvalidArgumentException('Enter a valid password.');}
  if($mode==='create'&&(strlen($password)<12||strlen($password)>128||$password!==($input['password_confirmation']??null))){throw new \InvalidArgumentException('Passwords must match and contain 12–128 characters.');}
  // Password recovery/reset is deliberately separate from ordinary staff edits.
  $hash=$mode==='create'?password_hash($password,PASSWORD_ARGON2ID):null;
  if($mode==='create'&&!StaffIdentity::ready()){throw new \InvalidArgumentException('Import migration 25 before creating member-linked accounts.');}
  $member=$mode==='create'?Validation::id($input,'member_id'):null;
  $accountType=$mode==='create'?(in_array($submittedRole,['executive_viewer','executive_reviewer'],true)?'executive':Validation::choice(['account_type'=>$input['account_type']??'staff'],'account_type',['staff','executive'])):'staff';
  if($accountType==='executive'&&(!ExecutiveAccess::ready()||$role!=='viewer')){throw new \InvalidArgumentException('Executive accounts require migration 24 and the Viewer staff role.');}
  Database::transaction(function()use($actor,$mode,$id,$version,$name,$email,$role,$active,$hash,$accountType,$member,$executiveRole):void{
   // Serialize account changes and recheck the administrator inside the lock.
   $users=Database::all('SELECT id,name,email,role,is_active,session_version FROM app_users ORDER BY id FOR UPDATE');
   $indexed=array_column($users,null,'id');$admin=$indexed[$actor['id']]??null;
   if(!$admin||!$admin['is_active']||$admin['role']!=='admin'||(int)$admin['session_version']!==(int)$actor['session_version']){throw new HttpException(403,'Your staff permissions changed. Sign in again.');}
   $old=$id?($indexed[$id]??null):null;
   if($id&&(!$old||(int)$old['session_version']!==$version)){throw new \InvalidArgumentException('This account changed. Refresh and review it before saving.');}
   if($mode==='update'&&$id===(int)$actor['id']){throw new \InvalidArgumentException('Your own administrator account is protected. Use My account to change your password.');}
   if($mode==='update'&&$role!=='viewer'&&ExecutiveAccess::ready()&&Database::scalar('SELECT user_id FROM staff_executive_access WHERE user_id=?',[$id])){throw new \InvalidArgumentException('Executive-linked accounts must retain the Viewer staff role. Use executive access settings to choose Viewer or Reviewer.');}
   if($old&&$old['role']==='admin'&&$old['is_active']&&$mode==='update'&&($role!=='admin'||$active!=='1')){
    $others=array_filter($users,static fn($u)=>(int)$u['id']!==$id&&$u['role']==='admin'&&(bool)$u['is_active']);
    if(!$others){throw new \InvalidArgumentException('Keep at least one active administrator.');}
   }
   if($mode==='create'){
    StaffIdentity::member($member);
    Database::execute('INSERT INTO app_users(name,email,password_hash,role,is_active,member_id) VALUES(?,?,?,?,?,?)',[$name,$email,$hash,$role,(int)$active,$member]);
    $saved=(int)Database::connection()->lastInsertId();
    if($accountType==='executive'){Database::execute('INSERT INTO staff_executive_access(user_id,member_id,access_role) VALUES(?,?,?)',[$saved,$member,$executiveRole]);}
    Auth::audit('staff.created','app_user',$saved,['role'=>$role,'account_type'=>$accountType,'executive_role'=>$accountType==='executive'?$executiveRole:null,'is_active'=>(int)$active],(int)$actor['id']);
   }elseif($mode==='revoke'){
    Database::execute('UPDATE app_users SET session_version=session_version+1 WHERE id=?',[$id]);
    Auth::audit('staff.sessions_revoked','app_user',$id,[],(int)$actor['id']);
   }else{
    Database::execute('UPDATE app_users SET name=?,email=?,role=?,is_active=?,session_version=session_version+1 WHERE id=?',[$name,$email,$role,(int)$active,$id]);
    Auth::audit('staff.updated','app_user',$id,['before'=>['role'=>$old['role'],'is_active'=>(int)$old['is_active']],'after'=>['role'=>$role,'is_active'=>(int)$active]],(int)$actor['id']);
   }
  });
 }
}
