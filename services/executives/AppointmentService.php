<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Auth,Database,Validation,LocationService,VoterService};
use App\People\LifeStatusService;
use App\Voters\TrashService;
final class AppointmentService
{
 public const TYPES=['elected'=>'Elected','appointed'=>'Appointed','acting'=>'Acting'];
 public static function baseReady(): bool {
  static $ready=null;
  return $ready??=(PositionService::ready()&&VoterService::officialIdsReady()&&LifeStatusService::ready()&&TrashService::ready()&&LocationService::ready()&&(int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name IN ('id','member_id','position_id','organization_level','jurisdiction_id','starts_on','ended_on','open_office')")===8);
 }
 public static function ready(): bool {
  static $ready=null;
  return $ready??=(self::baseReady()&&(int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name IN ('cancelled_at','end_notes','ended_by_user_id','replaces_appointment_id')")===4&&(bool)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name='open_office' AND generation_expression LIKE '%cancelled_at%'"));
 }
 public static function openClause(): string {return 'ended_on IS NULL'.(self::ready()?' AND cancelled_at IS NULL':'');}
 public static function guardMembershipRemoval(int $member): void {
  if(self::baseReady()&&Database::scalar('SELECT id FROM executive_appointments WHERE member_id=? AND '.self::openClause().' LIMIT 1',[$member])){throw new \InvalidArgumentException('This member has a current or scheduled executive appointment. End the appointment before removing membership. Open Executive appointments to end or cancel it.');}
 }
 private static function date(array $input,string $key,bool $required): ?string {
  $value=Validation::text($input,$key,10,$required);if($value===null){return null;}
  $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
  if(!$date||$date->format('Y-m-d')!==$value){throw new \InvalidArgumentException('Choose a valid '.\label($key).'.');}return $value;
 }
 public static function members(array $input): array {
  $q=Validation::text($input,'member_search',100,false);$selected=null;$candidates=[];
  $official=VoterService::officialIdsReady()?'m.official_membership_id':'NULL AS official_membership_id';
  $columns="m.id,v.id AS voter_record_id,v.full_name,v.voter_id,v.updated_at AS voter_version,m.updated_at AS membership_version,m.member_number,$official,m.status,v.is_deceased";
  $from=' FROM members m JOIN voters v ON v.id=m.voter_record_id WHERE v.deleted_at IS NULL';
  if(!empty($input['member_id'])){$selected=Database::one('SELECT '.$columns.$from.' AND m.id=?',[Validation::id($input,'member_id')]);}
  if($q!==null){
   $term='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$params=[$term,$term,$term];$search="(v.full_name LIKE ? ESCAPE '!' OR v.voter_id LIKE ? ESCAPE '!' OR m.member_number LIKE ? ESCAPE '!'";
   if(VoterService::officialIdsReady()){$search.=" OR m.official_membership_id LIKE ? ESCAPE '!'";$params[]=$term;}$search.=')';
   $candidates=Database::all('SELECT '.$columns.$from." AND m.status='active' AND v.is_deceased=0 AND $search ORDER BY v.full_name,m.id LIMIT 26",$params);
  }
  $more=count($candidates)>25;$candidates=array_slice($candidates,0,25);
  return compact('q','selected','candidates','more');
 }
 public static function form(array $input): array {
  Auth::requireRole(['admin','registrar']);$available=self::ready();if(!$available){return compact('available');}
  $editing=!empty($input['edit_id'])?LifecycleService::open(Validation::id($input,'edit_id')):null;
  if($editing&&$editing['starts_on']<=gmdate('Y-m-d')){throw new \InvalidArgumentException('This appointment has started. Use Manage service to correct its type, expected end or notes.');}
  if($editing&&!empty($input['replace_id'])){throw new \InvalidArgumentException('Editing and replacement are separate actions.');}
  $values=$editing??['starts_on'=>gmdate('Y-m-d'),'appointment_type'=>'appointed'];

  $saved=$_SESSION['executive_appointment_form']??null;unset($_SESSION['executive_appointment_form']);
  if(is_array($saved)&&(string)($saved['edit_id']??'')===(string)($input['edit_id']??'')&&(string)($saved['replace_id']??'')===(string)($input['replace_id']??'')&&(!$editing||($saved['edit_version']??'')===$editing['updated_at'])&&(!isset($input['member_id'])||(string)$input['member_id']===(string)($saved['member_id']??''))){
   $values=array_replace($values,$saved);if(!isset($input['member_id'])){$input['member_id']=$saved['member_id']??null;}
  }
  if($editing&&!isset($input['member_id'])){$input['member_id']=$editing['member_id'];}
  $replacement=!empty($input['replace_id'])?LifecycleService::open(Validation::id($input,'replace_id')):null;
  if($replacement&&!isset($values['replacement_ended_on'])){
   $values['replacement_ended_on']=gmdate('Y-m-d');
   $values['replacement_reason']='replaced';
   $values['starts_on']=max(gmdate('Y-m-d'),$values['replacement_ended_on']);
  }
  if($replacement){if($replacement['starts_on']>gmdate('Y-m-d')){throw new \InvalidArgumentException('Cancel the scheduled appointment before replacing it.');}foreach(['position_id','organization_level','region_id','constituency_id','branch_id','polling_station_id'] as $key){$values[$key]=$replacement[$key];}$values['organization_scope']=OrganizationService::code($replacement);}
  if(!$editing&&!$replacement&&!isset($values['organization_scope'])){$values['organization_scope']=Validation::choice(['organization_scope'=>$input['organization_scope']??'main'],'organization_scope',array_keys(OrganizationService::SCOPES));}
  $members=self::members($input);$positions=Database::all('SELECT id,name,updated_at,(SELECT GROUP_CONCAT(organization_level) FROM executive_position_levels pl WHERE pl.position_id=p.id) AS level_codes FROM executive_position_titles p WHERE is_active=1 ORDER BY display_order,name,id');
  $catalog=LocationService::catalog();return compact('available','values','members','positions','catalog','replacement','editing');
 }
 public static function create(array $input): int {
  Auth::requireRole(['admin','registrar']);if(!self::ready()){throw new \InvalidArgumentException('Complete migrations 13–18 in phpMyAdmin before appointing executives.');}
  if(($input['_action']??'')==='executive_appointment_update'&&empty($input['edit_id'])){throw new \InvalidArgumentException('The scheduled edit target is missing. Reopen the appointment and choose Edit scheduled appointment.');}
  $memberId=Validation::id($input,'member_id');$positionId=Validation::id($input,'position_id');$level=Validation::choice($input,'organization_level',array_keys(PositionService::LEVELS));
  $type=Validation::choice($input,'appointment_type',array_keys(self::TYPES));$start=self::date($input,'starts_on',true);$expected=self::date($input,'expected_ends_on',false);$notes=Validation::text($input,'notes',2000,false);
  if($expected!==null&&$expected<$start){throw new \InvalidArgumentException('Expected end date cannot precede the start date.');}
  $replacement=!empty($input['replace_id'])?LifecycleService::open(Validation::id($input,'replace_id')):null;
  $editing=!empty($input['edit_id'])?LifecycleService::open(Validation::id($input,'edit_id')):null;
  if($editing&&$replacement){throw new \InvalidArgumentException('Editing and replacement cannot be combined.');}
  $organization=Validation::choice(['organization_scope'=>$input['organization_scope']??OrganizationService::code($editing??$replacement??[])],'organization_scope',array_keys(OrganizationService::SCOPES));
  if($organization!=='main'&&!OrganizationService::ready()){throw new \InvalidArgumentException('Import database/21_wing_executive_scope_mysql8.sql in phpMyAdmin first.');}
  $lookup=Database::one('SELECT voter_record_id FROM members WHERE id=?',[$memberId])??throw new \InvalidArgumentException('Choose an existing member.');
  return Database::transaction(function()use($input,$memberId,$positionId,$level,$type,$start,$expected,$notes,$lookup,$replacement,$editing,$organization):int{
   if($replacement){
    $people=array_values(array_unique(array_filter([(int)$lookup['voter_record_id'],(int)$replacement['voter_record_id']])));
    $marks=implode(',',array_fill(0,count($people),'?'));Database::all("SELECT id FROM voters WHERE id IN ($marks) ORDER BY id FOR UPDATE",$people);
    $members=array_values(array_unique(array_filter([$memberId,(int)$replacement['member_id']])));
    $marks=implode(',',array_fill(0,count($members),'?'));Database::all("SELECT id FROM members WHERE id IN ($marks) ORDER BY id FOR UPDATE",$members);
   }
   if($editing){
    $people=array_values(array_unique(array_filter([(int)$lookup['voter_record_id'],(int)$editing['voter_record_id']])));
    $marks=implode(',',array_fill(0,count($people),'?'));Database::all("SELECT id FROM voters WHERE id IN ($marks) ORDER BY id FOR UPDATE",$people);
    $members=array_values(array_unique(array_filter([$memberId,(int)$editing['member_id']])));
    $marks=implode(',',array_fill(0,count($members),'?'));Database::all("SELECT id FROM members WHERE id IN ($marks) ORDER BY id FOR UPDATE",$members);
    $positions=array_values(array_unique([$positionId,(int)$editing['position_id']]));
    $marks=implode(',',array_fill(0,count($positions),'?'));Database::all("SELECT id FROM executive_position_titles WHERE id IN ($marks) ORDER BY id FOR UPDATE",$positions);
    $editing=Database::one('SELECT * FROM executive_appointments WHERE id=? FOR UPDATE',[$editing['id']])??throw new \InvalidArgumentException('Appointment no longer exists.');
    if($editing['ended_on']||$editing['cancelled_at']||$editing['starts_on']<=gmdate('Y-m-d')){throw new \InvalidArgumentException('Only open scheduled appointments can be edited.');}
    if(Validation::text($input,'edit_version',30)!==$editing['updated_at']){throw new \InvalidArgumentException('This appointment changed. Reopen it before editing.');}
    if($start<gmdate('Y-m-d')){throw new \InvalidArgumentException('A scheduled edit cannot move the start into the past. Choose today or a future date.');}
   }
   // Match person/membership writes: voter first, membership second, position third.
   $person=Database::one('SELECT * FROM voters WHERE id=? FOR UPDATE',[$lookup['voter_record_id']])??throw new \InvalidArgumentException('This person no longer exists.');TrashService::check($person);
   $member=Database::one('SELECT * FROM members WHERE id=? AND voter_record_id=? FOR UPDATE',[$memberId,$person['id']])??throw new \InvalidArgumentException('Membership changed. Select the member again.');
   if($member['status']!=='active'||$person['is_deceased']){throw new \InvalidArgumentException('Only active members who are not marked deceased can receive a new appointment.');}
   if(Validation::text($input,'voter_version',30)!==$person['updated_at']||Validation::text($input,'membership_version',30)!==$member['updated_at']){throw new \InvalidArgumentException('The member changed after selection. Review the current details before appointing.');}
   if($start<$member['joined_on']){throw new \InvalidArgumentException('Service cannot start before this membership began.');}
   $position=Database::one('SELECT * FROM executive_position_titles WHERE id=? FOR UPDATE',[$positionId])??throw new \InvalidArgumentException('Position not found.');
   if(!$position['is_active']||!Database::scalar('SELECT position_id FROM executive_position_levels WHERE position_id=? AND organization_level=?',[$positionId,$level])){throw new \InvalidArgumentException('Select an active position and one of its supported levels.');}
   $versions=json_decode(Validation::text($input,'position_versions',100000),true);
   if(!is_array($versions)||($versions[$positionId]??null)!==$position['updated_at']){throw new \InvalidArgumentException('The position changed after the form opened. Review it and try again.');}
   $scope=self::scope($level,$input);
   $wingEnrollment=OrganizationService::enrollment($organization,$person,$member,$start,$input);
   $termGrouping=TermService::selection($input,$start,$editing);
   $handoverPredecessorId=0;
   if($replacement){$previous=LifecycleService::closeForReplacement($input,$scope,$positionId,$level,$start,$organization);$handoverPredecessorId=(int)$previous['id'];}
   if($editing&&!empty($editing['replaces_appointment_id'])){
    $previous=Database::one('SELECT * FROM executive_appointments WHERE id=? FOR SHARE',[$editing['replaces_appointment_id']]);
    if($previous&&OrganizationService::code($previous)!==$organization){throw new \InvalidArgumentException('A successor must stay in the same party or wing organization as its predecessor.');}
    if(!$previous||!$previous['ended_on']||$previous['cancelled_at']||(int)$previous['position_id']!==$positionId||$previous['organization_level']!==$level||(int)$previous['jurisdiction_id']!==$scope['jurisdiction_id']||$start<$previous['ended_on']){throw new \InvalidArgumentException('A successor must keep its predecessor’s office and start on or after the previous actual end date.');}
    $handoverPredecessorId=(int)$previous['id'];
   }
   // The position lock serializes office checks; the unique key is a second safeguard.
   $excludedAppointmentId=$editing?(int)$editing['id']:0;
   $officeWhere='';$officeParams=[];
   if(OrganizationService::ready()){$officeWhere=' AND a.organization_scope=?';$officeParams[]=$organization;}
   $conflict=Database::one('SELECT a.id,a.member_name_snapshot,a.starts_on,a.ended_on FROM executive_appointments a WHERE a.id<>? AND a.position_id=? AND a.organization_level=? AND a.jurisdiction_id=?'.$officeWhere.' AND a.cancelled_at IS NULL AND (a.ended_on IS NULL OR a.ended_on>=?) AND NOT (a.id=? AND a.ended_on=?) ORDER BY a.id LIMIT 1 FOR UPDATE',[$excludedAppointmentId,$positionId,$level,$scope['jurisdiction_id'],...$officeParams,$start,$handoverPredecessorId,$start]);
   if($conflict){
    $dates=$conflict['starts_on'].' to '.($conflict['ended_on']??'open end');
    throw new \InvalidArgumentException('Conflicts with appointment #'.$conflict['id'].' for '.$conflict['member_name_snapshot'].' ('.$dates.'). '.($editing?'Appointment #'.$excludedAppointmentId.' itself was excluded from this check. ':'').'End/replace the other open appointment, or choose a start after its actual end date.');
   }
   if($editing){
    $after=['member_id'=>$memberId,'voter_record_id'=>$person['id'],'position_id'=>$positionId,'organization_level'=>$level,'jurisdiction_id'=>$scope['jurisdiction_id'],'region_id'=>$scope['region_id'],'constituency_id'=>$scope['constituency_id'],'branch_id'=>$scope['branch_id'],'polling_station_id'=>$scope['polling_station_id'],'member_name_snapshot'=>$person['full_name'],'member_number_snapshot'=>$member['member_number'],'party_id_snapshot'=>$member['official_membership_id']??null,'position_name_snapshot'=>$position['name'],'jurisdiction_snapshot'=>$scope['label'],'appointment_type'=>$type,'starts_on'=>$start,'expected_ends_on'=>$expected,'notes'=>$notes];
    $after=array_replace($after,$termGrouping);
    if(OrganizationService::ready()){$after['organization_scope']=$organization;$after['wing_enrollment_id']=$wingEnrollment;}
    $changed=false;foreach($after as $key=>$value){if((string)$editing[$key]!== (string)$value){$changed=true;break;}}if(!$changed){\App\Security\AppointmentAccounts::provision((int)$editing['id'],$input);return (int)$editing['id'];}
    $columns=implode(',',array_map(static fn($key)=>"$key=?",array_keys($after)));
    Database::execute('UPDATE executive_appointments SET '.$columns.',updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[...array_values($after),$editing['id']]);
    \App\Security\AppointmentAccounts::reconcileEditedGrant((int)$editing['id'],$editing);
    $auditFields=array_flip(['member_id','position_id','organization_level','jurisdiction_id','starts_on','expected_ends_on','appointment_type','leadership_term_id','organization_scope','wing_enrollment_id']);
    Auth::audit('executive.scheduled_appointment_updated','executive_appointment',(int)$editing['id'],['before'=>array_intersect_key($editing,$auditFields),'after'=>array_intersect_key($after,$auditFields),'notes_changed'=>$editing['notes']!==$notes]);
    \App\Security\AppointmentAccounts::provision((int)$editing['id'],$input);return (int)$editing['id'];
   }
   $params=[$memberId,$person['id'],$positionId,$level,$scope['jurisdiction_id'],$scope['region_id'],$scope['constituency_id'],$scope['branch_id'],$scope['polling_station_id'],$person['full_name'],$member['member_number'],$member['official_membership_id']??null,$position['name'],$scope['label'],$type,$start,$expected,$notes,Auth::user()['id']];
   $scopeColumns='';$scopeMarks='';
   if(OrganizationService::ready()){$scopeColumns=',organization_scope,wing_enrollment_id';$scopeMarks=',?,?';array_push($params,$organization,$wingEnrollment);}
   Database::execute('INSERT INTO executive_appointments(member_id,voter_record_id,position_id,organization_level,jurisdiction_id,region_id,constituency_id,branch_id,polling_station_id,member_name_snapshot,member_number_snapshot,party_id_snapshot,position_name_snapshot,jurisdiction_snapshot,appointment_type,starts_on,expected_ends_on,notes,created_by_user_id'.$scopeColumns.') VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?'.$scopeMarks.')',$params);
   $id=(int)Database::connection()->lastInsertId();if($termGrouping){Database::execute('UPDATE executive_appointments SET leadership_term_id=?,leadership_term_name_snapshot=? WHERE id=?',[$termGrouping['leadership_term_id'],$termGrouping['leadership_term_name_snapshot'],$id]);}if($replacement){Database::execute('UPDATE executive_appointments SET replaces_appointment_id=? WHERE id=?',[$replacement['id'],$id]);Auth::audit('executive.appointment_replaced','executive_appointment',$id,['previous_appointment_id'=>$replacement['id']]);}Auth::audit('executive.appointed','executive_appointment',$id,['organization_scope'=>$organization,'wing_enrollment_id'=>$wingEnrollment,'member_id'=>$memberId,'position_id'=>$positionId,'level'=>$level,'jurisdiction_id'=>$scope['jurisdiction_id'],'starts_on'=>$start,'expected_ends_on'=>$expected,'appointment_type'=>$type,'leadership_term_id'=>$termGrouping['leadership_term_id']??null]);\App\Security\AppointmentAccounts::provision($id,$input);return $id;
  });
 }
 private static function scope(string $level,array $input): array {
  $scope=['jurisdiction_id'=>0,'region_id'=>null,'constituency_id'=>null,'branch_id'=>null,'polling_station_id'=>null,'label'=>'National'];
  if($level==='national'){
   foreach(['region_id','constituency_id','branch_id','polling_station_id'] as $field){if(Validation::text($input,$field,20,false)!==null){throw new \InvalidArgumentException('National appointments must not have a local jurisdiction.');}}return $scope;
  }
  $region=Database::one('SELECT id,name FROM regions WHERE id=? FOR SHARE',[Validation::id($input,'region_id')])??throw new \InvalidArgumentException('Select an electoral region.');$scope['region_id']=(int)$region['id'];$scope['jurisdiction_id']=(int)$region['id'];$scope['label']=$region['name'];
  if($level==='region'){self::noExtraScope($input,['constituency_id','branch_id','polling_station_id']);return $scope;}
  $const=Database::one('SELECT id,name FROM constituencies WHERE id=? AND region_id=? FOR SHARE',[Validation::id($input,'constituency_id'),$region['id']])??throw new \InvalidArgumentException('Choose a constituency in the selected electoral region.');$scope['constituency_id']=(int)$const['id'];$scope['jurisdiction_id']=(int)$const['id'];$scope['label'].=' / '.$const['name'];
  if($level==='constituency'){self::noExtraScope($input,['branch_id','polling_station_id']);return $scope;}
  $branch=$level==='branch';self::noExtraScope($input,[$branch?'polling_station_id':'branch_id']);$table=$branch?'electoral_areas':'polling_stations';$key=$branch?'branch_id':'polling_station_id';
  $child=Database::one("SELECT id,name FROM $table WHERE id=? AND constituency_id=? FOR SHARE",[Validation::id($input,$key),$const['id']])??throw new \InvalidArgumentException('Choose a location belonging to this constituency.');
  $scope[$key]=(int)$child['id'];$scope['jurisdiction_id']=(int)$child['id'];$scope['label'].=' / '.$child['name'];return $scope;
 }
 private static function noExtraScope(array $input,array $fields): void {foreach($fields as $field){if(Validation::text($input,$field,20,false)!==null){throw new \InvalidArgumentException('Clear locations that do not apply to the selected level.');}}}
 public static function listing(array $input,bool $export=false): array {
  Auth::requireLogin();$available=self::ready();if(!$available){return compact('available');}
  $filters=[];$where='a.cancelled_at IS NULL AND a.ended_on IS NULL AND v.id IS NOT NULL AND v.deleted_at IS NULL';$params=[];$today=gmdate('Y-m-d');
  $filters['state']=Validation::choice(['state'=>$input['state']??'current'],'state',['current','scheduled','all_open','ended','cancelled']);
  if($filters['state']==='current'){$where.=' AND a.starts_on<=?';$params[]=$today;}elseif($filters['state']==='scheduled'){$where.=' AND a.starts_on>?';$params[]=$today;}
  if($filters['state']==='ended'){$where='a.ended_on IS NOT NULL AND a.cancelled_at IS NULL';}
  if($filters['state']==='cancelled'){$where='a.cancelled_at IS NOT NULL';}
  $q=Validation::text($input,'q',100,false);if($q!==null){$filters['q']=$q;$term='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where.=" AND (COALESCE(v.full_name,a.member_name_snapshot) LIKE ? ESCAPE '!' OR (CASE WHEN m.id IS NOT NULL THEN m.official_membership_id ELSE a.party_id_snapshot END) LIKE ? ESCAPE '!' OR a.member_number_snapshot LIKE ? ESCAPE '!' OR a.position_name_snapshot LIKE ? ESCAPE '!' OR a.jurisdiction_snapshot LIKE ? ESCAPE '!' OR a.member_name_snapshot LIKE ? ESCAPE '!' OR a.party_id_snapshot LIKE ? ESCAPE '!')";array_push($params,$term,$term,$term,$term,$term,$term,$term);}
  if(Validation::text($input,'level',20,false)!==null){$filters['level']=Validation::choice($input,'level',array_keys(PositionService::LEVELS));$where.=' AND a.organization_level=?';$params[]=$filters['level'];}
  foreach(['position_id','region_id','constituency_id'] as $field){if(Validation::text($input,$field,20,false)!==null){$filters[$field]=Validation::id($input,$field);$where.=" AND a.$field=?";$params[]=$filters[$field];}}
  if(Validation::text($input,'type',20,false)!==null){$filters['type']=Validation::choice($input,'type',array_keys(self::TYPES));$where.=' AND a.appointment_type=?';$params[]=$filters['type'];}
  foreach(['residential_region_filter'=>'residential_region_id','residential_district_filter'=>'residential_district_id'] as $key=>$column){
   if(Validation::text($input,$key,20,false)!==null){$filters[$key]=Validation::id($input,$key);$where.=" AND v.$column=? AND v.deleted_at IS NULL";$params[]=$filters[$key];}
  }
  if(Validation::text($input,'review',20,false)!==null){
   $filters['review']=Validation::choice($input,'review',['due','overdue']);
   $where.=' AND a.ended_on IS NULL AND a.cancelled_at IS NULL AND a.starts_on<=? AND a.expected_ends_on IS NOT NULL';$params[]=$today;
   if($filters['review']==='overdue'){$where.=' AND a.expected_ends_on<?';$params[]=$today;}
   else{$where.=' AND a.expected_ends_on BETWEEN ? AND ?';$params[]=$today;$params[]=(new \DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');}
  }
  TermService::filter($input,$filters,$where,$params);
  OrganizationService::filter($input,$filters,$where,$params);
  $termColumn=TermService::ready()?'a.leadership_term_name_snapshot':'NULL AS leadership_term_name_snapshot';
  $termColumn=OrganizationService::column().','.$termColumn;
  $from=' FROM executive_appointments a LEFT JOIN members m ON m.id=a.member_id LEFT JOIN voters v ON v.id=a.voter_record_id';
  $total=(int)Database::scalar('SELECT COUNT(*)'.$from.' WHERE '.$where,$params);$last=max(1,(int)ceil($total/25));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*25;
  $photo=\App\PhotoService::ready()?'(v.deleted_at IS NULL OR '.(Auth::admin()?'1':'0').') AND EXISTS(SELECT 1 FROM voter_photos vp WHERE vp.voter_record_id=a.voter_record_id)':'0';
  if($export&&$total>5000){throw new \InvalidArgumentException('This report exceeds 5,000 rows. Narrow the filters before printing or exporting.');}
  $limitSql=$export?' LIMIT 5000':" LIMIT 25 OFFSET $offset";
  $rows=Database::all('SELECT '.$termColumn.',a.id,a.member_id,a.voter_record_id,a.position_name_snapshot,a.organization_level,a.jurisdiction_snapshot,a.appointment_type,a.starts_on,a.expected_ends_on,a.ended_on,a.end_reason,a.cancelled_at,CASE WHEN a.ended_on IS NOT NULL OR a.cancelled_at IS NOT NULL THEN a.member_name_snapshot ELSE COALESCE(v.full_name,a.member_name_snapshot) END AS full_name,CASE WHEN a.ended_on IS NOT NULL OR a.cancelled_at IS NOT NULL THEN a.member_number_snapshot ELSE COALESCE(m.member_number,a.member_number_snapshot) END AS member_number,CASE WHEN a.ended_on IS NULL AND a.cancelled_at IS NULL AND m.id IS NOT NULL THEN m.official_membership_id ELSE a.party_id_snapshot END AS official_membership_id,m.status AS membership_status,v.is_deceased,'.$photo.' AS has_photo'.$from.' WHERE '.$where.' ORDER BY a.starts_on DESC,a.id DESC'.$limitSql,$params);
  $positions=Database::all('SELECT id,name FROM executive_position_titles ORDER BY display_order,name,id');$catalog=LocationService::catalog(false);
  $leadershipTerms=TermService::catalog();
  $scopeChoices=HistoryService::scopeChoices($filters);
  return compact('available','filters','total','last','p','rows','positions','catalog','today','scopeChoices','leadershipTerms');
 }
 public static function memberAppointments(int $member): array {
  return self::ready()?Database::all('SELECT '.OrganizationService::column('executive_appointments').',id,position_name_snapshot,organization_level,jurisdiction_snapshot,appointment_type,starts_on,expected_ends_on FROM executive_appointments WHERE member_id=? AND ended_on IS NULL AND cancelled_at IS NULL ORDER BY starts_on DESC,id DESC',[$member]):[];
 }
 public static function memberDirectoryRoles(array $members): array {
  if(!$members||!self::ready()){return [];}
  $members=array_values(array_unique(array_filter(array_map('intval',$members),static fn($id)=>$id>0)));
  if(!$members){return [];}
  $marks=implode(',',array_fill(0,count($members),'?'));
  $rows=Database::all('SELECT '.OrganizationService::column().',a.id,a.member_id,a.organization_level,a.position_name_snapshot,a.jurisdiction_snapshot,a.appointment_type FROM executive_appointments a WHERE a.member_id IN ('.$marks.') AND a.ended_on IS NULL AND a.cancelled_at IS NULL AND a.starts_on<=? ORDER BY a.position_name_snapshot,a.organization_level,a.id',[...$members,gmdate('Y-m-d')]);
  $roles=[];
  foreach($rows as $role){$roles[(int)$role['member_id']][$role['organization_level']][]=$role;}
  return $roles;
 }
 public static function detail(int $id): array {
  Auth::requireLogin();if(!self::ready()){throw new \InvalidArgumentException('Complete executive appointment migrations 17 and 18 first.');}
  $appointment=Database::one('SELECT a.*,u.name AS created_by,eu.name AS closed_by,(SELECT nxt.id FROM executive_appointments nxt WHERE nxt.replaces_appointment_id=a.id) AS successor_id,m.member_number,m.official_membership_id,v.full_name,v.is_deceased FROM executive_appointments a LEFT JOIN app_users u ON u.id=a.created_by_user_id LEFT JOIN app_users eu ON eu.id=a.ended_by_user_id LEFT JOIN members m ON m.id=a.member_id LEFT JOIN voters v ON v.id=a.voter_record_id WHERE a.id=?',[$id])??throw new \App\HttpException(404,'Appointment not found.');
  return compact('appointment');
 }
}
