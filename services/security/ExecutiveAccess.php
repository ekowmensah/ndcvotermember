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
 public static function condition(string $alias='v',?string $level=null,?string $capability=null): string {
  if(!preg_match('/\A[a-z]+\z/D',$alias)){throw new \LogicException('Invalid internal alias.');}
  $account=self::account();if(!$account){return '1=1';}
  if(!$account['enabled']||!$account['member_id']||!OrganizationService::ready()){return '1=0';}
  $member=(int)$account['member_id'];
  if($level!==null&&!in_array($level,['branch','constituency','polling_station'],true)){throw new \LogicException('Invalid permission level.');}
  $office=$level!==null?" AND ea.organization_scope='main' AND ea.organization_level='$level'":'';
  if(!empty($account['preset_managed'])){
   if($capability!==null&&!in_array($capability,AppointmentAccounts::CAPABILITIES,true)){throw new \LogicException('Invalid internal capability.');}
   $review=$capability!==null&&$capability!=='can_contacts'?" AND ag.access_role='reviewer'":'';
   $permission=$capability!==null?" AND ag.$capability=1":'';
   $office.=' AND EXISTS(SELECT 1 FROM executive_appointment_access ag WHERE ag.appointment_id=ea.id AND ag.user_id='.(int)$account['user_id'].' AND ag.enabled=1'.$permission.$review.')';
  }
  // Derive geography from the actual polling station, including optional branch.
  return "EXISTS(SELECT 1 FROM executive_appointments ea JOIN members em ON em.id=ea.member_id JOIN voters ev ON ev.id=em.voter_record_id WHERE em.id=$member$office AND em.status='active' AND ev.deleted_at IS NULL AND ev.is_deceased=0 AND ea.cancelled_at IS NULL AND ea.ended_on IS NULL AND ea.starts_on<=UTC_DATE() AND (ea.organization_scope='main' OR EXISTS(SELECT 1 FROM member_wing_enrollments aw WHERE aw.member_id=em.id AND aw.wing_code=ea.organization_scope AND aw.ended_on IS NULL)) AND (ea.organization_scope='main' OR EXISTS(SELECT 1 FROM members tm JOIN member_wing_enrollments tw ON tw.member_id=tm.id WHERE tm.voter_record_id=$alias.id AND tw.wing_code=ea.organization_scope AND tw.ended_on IS NULL)) AND (ea.organization_level='national' OR EXISTS(SELECT 1 FROM polling_stations sp JOIN constituencies sc ON sc.id=sp.constituency_id WHERE sp.id=$alias.polling_station_id AND ((ea.organization_level='region' AND ea.jurisdiction_id=sc.region_id) OR (ea.organization_level='constituency' AND ea.jurisdiction_id=sc.id) OR (ea.organization_level='branch' AND ea.jurisdiction_id=sp.electoral_area_id) OR (ea.organization_level='polling_station' AND ea.jurisdiction_id=sp.id)))))";
 }
 public static function person(int $id): array {
  $where=self::condition();
  $private=self::contacts($id)?'v.date_of_birth,v.phone_encrypted,v.email_encrypted,v.address_encrypted':'NULL AS date_of_birth,NULL AS phone_encrypted,NULL AS email_encrypted,NULL AS address_encrypted';
  $row=Database::one("SELECT v.id,v.voter_id,v.full_name,v.gender,v.updated_at,$private,m.id AS membership_id,m.member_number,m.official_membership_id,ps.name AS station,ps.station_code,b.name AS branch,c.name AS constituency,r.name AS region FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id LEFT JOIN polling_stations ps ON ps.id=v.polling_station_id LEFT JOIN constituencies c ON c.id=ps.constituency_id LEFT JOIN regions r ON r.id=c.region_id LEFT JOIN electoral_areas b ON b.id=ps.electoral_area_id WHERE v.id=? AND v.deleted_at IS NULL AND ($where)",[$id]);
  if(!$row){throw new HttpException(404,'Record unavailable in your current jurisdiction.');}
  if(!self::contacts($id)){foreach(['phone_encrypted','email_encrypted','address_encrypted','date_of_birth'] as $key){$row[$key]=null;}}
  return $row;
 }
 public static function listing(array $input): array {
  $q=Validation::text($input,'q',100,false)??'';$members=($input['page']??'')==='members';
  $where='v.deleted_at IS NULL AND ('.self::condition().')'.($members?' AND m.id IS NOT NULL':'');$params=[];
  if($q!==''){$where.=' AND (v.full_name LIKE ? OR v.voter_id LIKE ? OR m.official_membership_id LIKE ?)';$term='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where=str_replace('LIKE ?','LIKE ? ESCAPE \'!\'',$where);$params=[$term,$term,$term];}
  $from=' FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id';
  $total=(int)Database::scalar('SELECT COUNT(*)'.$from.' WHERE '.$where,$params);
  $last=max(1,(int)ceil($total/25));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*25;
  $rows=Database::all('SELECT v.id,v.voter_id,v.full_name,v.gender,m.id AS membership_id,m.member_number,m.official_membership_id'.$from.' WHERE '.$where.' ORDER BY v.full_name,v.id LIMIT 25 OFFSET '.$offset,$params);
  return compact('rows','total','last','p','q','members');
 }
 public static function dispatch(string $page): bool {
  if(!self::restricted()){return false;}
  if(in_array($page,['account','voter_photo','security_confirm','branch_voter_edit','membership_nominations','membership_nomination'],true)){return false;}
  if($page==='dashboard'){$data=self::listing(['page'=>'voters']);$memberData=self::listing(['page'=>'members']);View::render('security/executive/dashboard',['voters'=>$data['total'],'members'=>$memberData['total'],'access'=>self::account()],'My jurisdiction');return true;}
  if(in_array($page,['voters','members'],true)){View::render('security/executive/index',self::listing($_GET),'My jurisdiction records');return true;}
  if(in_array($page,['voter','member'],true)){
   $id=Validation::id($_GET,'id');if($page==='member'){$id=(int)Database::scalar('SELECT voter_record_id FROM members WHERE id=?',[$id]);}
   View::render('security/executive/show',['person'=>self::person($id),'access'=>self::account()],'Record details');return true;
  }
  throw new HttpException(403,'This page is unavailable for executive access. Use My jurisdiction, Voters or Members.');
 }
 public static function guardPost(string $action): void {
  if(self::restricted()&&!in_array($action,['logout','password_change','security_confirm','executive_flag','executive_data_csv','branch_contact_save','membership_nominate','membership_decide'],true)){throw new HttpException(403,'Executive accounts cannot directly change records.');}
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
   $actor=(int)Auth::user()['id'];
   // A small per-user quota prevents accidental rapid repeats without retaining form content.
   if((int)Database::scalar('SELECT COUNT(*) FROM record_review_flags WHERE raised_by_user_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 HOUR',[$actor])>=30){throw new \InvalidArgumentException('Review limit reached. Please try again later.');}
   Database::execute('INSERT INTO record_review_flags(voter_record_id,raised_by_user_id,note_encrypted) VALUES(?,?,?)',[$id,$actor,Crypto::encrypt($note)]);
   Auth::audit('record.flagged','voter',$id);
  });
 }
}
