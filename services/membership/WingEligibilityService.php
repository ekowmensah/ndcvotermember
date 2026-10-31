<?php
declare(strict_types=1);
namespace App\Membership;
use App\{Auth,Database,Validation};

final class WingEligibilityService
{
 public const YOUTH_MIN=18;
 public const YOUTH_MAX=49;
 public static function age(?string $birth,?string $on=null): ?int {
  if(!$birth){return null;}
  $zone=new \DateTimeZone('UTC');$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$birth,$zone);
  $at=\DateTimeImmutable::createFromFormat('!Y-m-d',$on??gmdate('Y-m-d'),$zone);
  if(!$date||$date->format('Y-m-d')!==$birth||!$at||$date>$at){return null;}
  return $date->diff($at)->y;
 }
 public static function ruleIssue(array $person,string $wing,?string $on=null): ?string {
  if($wing==='women'){return ($person['gender']??'unknown')==='female'?null:"Women's Wing requires the member to be recorded as Female.";}
  $age=self::age($person['date_of_birth']??null,$on);
  if($age===null){return 'Record a valid date of birth to assess Youth Wing eligibility.';}
  return $age>=self::YOUTH_MIN&&$age<=self::YOUTH_MAX?null:'Youth Wing requires age 18–49 inclusive.';
 }
 // SQL expressions use fixed internal aliases and exactly the same agreed rules.
 public static function ruleSql(string $wing): string {
  return $wing==='women'?"v.gender='female'":"v.date_of_birth IS NOT NULL AND v.date_of_birth<=UTC_DATE() AND TIMESTAMPDIFF(YEAR,v.date_of_birth,UTC_DATE()) BETWEEN 18 AND 49";
 }
 public static function reviewSql(string $wing): string {
  return "(m.id IS NULL OR m.status<>'active' OR v.id IS NULL OR v.is_deceased=1 OR NOT (".self::ruleSql($wing).'))';
 }
 public static function agedOutSql(): string {return 'v.date_of_birth IS NOT NULL AND v.date_of_birth<=UTC_DATE() AND TIMESTAMPDIFF(YEAR,v.date_of_birth,UTC_DATE())>=50';}
 public static function candidateCondition(string $wing,array &$params): string {
  array_push($params,$wing,$wing);
  return "m.status='active' AND v.is_deceased=0 AND ".\App\Voters\TrashService::active().' AND ('.self::ruleSql($wing).') AND NOT EXISTS(SELECT 1 FROM member_wing_enrollments w WHERE w.voter_record_id=v.id AND w.wing_code=? AND w.ended_on IS NULL) AND NOT EXISTS(SELECT 1 FROM member_wing_enrollments past WHERE past.voter_record_id=v.id AND past.wing_code=? AND past.ended_on>=UTC_DATE())';
 }
 public static function candidates(array $input,bool $export=false): array {
  Auth::requireLogin();$wing=Validation::choice($input,'wing',array_keys(WingService::WINGS));$available=WingService::ready();
  if(!$available){return compact('available','wing');}
  $filters=['wing'=>$wing];$params=[];$where=self::candidateCondition($wing,$params);
  WingScopeService::apply($input,$filters,$where,$params);
  $q=Validation::text($input,'q',100,false);
  if($q!==null){$filters['q']=$q;$term='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where.=" AND (v.full_name LIKE ? ESCAPE '!' OR m.official_membership_id LIKE ? ESCAPE '!' OR m.member_number LIKE ? ESCAPE '!')";array_push($params,$term,$term,$term);}
  foreach(['constituency_filter'=>'ps.constituency_id','branch_filter'=>'ps.electoral_area_id'] as $key=>$column){if(Validation::text($input,$key,20,false)!==null){$filters[$key]=Validation::id($input,$key);$where.=" AND $column=?";$params[]=$filters[$key];}}
  $from=' FROM members m JOIN voters v ON v.id=m.voter_record_id LEFT JOIN polling_stations ps ON ps.id=v.polling_station_id LEFT JOIN constituencies c ON c.id=ps.constituency_id LEFT JOIN electoral_areas b ON b.id=ps.electoral_area_id';
  $total=(int)Database::scalar('SELECT COUNT(*)'.$from.' WHERE '.$where,$params);$last=max(1,(int)ceil($total/25));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*25;
  if($export){\App\Reporting\ReportOutput::checkSize($total);}
  $limit=$export?' LIMIT 5000':" LIMIT 25 OFFSET $offset";
  $rows=Database::all('SELECT m.id AS member_id,m.member_number,m.official_membership_id,v.full_name,v.gender,TIMESTAMPDIFF(YEAR,v.date_of_birth,UTC_DATE()) AS age,c.name AS constituency,b.name AS branch'.$from.' WHERE '.$where.' ORDER BY v.full_name,m.id'.$limit,$params);
  $catalog=\App\LocationService::catalog(false);return compact('available','wing','filters','total','last','p','rows','catalog');
 }
}
