<?php
declare(strict_types=1);
namespace App;

final class CsvPreviewService
{
 public const LABELS=['new'=>'New','update'=>'Update voter','skip'=>'Existing / skip','member'=>'Protected member','duplicate'=>'Duplicate in file','conflict'=>'Database conflict','invalid'=>'Invalid row'];
 public const ISSUES=['duplicate','conflict','invalid'];
 public static function stage(string $kind,array $file,array $options): string {
  VoterService::requireManage();self::cleanup();
  $station=$kind==='stations';$limit=$station?5:10;$maxRows=$station?10000:20000;
  if($station&&!PollingStationImportService::ready()){throw new \InvalidArgumentException('Complete location migrations 02–05 and import database/22_polling_station_shared_names_mysql8.sql in phpMyAdmin before uploading stations.');}
  if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])){throw new \InvalidArgumentException('Choose a CSV file that completed uploading.');}
  $size=filesize($file['tmp_name']);
  if($size===false||$size<1||$size>$limit*1024*1024||strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION))!=='csv'){throw new \InvalidArgumentException("Choose a nonempty .csv file up to $limit MB.");}
  $handle=fopen($file['tmp_name'],'rb');if(!$handle){throw new \InvalidArgumentException('The CSV could not be read.');}
  $rows=[];$number=1;
  try{
   $headers=fgetcsv($handle,0,',','"','');
   if(!$headers){throw new \InvalidArgumentException('The CSV is empty.');}
   foreach($headers as $header){if(!mb_check_encoding((string)$header,'UTF-8')){throw new \InvalidArgumentException('Save the CSV as UTF-8.');}}
   $headers[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$headers[0]);
   $headers=array_map(static fn($value)=>str_replace(' ','_',mb_strtolower(trim((string)$value))),$headers);
   if(count(array_unique($headers))!==count($headers)||in_array('',$headers,true)){throw new \InvalidArgumentException('CSV headers must be unique and nonempty.');}
   if($station){
    if(count($headers)!==2||array_diff(PollingStationImportService::HEADERS,$headers)){throw new \InvalidArgumentException('Station CSV headers must be station_code,name.');}
   }elseif(!in_array('voter_id',$headers,true)||(!in_array('full_name',$headers,true)&&array_diff(['first_name','last_name'],$headers)&&array_diff(['surname','given_names'],$headers))){
    throw new \InvalidArgumentException('Include voter_id and first_name/last_name (or the supported legacy full-name columns).');
   }
   while(($cells=fgetcsv($handle,0,',','"',''))!==false){
    $number++;if(count($cells)===1&&trim((string)$cells[0])===''){continue;}
    if(count($rows)>=$maxRows){throw new \InvalidArgumentException("Upload at most $maxRows records at a time.");}
    $error=null;
    if(count($cells)!==count($headers)){$error='Column count differs from the header.';}
    foreach($cells as $cell){if(!mb_check_encoding((string)$cell,'UTF-8')){$error='Text must use UTF-8 encoding.';break;}}
    $rows[]=['row'=>$number,'raw'=>$error?[]:array_combine($headers,$cells),'error'=>$error];
   }
  }finally{fclose($handle);}
  if(!$rows){throw new \InvalidArgumentException('The CSV contains headers but no records.');}
  set_time_limit(180);
  $draft=['kind'=>$kind,'options'=>$options,'filename'=>mb_substr(basename((string)$file['name']),0,255),'rows'=>$rows];
  $draft['plan']=self::analyze($draft);
  $draft['revision']=bin2hex(random_bytes(16));
  while(count($_SESSION['csv_previews']??[])>=3){self::discard((string)array_key_first($_SESSION['csv_previews']));}
  $token=bin2hex(random_bytes(32));$path=sys_get_temp_dir().DIRECTORY_SEPARATOR.self::tempPrefix().$token.'.tmp';
  $temporary=fopen($path,'xb');
  if($temporary===false){throw new \RuntimeException('Preview storage is unavailable.');}
  fclose($temporary);
  $_SESSION['csv_previews'][$token]=['path'=>$path,'owner'=>(int)Auth::user()['id'],'expires'=>time()+1800];
  try{self::save($token,$draft);}catch(\Throwable $error){self::discard($token);throw $error;}
  return $token;
 }
 private static function analyze(array $draft,bool $lock=false,bool $includeData=false): array {
  $station=$draft['kind']==='stations';$parent=(int)($draft['options']['parent_id']??0);
  if($station){
   if(!PollingStationImportService::ready()){throw new \InvalidArgumentException('Complete location migrations 02–05 and import database/22_polling_station_shared_names_mysql8.sql in phpMyAdmin, then refresh this preview.');}
   if(!Database::scalar('SELECT id FROM constituencies WHERE id=?'.($lock?' FOR UPDATE':''),[$parent])){throw new \InvalidArgumentException('The selected constituency no longer exists.');}
  }
  $plan=[];$groups=[];
  foreach($draft['rows'] as $index=>$item){
   $raw=$item['raw'];$identifier=trim((string)($raw[$station?'station_code':'voter_id']??''));
   $entry=['row'=>$item['row'],'identifier'=>$identifier,'name'=>$station?($raw['name']??''):(trim((string)($raw['first_name']??$raw['given_names']??'').' '.(string)($raw['last_name']??$raw['surname']??''))?:($raw['full_name']??'')),
    'existing_name'=>null,'target_id'=>null,'version'=>null,'fingerprint'=>null];
   try{
    if($item['error']){throw new \InvalidArgumentException($item['error']);}
    $entry=array_replace($entry,$station?PollingStationImportService::reviewImportRow($raw,$parent,$lock):VoterService::reviewImportRow($raw,(bool)($draft['options']['update']??false),$lock));
   }catch(\InvalidArgumentException $error){$entry['status']='invalid';$entry['message']=$error->getMessage();}
   if(!$includeData){unset($entry['data']);}
   $plan[]=$entry;
   if($identifier!==''&&mb_strlen($identifier)<=64){
    if($station){
     // Duplicate identity is the official Station ID, never the station name.
     // Match the database collation without coercing IDs to numbers.
     $keys=Database::one("SELECT HEX(WEIGHT_STRING(CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)) AS code_key",[$identifier]);
     $groups['code:'.$keys['code_key']][]=$index;
    }else{$groups['code:'.$identifier][]=$index;}
   }
  }
  foreach($groups as $indexes){
   if(count($indexes)<2){continue;}
   $records=implode(', ',array_map(static fn($index)=>$plan[$index]['row'],$indexes));
   foreach($indexes as $index){$plan[$index]['status']='duplicate';$plan[$index]['message']=($station?'Repeated official Station ID':'Repeated voter ID').' in CSV records '.$records.'. All occurrences of this ID are excluded together.';unset($plan[$index]['data']);}
  }
  return $plan;
 }
 private static function signature(array $plan): string {
  foreach($plan as &$row){unset($row['data']);}unset($row);
  return hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR));
 }
 private static function save(string $token,array $draft): void {
  $path=$_SESSION['csv_previews'][$token]['path'];
  $bytes=Crypto::encrypt(json_encode($draft,JSON_THROW_ON_ERROR));
  if(file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes)){throw new \RuntimeException('The preview could not be stored.');}
  @chmod($path,0600);
 }
 public static function load(string $token): array {
  VoterService::requireManage();self::cleanup();
  $meta=$_SESSION['csv_previews'][$token]??null;
  if(!$meta||(int)$meta['owner']!==(int)Auth::user()['id']||!is_file($meta['path'])){self::discard($token);throw new \InvalidArgumentException('This preview expired or was already completed. Upload the CSV again.');}
  $contents=file_get_contents($meta['path']);
  if($contents===false){self::discard($token);throw new \InvalidArgumentException('This preview is unavailable. Upload the CSV again.');}
  try{$draft=json_decode(Crypto::decrypt($contents),true,512,JSON_THROW_ON_ERROR);}
  catch(\RuntimeException|\JsonException $error){self::discard($token);throw new \InvalidArgumentException('This preview could not be restored. Upload the CSV again.');}
  return $draft;
 }
 public static function refresh(string $token): void {
  $draft=self::load($token);set_time_limit(180);$draft['plan']=self::analyze($draft);$draft['revision']=bin2hex(random_bytes(16));self::save($token,$draft);
 }
 public static function confirm(string $token,string $revision,bool $excludeIssues): array {
  $draft=self::load($token);
  if(!hash_equals($draft['revision'],$revision)){throw new \InvalidArgumentException('This preview changed. Review the latest version before confirming.');}
  $issues=count(array_filter($draft['plan'],static fn($row)=>in_array($row['status'],self::ISSUES,true)));
  if($issues&&!$excludeIssues){throw new \InvalidArgumentException('Correct the file, or explicitly choose to exclude all problem rows.');}
  if($issues===count($draft['plan'])){throw new \InvalidArgumentException('There are no eligible rows. Correct the file and upload it again.');}
  set_time_limit(180);
  $counts=Database::transaction(static function()use($draft):array{
   $current=self::analyze($draft,true);
   if(!hash_equals(self::signature($draft['plan']),self::signature($current))){throw new \InvalidArgumentException('Records or locations changed after this preview. Refresh the preview and review it again. No rows were imported.');}
   return $draft['kind']==='stations'
    ?PollingStationImportService::applyReviewedImport($current,$draft['rows'],(int)$draft['options']['parent_id'],$draft['filename'])
    :VoterService::applyReviewedImport($current,$draft['rows'],(bool)($draft['options']['update']??false),$draft['filename']);
  });
  self::discard($token);
  return ['kind'=>$draft['kind'],'parent_id'=>$draft['options']['parent_id']??null,'counts'=>$counts];
 }
 public static function discard(string $token): void {
  $meta=$_SESSION['csv_previews'][$token]??null;
  if($meta&&is_file($meta['path'])){@unlink($meta['path']);}
  unset($_SESSION['csv_previews'][$token]);
 }
 public static function clear(): void {foreach(array_keys($_SESSION['csv_previews']??[]) as $token){self::discard($token);}}
 private static function tempPrefix(): string {return 'ndc_csv_'.substr(hash('sha256',Config::baseUrl().Config::key()),0,12).'_';}
 private static function cleanup(): void {
  foreach($_SESSION['csv_previews']??[] as $token=>$meta){if($meta['expires']<time()){self::discard((string)$token);}}
  // Reclaim encrypted drafts left by abandoned/garbage-collected sessions, for this app only.
  foreach(glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.self::tempPrefix().'*')?:[] as $path){
   if(is_file($path)&&filemtime($path)<time()-3600){@unlink($path);}
  }
 }
 public static function pending(string $kind): array {
  VoterService::requireManage();self::cleanup();$pending=[];
  foreach(array_keys($_SESSION['csv_previews']??[]) as $token){
   try{$draft=self::load($token);}catch(\InvalidArgumentException $error){continue;}
   if($draft['kind']!==$kind){continue;}
   $pending[]=['token'=>$token,'filename'=>$draft['filename'],'rows'=>count($draft['rows']),'expires'=>$_SESSION['csv_previews'][$token]['expires']];
  }
  return $pending;
 }
 public static function page(array $input): array {
  $token=Validation::text($input,'token',64);$draft=self::load($token);$counts=array_fill_keys(array_keys(self::LABELS),0);
  foreach($draft['plan'] as $row){$counts[$row['status']]++;}
  $filter=Validation::choice(['status'=>$input['status']??'all'],'status',array_merge(['all','issues'],array_keys(self::LABELS)));
  $rows=array_values(array_filter($draft['plan'],static fn($row)=>$filter==='all'||($filter==='issues'?in_array($row['status'],self::ISSUES,true):$row['status']===$filter)));
  $total=count($rows);$last=max(1,(int)ceil($total/50));$page=min($last,max(1,(int)($input['p']??1)));$rows=array_slice($rows,($page-1)*50,50);
  $issues=$counts['duplicate']+$counts['conflict']+$counts['invalid'];
  $context=$draft['kind']==='stations'?Database::scalar('SELECT name FROM constituencies WHERE id=?',[$draft['options']['parent_id']]):null;
  $expires=$_SESSION['csv_previews'][$token]['expires'];
  return compact('token','draft','counts','filter','rows','total','last','page','issues','context','expires');
 }
}
