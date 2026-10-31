<?php
declare(strict_types=1);
namespace App\Reporting;
use App\{Auth,Database,Validation,HttpException,LocationService,VoterService};
use App\Security\ExecutiveAccess;
final class PeopleReports
{
 public const KINDS=['voters'=>'Voter register','members'=>'Membership register','locations'=>'Electoral location summary','nominations'=>'Membership nominations','quality'=>'Data quality','executives'=>'Current executive directory'];
 public const LEVELS=['region'=>'Region','constituency'=>'Constituency','branch'=>'Electoral area / branch','station'=>'Polling station'];
 public static function canExport(): bool {
  $user=Auth::requireLogin();return ExecutiveAccess::restricted()?(!empty(ExecutiveAccess::account()['enabled'])&&!empty(ExecutiveAccess::account()['can_export'])):in_array($user['role'],['admin','registrar'],true);
 }
 public static function requireExport(): void {if(!self::canExport()){throw new HttpException(403,'CSV export permission is required.');}}
 public static function filters(array $input): array {
  if(in_array($input['kind']??'',OperationalReports::KINDS,true)){return OperationalReports::filters($input);}
  $filters=['kind'=>Validation::choice($input,'kind',array_keys(self::KINDS))];
  $q=Validation::text($input,'q',100,false);if($q!==null){$filters['q']=$q;}
  foreach(['gender'=>['female','male','other','unknown'],'life_status'=>['0','1'],'membership'=>['member','nonmember'],'status'=>['active','inactive']] as $key=>$values){if(Validation::text($input,$key,20,false)!==null){$filters[$key]=Validation::choice($input,$key,$values);}}
  if($filters['kind']==='members'){unset($filters['membership']);}else{unset($filters['status']);}
  foreach(array_keys(self::LEVELS) as $key){if(Validation::text($input,$key,20,false)!==null){$filters[$key]=Validation::id($input,$key);}}
  foreach(['from','to'] as $key){$date=Validation::date($input,$key);if($date!==null){$filters[$key]=$date;}}
  if(isset($filters['from'],$filters['to'])&&$filters['from']>$filters['to']){throw new \InvalidArgumentException('The start date must not be after the end date.');}
  if($filters['kind']==='locations'){$filters['level']=Validation::choice(['level'=>$input['level']??'constituency'],'level',array_keys(self::LEVELS));}
  $filters['per_page']=(int)Validation::choice(['per_page'=>$input['per_page']??'25'],'per_page',['25','50','100']);
  return $filters;
 }
 private static function from(): string {return ' FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id LEFT JOIN polling_stations ps ON ps.id=v.polling_station_id LEFT JOIN constituencies c ON c.id=ps.constituency_id LEFT JOIN regions r ON r.id=c.region_id LEFT JOIN electoral_areas b ON b.id=ps.electoral_area_id';}
 public static function prepare(array $input,bool $full=false): array {
  Auth::requireLogin();if(!LocationService::ready()||!LocationService::stationCodesReady()||!VoterService::officialIdsReady()||!\App\People\LifeStatusService::ready()||!\App\Voters\TrashService::ready()){throw new \InvalidArgumentException('Complete electoral location, Party ID and record lifecycle migrations before using these reports.');}
  $filters=self::filters($input);
  if(in_array($filters['kind'],OperationalReports::KINDS,true)){return OperationalReports::prepare($input,$full);}
  return Database::transaction(function()use($input,$filters,$full):array{
   $kind=$filters['kind'];$scope='v.deleted_at IS NULL AND ('.ExecutiveAccess::condition().')';$base=$scope.($kind==='members'?' AND m.id IS NOT NULL':'');$where=$base;$params=[];
   if(isset($filters['q'])){$term='%'.strtr($filters['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where.=" AND (v.full_name LIKE ? ESCAPE '!' OR v.voter_id LIKE ? ESCAPE '!' OR m.official_membership_id LIKE ? ESCAPE '!' OR m.member_number LIKE ? ESCAPE '!')";$params=[$term,$term,$term,$term];}
   foreach(['gender'=>'v.gender','life_status'=>'v.is_deceased','status'=>'m.status','region'=>'r.id','constituency'=>'c.id','branch'=>'b.id','station'=>'ps.id'] as $key=>$column){if(isset($filters[$key])){$where.=" AND $column=?";$params[]=$filters[$key];}}
   if(isset($filters['membership'])){$where.=$filters['membership']==='member'?' AND m.id IS NOT NULL':' AND m.id IS NULL';}
   $dateColumn=$kind==='members'?'m.joined_on':'v.created_at';
   if(isset($filters['from'])){$where.=" AND $dateColumn>=?";$params[]=$filters['from'];}
   if(isset($filters['to'])){$where.=" AND $dateColumn<?";$params[]=(new \DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');}
   $summary=array_map('intval',Database::one("SELECT COUNT(*) AS voters,COUNT(m.id) AS members,COALESCE(SUM(m.id IS NULL),0) AS nonmembers,COALESCE(SUM(v.is_deceased=1),0) AS deceased".self::from().' WHERE '.$where,$params)??[]);
   $locationRows=Database::all('SELECT DISTINCT r.id AS region_id,r.name AS region,c.id AS constituency_id,c.name AS constituency,b.id AS branch_id,b.name AS branch,ps.id AS station_id,ps.name AS station,ps.station_code'.self::from().' WHERE '.$base.' AND ps.id IS NOT NULL ORDER BY r.name,c.name,b.name,ps.name,ps.id');
   $choices=array_fill_keys(array_keys(self::LEVELS),[]);
   foreach($locationRows as $location){foreach(array_keys($choices) as $key){if($location[$key.'_id']){$choices[$key][$location[$key.'_id']]=$key==='station'?($location['station_code']!==null&&$location['station_code']!==''?$location['station_code'].' · ':'System #'.$location['station_id'].' · ').$location[$key]:$location[$key];}}}
   $records=[];$keys=[];$group='';
   if($kind==='locations'){
    $groups=['region'=>['r.id','r.name'],'constituency'=>['c.id','c.name'],'branch'=>['b.id','b.name'],'station'=>['ps.id','ps.name','ps.station_code']];$select=[];$columns=[];
    foreach($groups as $key=>$fields){foreach($fields as $field){$keys[]=$field;}$select[]=($key==='station'?"CONCAT(COALESCE(NULLIF(ps.station_code,''),IF(ps.id IS NULL,'No station',CONCAT('System #',ps.id))),' · ',COALESCE(ps.name,'Not assigned'))":"COALESCE(".['region'=>'r.name','constituency'=>'c.name','branch'=>'b.name'][$key].",'Not assigned')").' AS '.$key;$columns[]=self::LEVELS[$key];if($filters['level']===$key){break;}}
    $group=' GROUP BY '.implode(',',$keys);$query='SELECT '.implode(',',$select).",COUNT(*) AS voters,COUNT(m.id) AS members,SUM(m.id IS NULL) AS nonmembers,SUM(v.is_deceased=1) AS deceased,ROUND(100*COUNT(m.id)/COUNT(*),1) AS coverage".self::from().' WHERE '.$where.$group;
    $total=(int)Database::scalar('SELECT COUNT(*) FROM ('.$query.') report_groups',$params);
    $columns=[...$columns,'Voters','Members','Nonmembers','Marked deceased','Membership coverage (%)'];
   }else{
    $total=$summary['voters'];$names=VoterService::namesReady()?'v.last_name,v.first_name':'NULL AS last_name,NULL AS first_name';
    $fields=$kind==='members'?"m.official_membership_id,m.member_number,v.voter_id,$names,v.full_name,v.gender,m.status,m.joined_on":"v.voter_id,$names,v.full_name,v.gender,IF(m.id IS NULL,'Nonmember','Member') AS membership,m.official_membership_id,m.member_number";
    $query='SELECT '.$fields.",r.name AS region,c.name AS constituency,b.name AS branch,ps.name AS station,ps.station_code,IF(v.is_deceased=1,'Deceased','Not marked deceased') AS life_status".self::from().' WHERE '.$where;
    $columns=$kind==='members'?['Party ID','Sys No.','Voter ID','Last name','First name','Saved full name','Gender','Membership status','Joined on']:['Voter ID','Last name','First name','Saved full name','Gender','Membership','Party ID','Sys No.'];
    $columns=[...$columns,'Region','Constituency','Electoral area','Polling station','Station ID','Life status'];
   }
   if($full){ReportOutput::checkSize($total);}
   $perPage=$filters['per_page'];$last=max(1,(int)ceil($total/$perPage));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*$perPage;
   $order=' ORDER BY v.full_name,v.id';
   // Group order uses only fixed report aliases; never caller-provided SQL.
   if($kind==='locations'){$order=' ORDER BY '.implode(',',array_keys(array_slice(self::LEVELS,0,count($select),true))).','.implode(',',$keys);}
   $rows=Database::all($query.$order.($full?'':" LIMIT $perPage OFFSET $offset"),$params);
   foreach($rows as $row){$records[]=array_map(static fn($value)=>$value===null?'':(string)$value,array_values($row));}
   $criteria=['Access scope'=>ExecutiveAccess::restricted()?'Current permitted executive jurisdictions':'Authorized staff scope','Snapshot'=>'Current saved records; deleted voters excluded','Coverage basis'=>'Matching people after all filters; not a historical conversion rate','Date filter basis'=>$kind==='members'?'Membership joined date':'Voter record creation date'];
   $labels=['q'=>'Search','gender'=>'Gender','life_status'=>'Life status','membership'=>'Membership','status'=>'Membership status','region'=>'Region','constituency'=>'Constituency','branch'=>'Branch','station'=>'Station','from'=>'From','to'=>'Through','level'=>'Group by'];
   foreach($labels as $key=>$label){if(isset($filters[$key])){$value=$choices[$key][$filters[$key]]??($key==='level'?self::LEVELS[$filters[$key]]:($key==='life_status'?($filters[$key]==='1'?'Deceased':'Not marked deceased'):$filters[$key]));$criteria[$label]=(string)$value;}}
   $report=['title'=>self::KINDS[$kind],'kind'=>$kind,'columns'=>$columns,'records'=>$records,'total'=>$total,'criteria'=>$criteria,'generated_at'=>gmdate('d M Y H:i').' UTC','notice'=>'Current records only. Members are included in voter totals. Contacts, DOB, photos and notes are excluded. Empty directory locations are not included; unassigned records form separate groups. Import identifier columns as text to preserve leading zeros.','back_url'=>\url('reports',$filters)];
   $pagination=['total'=>$total,'page'=>$p,'per_page'=>$perPage,'last'=>$last,'route'=>'reports','filters'=>$filters];
   return compact('report','filters','choices','summary','pagination');
  });
 }
}
