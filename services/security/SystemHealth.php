<?php
declare(strict_types=1);
namespace App\Security;
use App\{Auth,Config,Database,View};
final class SystemHealth
{
 public static function show(): void {
  Auth::requireRole(['admin']);$checks=[];
  $add=static function(string $name,string $state,string $detail)use(&$checks):void{$checks[]=compact('name','state','detail');};
  foreach(['pdo_mysql','mbstring','sodium','fileinfo','gd'] as $extension){$found=extension_loaded($extension);$add('PHP extension: '.$extension,$found?'pass':'action',$found?'Available':'Enable this extension in Laragon PHP settings.');}
  $add('PHP session cookies',ini_get('session.use_strict_mode')&&ini_get('session.cookie_httponly')?'pass':'action','Strict session IDs and HttpOnly cookies should be enabled.');
  $https=strtolower((string)parse_url(Config::baseUrl(),PHP_URL_SCHEME))==='https';
  $add('HTTPS configuration',$https?'pass':'review',$https?'APP_URL uses HTTPS. Verify the certificate and actual connection manually.':'HTTP is configured. Use HTTPS before exposing the system beyond trusted local development.');
  $add('Error display',filter_var(ini_get('display_errors'),FILTER_VALIDATE_BOOLEAN)?'action':'pass','Detailed errors must stay off in browser responses.');
  $add('Storage access',is_writable(BASE_PATH.'/storage')?'pass':'action','The application needs write access to storage for private logs.');
  $add('Database connection','pass','This page reached the database. No application records were modified.');
  $tables=Database::all('SELECT table_name,column_name FROM information_schema.columns WHERE table_schema=DATABASE()');$columns=[];
  foreach($tables as $row){$columns[$row['TABLE_NAME']??$row['table_name']][]=$row['COLUMN_NAME']??$row['column_name'];}
  foreach(['Member identity'=>['app_users','member_id'],'Executive access'=>['staff_executive_access','enabled'],'Review flags'=>['record_review_flags','note_encrypted'],'Workflow permissions'=>['executive_workflow_permissions','can_nominate'],'Membership nominations'=>['membership_nominations','pending_flag']] as $label=>[$table,$column]){
   $exists=in_array($column,$columns[$table]??[],true);$add($label,$exists?'pass':'action',$exists?'Required column found. This is a structural check, not a full integrity test.':'Required structure missing. Review the corresponding manual migration.');
  }
  $default=Database::scalar("SELECT column_default FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='app_users' AND column_name='role'");
  $add('Least-privilege database default',$default==='viewer'?'pass':'action',$default==='viewer'?'New inserts without a role default to Viewer.':'Review migration 23; existing account roles must be preserved.');
  if(StaffIdentity::ready()){$unlinked=(int)Database::scalar('SELECT COUNT(*) FROM app_users WHERE member_id IS NULL');$add('Legacy account member links',$unlinked?'review':'pass',$unlinked?$unlinked.' existing account(s) still need deliberate member linking.':'All login accounts have a member link.');}
  $runtimeUser=(string)Database::scalar('SELECT CURRENT_USER()');
  $add('Database account',str_starts_with(strtolower($runtimeUser),'root@')?'action':'review',str_starts_with(strtolower($runtimeUser),'root@')?'The application uses a root database account. Configure a dedicated limited account.':'Verify database grants manually. A non-root name alone does not establish least privilege.');
  $add('Private-file HTTP protection','manual','Cannot be verified from PHP file contents. Check the private URLs listed in the operations guide using Laragon.');
  $add('Backups and restore readiness','manual','Not verified. Confirm encrypted off-machine copies, separate key storage and a successful isolated restore. No backup or restore is performed by this page.');
  View::render('security/health/index',compact('checks'),'System health');
 }
}
