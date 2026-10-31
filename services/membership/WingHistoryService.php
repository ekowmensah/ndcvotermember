<?php
declare(strict_types=1);
namespace App\Membership;
use App\{Auth,Database,HttpException,Validation};
final class WingHistoryService
{
 public static function hasHistory(int $person): bool {
  return WingService::ready()&&(bool)Database::scalar('SELECT id FROM member_wing_enrollments WHERE voter_record_id=? LIMIT 1',[$person]);
 }
 public static function rows(int $person,int $limit=5,int $offset=0,array $filters=[],bool $export=false): array {
  if(!WingService::ready()){return [];}
  $params=[$person];$where=self::where($filters,$params);$limit=max(1,min($export?5000:25,$limit));$offset=max(0,$offset);
  return Database::all('SELECT w.id,w.wing_code,w.member_name_snapshot,w.member_number_snapshot,w.joined_on,w.ended_on,w.end_reason,w.created_at,w.updated_at,u.name AS enrolled_by,e.name AS ended_by FROM member_wing_enrollments w LEFT JOIN app_users u ON u.id=w.created_by_user_id LEFT JOIN app_users e ON e.id=w.ended_by_user_id WHERE '.$where." ORDER BY w.joined_on DESC,w.id DESC LIMIT $limit OFFSET $offset",$params);
 }
 private static function where(array $filters,array &$params): string {
  $where='w.voter_record_id=?';
  if(isset($filters['wing'])){$where.=' AND w.wing_code=?';$params[]=$filters['wing'];}
  if(isset($filters['state'])){$where.=$filters['state']==='active'?' AND w.ended_on IS NULL':' AND w.ended_on IS NOT NULL';}
  return $where;
 }
 public static function page(array $input,bool $export=false): array {
  Auth::requireLogin();$person=Database::one('SELECT v.id,v.full_name,m.id AS membership_id FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id WHERE v.id=? AND '.\App\Voters\TrashService::active(),[Validation::id($input,'id')])??throw new HttpException(404,'Person not found.');$available=WingService::ready();
  if(!$available){return compact('person','available');}
  $filters=['id'=>$person['id']];
  if(Validation::text($input,'wing',20,false)!==null){$filters['wing']=Validation::choice($input,'wing',array_keys(WingService::WINGS));}
  if(Validation::text($input,'state',20,false)!==null){$filters['state']=Validation::choice($input,'state',['active','ended']);}
  $params=[$person['id']];$where=self::where($filters,$params);
  $total=(int)Database::scalar('SELECT COUNT(*) FROM member_wing_enrollments w WHERE '.$where,$params);$last=max(1,(int)ceil($total/25));$p=min($last,max(1,(int)($input['p']??1)));
  if($export){\App\Reporting\ReportOutput::checkSize($total);}
  $rows=self::rows((int)$person['id'],$export?5000:25,$export?0:($p-1)*25,$filters,$export);
  return compact('person','available','filters','total','last','p','rows');
 }
}
