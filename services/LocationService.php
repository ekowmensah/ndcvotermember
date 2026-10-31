<?php
declare(strict_types=1);
namespace App;
final class LocationService
{
 public const TYPES=['region'=>'regions','constituency'=>'constituencies','electoral_area'=>'electoral_areas','polling_station'=>'polling_stations'];
 public static function usesShortname(string $kind): bool {return in_array($kind,['region','constituency'],true);}
 public static function withoutShortnamesReady(): bool {
  static $ready=null;
  return $ready??=((int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name IN ('electoral_areas','polling_stations') AND column_name='shortname' AND is_nullable='YES'")===2);
 }
 public static function stationCodesReady(): bool {
  static $ready=null;
  return $ready??=(bool)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='polling_stations' AND column_name='station_code'");
 }
 public static function sharedStationNamesReady(): bool {
  static $ready=null;
  return $ready??=(self::stationCodesReady()&&(int)Database::scalar("SELECT COUNT(*) FROM (SELECT index_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='polling_stations' AND non_unique=0 GROUP BY index_name HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index)='station_code' AND SUM(sub_part IS NOT NULL)=0) AS official_ids")>0&&(int)Database::scalar("SELECT COUNT(*) FROM (SELECT index_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='polling_stations' AND non_unique=0 AND index_name<>'PRIMARY' GROUP BY index_name HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) IN ('name','constituency_id,name','name,constituency_id')) AS unique_names")===0);
 }
 public static function stationOptions(array $stations): array {
  $options=[];foreach($stations as $station){$code=(string)($station['station_code']??'');$options[$station['id']]=($code!==''?$code:'#'.$station['id']).' · '.$station['name'];}return $options;
 }
 public static function enhanced(): bool {
  static $ready=null;
  return $ready??=((int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name IN ('regions','constituencies','electoral_areas','polling_stations') AND column_name IN ('shortname','created_at','updated_at')")===12);
 }
 public static function context(array $input): array {
  $out=['type'=>Validation::choice(['type'=>$input['type']??'region'],'type',array_keys(self::TYPES))];
  foreach(['region_filter','constituency_filter','branch_filter','p'] as $key){if(!empty($input[$key])){$out[$key]=Validation::id($input,$key);}}
  $q=Validation::text($input,'q',100,false);if($q!==null){$out['q']=$q;}
  if(in_array($input['assignment']??'',['unassigned','assigned'],true)){$out['assignment']=$input['assignment'];}
  if(($out['type']??'')==='polling_station'&&self::stationCodesReady()&&Validation::text($input,'code_status',16,false)!==null){$out['code_status']=Validation::choice($input,'code_status',['missing','recorded']);}
  $out['sort']=Validation::choice(['sort'=>$input['sort']??'name'],'sort',['name','name_desc','newest']);
  $out['per_page']=(int)Validation::choice(['per_page'=>$input['per_page']??'25'],'per_page',['25','50','100']);
  return $out;
 }
 public static function directory(array $input): array {
  if(!self::enhanced()){return [];}
  $filters=self::context($input);$kind=$filters['type'];$table=self::TYPES[$kind];
  $joins='';$columns='l.*';$where=['1=1'];$params=[];
  if($kind==='constituency'){$joins=' JOIN regions r ON r.id=l.region_id';$columns.=',r.name AS region_name';}
  elseif($kind!=='region'){$joins=' JOIN constituencies c ON c.id=l.constituency_id JOIN regions r ON r.id=c.region_id';$columns.=',c.name AS constituency_name,r.name AS region_name,c.region_id';}
  if($kind==='polling_station'){$joins.=' LEFT JOIN electoral_areas b ON b.id=l.electoral_area_id';$columns.=',b.name AS branch_name';}
  if(!empty($filters['region_filter'])){$where[]=($kind==='region'?'l.id':($kind==='constituency'?'l.region_id':'c.region_id')).'=?';$params[]=$filters['region_filter'];}
  if($kind!=='region'&&!empty($filters['constituency_filter'])){$where[]=($kind==='constituency'?'l.id':'l.constituency_id').'=?';$params[]=$filters['constituency_filter'];}
  if($kind==='polling_station'&&!empty($filters['branch_filter'])){$where[]='l.electoral_area_id=?';$params[]=$filters['branch_filter'];}
  if($kind==='polling_station'&&isset($filters['assignment'])){$where[]=$filters['assignment']==='assigned'?'l.electoral_area_id IS NOT NULL':'l.electoral_area_id IS NULL';}
  if($kind==='polling_station'&&isset($filters['code_status'])){$where[]=$filters['code_status']==='missing'?'l.station_code IS NULL':'l.station_code IS NOT NULL';}
  if(isset($filters['q'])){
   $term=strtr($filters['q'],['!'=>'!!','%'=>'!%','_'=>'!_']);$search="l.name LIKE ? ESCAPE '!' OR CAST(l.id AS CHAR)=?";array_push($params,'%'.$term.'%',$filters['q']);
   if(self::usesShortname($kind)){$search.=" OR l.shortname LIKE ? ESCAPE '!'";$params[]='%'.$term.'%';}
   if($kind==='polling_station'&&self::stationCodesReady()){$search.=" OR l.station_code LIKE ? ESCAPE '!'";$params[]='%'.$term.'%';}
   $where[]='('.$search.')';
  }
  $sql=' FROM '.$table.' l'.$joins.' WHERE '.implode(' AND ',$where);
  $total=(int)Database::scalar('SELECT COUNT(*)'.$sql,$params);$perPage=$filters['per_page'];$last=max(1,(int)ceil($total/$perPage));$page=min($filters['p']??1,$last);$offset=($page-1)*$perPage;$filters['p']=$page;
  $usageColumn=match($kind){'region'=>'lv.region_id','constituency'=>'lv.constituency_id','polling_station'=>'lv.polling_station_id',default=>'lp.electoral_area_id'};
  $usageJoin=$kind==='electoral_area'?' JOIN polling_stations lp ON lp.id=lv.polling_station_id':'';
  $columns.=",(SELECT COUNT(*) FROM voters lv$usageJoin WHERE ".\App\Voters\TrashService::active('lv')." AND $usageColumn=l.id) AS voter_count";
  $columns.=",(SELECT COUNT(*) FROM voters lv JOIN members lm ON lm.voter_record_id=lv.id$usageJoin WHERE ".\App\Voters\TrashService::active('lv')." AND $usageColumn=l.id) AS member_count";
  $order=match($filters['sort']){'name_desc'=>'l.name DESC,l.id DESC','newest'=>'l.id DESC',default=>'l.name,l.id'};
  $rows=Database::all('SELECT '.$columns.$sql." ORDER BY $order LIMIT $perPage OFFSET $offset",$params);
  $counts=[];foreach(self::TYPES as $type=>$name){$counts[$type]=(int)Database::scalar("SELECT COUNT(*) FROM $name");}
  $counts['unassigned']=(int)Database::scalar('SELECT COUNT(*) FROM polling_stations WHERE electoral_area_id IS NULL');
  $counts['missing_codes']=self::stationCodesReady()?(int)Database::scalar('SELECT COUNT(*) FROM polling_stations WHERE station_code IS NULL'):0;
  $editing=null;$history=[];
  if(!empty($input['edit'])){
   $editing=self::row($table,Validation::id($input,'edit'));
   $history=Database::all('SELECT a.action_code,a.occurred_at,u.name AS actor FROM audit_events a LEFT JOIN app_users u ON u.id=a.actor_user_id WHERE a.entity_type=? AND a.entity_id=? ORDER BY a.id DESC LIMIT 5',[$kind,$editing['id']]);
  }
  return compact('filters','kind','rows','total','page','last','counts','editing','history','perPage');
 }
 public static function ready(): bool {
  static $ready=null;
  return $ready??=(bool)Database::scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='polling_stations'");
 }
 public static function catalog(bool $includeStations=true): array {
  if(!self::ready()){return [];}
  $fields='id,name'.(self::enhanced()?',shortname':'');
  return ['regions'=>Database::all("SELECT $fields FROM regions ORDER BY name"),
   'constituencies'=>Database::all("SELECT $fields,region_id FROM constituencies ORDER BY name"),
   'electoral_areas'=>Database::all('SELECT id,name,constituency_id FROM electoral_areas ORDER BY name'),
   'polling_stations'=>$includeStations?Database::all('SELECT id,name,constituency_id,electoral_area_id'.(self::stationCodesReady()?',station_code':'').' FROM polling_stations ORDER BY name'):[]];
 }
 private static function row(string $table,int $id): array {
  return Database::one("SELECT * FROM $table WHERE id=?",[$id])??throw new \InvalidArgumentException('The selected location no longer exists.');
 }
 public static function selection(array $input): array {
  $ids=[];foreach(['region','constituency','electoral_area','polling_station'] as $key){$ids[$key.'_id']=empty($input[$key.'_id'])?null:Validation::id($input,$key.'_id');}
  $region=$ids['region_id']?self::row('regions',$ids['region_id']):null;
  $const=$ids['constituency_id']?self::row('constituencies',$ids['constituency_id']):null;
  $branch=$ids['electoral_area_id']?self::row('electoral_areas',$ids['electoral_area_id']):null;
  $station=$ids['polling_station_id']?self::row('polling_stations',$ids['polling_station_id']):null;
  if(!$station){throw new \InvalidArgumentException('Select a valid polling station. Every voter must be linked to a station.');}
  if(!$const){$const=self::row('constituencies',(int)$station['constituency_id']);$ids['constituency_id']=(int)$const['id'];}
  if(!$region){$region=self::row('regions',(int)$const['region_id']);$ids['region_id']=(int)$region['id'];}
  if($branch&&!$station){throw new \InvalidArgumentException('A voter’s branch is assigned through their polling station. Select a polling station; manage its branch in Locations.');}
  if($const&&(!$region||(int)$const['region_id']!==(int)$region['id'])){throw new \InvalidArgumentException('Choose a constituency in the selected region.');}
  foreach([$branch,$station] as $child){if($child&&(!$const||(int)$child['constituency_id']!==(int)$const['id'])){throw new \InvalidArgumentException('The branch and polling station must belong to the selected constituency.');}}
  if($station){
   if($branch&&(int)$station['electoral_area_id']!==(int)$branch['id']){throw new \InvalidArgumentException('Choose the station’s assigned branch, or leave the branch blank. Assign stations to branches in Locations.');}
   $branch=$station['electoral_area_id']?self::row('electoral_areas',(int)$station['electoral_area_id']):null;
  }
  $ids['electoral_area_id']=null; // Station owns the branch assignment; never freeze it on a voter.
  return $ids+['region'=>$region['name']??null,'constituency'=>$const['name']??null,'electoral_area'=>$branch['name']??null,'polling_station'=>$station['name']??null];
 }
 public static function save(array $input): void {
  VoterService::requireManage();
  if(!self::enhanced()){throw new \InvalidArgumentException('Import database/03_location_shortnames_mysql8.sql in phpMyAdmin first.');}
  $kind=Validation::choice($input,'kind',['region','constituency','electoral_area','polling_station','assignment']);
  Database::transaction(static function()use($input,$kind){
   if($kind==='assignment'){
    $station=Database::one('SELECT * FROM polling_stations WHERE id=? FOR UPDATE',[Validation::id($input,'station_id')])??throw new \InvalidArgumentException('Polling station not found.');
    self::checkVersion($input,$station);
    $branch=empty($input['branch_id'])?null:self::row('electoral_areas',Validation::id($input,'branch_id'));
    if($branch&&(int)$branch['constituency_id']!==(int)$station['constituency_id']){throw new \InvalidArgumentException('Choose a branch in the station’s constituency.');}
    Database::execute('UPDATE polling_stations SET electoral_area_id=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$branch['id']??null,$station['id']]);
    Auth::audit('station.branch_assigned','polling_station',(int)$station['id'],['before'=>$station['electoral_area_id'],'after'=>$branch['id']??null]);return;
   }
   $name=Validation::text($input,'name',match($kind){'region'=>160,'constituency'=>180,'electoral_area'=>200,default=>250});
   $hasShortname=self::usesShortname($kind);$shortname=null;
   if($hasShortname){
    $shortname=strtoupper(Validation::text($input,'shortname',24));
    if(!preg_match('/^[A-Z0-9][A-Z0-9._-]{0,23}$/D',$shortname)){throw new \InvalidArgumentException('Shortname must use 1–24 letters or numbers, with optional dots, underscores or hyphens.');}
   }
   $stationCode=null;
   if($kind==='polling_station'){
    if(!self::stationCodesReady()){throw new \InvalidArgumentException('Import database/04_polling_station_codes_mysql8.sql in phpMyAdmin first.');}
    if(!self::sharedStationNamesReady()){throw new \InvalidArgumentException('Import database/22_polling_station_shared_names_mysql8.sql in phpMyAdmin to allow stations with shared names while retaining unique Station IDs.');}
    $stationCode=Validation::text($input,'station_code',64);
    if(preg_match('/[\x00-\x1F\x7F]/',$stationCode)){throw new \InvalidArgumentException('Polling station ID cannot contain control characters.');}
   }
   $table=self::TYPES[$kind];$parentColumn=$kind==='constituency'?'region_id':'constituency_id';
   if(!empty($input['location_id'])){
    $id=Validation::id($input,'location_id');$before=Database::one("SELECT * FROM $table WHERE id=? FOR UPDATE",[$id])??throw new \InvalidArgumentException('Location not found.');
    self::checkVersion($input,$before);
    // Parent relationships stay stable when names or initials change.
    $params=[$name];$extraSql='';if($hasShortname){$extraSql.=',shortname=?';$params[]=$shortname;}if($kind==='polling_station'){$extraSql.=',station_code=?';$params[]=$stationCode;}$params[]=$id;
    Database::execute("UPDATE $table SET name=?$extraSql,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?",$params);
    $old=['name'=>$before['name']];$new=['name'=>$name];
    if($hasShortname){$old['shortname']=$before['shortname'];$new['shortname']=$shortname;}
    if($kind==='polling_station'){$old['station_code']=$before['station_code'];$new['station_code']=$stationCode;}
    Auth::audit('location.updated',$kind,$id,['before'=>$old,'after'=>$new]);return;
   }
   if($kind==='region'){Database::execute('INSERT INTO regions(name,shortname) VALUES(?,?)',[$name,$shortname]);}
   else{
    if(!$hasShortname&&!self::withoutShortnamesReady()){throw new \InvalidArgumentException('Import database/05_branch_station_without_shortnames_mysql8.sql in phpMyAdmin first.');}
    $parent=Validation::id($input,'parent_id');self::row($kind==='constituency'?'regions':'constituencies',$parent);
    if($kind==='polling_station'){self::insertPollingStation($parent,$stationCode,$name);return;}
    elseif($kind==='electoral_area'){Database::execute('INSERT INTO electoral_areas(constituency_id,name) VALUES(?,?)',[$parent,$name]);}
    else{Database::execute("INSERT INTO $table($parentColumn,name,shortname) VALUES(?,?,?)",[$parent,$name,$shortname]);}
   }
   $summary=['name'=>$name];if($hasShortname){$summary['shortname']=$shortname;}if($kind==='polling_station'){$summary['station_code']=$stationCode;}
   Auth::audit('location.created',$kind,(int)Database::connection()->lastInsertId(),$summary);
  });
 }
 // Shared by manual creation and CSV import; caller owns the transaction.
 public static function insertPollingStation(int $constituency,string $code,string $name,string $origin='manual'): int {
  VoterService::requireManage();
  if(!self::enhanced()||!self::stationCodesReady()||!self::withoutShortnamesReady()){throw new \InvalidArgumentException('Complete location migrations 02–05 in phpMyAdmin before adding polling stations.');}
  if(!self::sharedStationNamesReady()){throw new \InvalidArgumentException('Import database/22_polling_station_shared_names_mysql8.sql in phpMyAdmin before adding polling stations.');}
  if(!Database::connection()->inTransaction()){throw new \LogicException('Polling station creation requires a transaction.');}
  $code=Validation::text(['station_code'=>$code],'station_code',64);$name=Validation::text(['name'=>$name],'name',250);
  if(preg_match('/[\x00-\x1F\x7F]/',$code)){throw new \InvalidArgumentException('Polling station ID cannot contain control characters.');}
  if(!Database::one('SELECT id FROM constituencies WHERE id=? FOR UPDATE',[$constituency])){throw new \InvalidArgumentException('Choose an existing constituency.');}
  Database::execute('INSERT INTO polling_stations(constituency_id,name,station_code) VALUES(?,?,?)',[$constituency,$name,$code]);
  $id=(int)Database::connection()->lastInsertId();
  Auth::audit('location.created','polling_station',$id,['name'=>$name,'station_code'=>$code,'constituency_id'=>$constituency,'origin'=>$origin]);
  return $id;
 }
 private static function checkVersion(array $input,array $row): void {
  if(Validation::text($input,'version',30)!==$row['updated_at']){throw new \InvalidArgumentException('This location changed after you opened the page. Refresh it and review the latest details before saving.');}
 }
 // CSV names link to existing directory entries; typos never create new locations.
 public static function fromNames(array $row): array {
  $input=[];$parent=null;
  $code=Validation::text($row,'polling_station_code',64,false);$codedStation=null;
  if($code!==null){
   if(!self::stationCodesReady()){throw new \InvalidArgumentException('Import migration 04 before uploading official polling station IDs.');}
   $codedStation=Database::one('SELECT s.*,c.region_id FROM polling_stations s JOIN constituencies c ON c.id=s.constituency_id WHERE s.station_code=?',[$code]);
   if(!$codedStation){throw new \InvalidArgumentException('Unknown polling station ID "'.$code.'". Add it in Locations first.');}
   $input=['region_id'=>$codedStation['region_id'],'constituency_id'=>$codedStation['constituency_id'],'polling_station_id'=>$codedStation['id']];
  }
  foreach(['region'=>['regions',null],'constituency'=>['constituencies','region_id'],'electoral_area'=>['electoral_areas','constituency_id'],'polling_station'=>['polling_stations','constituency_id']] as $key=>[$table,$parentKey]){
   $name=$row[$key]??'';if($name===null||$name===''){continue;}
   $parent=$key==='constituency'?($input['region_id']??null):($input['constituency_id']??null);
   if($parentKey&&!$parent){throw new \InvalidArgumentException('CSV locations require region and constituency before a branch or polling station.');}
   if($key==='polling_station'){
    if($codedStation){
     $found=Database::one('SELECT id FROM polling_stations WHERE id=? AND name=? AND constituency_id=?',[$codedStation['id'],$name,$parent]);
     if(!$found){throw new \InvalidArgumentException('The supplied polling station name does not match Station ID "'.$code.'".');}
    }else{
     $matches=Database::all('SELECT id FROM polling_stations WHERE name=? AND constituency_id=? ORDER BY id LIMIT 2',[$name,$parent]);
     if(count($matches)>1){throw new \InvalidArgumentException('This polling station name matches more than one station in the constituency. Supply polling_station_code to identify the correct station.');}
     $found=$matches[0]??null;
    }
   }else{$found=Database::one("SELECT id FROM $table WHERE name=?".($parentKey?" AND $parentKey=?":''),$parentKey?[$name,$parent]:[$name]);}
   if(!$found){throw new \InvalidArgumentException('Unknown '.$key.' "'.$name.'". Add it in Locations first.');}
   if(isset($input[$key.'_id'])&&(int)$input[$key.'_id']!==(int)$found['id']){throw new \InvalidArgumentException('The supplied '.$key.' does not match polling station ID "'.$code.'".');}
   $input[$key.'_id']=$found['id'];
  }
  return self::selection($input);
 }
}
