<?php
declare(strict_types=1);
namespace App\Security;
use App\{Auth,Database,HttpException,Validation,View,Crypto};
use App\Executives\OrganizationService;

final class ExecutiveAccess
{
 public static function ready(): bool {
  static $ready=null;
  return $ready??=((int)Database::scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('staff_executive_access','record_review_flags')")===2);
 }
 public static function account(): ?array {
  static $loaded=false,$account=null;
  if(!$loaded){$loaded=true;$user=Auth::user();if($user&&self::ready()){$account=Database::one('SELECT * FROM staff_executive_access WHERE user_id=?',[$user['id']]);if($account&&AppointmentAccounts::managed((int)$user['id'])){$effective=AppointmentAccounts::effective((int)$user['id']);$account['access_role']=$effective['reviewer']?'reviewer':'viewer';$account['can_contacts']=$effective['can_contacts'];$account['preset_managed']=true;}}}
  return $account;
 }
 public static function restricted(): bool {return self::account()!==null;}
 public static function roleLabel(): string {
  $account=self::account();
  return $account?'Executive '.ucfirst($account['access_role']):(StaffService::ROLES[Auth::user()['role']]??'Staff');
 }
 public static function contacts(?int $voter=null): bool {
  if(!self::restricted()){return true;}
  if(empty(self::account()['can_contacts'])){return false;}
  if($voter!==null&&!empty(self::account()['preset_managed'])){return (bool)Database::scalar('SELECT v.id FROM voters v WHERE v.id=? AND ('.self::condition('v',null,'can_contacts').')',[$voter]);}
  return true;
 }
 public static function canReview(int $voter): bool {
  if(!self::restricted()||self::account()['access_role']!=='reviewer'){return false;}
  return (bool)Database::scalar('SELECT v.id FROM voters v WHERE v.id=? AND v.deleted_at IS NULL AND ('.self::condition('v',null,'review').')',[$voter]);
 }
 public static function condition(string $alias='v',?string $level=null,?string $capability=null): string {
  if(!preg_match('/\A[a-z]+\z/D',$alias)){throw new \LogicException('Invalid internal alias.');}
  $account=self::account();if(!$account){return '1=1';}
  if(!$account['enabled']||!$account['member_id']||!OrganizationService::ready()){return '1=0';}
  $member=(int)$account['member_id'];
  if($level!==null&&!in_array($level,['branch','constituency','polling_station'],true)){throw new \LogicException('Invalid permission level.');}
  $office=$level!==null?" AND ea.organization_scope='main' AND ea.organization_level='$level'":'';
  if(!empty($account['preset_managed'])){
   if($capability!==null&&!in_array($capability,[...AppointmentAccounts::CAPABILITIES,'review'],true)){throw new \LogicException('Invalid internal capability.');}
   $review=$capability!==null&&$capability!=='can_contacts'?" AND ag.access_role='reviewer'":'';
   $permission=$capability!==null&&$capability!=='review'?" AND ag.$capability=1":'';
   $office.=' AND EXISTS(SELECT 1 FROM executive_appointment_access ag WHERE ag.appointment_id=ea.id AND ag.user_id='.(int)$account['user_id'].' AND ag.enabled=1'.$permission.$review.')';
  }
  // Derive geography from the actual polling station, including optional branch.
  return "EXISTS(SELECT 1 FROM executive_appointments ea JOIN members em ON em.id=ea.member_id JOIN voters ev ON ev.id=em.voter_record_id WHERE em.id=$member$office AND em.status='active' AND ev.deleted_at IS NULL AND ev.is_deceased=0 AND ea.cancelled_at IS NULL AND ea.ended_on IS NULL AND ea.starts_on<=UTC_DATE() AND (ea.organization_scope='main' OR EXISTS(SELECT 1 FROM member_wing_enrollments aw WHERE aw.member_id=em.id AND aw.wing_code=ea.organization_scope AND aw.ended_on IS NULL)) AND (ea.organization_scope='main' OR EXISTS(SELECT 1 FROM members tm JOIN member_wing_enrollments tw ON tw.member_id=tm.id WHERE tm.voter_record_id=$alias.id AND tw.wing_code=ea.organization_scope AND tw.ended_on IS NULL)) AND (ea.organization_level='national' OR EXISTS(SELECT 1 FROM polling_stations sp JOIN constituencies sc ON sc.id=sp.constituency_id WHERE sp.id=$alias.polling_station_id AND ((ea.organization_level='region' AND ea.jurisdiction_id=sc.region_id) OR (ea.organization_level='constituency' AND ea.jurisdiction_id=sc.id) OR (ea.organization_level='branch' AND ea.jurisdiction_id=sp.electoral_area_id) OR (ea.organization_level='polling_station' AND ea.jurisdiction_id=sp.id)))))";
 }
 public static function person(int $id): array {
  $where=self::condition();
  $extra=(\App\VoterService::namesReady()?',v.first_name,v.last_name':',NULL AS first_name,NULL AS last_name').',v.is_deceased,m.status AS membership_status,m.joined_on';
  $extra.=\App\PhotoService::ready()?',EXISTS(SELECT 1 FROM voter_photos vp WHERE vp.voter_record_id=v.id) AS has_photo':',0 AS has_photo';
  $residentialJoin='';
  if(\App\AddressService::ready()){$extra.=',v.town,rr.name AS residential_region,rd.name AS residential_district';$residentialJoin=' LEFT JOIN residential_regions rr ON rr.id=v.residential_region_id LEFT JOIN districts rd ON rd.id=v.residential_district_id';}
  else{$extra.=',NULL AS town,NULL AS residential_region,NULL AS residential_district';}
  $private=self::contacts($id)?'v.date_of_birth,v.phone_encrypted,v.email_encrypted,v.address_encrypted':'NULL AS date_of_birth,NULL AS phone_encrypted,NULL AS email_encrypted,NULL AS address_encrypted';
  $row=Database::one("SELECT v.id,v.voter_id,v.full_name,v.gender,v.updated_at,$private,m.id AS membership_id,m.member_number,m.official_membership_id,ps.name AS station,ps.station_code,b.name AS branch,c.name AS constituency,r.name AS region$extra FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id LEFT JOIN polling_stations ps ON ps.id=v.polling_station_id LEFT JOIN constituencies c ON c.id=ps.constituency_id LEFT JOIN regions r ON r.id=c.region_id LEFT JOIN electoral_areas b ON b.id=ps.electoral_area_id$residentialJoin WHERE v.id=? AND v.deleted_at IS NULL AND ($where)",[$id]);
  if(!$row){throw new HttpException(404,'Record unavailable in your current jurisdiction.');}
  if(!self::contacts($id)){foreach(['phone_encrypted','email_encrypted','address_encrypted','date_of_birth'] as $key){$row[$key]=null;}}
  return $row;
 }
 public static function appointmentCondition(string $alias='a'): string {
  if(!preg_match('/\A[a-z]+\z/D',$alias)){throw new \LogicException('Invalid internal office alias.');}
  $account=self::account();if(!$account){return '1=1';}
  if(!$account['enabled']||!$account['member_id']||!OrganizationService::ready()){return '1=0';}
  $member=(int)$account['member_id'];
  $grants=!empty($account['preset_managed'])?' AND EXISTS(SELECT 1 FROM executive_appointment_access ga WHERE ga.appointment_id=ea.id AND ga.user_id='.(int)$account['user_id'].' AND ga.enabled=1)':'';
  return "EXISTS(SELECT 1 FROM executive_appointments ea JOIN members em ON em.id=ea.member_id JOIN voters ev ON ev.id=em.voter_record_id WHERE em.id=$member AND em.status='active' AND ev.deleted_at IS NULL AND ev.is_deceased=0 AND ea.starts_on<=UTC_DATE() AND ea.ended_on IS NULL AND ea.cancelled_at IS NULL$grants AND (ea.organization_scope='main' OR (ea.organization_scope=$alias.organization_scope AND EXISTS(SELECT 1 FROM member_wing_enrollments w WHERE w.member_id=em.id AND w.wing_code=ea.organization_scope AND w.ended_on IS NULL))) AND (ea.organization_level='national' OR (ea.organization_level='region' AND $alias.region_id=ea.jurisdiction_id) OR (ea.organization_level='constituency' AND $alias.constituency_id=ea.jurisdiction_id) OR (ea.organization_level='branch' AND (($alias.organization_level='branch' AND $alias.jurisdiction_id=ea.jurisdiction_id) OR ($alias.organization_level='polling_station' AND EXISTS(SELECT 1 FROM polling_stations office_station WHERE office_station.id=$alias.polling_station_id AND office_station.electoral_area_id=ea.jurisdiction_id)))) OR (ea.organization_level='polling_station' AND $alias.organization_level='polling_station' AND $alias.polling_station_id=ea.jurisdiction_id)))";
 }
 public static function dispatch(string $page): bool {
  if(!self::restricted()){return false;}
  if(in_array($page,['account','voter_photo','security_confirm','branch_voter_edit','membership_nominations','membership_nomination','reports','reports_print'],true)){return false;}
  if($page==='dashboard'){View::render('security/executive/dashboard',\App\Security\Workspace\ExecutiveWorkspace::overview(),'Executive workspace');return true;}
  if(in_array($page,['voters','members'],true)){View::render('security/executive/people/index',\App\Security\Workspace\ExecutivePeople::listing($_GET),$page==='members'?'Members in my jurisdiction':'Voters in my jurisdiction');return true;}
  if(in_array($page,['voter','member'],true)){
   $id=Validation::id($_GET,'id');if($page==='member'){$id=(int)Database::scalar('SELECT voter_record_id FROM members WHERE id=?',[$id]);}
   $person=self::person($id);
   if($page==='voter'&&$person['membership_id']){\redirect('member',['id'=>$person['membership_id']]);}
   View::render($person['membership_id']?'security/executive/members/show':'security/executive/voters/show',\App\Security\Workspace\ExecutivePeople::profile($person),$person['membership_id']?'Member profile':'Voter profile');return true;
  }
  throw new HttpException(403,'This page is unavailable for executive access. Use My jurisdiction, Voters or Members.');
 }
 public static function guardPost(string $action): void {
  if(self::restricted()&&!in_array($action,['logout','password_change','security_confirm','executive_flag','executive_data_csv','branch_contact_save','membership_nominate','membership_decide','people_report_csv'],true)){throw new HttpException(403,'Executive accounts cannot directly change records.');}
 }
 public static function export(): never {
  if(!self::restricted()||!self::account()['enabled']||!self::account()['can_export']){throw new HttpException(403,'Export access has not been granted.');}
  $where='v.deleted_at IS NULL AND ('.self::condition().')';
  $rows=Database::all('SELECT v.voter_id,v.full_name,v.gender,m.official_membership_id,m.member_number FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id WHERE '.$where.' ORDER BY v.full_name,v.id LIMIT 5001');
  \App\Reporting\ReportOutput::checkSize(count($rows));
  Auth::audit('executive.records_exported','app_user',(int)Auth::user()['id'],['records'=>count($rows)]);
  header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="my-jurisdiction.csv"');
  $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Voter ID','Name','Gender','Party ID','Sys No.'],',','"','');
  foreach($rows as $row){fputcsv($out,array_map(static fn($value)=>preg_match('/^[\s\x{FEFF}]*[=+\-@]|^[\t\r\n]/u',(string)$value)?"'".$value:(string)$value,$row),',','"','');}fclose($out);exit;
 }
 public static function flag(array $input): void {
  if(!self::restricted()||self::account()['access_role']!=='reviewer'){throw new HttpException(403,'Reviewer access is required.');}
  $id=Validation::id($input,'voter_id');$note=Validation::text($input,'note',1000);
  Database::transaction(function()use($id,$note):void{
   self::person($id);
   if(!self::canReview($id)){throw new HttpException(403,'Reviewer permission is not granted for this record’s jurisdiction.');}
   $actor=(int)Auth::user()['id'];
   // A small per-user quota prevents accidental rapid repeats without retaining form content.
   if((int)Database::scalar('SELECT COUNT(*) FROM record_review_flags WHERE raised_by_user_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 HOUR',[$actor])>=30){throw new \InvalidArgumentException('Review limit reached. Please try again later.');}
   Database::execute('INSERT INTO record_review_flags(voter_record_id,raised_by_user_id,note_encrypted) VALUES(?,?,?)',[$id,$actor,Crypto::encrypt($note)]);
   Auth::audit('record.flagged','voter',$id);
  });
 }
}
