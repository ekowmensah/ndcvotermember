<?php
declare(strict_types=1);
namespace App\Membership;
use App\{Auth,Database,Repository,Validation};

final class WingService
{
 public const WINGS=['youth'=>'Youth Wing','women'=>"Women's Wing"];
 public static function enrollmentIssue(array $person,string $status,string $wing): ?string {
  if($status!=='active'||!empty($person['is_deceased'])){return 'Only active members who are not marked deceased can be enrolled.';}
  return WingEligibilityService::ruleIssue($person,$wing);
 }
 // Caller owns the person-save transaction and already holds the person lock.
 public static function guardGender(int $person,string $gender): void {
  if($gender==='female'||!self::ready()){return;}
  if(Database::one("SELECT id FROM member_wing_enrollments WHERE voter_record_id=? AND wing_code='women' AND ended_on IS NULL FOR UPDATE",[$person])){
   throw new \InvalidArgumentException("A member with an active Women's Wing enrollment must be recorded as Female. Correct the gender or end the wing enrollment before saving.");
  }
 }
 public static function ready(): bool {
  static $ready=null;
  return $ready??=((int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='member_wing_enrollments' AND column_name IN ('id','wing_code','member_id','voter_record_id','joined_on','ended_on','end_reason','updated_at','open_flag')")===9);
 }
 public static function current(int $member): array {
  return self::ready()?Database::all('SELECT id,wing_code,joined_on,updated_at FROM member_wing_enrollments WHERE member_id=? AND ended_on IS NULL ORDER BY wing_code',[$member]):[];
 }
 public static function earliestEnrollment(array $person,string $wing,?string $previousEnd=null): string {
  $earliest=$person['joined_on'];$zone=new \DateTimeZone('UTC');
  if($previousEnd!==null){$earliest=max($earliest,(new \DateTimeImmutable($previousEnd,$zone))->modify('+1 day')->format('Y-m-d'));}
  if($wing==='youth'&&WingEligibilityService::age($person['date_of_birth']??null)!==null){
   $birthday=(new \DateTimeImmutable($person['date_of_birth'],$zone))->modify('+'.WingEligibilityService::YOUTH_MIN.' years')->format('Y-m-d');
   $earliest=max($earliest,$birthday);
  }
  return $earliest;
 }
 public static function enrollmentWindows(array $person): array {
  if(!self::ready()){return [];}
  $latest=array_column(Database::all('SELECT wing_code,MAX(id) AS revision,MAX(ended_on) AS previous_end FROM member_wing_enrollments WHERE voter_record_id=? GROUP BY wing_code',[$person['id']]),null,'wing_code');
  $windows=[];
  foreach(self::WINGS as $wing=>$label){
   $minimum=self::earliestEnrollment($person,$wing,$latest[$wing]['previous_end']??null);
   $windows[$wing]=['minimum'=>$minimum,'available_today'=>$minimum<=gmdate('Y-m-d'),'revision'=>(int)($latest[$wing]['revision']??0)];
  }
  return $windows;
 }
 public static function editor(int $id): array {
  Auth::requireRole(['admin','registrar']);$voter=Repository::member($id);$available=self::ready();
  $enrollments=$available?array_column(self::current($id),null,'wing_code'):[];
  $windows=$available?self::enrollmentWindows($voter):[];
  $revisions=[];foreach($windows as $wing=>$window){$revisions[$wing]=$window['revision'];}
  $exitRules=\App\Executives\OrganizationService::wingExitRules($id,$enrollments);
  return compact('available','voter','enrollments','revisions','windows','exitRules');
 }
 public static function save(array $input): void {
  Auth::requireRole(['admin','registrar']);
  if(!self::ready()){throw new \InvalidArgumentException('Import database/20_membership_wings_mysql8.sql in phpMyAdmin first.');}
  $memberId=Validation::id($input,'member_id');$personId=Validation::id($input,'voter_record_id');
  $wing=Validation::choice($input,'wing',array_keys(self::WINGS));$mode=Validation::choice($input,'mode',['enroll','leave']);
  $date=Validation::date($input,'effective_on',true);
  if($date<'1000-01-01'){throw new \InvalidArgumentException('Enter a valid date from year 1000 onward.');}
  if(($input['confirm']??'')!=='1'){throw new \InvalidArgumentException('Confirm this wing membership change before saving.');}
  Database::transaction(function()use($input,$memberId,$personId,$wing,$mode,$date):void{
   // Same lock order as shared-person editing and membership removal.
   $person=Database::one('SELECT * FROM voters WHERE id=? FOR UPDATE',[$personId])??throw new \InvalidArgumentException('This person no longer exists.');
   \App\Voters\TrashService::check($person);
   $member=Database::one('SELECT * FROM members WHERE id=? AND voter_record_id=? FOR UPDATE',[$memberId,$personId])??throw new \InvalidArgumentException('This membership changed or was removed. Reload the member profile.');
   if(Validation::text($input,'membership_version',30)!==$member['updated_at']||Validation::text($input,'voter_version',30)!==$person['updated_at']){throw new \InvalidArgumentException('Member details changed. Reload this page before continuing.');}
   $old=Database::one('SELECT * FROM member_wing_enrollments WHERE voter_record_id=? AND wing_code=? AND ended_on IS NULL FOR UPDATE',[$personId,$wing]);
   if($mode==='enroll'){
    $revision=filter_var($input['latest_enrollment_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    $latest=(int)(Database::scalar('SELECT MAX(id) FROM member_wing_enrollments WHERE voter_record_id=? AND wing_code=?',[$personId,$wing])??0);
    if($revision===false||$revision!==$latest){throw new \InvalidArgumentException('Wing enrollment changed. Reload before enrolling again.');}
    if($old){throw new \InvalidArgumentException('This person is already enrolled in this wing. Reload to see the latest details.');}
    $issue=self::enrollmentIssue($person,$member['status'],$wing);
    if($issue!==null){throw new \InvalidArgumentException($issue);}
    $startIssue=WingEligibilityService::ruleIssue($person,$wing,$date);
    if($startIssue!==null){throw new \InvalidArgumentException('On the enrollment date: '.$startIssue);}
    if($date<$member['joined_on']){throw new \InvalidArgumentException('Wing enrollment cannot start before party membership.');}
    $previous=Database::scalar('SELECT MAX(ended_on) FROM member_wing_enrollments WHERE voter_record_id=? AND wing_code=?',[$personId,$wing]);
    $minimum=self::earliestEnrollment(array_replace($person,['joined_on'=>$member['joined_on']]),$wing,$previous);
    if($date<$minimum){throw new \InvalidArgumentException('Enrollment must start on or after '.$minimum.'. Previous enrollment end dates are inclusive.');}
    Database::execute('INSERT INTO member_wing_enrollments(wing_code,member_id,voter_record_id,member_name_snapshot,member_number_snapshot,joined_on,created_by_user_id) VALUES(?,?,?,?,?,?,?)',[$wing,$memberId,$personId,$person['full_name'],$member['member_number'],$date,Auth::user()['id']]);
    $id=(int)Database::connection()->lastInsertId();
    Auth::audit('wing.enrolled','wing_enrollment',$id,['member_id'=>$memberId,'wing'=>$wing,'joined_on'=>$date]);
   }else{
    if(!$old||(int)$old['member_id']!==$memberId||(int)$old['id']!==Validation::id($input,'enrollment_id')||$old['updated_at']!==Validation::text($input,'enrollment_version',30)){throw new \InvalidArgumentException('Wing enrollment changed. Reload before ending it.');}
    if($date<$old['joined_on']){throw new \InvalidArgumentException('The end date cannot be before enrollment.');}
    \App\Executives\OrganizationService::guardWingExit($memberId,$wing,(int)$old['id'],$date);
    Database::execute("UPDATE member_wing_enrollments SET ended_on=?,end_reason='left',ended_by_user_id=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?",[$date,Auth::user()['id'],$old['id']]);
    Auth::audit('wing.ended','wing_enrollment',(int)$old['id'],['member_id'=>$memberId,'wing'=>$wing,'joined_on'=>$old['joined_on'],'ended_on'=>$date,'reason'=>'left']);
   }
  });
 }
 // Caller owns the membership-removal transaction and person/member locks.
 public static function closeForMembershipRemoval(int $member): void {
  if(!self::ready()){return;}
  $rows=Database::all('SELECT * FROM member_wing_enrollments WHERE member_id=? AND ended_on IS NULL ORDER BY id FOR UPDATE',[$member]);
  foreach($rows as $row){
   Database::execute("UPDATE member_wing_enrollments SET ended_on=?,end_reason='membership_removed',ended_by_user_id=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?",[gmdate('Y-m-d'),Auth::user()['id'],$row['id']]);
   Auth::audit('wing.ended','wing_enrollment',(int)$row['id'],['member_id'=>$member,'wing'=>$row['wing_code'],'reason'=>'membership_removed','ended_on'=>gmdate('Y-m-d')]);
  }
 }
 public static function listing(array $input,bool $export=false): array {
  Auth::requireLogin();$wing=Validation::choice($input,'wing',array_keys(self::WINGS));$available=self::ready();
  if(!$available){return compact('available','wing');}
  $filters=['wing'=>$wing];$params=[$wing];$where='w.wing_code=?';
  $filters['state']=Validation::choice(['state'=>$input['state']??'active'],'state',['active','ended']);
  if(Validation::text($input,'review',20,false)!==null){
   $filters['review']=Validation::choice($input,'review',$wing==='youth'?['needs_review','aged_out']:['needs_review']);
   $filters['state']='active';
   $where.=' AND w.ended_on IS NULL AND '.($filters['review']==='aged_out'?WingEligibilityService::agedOutSql():WingEligibilityService::reviewSql($wing));
  }
  $where.=$filters['state']==='active'?' AND w.ended_on IS NULL':' AND w.ended_on IS NOT NULL';
  WingScopeService::apply($input,$filters,$where,$params);
  if(Validation::text($input,'active_people',1,false)!==null){$filters['active_people']=Validation::choice($input,'active_people',['1']);$where.=' AND v.id IS NOT NULL AND '.\App\Voters\TrashService::active();}
  $q=Validation::text($input,'q',100,false);
  if($q!==null){$filters['q']=$q;$term='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where.=" AND (COALESCE(v.full_name,w.member_name_snapshot) LIKE ? ESCAPE '!' OR COALESCE(m.official_membership_id,'') LIKE ? ESCAPE '!' OR w.member_number_snapshot LIKE ? ESCAPE '!')";array_push($params,$term,$term,$term);}
  foreach(['constituency_filter'=>'ps.constituency_id','branch_filter'=>'ps.electoral_area_id'] as $key=>$column){if(Validation::text($input,$key,20,false)!==null){$filters[$key]=Validation::id($input,$key);$where.=" AND $column=?";$params[]=$filters[$key];}}
  $from=' FROM member_wing_enrollments w LEFT JOIN voters v ON v.id=w.voter_record_id LEFT JOIN members m ON m.id=w.member_id LEFT JOIN polling_stations ps ON ps.id=v.polling_station_id LEFT JOIN constituencies c ON c.id=ps.constituency_id LEFT JOIN electoral_areas b ON b.id=ps.electoral_area_id';
  $total=(int)Database::scalar('SELECT COUNT(*)'.$from.' WHERE '.$where,$params);$last=max(1,(int)ceil($total/25));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*25;
  if($export){\App\Reporting\ReportOutput::checkSize($total);}
  $limit=$export?' LIMIT 5000':" LIMIT 25 OFFSET $offset";
  $rows=Database::all('SELECT w.id,w.member_id,w.member_name_snapshot,w.member_number_snapshot,w.joined_on,w.ended_on,w.end_reason,v.full_name,v.gender,v.date_of_birth,v.is_deceased,m.official_membership_id,m.status AS membership_status,c.name AS constituency,b.name AS branch'.$from.' WHERE '.$where." ORDER BY w.joined_on DESC,w.id DESC".$limit,$params);
  foreach($rows as &$row){$row['review_issue']=$row['ended_on']?null:self::enrollmentIssue($row,$row['membership_status']??'removed',$wing);}unset($row);
  $catalog=\App\LocationService::catalog(false);return compact('available','wing','filters','total','last','p','rows','catalog');
 }
}
