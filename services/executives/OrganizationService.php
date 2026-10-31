<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Database,Validation};
use App\Membership\{WingService,WingEligibilityService};

final class OrganizationService
{
 public const SCOPES=['main'=>'Main Party','youth'=>'Youth Wing','women'=>"Women's Wing"];
 public static function ready(): bool {
  static $ready=null;
  return $ready??=(WingService::ready()&&(int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name IN ('organization_scope','wing_enrollment_id')")===2&&Database::scalar("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND index_name='uq_exec_open_office' AND non_unique=0")==='organization_scope,position_id,organization_level,jurisdiction_id,open_office');
 }
 public static function code(array $row): string {return $row['organization_scope']??'main';}
 public static function label(array $row): string {return self::SCOPES[self::code($row)]??'Main Party';}
 public static function column(string $alias='a'): string {return self::ready()?"$alias.organization_scope":"'main' AS organization_scope";}
 public static function filter(array $input,array &$filters,string &$where,array &$params): void {
  if(Validation::text($input,'organization_scope',20,false)===null){return;}
  $scope=Validation::choice($input,'organization_scope',array_keys(self::SCOPES));$filters['organization_scope']=$scope;
  if(!self::ready()){if($scope!=='main'){throw new \InvalidArgumentException('Import database/21_wing_executive_scope_mysql8.sql in phpMyAdmin to enable wing appointments.');}return;}
  $where.=' AND a.organization_scope=?';$params[]=$scope;
 }
 // Appointment transaction already holds person/member locks.
 public static function enrollment(string $scope,array $person,array $member,string $start,array $input): ?int {
  if($scope==='main'){return null;}
  if(!self::ready()){throw new \InvalidArgumentException('Import database/21_wing_executive_scope_mysql8.sql in phpMyAdmin before appointing wing executives.');}
  $issue=WingService::enrollmentIssue($person,$member['status'],$scope);
  if($issue!==null){throw new \InvalidArgumentException('Wing executive eligibility: '.$issue);}
  $issue=WingEligibilityService::ruleIssue($person,$scope,$start);
  if($issue!==null){throw new \InvalidArgumentException('On the appointment start date: '.$issue);}
  $entry=Database::one('SELECT id,joined_on,updated_at FROM member_wing_enrollments WHERE member_id=? AND voter_record_id=? AND wing_code=? AND ended_on IS NULL FOR UPDATE',[$member['id'],$person['id'],$scope]);
  if(!$entry){throw new \InvalidArgumentException('Enroll this member in '.self::SCOPES[$scope].' before creating its executive appointment.');}
  $versions=json_decode(Validation::text($input,'wing_versions',2000),true);
  $shown=is_array($versions)?($versions[$scope]??null):null;
  if(!is_array($shown)||(string)($shown['id']??'')!==(string)$entry['id']||($shown['updated_at']??null)!==$entry['updated_at']){throw new \InvalidArgumentException('Wing enrollment changed since the appointment form opened. Review the member and retry.');}
  if($start<$entry['joined_on']){throw new \InvalidArgumentException('The executive appointment cannot start before the current wing enrollment.');}
  return (int)$entry['id'];
 }
 // Read-only editor hints; the mutation guard below rechecks under locks.
 public static function wingExitRules(int $member,array $enrollments): array {
  $rules=[];
  foreach($enrollments as $wing=>$entry){$rules[$wing]=['minimum'=>$entry['joined_on'],'open_roles'=>[]];}
  if(!self::ready()||!AppointmentService::ready()){return $rules;}
  foreach(AppointmentService::memberAppointments($member) as $role){
   $wing=self::code($role);if(isset($rules[$wing])){$rules[$wing]['open_roles'][]=$role;}
  }
  $endings=array_column(Database::all("SELECT wing_enrollment_id,MAX(ended_on) AS last_end FROM executive_appointments WHERE member_id=? AND organization_scope<>'main' AND cancelled_at IS NULL AND ended_on IS NOT NULL GROUP BY wing_enrollment_id",[$member]),'last_end','wing_enrollment_id');
  foreach($enrollments as $wing=>$entry){if(isset($endings[$entry['id']])){$rules[$wing]['minimum']=max($rules[$wing]['minimum'],$endings[$entry['id']]);}}
  return $rules;
 }
 // Wing-save transaction already holds the same person/member locks as appointments.
 public static function guardWingExit(int $member,string $wing,int $enrollment,string $end): void {
  if(!self::ready()||!AppointmentService::ready()){return;}
  $role=Database::one('SELECT id FROM executive_appointments WHERE member_id=? AND organization_scope=? AND ended_on IS NULL AND cancelled_at IS NULL ORDER BY id LIMIT 1 FOR UPDATE',[$member,$wing]);
  if($role){throw new \InvalidArgumentException('Close or cancel '.self::SCOPES[$wing].' executive appointment #'.$role['id'].' before ending this wing enrollment.');}
  $later=Database::one('SELECT id FROM executive_appointments WHERE wing_enrollment_id=? AND cancelled_at IS NULL AND ended_on>? ORDER BY ended_on DESC,id DESC LIMIT 1 FOR UPDATE',[$enrollment,$end]);
  if($later){throw new \InvalidArgumentException('The wing end date cannot precede the actual end of executive appointment #'.$later['id'].'.');}
 }
}
