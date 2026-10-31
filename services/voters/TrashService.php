<?php
declare(strict_types=1);
namespace App\Voters;
use App\{Auth,Database,HttpException,Validation,PhotoService,AddressService,LocationService,TownService,VoterService};

final class TrashService
{
 public static function ready(): bool {
  static $ready=null;
  return $ready??=(int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='voters' AND column_name IN ('deleted_at','deleted_by_user_id')")===2;
 }
 public static function active(string $alias='v'): string {return self::ready()?"$alias.deleted_at IS NULL":'1=1';}
 public static function check(array $person): void {
  if(!empty($person['deleted_at'])){throw new \InvalidArgumentException('This voter is in Deleted voters. An administrator must restore it before any updates or enrollment.');}
 }
 private static function requireReady(): void {
  if(!self::ready()){throw new \InvalidArgumentException('Import the complete database/13_deleted_voters_mysql8.sql file in phpMyAdmin first.');}
 }
 private static function row(int $id,bool $lock=false): array {
  return Database::one('SELECT * FROM voters WHERE id=?'.($lock?' FOR UPDATE':''),[$id])??throw new HttpException(404,'Voter not found.');
 }
 private static function noMember(int $id): void {
  if(Database::scalar('SELECT id FROM members WHERE voter_record_id=? FOR UPDATE',[$id])){throw new \InvalidArgumentException('Member records are protected. Remove membership through the member profile before deleting or combining this voter.');}
 }
 public static function listing(array $input): array {
  Auth::requireRole(['admin']);$available=self::ready();if(!$available){return compact('available');}
  $q=Validation::text($input,'q',100,false);$where='deleted_at IS NOT NULL';$params=[];
  if($q!==null){$where.=" AND (full_name LIKE ? ESCAPE '!' OR voter_id LIKE ? ESCAPE '!')";$term='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$params=[$term,$term];}
  $total=(int)Database::scalar('SELECT COUNT(*) FROM voters WHERE '.$where,$params);$last=max(1,(int)ceil($total/25));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*25;
  $rows=Database::all('SELECT id,voter_id,full_name,deleted_at FROM voters WHERE '.$where." ORDER BY deleted_at DESC,id DESC LIMIT 25 OFFSET $offset",$params);
  return compact('available','q','total','last','p','rows');
 }
 public static function review(int $id,bool $deleting=false): array {
  if($deleting){VoterService::requireManage();}else{Auth::requireRole(['admin']);}
  self::requireReady();$person=self::row($id);
  if($deleting&&!empty($person['deleted_at'])){throw new \InvalidArgumentException('This voter is already deleted.');}
  if(!$deleting&&empty($person['deleted_at'])){throw new \InvalidArgumentException('This voter is already active.');}
  if(Database::scalar('SELECT id FROM members WHERE voter_record_id=?',[$id])){throw new \InvalidArgumentException('Remove membership through the member profile first.');}
  $duplicates=$deleting?[]:Database::all('SELECT v.id,v.voter_id,v.full_name,v.updated_at,m.id AS membership_id FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id WHERE v.deleted_at IS NULL AND v.id<>? AND (v.voter_id=? OR v.full_name=?) ORDER BY v.id FOR UPDATE',[$id,$person['voter_id'],$person['full_name']]);
  // Bind review to both the deleted person and every candidate version.
  $fingerprint=hash('sha256',json_encode([$person['id'],$person['updated_at'],$person['deleted_at'],$duplicates],JSON_THROW_ON_ERROR));
  $token=bin2hex(random_bytes(32));$_SESSION['voter_trash_review'][$id]=compact('token','fingerprint');
  // Keep session review metadata bounded; never store contact data here.
  if(count($_SESSION['voter_trash_review'])>10){unset($_SESSION['voter_trash_review'][array_key_first($_SESSION['voter_trash_review'])]);}
  return compact('person','duplicates','deleting','token');
 }
 public static function act(array $input): string {
  self::requireReady();$mode=Validation::choice($input,'mode',['delete','restore','merge','replace','purge']);
  if($mode==='delete'){VoterService::requireManage();}else{Auth::requireRole(['admin']);}
  $id=Validation::id($input,'id');$token=Validation::text($input,'review_token',64);
  $review=$_SESSION['voter_trash_review'][$id]??[];
  if(!hash_equals((string)($review['token']??''),$token)){throw new \InvalidArgumentException('Review expired. Open the action again.');}
  $result=Database::transaction(function() use($input,$mode,$id,$review): string {
   // Lock in a stable order, including candidates, before any membership/photo writes.
   $lookup=self::row($id);
   Database::all('SELECT id FROM voters WHERE id=? OR (deleted_at IS NULL AND (voter_id=? OR full_name=?)) ORDER BY id FOR UPDATE',[$id,$lookup['voter_id'],$lookup['full_name']]);
   $person=self::row($id,true);self::noMember($id);
   $duplicates=$mode==='delete'?[]:Database::all('SELECT v.id,v.voter_id,v.full_name,v.updated_at,m.id AS membership_id FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id WHERE v.deleted_at IS NULL AND v.id<>? AND (v.voter_id=? OR v.full_name=?) ORDER BY v.id FOR UPDATE',[$id,$person['voter_id'],$person['full_name']]);
   $fingerprint=hash('sha256',json_encode([$person['id'],$person['updated_at'],$person['deleted_at'],$duplicates],JSON_THROW_ON_ERROR));
   if(!hash_equals($review['fingerprint'],$fingerprint)){throw new \InvalidArgumentException('Records changed since review. Open the review again before continuing.');}
   if($mode==='delete'){
    self::check($person);self::archive($id);Auth::audit('voter.deleted','voter',$id);return 'Voter moved to Deleted voters. Details and photo have been retained.';
   }
   if(empty($person['deleted_at'])){throw new \InvalidArgumentException('This voter has already been restored.');}
   if($mode==='purge'){
    if(Validation::text($input,'confirm_id',64)!==$person['voter_id']){throw new \InvalidArgumentException('Type the exact voter ID to confirm permanent deletion.');}
    self::erase($id);Auth::audit('voter.permanently_deleted','voter',$id);return 'Deleted voter permanently removed.';
   }
   if(in_array($mode,['merge','replace'],true)){
    $targetId=Validation::id($input,'target_id');$candidate=null;
    foreach($duplicates as $row){if((int)$row['id']===$targetId){$candidate=$row;break;}}
    if(!$candidate){throw new \InvalidArgumentException('Choose a matching active record from this review.');}
    self::noMember($targetId);$target=self::row($targetId,true);
    if($mode==='merge'){
     self::mergeMissing($person,$target);self::erase($id);
     Auth::audit('voter.merged','voter',$id,['surviving_voter_id'=>$targetId]);return 'Missing details merged into the existing voter. The existing record and its saved details were kept.';
    }
    self::archive($targetId);Auth::audit('voter.deleted','voter',$targetId,['replacement_voter_id'=>$id]);
   }elseif($duplicates&&!isset($input['different_people'])){throw new \InvalidArgumentException('Confirm these are different people, or select a merge/replacement action.');}
   Database::execute('UPDATE voters SET deleted_at=NULL,deleted_by_user_id=NULL,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$id]);
   Auth::audit('voter.restored','voter',$id);return 'Voter restored.';
  });
  unset($_SESSION['voter_trash_review'][$id]);return $result;
 }
 private static function archive(int $id): void {Database::execute('UPDATE voters SET deleted_at=CURRENT_TIMESTAMP(6),deleted_by_user_id=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[Auth::user()['id'],$id]);}
 private static function erase(int $id): void {
  if(PhotoService::ready()){Database::execute('DELETE FROM voter_photos WHERE voter_record_id=?',[$id]);}
  Database::execute('DELETE FROM voters WHERE id=?',[$id]);
 }
 private static function mergeMissing(array $source,array $target): void {
  if(\App\Membership\WingService::ready()){
   Database::execute('UPDATE member_wing_enrollments SET voter_record_id=?,updated_at=updated_at WHERE voter_record_id=?',[$target['id'],$source['id']]);
  }
  // Preserve person-linked service history when duplicate nonmembers are combined.
  if(\App\Executives\AppointmentService::baseReady()){
   Database::execute('UPDATE executive_appointments SET voter_record_id=?,updated_at=CURRENT_TIMESTAMP(6) WHERE voter_record_id=?',[$target['id'],$source['id']]);
  }
  $fields=['phone_encrypted','email_encrypted','address_encrypted','date_of_birth'];
  if(VoterService::namesReady()&&$source['full_name']===$target['full_name']){array_push($fields,'first_name','last_name');}
  $changes=[];
  foreach($fields as $field){if(empty($target[$field])&&!empty($source[$field])){$changes[$field]=$source[$field];}}
  if(($target['gender']??'unknown')==='unknown'&&($source['gender']??'unknown')!=='unknown'){$changes['gender']=$source['gender'];}
  if(LocationService::ready()&&empty($target['polling_station_id'])&&!empty($source['polling_station_id'])){
   foreach(['region_id','constituency_id','polling_station_id','region','constituency','polling_station'] as $field){$changes[$field]=$source[$field];}
   $changes['electoral_area_id']=null;$changes['electoral_area']=null;
  }
  if(AddressService::ready()&&empty($target['town'])&&empty($target['residential_region_id'])&&empty($target['residential_district_id'])){
   foreach(['town','residential_region_id','residential_district_id'] as $field){$changes[$field]=$source[$field];}
   if(TownService::ready()){$changes['residential_town_id']=$source['residential_town_id'];}
  }
  if($changes){$sql=implode(',',array_map(static fn($field)=>"$field=?",array_keys($changes)));Database::execute('UPDATE voters SET '.$sql.',updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[...array_values($changes),$target['id']]);}
  else{Database::execute('UPDATE voters SET updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$target['id']]);}
  if(PhotoService::ready()&&!Database::scalar('SELECT voter_record_id FROM voter_photos WHERE voter_record_id=?',[$target['id']])){Database::execute('UPDATE voter_photos SET voter_record_id=? WHERE voter_record_id=?',[$target['id'],$source['id']]);}
 }
}
