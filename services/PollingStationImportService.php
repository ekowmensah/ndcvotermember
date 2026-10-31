<?php
declare(strict_types=1);
namespace App;

final class PollingStationImportService
{
 public const HEADERS=['station_code','name'];
 public static function ready(): bool {return LocationService::enhanced()&&LocationService::stationCodesReady()&&LocationService::withoutShortnamesReady()&&LocationService::sharedStationNamesReady();}
 public static function page(array $input): array {
  $available=self::ready();$catalog=LocationService::catalog(false);$parent=null;
  if(!empty($input['parent_id'])&&$available){
   $parent=Validation::id($input,'parent_id');
   if(!Database::scalar('SELECT id FROM constituencies WHERE id=?',[$parent])){throw new \InvalidArgumentException('Choose an existing constituency.');}
  }
  $history=Database::all("SELECT a.occurred_at,a.change_summary,u.name AS uploaded_by FROM audit_events a LEFT JOIN app_users u ON u.id=a.actor_user_id WHERE a.action_code='polling_stations.uploaded' ORDER BY a.id DESC LIMIT 10");
  return compact('available','catalog','parent','history');
 }
 public static function reviewImportRow(array $raw,int $parent,bool $lock=false): array {
  $code=Validation::text($raw,'station_code',64);$name=Validation::text($raw,'name',250);
  if(preg_match('/[\x00-\x1F\x7F]/',$code)){throw new \InvalidArgumentException('Polling station ID cannot contain control characters.');}
  $existing=Database::one('SELECT id,name,constituency_id,updated_at,(name=?) AS same_name FROM polling_stations WHERE station_code=?'.($lock?' FOR UPDATE':''),[$name,$code]);
  $base=['identifier'=>$code,'name'=>$name,'existing_name'=>$existing['name']??null,'target_id'=>$existing?(int)$existing['id']:null,'version'=>$existing['updated_at']??null,
   'fingerprint'=>hash('sha256',json_encode([$code,$name,$parent],JSON_THROW_ON_ERROR))];
  if($existing){
   if((int)$existing['constituency_id']!==$parent){return $base+['status'=>'conflict','message'=>'This Station ID already exists in another constituency. No duplicate will be created. Correct the file or review the existing station.'];}
   if(!(int)$existing['same_name']){return $base+['status'=>'conflict','message'=>'This Station ID already exists with a different station name. Review the existing station; CSV uploads do not overwrite it.'];}
   return $base+['status'=>'skip','message'=>'This Station ID already exists with the same name and constituency. It will be skipped; its branch and voter links are preserved.'];
  }
  return $base+['status'=>'new','message'=>'New station under the selected constituency; no initial branch.','data'=>['code'=>$code,'name'=>$name]];
 }
 public static function applyReviewedImport(array $rows,array $sourceRows,int $parent,string $filename): array {
  VoterService::requireManage();
  if(!Database::connection()->inTransaction()){throw new \LogicException('A reviewed import requires a transaction.');}
  $counts=['added'=>0,'skipped'=>0];
  foreach($rows as $index=>$row){
   if($row['status']==='new'){
    $review=self::reviewImportRow($sourceRows[$index]['raw'],$parent,true);
    if($review['status']!=='new'||$review['fingerprint']!==$row['fingerprint']){throw new \InvalidArgumentException('A station changed during confirmation. No rows were imported; refresh the preview.');}
    LocationService::insertPollingStation($parent,$review['data']['code'],$review['data']['name'],'csv');$counts['added']++;
   }
   else{$counts['skipped']++;}
  }
  Auth::audit('polling_stations.uploaded','constituency',$parent,$counts+['filename'=>$filename,'constituency_id'=>$parent,'reviewed'=>true]);
  return $counts;
 }
}
