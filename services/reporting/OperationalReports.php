<?php
declare(strict_types=1);
namespace App\Reporting;
use App\{Auth,Database,Validation,HttpException,VoterService,PhotoService,AddressService,TownService};
use App\Security\ExecutiveAccess;
use App\Membership\BranchWorkflow;
use App\Executives\{AppointmentService,OrganizationService,PositionService};
final class OperationalReports
{
 public const KINDS=['nominations','quality','executives'];
 public const ISSUES=['all'=>'Any missing information','names'=>'Missing name parts','photo'=>'Missing photo','party_id'=>'Missing Party ID','station'=>'Missing polling station','branch'=>'Station has no branch','residence'=>'Incomplete residential location','phone'=>'Missing phone (authorized contacts only)','email'=>'Missing email (authorized contacts only)'];
 public static function available(string $kind): bool {return match($kind){'nominations'=>BranchWorkflow::ready(),'executives'=>AppointmentService::ready()&&OrganizationService::ready(),default=>true};}
 public static function accessible(string $kind): bool {
  Auth::requireLogin();return $kind!=='nominations'||(!ExecutiveAccess::restricted()?Auth::canManage():(BranchWorkflow::permitted('can_nominate')||BranchWorkflow::permitted('can_approve_membership')));
 }
 public static function filters(array $input): array {
  $f=['kind'=>Validation::choice($input,'kind',self::KINDS),'per_page'=>(int)Validation::choice(['per_page'=>$input['per_page']??'25'],'per_page',['25','50','100'])];
  $q=Validation::text($input,'q',100,false);if($q!==null){$f['q']=$q;}
  foreach(array_keys(PeopleReports::LEVELS) as $key){if(Validation::text($input,$key,20,false)!==null){$f[$key]=Validation::id($input,$key);}}
  if($f['kind']==='nominations'){
   $f['nomination_status']=Validation::choice(['nomination_status'=>$input['nomination_status']??'all'],'nomination_status',['all','pending','approved','rejected','superseded']);
   $f['date_basis']=Validation::choice(['date_basis'=>$input['date_basis']??'submitted'],'date_basis',['submitted','decided']);
  }elseif($f['kind']==='quality'){$f['issue']=Validation::choice(['issue'=>$input['issue']??'all'],'issue',array_keys(self::ISSUES));$f['membership']=Validation::choice(['membership'=>$input['membership']??'all'],'membership',['all','member','nonmember']);}
  else{foreach(['office_level'=>array_keys(PositionService::LEVELS),'organization_scope'=>array_keys(OrganizationService::SCOPES),'appointment_type'=>array_keys(AppointmentService::TYPES)] as $key=>$allowed){if(Validation::text($input,$key,32,false)!==null){$f[$key]=Validation::choice($input,$key,$allowed);}}}
  foreach(['from','to'] as $key){$date=Validation::date($input,$key);if($date!==null){$f[$key]=$date;}}
  if(isset($f['from'],$f['to'])&&$f['from']>$f['to']){throw new \InvalidArgumentException('The start date must not be after the end date.');}
  return $f;
 }
 private static function peopleFrom(): string {return ' FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id LEFT JOIN polling_stations ps ON ps.id=v.polling_station_id LEFT JOIN constituencies c ON c.id=ps.constituency_id LEFT JOIN regions r ON r.id=c.region_id LEFT JOIN electoral_areas b ON b.id=ps.electoral_area_id';}
 public static function prepare(array $input,bool $full=false): array {
  Auth::requireLogin();$f=self::filters($input);$kind=$f['kind'];
  if(!self::accessible($kind)){throw new HttpException(403,'Nomination reporting is limited to authorized staff and nomination reviewers.');}
  if(!self::available($kind)){throw new \InvalidArgumentException('Complete the existing '.$kind.' module migrations before using this report.');}
  return Database::transaction(function()use($input,$f,$kind,$full):array{
   $params=[];$links=[];$where='v.deleted_at IS NULL AND ('.ExecutiveAccess::condition().')';$from=self::peopleFrom();$locationCols=['region'=>'r.id','constituency'=>'c.id','branch'=>'b.id','station'=>'ps.id'];
   if($kind==='nominations'){
    $from=' FROM membership_nominations n LEFT JOIN voters v ON v.id=n.voter_record_id LEFT JOIN members m ON m.voter_record_id=v.id LEFT JOIN polling_stations ps ON ps.id=v.polling_station_id LEFT JOIN constituencies c ON c.id=ps.constituency_id LEFT JOIN regions r ON r.id=c.region_id LEFT JOIN electoral_areas b ON b.id=ps.electoral_area_id JOIN app_users nu ON nu.id=n.nominated_by_user_id LEFT JOIN app_users du ON du.id=n.decided_by_user_id';
    $where='('.BranchWorkflow::reportScope().')';
    if($f['nomination_status']!=='all'){$where.=' AND n.status=?';$params[]=$f['nomination_status'];}
    $fields="n.id,n.voter_name_snapshot,v.voter_id,n.status,c.name AS constituency,b.name AS branch,ps.name AS station,nu.name AS submitted_by,n.created_at,du.name AS decided_by,n.decided_at,IF(n.decided_at IS NULL,NULL,ROUND(GREATEST(0,TIMESTAMPDIFF(HOUR,n.created_at,n.decided_at))/24,1)) AS decision_days,IF(n.status='pending',ROUND(GREATEST(0,TIMESTAMPDIFF(HOUR,n.created_at,UTC_TIMESTAMP()))/24,1),NULL) AS pending_days";
    $columns=['Nomination #','Name at submission','Current voter ID','Outcome','Current constituency','Current branch','Current station','Submitted by','Submitted (UTC)','Decided by','Decided (UTC)','Days to decision','Pending days'];
    $aggregate="COUNT(*) AS total,SUM(n.status='pending') AS pending,SUM(n.status='approved') AS approved,SUM(n.status='rejected') AS rejected,SUM(n.status='superseded') AS superseded";
    $dateColumn=$f['date_basis']==='decided'?'n.decided_at':'n.created_at';$dateLabel=$f['date_basis']==='decided'?'Recorded decision date':'Recorded submission date';$order='n.created_at DESC,n.id DESC';$search=['n.voter_name_snapshot','v.voter_id','nu.name'];
    $notice='Nomination records, not unique people. Approved, rejected and enrolled separately are distinct outcomes. Timing uses recorded submission/decision timestamps; pending days is current age. Notes are excluded. Location labels use the person’s current station; deleted/unavailable voters remain only for authorized ordinary staff.';
   }elseif($kind==='executives'){
    $from=' FROM executive_appointments a LEFT JOIN members m ON m.id=a.member_id LEFT JOIN voters v ON v.id=a.voter_record_id LEFT JOIN regions r ON r.id=a.region_id LEFT JOIN constituencies c ON c.id=a.constituency_id LEFT JOIN polling_stations ps ON ps.id=a.polling_station_id LEFT JOIN electoral_areas b ON b.id=COALESCE(a.branch_id,ps.electoral_area_id)';
    $where='a.ended_on IS NULL AND a.cancelled_at IS NULL AND a.starts_on<=UTC_DATE() AND ('.ExecutiveAccess::appointmentCondition().')';
    $locationCols=['region'=>'a.region_id','constituency'=>'a.constituency_id','branch'=>'b.id','station'=>'a.polling_station_id'];
    foreach(['office_level'=>'a.organization_level','organization_scope'=>'a.organization_scope','appointment_type'=>'a.appointment_type'] as $key=>$column){if(isset($f[$key])){$where.=" AND $column=?";$params[]=$f[$key];}}
    $fields="a.id,COALESCE(v.full_name,a.member_name_snapshot) AS executive_name,IF(m.id IS NULL,a.party_id_snapshot,m.official_membership_id) AS party_id,COALESCE(m.member_number,a.member_number_snapshot) AS system_number,a.position_name_snapshot,a.organization_scope,a.organization_level,a.jurisdiction_snapshot,a.appointment_type,a.starts_on,a.expected_ends_on,COALESCE(m.status,'Membership removed') AS membership_status,IF(v.id IS NULL,'Person unavailable',IF(v.is_deceased=1,'Deceased','Not marked deceased')) AS life_status,IF(a.expected_ends_on<UTC_DATE(),'Expected end overdue','') AS expected_end_review";
    $columns=['Appointment #','Name','Party ID','Sys No.','Position','Organization','Level','Saved office jurisdiction','Appointment type','Service starts','Expected end','Current membership','Life status','Review'];
    $aggregate="COUNT(*) AS total,COUNT(DISTINCT a.voter_record_id) AS people,SUM(a.appointment_type='acting') AS acting,SUM(a.expected_ends_on<UTC_DATE()) AS overdue";
    $dateColumn='a.starts_on';$dateLabel='Service start date';$order='a.organization_level,a.position_name_snapshot,a.id';$search=['v.full_name','a.member_name_snapshot','a.position_name_snapshot','m.official_membership_id','a.party_id_snapshot','a.member_number_snapshot'];
    $notice='Current noncancelled, nonended offices only. Expected end is advisory. Names/identifiers use current linked records with saved snapshots as fallback; office titles/jurisdiction retain their saved labels. Membership/life flags reflect current linked records. Executive access is bounded by office jurisdiction, not the holder’s residential or voter station. Appointments are not distinct people.';
   }else{
    $contact=ExecutiveAccess::contacts()?ExecutiveAccess::condition('v',null,'can_contacts'):'1=0';
    $rules=['names'=>VoterService::namesReady()?"(v.first_name IS NULL OR TRIM(v.first_name)='' OR v.last_name IS NULL OR TRIM(v.last_name)='')":'1=1','photo'=>PhotoService::ready()?'NOT EXISTS(SELECT 1 FROM voter_photos vp WHERE vp.voter_record_id=v.id)':'1=1','party_id'=>"(m.id IS NOT NULL AND (m.official_membership_id IS NULL OR TRIM(m.official_membership_id)=''))",'station'=>'v.polling_station_id IS NULL','branch'=>'(ps.id IS NOT NULL AND ps.electoral_area_id IS NULL)','residence'=>AddressService::ready()&&TownService::ready()?'(v.residential_region_id IS NULL OR v.residential_district_id IS NULL OR v.residential_town_id IS NULL)':'0=1','phone'=>"(($contact) AND (v.phone_encrypted IS NULL OR v.phone_encrypted=''))",'email'=>"(($contact) AND (v.email_encrypted IS NULL OR v.email_encrypted=''))"];
    $issueLabels=['names'=>'Name parts','photo'=>'Photo','party_id'=>'Party ID','station'=>'Polling station','branch'=>'Station branch','residence'=>'Residential location','phone'=>'Phone','email'=>'Email'];$parts=[];
    foreach($rules as $key=>$rule){$parts[]="IF(($rule),'".$issueLabels[$key]."',NULL)";}
    $where.=' AND ('.($f['issue']==='all'?implode(' OR ',array_map(static fn($r)=>'('.$r.')',$rules)):$rules[$f['issue']]).')';
    if($f['membership']!=='all'){$where.=$f['membership']==='member'?' AND m.id IS NOT NULL':' AND m.id IS NULL';}
    $fields="v.id AS internal_voter,m.id AS internal_member,v.voter_id,v.full_name,IF(m.id IS NULL,'Nonmember','Member') AS membership,m.official_membership_id,m.member_number,c.name AS constituency,b.name AS branch,ps.name AS station,CONCAT_WS(', ',".implode(',',$parts).") AS missing_information";
    $columns=['Voter ID','Saved name','Membership','Party ID','Sys No.','Constituency','Branch','Polling station','Missing information'];
    $aggregate='COUNT(*) AS total,COUNT(m.id) AS members,SUM(m.id IS NULL) AS nonmembers,SUM(v.is_deceased=1) AS deceased';
    $dateColumn='v.created_at';$dateLabel='Voter record creation date';$order='v.full_name,v.id';$search=['v.full_name','v.voter_id','m.official_membership_id','m.member_number'];
    $notice='One row per matching person; several issues may apply. Optional missing details are review opportunities, not invalid records. Phone/email completeness is evaluated only where contact reading is permitted. No contact values, DOB, photos or notes are exported. Open record follows existing editor permissions; no report action edits data.';
   }
   if(isset($f['q'])){$term='%'.strtr($f['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where.=' AND ('.implode(' OR ',array_map(static fn($field)=>"$field LIKE ? ESCAPE '!'",$search)).')';foreach($search as $_){$params[]=$term;}}
   foreach($locationCols as $key=>$column){if(isset($f[$key])){$where.=" AND $column=?";$params[]=$f[$key];}}
   if(isset($f['from'])){$where.=" AND $dateColumn>=?";$params[]=$f['from'];}if(isset($f['to'])){$where.=" AND $dateColumn<?";$params[]=(new \DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d');}
   $totals=array_map(static fn($value)=>(int)($value??0),Database::one('SELECT '.$aggregate.$from.' WHERE '.$where,$params)??[]);$total=$totals['total'];
   if($full){ReportOutput::checkSize($total);}
   $perPage=$f['per_page'];$last=max(1,(int)ceil($total/$perPage));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*$perPage;
   $rows=Database::all('SELECT '.$fields.$from.' WHERE '.$where.' ORDER BY '.$order.($full?'':" LIMIT $perPage OFFSET $offset"),$params);$records=[];
   foreach($rows as $row){if($kind==='quality'){$links[]=\url($row['internal_member']?'member':'voter',['id'=>$row['internal_member']?:$row['internal_voter']]);unset($row['internal_voter'],$row['internal_member']);}elseif($kind==='nominations'){$row['status']=$row['status']==='superseded'?'Enrolled separately':ucfirst($row['status']);}elseif($kind==='executives'){$row['organization_scope']=OrganizationService::SCOPES[$row['organization_scope']];$row['organization_level']=PositionService::LEVELS[$row['organization_level']];$row['appointment_type']=AppointmentService::TYPES[$row['appointment_type']];}$records[]=array_map(static fn($value)=>$value===null?'':(string)$value,array_values($row));}
   $choices=array_fill_keys(array_keys(PeopleReports::LEVELS),[]);
   // Only metadata already matching this scoped report contributes dropdowns.
   $locations=Database::all('SELECT DISTINCT r.id AS region_id,r.name AS region,c.id AS constituency_id,c.name AS constituency,b.id AS branch_id,b.name AS branch,ps.id AS station_id,ps.name AS station,ps.station_code'.$from.' WHERE '.$where,$params);
   foreach($locations as $location){foreach(array_keys($choices) as $key){if($location[$key.'_id']){$choices[$key][$location[$key.'_id']]=$key==='station'?($location['station_code']!==null&&$location['station_code']!==''?$location['station_code'].' · ':'System #'.$location['station_id'].' · ').$location[$key]:$location[$key];}}}
   foreach($choices as &$choice){natcasesort($choice);}unset($choice);
   $criteria=['Scope'=>ExecutiveAccess::restricted()?'Current permitted executive jurisdictions':'Authorized staff scope','Record basis'=>$kind==='executives'?'Current office snapshots':'Current saved records','Date filter basis'=>$dateLabel];
   $criterionChoices=['issue'=>self::ISSUES,'nomination_status'=>['all'=>'All outcomes','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','superseded'=>'Enrolled separately'],'date_basis'=>['submitted'=>'Submission date','decided'=>'Decision date'],'membership'=>['all'=>'All voters','member'=>'Members','nonmember'=>'Nonmembers'],'office_level'=>PositionService::LEVELS,'organization_scope'=>OrganizationService::SCOPES,'appointment_type'=>AppointmentService::TYPES];
   foreach($f as $key=>$value){if(in_array($key,['kind','per_page'],true)){continue;}$criteria[\label($key)]=$choices[$key][$value]??$criterionChoices[$key][$value]??(string)$value;}
   $metricLabels=match($kind){'nominations'=>['total'=>'Matching nominations','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','superseded'=>'Enrolled separately'],'executives'=>['total'=>'Current appointments','people'=>'Linked people','acting'=>'Acting offices','overdue'=>'Expected end overdue'],default=>['total'=>'People needing review','members'=>'Members','nonmembers'=>'Nonmembers','deceased'=>'Marked deceased']};$metrics=[];foreach($metricLabels as $key=>$label){$metrics[$label]=$totals[$key];}
   $report=['title'=>PeopleReports::KINDS[$kind],'kind'=>$kind,'columns'=>$columns,'records'=>$records,'total'=>$total,'criteria'=>$criteria,'generated_at'=>gmdate('d M Y H:i').' UTC','notice'=>$notice.' Identifiers should be imported as text to preserve leading zeros.','back_url'=>\url('reports',$f),'links'=>$links];
   $pagination=['total'=>$total,'page'=>$p,'per_page'=>$perPage,'last'=>$last,'route'=>'reports','filters'=>$f];
   return ['report'=>$report,'filters'=>$f,'choices'=>$choices,'summary'=>[],'metrics'=>$metrics,'pagination'=>$pagination,'dateLabel'=>$dateLabel];
  });
 }
}
