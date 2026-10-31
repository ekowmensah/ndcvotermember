<?php
declare(strict_types=1);
namespace App\Membership;
use App\{Auth,Database,Validation,HttpException,Crypto,View,AddressService,TownService};
use App\Security\ExecutiveAccess;
final class BranchWorkflow
{
 public static function ready(): bool {
  static $ready=null;return $ready??=((int)Database::scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('executive_workflow_permissions','membership_nominations')")===2);
 }
 public static function permissions(?int $user=null): array {
  $user??=(int)Auth::user()['id'];if(\App\Security\AppointmentAccounts::managed($user)){return \App\Security\AppointmentAccounts::effective($user);}
  return self::ready()?Database::one('SELECT * FROM executive_workflow_permissions WHERE user_id=?',[$user])??[]:[];
 }
 public static function permitted(string $permission): bool {
  return ExecutiveAccess::restricted()&&self::ready()&&ExecutiveAccess::account()['access_role']==='reviewer'&&!empty(self::permissions()[$permission])&&!empty(ExecutiveAccess::account()['enabled']);
 }
 private static function requirePermission(string $permission): void {
  Auth::requireLogin();if(!self::permitted($permission)){throw new HttpException(403,'This executive permission has not been granted.');}
 }
 private static function actorLocked(string $permission): void {
  self::requirePermission($permission);$actor=Auth::user();$row=Database::one('SELECT is_active,session_version,member_id FROM app_users WHERE id=? FOR UPDATE',[$actor['id']]);
  $access=Database::one('SELECT * FROM staff_executive_access WHERE user_id=? FOR UPDATE',[$actor['id']]);
  $perms=Database::one('SELECT * FROM executive_workflow_permissions WHERE user_id=? FOR UPDATE',[$actor['id']]);
  if(\App\Security\AppointmentAccounts::managed((int)$actor['id'])){$perms=\App\Security\AppointmentAccounts::effective((int)$actor['id'],true);if($access){$access['access_role']=$perms['reviewer']?'reviewer':'viewer';}}
  if(!$row||!$row['is_active']||(int)$row['session_version']!==(int)$actor['session_version']||!$access||!$access['enabled']||$access['access_role']!=='reviewer'||!$perms||empty($perms[$permission])||!$row['member_id']||(int)$row['member_id']!==(int)$access['member_id']){throw new HttpException(403,'Your access changed. Sign in and review again.');}
  $person=Database::scalar('SELECT voter_record_id FROM members WHERE id=?',[$row['member_id']]);
  if(!$person){throw new HttpException(403,'Member identity unavailable.');}
  Database::one('SELECT id FROM voters WHERE id=? FOR UPDATE',[$person]);
  Database::one('SELECT id FROM members WHERE id=? FOR UPDATE',[$row['member_id']]);
  Database::all('SELECT id FROM executive_appointments WHERE member_id=? ORDER BY id FOR UPDATE',[$row['member_id']]);
  if(\App\Security\AppointmentAccounts::managed((int)$actor['id'])){Database::all('SELECT appointment_id FROM executive_appointment_access WHERE user_id=? FOR UPDATE',[$actor['id']]);if(empty(self::permissions()[$permission])){throw new HttpException(403,'Appointment permission ended.');}}
 }
 private static function scope(string $level,?string $capability=null): string {
  if($level==='nomination'){return '('.ExecutiveAccess::condition('v','branch','can_nominate').') OR ('.ExecutiveAccess::condition('v','polling_station','can_nominate').')';}
  return ExecutiveAccess::condition('v',$level,$capability??($level==='branch'?'can_edit_voter_contacts':'can_approve_membership'));
 }
 private static function target(int $id,string $level,bool $lock=false,bool $reject=false): array {
  $row=Database::one('SELECT v.*,m.id AS membership_id FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id WHERE v.id=?'.($lock?' FOR UPDATE':''),[$id]);
  if(!$row||($row['deleted_at']&&!($reject&&Auth::admin()))||($row['is_deceased']&&!$reject)){throw new HttpException(404,'Voter unavailable.');}
  if($row['membership_id']&&!$reject){throw new \InvalidArgumentException('This person is already a member. Use the member workflow.');}
  if($lock&&$row['polling_station_id']){Database::one('SELECT id FROM polling_stations WHERE id=? FOR UPDATE',[$row['polling_station_id']]);}
  $scope=self::scope($level);
  if(!Database::scalar('SELECT v.id FROM voters v WHERE v.id=? AND ('.$scope.')'.($lock?' FOR UPDATE':''),[$id])){throw new HttpException(403,'Voter is outside your current '.$level.' jurisdiction.');}
  return $row;
 }
 public static function canOnPerson(int $id,string $permission,string $level): bool {
  if(!self::permitted($permission)){return false;}
  $where=self::scope($level);
  return (bool)Database::scalar('SELECT v.id FROM voters v WHERE v.id=? AND v.deleted_at IS NULL AND v.is_deceased=0 AND NOT EXISTS(SELECT 1 FROM members m WHERE m.voter_record_id=v.id) AND ('.$where.')',[$id]);
 }
 public static function editPage(int $id): void {
  if(!AddressService::ready()||!TownService::ready()){throw new \InvalidArgumentException('Complete residential migrations through 12 first.');}
  self::requirePermission('can_edit_voter_contacts');if(!ExecutiveAccess::contacts()){throw new HttpException(403,'Contact access is also required.');}
  $person=self::target($id,'branch');
  $values=['address'=>Crypto::decrypt($person['address_encrypted']),'town'=>$person['town']??'',
   'residential_region'=>Database::scalar('SELECT name FROM residential_regions WHERE id=?',[$person['residential_region_id']])??'',
   'residential_district'=>Database::scalar('SELECT name FROM districts WHERE id=?',[$person['residential_district_id']])??''];
  View::render('membership/nominations/edit_voter',compact('person','values'),'Update voter contact details');
 }
 public static function saveContact(array $input): void {
  if(!AddressService::ready()||!TownService::ready()){throw new \InvalidArgumentException('Complete residential migrations through 12 first.');}
  $id=Validation::id($input,'voter_id');$version=Validation::text($input,'voter_version',30);
  $phone=Validation::text($input,'phone',40,false);$email=Validation::email($input,'email',false);$address=Validation::text($input,'address',1000,false);
  Database::transaction(function()use($input,$id,$version,$phone,$email,$address):void{
   self::actorLocked('can_edit_voter_contacts');if(!ExecutiveAccess::contacts()){throw new HttpException(403,'Contact access is required.');}
   $person=self::target($id,'branch',true);
   if($person['updated_at']!==$version){throw new \InvalidArgumentException('Voter details changed. Refresh before editing.');}
   $residence=AddressService::selection($input);
   $town=TownService::resolve($residence['town'],$residence['residential_region_id'],$residence['residential_district_id']);
   Database::execute('UPDATE voters SET phone_encrypted=?,email_encrypted=?,address_encrypted=?,town=?,residential_region_id=?,residential_district_id=?,residential_town_id=?,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[Crypto::encrypt($phone),Crypto::encrypt($email),Crypto::encrypt($address),$town['name']??null,$residence['residential_region_id'],$residence['residential_district_id'],$town['id']??null,$id]);
   Auth::audit('voter.branch_contacts_updated','voter',$id,['fields'=>['phone','email','address','residential_region','residential_district','town']]);
  });
 }
 public static function nominate(array $input): void {
  $id=Validation::id($input,'voter_id');$version=Validation::text($input,'voter_version',30);$note=Validation::text($input,'note',1000,false);
  Database::transaction(function()use($id,$version,$note):void{
   self::actorLocked('can_nominate');$person=self::target($id,'nomination',true);
   if($person['updated_at']!==$version){throw new \InvalidArgumentException('Voter details changed. Refresh before nominating.');}
   if(Database::scalar("SELECT id FROM membership_nominations WHERE voter_record_id=? AND status='pending'",[$id])){throw new \InvalidArgumentException('This voter already has a pending membership nomination.');}
   $constituency=Database::scalar('SELECT constituency_id FROM polling_stations WHERE id=?',[$person['polling_station_id']]);
   Database::execute('INSERT INTO membership_nominations(voter_record_id,voter_name_snapshot,constituency_id_snapshot,nominated_by_user_id,note_encrypted) VALUES(?,?,?,?,?)',[$id,$person['full_name'],$constituency,Auth::user()['id'],Crypto::encrypt($note)]);
   Auth::audit('membership.nominated','membership_nomination',(int)Database::connection()->lastInsertId(),['voter_id'=>$id]);
  });
 }
 private static function nominationScope(): string {
  if(Auth::admin()){return '1=1';}
  $parts=[];
  if(self::permitted('can_approve_membership')){$parts[]='('.ExecutiveAccess::condition('v','constituency').')';}
  if(self::permitted('can_nominate')){$parts[]='(n.nominated_by_user_id='.(int)Auth::user()['id'].' AND ('.self::scope('nomination').'))';}
  return $parts?'v.deleted_at IS NULL AND ('.implode(' OR ',$parts).')':'1=0';
 }
 public static function listing(array $input): void {
  Auth::requireLogin();if(!self::ready()){throw new \InvalidArgumentException('Import migration 26 in phpMyAdmin first.');}
  if(!Auth::admin()&&!self::permitted('can_nominate')&&!self::permitted('can_approve_membership')){throw new HttpException(403,'Nomination access is required.');}
  $status=Validation::choice(['status'=>$input['status']??'pending'],'status',['pending','approved','rejected','superseded']);
  $from=' FROM membership_nominations n LEFT JOIN voters v ON v.id=n.voter_record_id';$where='n.status=? AND ('.self::nominationScope().')';
  $total=(int)Database::scalar('SELECT COUNT(*)'.$from.' WHERE '.$where,[$status]);$last=max(1,(int)ceil($total/25));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*25;
  $rows=Database::all('SELECT n.id,n.voter_name_snapshot,n.created_at,n.status'.$from.' WHERE '.$where.' ORDER BY n.id DESC LIMIT 25 OFFSET '.$offset,[$status]);
  View::render('membership/nominations/index',compact('rows','status','total','p','last'),'Membership nominations');
 }
 public static function review(int $id): void {
  Auth::requireLogin();if(!self::ready()){throw new \InvalidArgumentException('Import migration 26 first.');}
  $row=Database::one('SELECT n.*,v.full_name,v.voter_id,v.updated_at AS voter_version,u.name AS nominated_by,ps.constituency_id AS current_constituency_id,c.name AS current_constituency,ps.name AS current_station,b.name AS current_branch FROM membership_nominations n LEFT JOIN voters v ON v.id=n.voter_record_id LEFT JOIN polling_stations ps ON ps.id=v.polling_station_id LEFT JOIN constituencies c ON c.id=ps.constituency_id LEFT JOIN electoral_areas b ON b.id=ps.electoral_area_id JOIN app_users u ON u.id=n.nominated_by_user_id WHERE n.id=? AND ('.self::nominationScope().')',[$id])??throw new HttpException(404,'Nomination unavailable.');
  $canApprove=Auth::admin()||($row['voter_record_id']&&self::canOnPerson((int)$row['voter_record_id'],'can_approve_membership','constituency'));
  $canApprove=$canApprove&&(int)$row['nominated_by_user_id']!==(int)Auth::user()['id'];
  View::render('membership/nominations/show',compact('row','canApprove'),'Review membership nomination');
 }
 public static function decide(array $input): void {
  if(!self::ready()){throw new \InvalidArgumentException('Import migration 26 first.');}
  $id=Validation::id($input,'id');$revision=Validation::id($input,'revision');$decision=Validation::choice($input,'decision',['approve','reject']);$note=Validation::text($input,'decision_note',1000,$decision==='reject');
  Database::transaction(function()use($input,$id,$revision,$decision,$note):void{
   if(Auth::admin()){
    $actor=Auth::user();$account=Database::one('SELECT role,is_active,session_version FROM app_users WHERE id=? FOR UPDATE',[$actor['id']]);
    if(!$account||!$account['is_active']||$account['role']!=='admin'||(int)$account['session_version']!==(int)$actor['session_version']){throw new HttpException(403,'Administrator access changed.');}
   }else{self::actorLocked('can_approve_membership');}
   $personId=Database::scalar('SELECT voter_record_id FROM membership_nominations WHERE id=?',[$id]);
   if(!$personId&&!($decision==='reject'&&Auth::admin())){throw new \InvalidArgumentException('The nominated voter is unavailable.');}
   $person=$personId?self::target((int)$personId,'constituency',true,$decision==='reject'):null;
   $row=Database::one('SELECT * FROM membership_nominations WHERE id=? FOR UPDATE',[$id]);
   if(!$row||$row['status']!=='pending'||(int)$row['revision']!==$revision){throw new \InvalidArgumentException('Nomination already decided or changed. Refresh the review.');}
   if((int)$row['nominated_by_user_id']===(int)Auth::user()['id']){throw new \InvalidArgumentException('Another authorized reviewer must decide your nomination.');}
   if($person&&Validation::text($input,'voter_version',30)!==$person['updated_at']){throw new \InvalidArgumentException('Voter details changed. Review the latest record before deciding.');}
   $member=$decision==='approve'?EnrollmentWriter::create((int)$personId,$input,$id):null;
   Database::execute('UPDATE membership_nominations SET status=?,membership_id=?,decision_note_encrypted=?,decided_by_user_id=?,decided_at=UTC_TIMESTAMP(6),revision=revision+1 WHERE id=?',[$decision==='approve'?'approved':'rejected',$member,Crypto::encrypt($note),Auth::user()['id'],$id]);
   Auth::audit('membership.nomination_'.$decision,'membership_nomination',$id,['membership_id'=>$member]);
  });
 }
}
