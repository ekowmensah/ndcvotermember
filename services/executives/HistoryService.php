<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Auth,Database,Validation,LocationService,PhotoService};
final class HistoryService
{
 public static function scopeChoices(array $filters): array {
  $choices=[];foreach(['residential_region_filter'=>'residential_regions','residential_district_filter'=>'districts'] as $key=>$table){if(isset($filters[$key])){$choices[$key]=[$filters[$key]=>Database::scalar("SELECT name FROM $table WHERE id=?",[$filters[$key]])??'Unknown location'];}}return $choices;
 }
 private static function residentialScope(array $input,array &$filters,string &$where,array &$params): void {
  foreach(['residential_region_filter'=>'residential_region_id','residential_district_filter'=>'residential_district_id'] as $key=>$column){
   if(Validation::text($input,$key,20,false)!==null){$filters[$key]=Validation::id($input,$key);$where.=" AND v.$column=?";$params[]=$filters[$key];}
  }
 }
 public static function listing(array $input,bool $export=false): array {
  Auth::requireLogin();$available=AppointmentService::ready();if(!$available){return compact('available');}
  $where='a.ended_on IS NOT NULL AND a.cancelled_at IS NULL';$params=[];$filters=[];
  $q=Validation::text($input,'q',100,false);
  if($q!==null){$filters['q']=$q;$term='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where.=" AND (a.member_name_snapshot LIKE ? ESCAPE '!' OR a.position_name_snapshot LIKE ? ESCAPE '!' OR a.jurisdiction_snapshot LIKE ? ESCAPE '!' OR a.party_id_snapshot LIKE ? ESCAPE '!' OR a.member_number_snapshot LIKE ? ESCAPE '!')";array_push($params,$term,$term,$term,$term,$term);}
  foreach(['level'=>['a.organization_level',array_keys(PositionService::LEVELS)],'type'=>['a.appointment_type',array_keys(AppointmentService::TYPES)],'reason'=>['a.end_reason',array_keys(LifecycleService::REASONS)]] as $key=>[$column,$choices]){
   if(Validation::text($input,$key,30,false)!==null){$filters[$key]=Validation::choice($input,$key,$choices);$where.=" AND $column=?";$params[]=$filters[$key];}
  }
  foreach(['position_id','region_id','constituency_id','branch_id','polling_station_id'] as $key){if(Validation::text($input,$key,20,false)!==null){$filters[$key]=Validation::id($input,$key);$where.=" AND a.$key=?";$params[]=$filters[$key];}}
  $year=Validation::text($input,'year',4,false);
  if($year!==null){
   if(!preg_match('/^[1-9][0-9]{3}$/D',$year)){throw new \InvalidArgumentException('Enter a four-digit service year.');}
   $filters['year']=$year;$where.=' AND a.starts_on<=? AND a.ended_on>=?';array_push($params,$year.'-12-31',$year.'-01-01');
  }
  self::residentialScope($input,$filters,$where,$params);
  if(Validation::text($input,'active_people',1,false)!==null){$filters['active_people']=Validation::choice($input,'active_people',['1']);$where.=' AND v.id IS NOT NULL AND v.deleted_at IS NULL';}
  $filters['sort']=Validation::choice(['sort'=>$input['sort']??'latest'],'sort',['latest','oldest','name','position']);
  $order=match($filters['sort']){'oldest'=>'a.ended_on,a.starts_on,a.id','name'=>'a.member_name_snapshot,a.ended_on DESC,a.id DESC','position'=>'a.position_name_snapshot,a.ended_on DESC,a.id DESC',default=>'a.ended_on DESC,a.starts_on DESC,a.id DESC'};
  TermService::filter($input,$filters,$where,$params);
  OrganizationService::filter($input,$filters,$where,$params);
  $termColumn=TermService::ready()?'a.leadership_term_name_snapshot':'NULL AS leadership_term_name_snapshot';
  $termColumn=OrganizationService::column().','.$termColumn;
  $from=' FROM executive_appointments a LEFT JOIN voters v ON v.id=a.voter_record_id LEFT JOIN members m ON m.id=a.member_id';
  $summary=Database::one('SELECT COUNT(*) AS terms,COUNT(DISTINCT a.member_number_snapshot) AS memberships,COUNT(DISTINCT a.position_id) AS positions,COUNT(DISTINCT a.organization_level) AS levels'.$from.' WHERE '.$where,$params);
  $total=(int)$summary['terms'];$last=max(1,(int)ceil($total/25));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*25;
  $photo=PhotoService::ready()?'((v.deleted_at IS NULL OR '.(Auth::admin()?'1':'0').') AND EXISTS(SELECT 1 FROM voter_photos vp WHERE vp.voter_record_id=a.voter_record_id))':'0';
  if($export&&$total>5000){throw new \InvalidArgumentException('This report exceeds 5,000 rows. Narrow the filters before printing or exporting.');}
  $limitSql=$export?' LIMIT 5000':" LIMIT 25 OFFSET $offset";
  $rows=Database::all('SELECT '.$termColumn.',a.id,a.member_id,a.voter_record_id,a.member_name_snapshot,a.member_number_snapshot AS member_number,a.party_id_snapshot AS official_membership_id,a.position_name_snapshot,a.organization_level,a.jurisdiction_snapshot,a.appointment_type,a.starts_on,a.ended_on,a.expected_ends_on,a.end_reason,v.is_deceased,'.$photo.' AS has_photo'.$from.' WHERE '.$where." ORDER BY $order".$limitSql,$params);
  $positions=Database::all('SELECT id,name FROM executive_position_titles ORDER BY display_order,name,id');$catalog=LocationService::catalog();
  $leadershipTerms=TermService::catalog();
  $scopeChoices=self::scopeChoices($filters);
  return compact('available','filters','summary','total','last','p','rows','positions','catalog','scopeChoices','leadershipTerms');
 }
 public static function dashboard(array $scope): array {
  if(!AppointmentService::ready()){return ['available'=>false];}
  $filters=[];$where='v.deleted_at IS NULL AND a.cancelled_at IS NULL';$params=[];self::residentialScope($scope,$filters,$where,$params);
  OrganizationService::filter($scope,$filters,$where,$params);
  $today=gmdate('Y-m-d');$soon=(new \DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');
  $from=' FROM executive_appointments a JOIN voters v ON v.id=a.voter_record_id';
  $counts=Database::one('SELECT COALESCE(SUM(a.ended_on IS NULL AND a.starts_on<=?),0) AS current,COALESCE(SUM(a.ended_on IS NULL AND a.starts_on>?),0) AS scheduled,COALESCE(SUM(a.ended_on IS NOT NULL),0) AS completed,COALESCE(SUM(a.ended_on IS NULL AND a.starts_on<=? AND a.expected_ends_on BETWEEN ? AND ?),0) AS due,COALESCE(SUM(a.ended_on IS NULL AND a.starts_on<=? AND a.expected_ends_on<?),0) AS overdue'.$from.' WHERE '.$where,[$today,$today,$today,$today,$soon,$today,$today,...$params]);
  $byLevel=Database::all('SELECT a.organization_level,COUNT(*) AS appointments,COUNT(DISTINCT a.voter_record_id) AS people'.$from.' WHERE '.$where.' AND a.ended_on IS NULL AND a.starts_on<=? GROUP BY a.organization_level', [...$params,$today]);
  return ['available'=>true,'counts'=>$counts,'byLevel'=>$byLevel,'scope'=>$filters];
 }
}
