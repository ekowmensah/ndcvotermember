<?php
declare(strict_types=1);
namespace App;
final class VoterService
{
 public const HEADERS=['voter_id','first_name','last_name','phone','email','region','constituency','polling_station','polling_station_code','gender','date_of_birth','address','town','residential_district','residential_region'];
 public static function namesReady(): bool {
  static $ready=null;
  return $ready??=((int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='voters' AND column_name IN ('first_name','last_name')")===2);
 }
 public static function requireManage(): void {Auth::requireRole(['admin','registrar']);}
 public static function officialIdsReady(): bool {
  static $ready=null;
  return $ready??=((int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='members' AND column_name IN ('official_membership_id','updated_at')")===2);
 }
 private static function officialId(array $input): ?string {
  $value=Validation::text($input,'official_membership_id',64,false);
  if($value!==null){
   if(!self::officialIdsReady()){throw new \InvalidArgumentException('Import database/08_official_membership_ids_mysql8.sql in phpMyAdmin first.');}
   if(preg_match('/[\x00-\x1F\x7F]/',$value)){throw new \InvalidArgumentException('Official party membership ID cannot contain control characters.');}
  }
  return $value;
 }
 public static function values(?array $voter): array {
  $voter??=[];
  foreach(['phone','email','address'] as $field){$voter[$field]=Crypto::decrypt($voter[$field.'_encrypted']??null)??'';}
  return $voter;
 }
 private static function normalize(array $input): array {
  $gender=mb_strtolower(trim((string)($input['gender']??'unknown')));
  $gender=match($gender){'','u'=>'unknown','m'=>'male','f'=>'female',default=>$gender};
  $input['gender']=$gender;
  if(empty($input['full_name'])&&!isset($input['first_name'],$input['last_name'])&&isset($input['surname'],$input['given_names'])){
   $input['first_name']=trim(Validation::text($input,'given_names',120).' '.Validation::text($input,'other_names',120,false));$input['last_name']=$input['surname'];
  }
  $first=Validation::text($input,'first_name',120,false);$last=Validation::text($input,'last_name',120,false);
  if($first!==null||$last!==null){
   $first=Validation::text($input,'first_name',120);$last=Validation::text($input,'last_name',120);
   if(!self::namesReady()){throw new \InvalidArgumentException('Import database/07_voter_names_mysql8.sql in phpMyAdmin before saving first and last names.');}
   $input['full_name']=$first.' '.$last;
  }
  return [
   'voter_id'=>Validation::text($input,'voter_id',64),'full_name'=>Validation::text($input,'full_name',250),
   'first_name'=>$first,'last_name'=>$last,
   'gender'=>Validation::choice($input,'gender',['unknown','female','male','other']),
   'date_of_birth'=>Validation::date($input,'date_of_birth'),
   'phone'=>Validation::text($input,'phone',40,false),'email'=>Validation::email($input,'email',false),
   'address'=>Validation::text($input,'address',1000,false),'region'=>Validation::text($input,'region',160,false),
   'town'=>Validation::text($input,'town',180,false),'residential_district'=>Validation::text($input,'residential_district',180,false),'residential_region'=>Validation::text($input,'residential_region',160,false),
   'constituency'=>Validation::text($input,'constituency',180,false),'electoral_area'=>Validation::text($input,'electoral_area',200,false),
   'polling_station'=>Validation::text($input,'polling_station',250,false),
   'polling_station_code'=>Validation::text($input,'polling_station_code',64,false),
  ];
 }
 private static function write(array $row,?int $id=null): int {
  if($id){\App\Membership\WingService::guardGender($id,$row['gender']);}
  if(!LocationService::ready()||empty($row['polling_station_id'])){throw new \InvalidArgumentException('A valid directory polling station is required before saving this person.');}
  $params=[$row['voter_id'],$row['full_name'],$row['gender'],$row['date_of_birth'],Crypto::encrypt($row['phone']),Crypto::encrypt($row['email']),Crypto::encrypt($row['address']),$row['region'],$row['constituency'],$row['electoral_area'],$row['polling_station']];
  if($id){$params[]=$id;Database::execute('UPDATE voters SET voter_id=?,full_name=?,gender=?,date_of_birth=?,phone_encrypted=?,email_encrypted=?,address_encrypted=?,region=?,constituency=?,electoral_area=?,polling_station=? WHERE id=?',$params);}
  else{$params[]=Auth::user()['id'];Database::execute('INSERT INTO voters(voter_id,full_name,gender,date_of_birth,phone_encrypted,email_encrypted,address_encrypted,region,constituency,electoral_area,polling_station,created_by_user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',$params);$id=(int)Database::connection()->lastInsertId();}
  if(LocationService::ready()){
   Database::execute('UPDATE voters SET region_id=?,constituency_id=?,electoral_area_id=?,polling_station_id=? WHERE id=?',[$row['region_id']??null,$row['constituency_id']??null,null,$row['polling_station_id']??null,$id]);
  }
  if(self::namesReady()){Database::execute('UPDATE voters SET first_name=?,last_name=? WHERE id=?',[$row['first_name']??null,$row['last_name']??null,$id]);}
  if(AddressService::ready()){Database::execute('UPDATE voters SET town=?,residential_region_id=?,residential_district_id=? WHERE id=?',[$row['town']??null,$row['residential_region_id']??null,$row['residential_district_id']??null,$id]);}
  if(TownService::ready()){
   $town=TownService::resolve($row['town']??null,isset($row['residential_region_id'])?(int)$row['residential_region_id']:null,isset($row['residential_district_id'])?(int)$row['residential_district_id']:null);
   Database::execute('UPDATE voters SET residential_town_id=?,town=? WHERE id=?',[$town['id']??null,$town['name']??null,$id]);
  }
  Database::execute('UPDATE voters SET updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$id]);
  return $id;
 }
 private static function personData(array $input,?array $existing): array {
  Validation::text($input,'first_name',120);Validation::text($input,'last_name',120);
  $data=array_replace(self::normalize($input),AddressService::selection($input));
  if(!LocationService::ready()){throw new \InvalidArgumentException('Set up electoral locations in phpMyAdmin, then select a polling station before saving.');}
  if(LocationService::ready()){
   $location=LocationService::selection($input);
   $data=array_replace($data,$location);
  }
  return $data;
 }
 private static function checkPersonVersion(array $input,array $existing): void {
  if(Validation::text($input,'voter_version',30)!==$existing['updated_at']){
   throw new \InvalidArgumentException('This person changed after you opened the form. Refresh and review the latest details before saving.');
  }
 }
 public static function save(array $input,?array $photo=null): int {
  self::requireManage();$id=!empty($input['id'])?Validation::id($input,'id'):null;
  $remove=isset($input['remove_photo']);$photoBytes=PhotoService::prepare($photo);
  if($remove&&$photoBytes!==null){throw new \InvalidArgumentException('Choose either a replacement photo or Remove photo, then save.');}
  return Database::transaction(static function()use($input,$id,$photoBytes,$remove):int{
   $existing=null;
   if($id){
    $locked=Database::one('SELECT * FROM voters WHERE id=? FOR UPDATE',[$id])??throw new HttpException(404,'Voter not found.');
    \App\Voters\TrashService::check($locked);
    if(Database::one('SELECT id FROM members WHERE voter_record_id=? FOR UPDATE',[$id])){
     throw new \InvalidArgumentException('This person is a member. No voter changes were saved. Update all their details from the member profile.');
    }
    self::checkPersonVersion($input,$locked);$existing=Repository::voter($id);
   }
   $data=self::personData($input,$existing);
   $saved=self::write($data,$id);PhotoService::save($saved,$photoBytes,$remove);
   Auth::audit($id?'voter.updated':'voter.created','voter',$saved);return $saved;
  });
 }
 public static function markMember(int $voter,array $input): int {
  self::requireManage();
  return Database::transaction(static fn():int=>\App\Membership\EnrollmentWriter::create($voter,$input));
 }
 public static function removeMember(int $voter,int $membership): bool {
  self::requireManage();
  return Database::transaction(static function()use($voter,$membership):bool{
   // Use the same voter-first locking order as marking a voter as a member.
   $enrollPerson=Database::one('SELECT * FROM voters WHERE id=? FOR UPDATE',[$voter])??throw new HttpException(404,'Voter not found.');\App\Voters\TrashService::check($enrollPerson);
   $record=Database::one('SELECT * FROM members WHERE id=? FOR UPDATE',[$membership]);
   if(!$record){return false;} // Retrying an old form must not remove a later membership.
   if((int)$record['voter_record_id']!==$voter){throw new \InvalidArgumentException('This membership does not belong to the selected voter.');}
   \App\Executives\AppointmentService::guardMembershipRemoval($membership);
   \App\Security\StaffIdentity::guardRemoval($membership);
   Auth::audit('member.removed','member',$membership,[
    'voter_record_id'=>$voter,'member_number'=>$record['member_number'],
    'official_membership_id'=>$record['official_membership_id']??null,
    'member_profile'=>MemberProfileService::record($membership),
    'joined_on'=>$record['joined_on'],'status'=>$record['status'],
    'marked_by_user_id'=>$record['marked_by_user_id'],'created_at'=>$record['created_at'],
   ]);
   \App\Membership\WingService::closeForMembershipRemoval($membership);
   Database::execute('DELETE FROM members WHERE id=? AND voter_record_id=?',[$membership,$voter]);
   Database::execute('UPDATE voters SET updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$voter]);
   return true;
  });
 }
 public static function updateMember(array $input,?array $photo=null): void {
  self::requireManage();$id=Validation::id($input,'membership_id');
  $record=Database::one('SELECT voter_record_id FROM members WHERE id=?',[$id])??throw new \InvalidArgumentException('This membership has been removed. No changes were saved.');
  $voterId=(int)$record['voter_record_id'];$withPerson=isset($input['member_person_present']);
  if($withPerson&&Validation::id($input,'voter_record_id')!==$voterId){throw new \InvalidArgumentException('This membership does not belong to the selected person.');}
  $status=Validation::choice($input,'status',['active','inactive']);$joined=Validation::date($input,'joined_on',true);
  $official=self::officialId($input);$profile=MemberProfileService::normalize($input);
  $remove=$withPerson&&isset($input['remove_photo']);$photoBytes=$withPerson?PhotoService::prepare($photo):null;
  if($remove&&$photoBytes!==null){throw new \InvalidArgumentException('Choose either a replacement photo or Remove photo, then save.');}
  Database::transaction(static function()use($id,$voterId,$withPerson,$photoBytes,$remove,$status,$joined,$official,$input,$profile){
   // Every person/membership operation locks the voter first, then the exact membership.
   $person=Database::one('SELECT * FROM voters WHERE id=? FOR UPDATE',[$voterId])??throw new \InvalidArgumentException('This voter record no longer exists.');
   $before=Database::one('SELECT * FROM members WHERE id=? AND voter_record_id=? FOR UPDATE',[$id,$voterId])??throw new \InvalidArgumentException('This membership has been removed. No changes were saved.');
   if(self::officialIdsReady()&&Validation::text($input,'membership_version',30)!==$before['updated_at']){
    throw new \InvalidArgumentException('This membership changed after you opened it. Refresh and review the latest details before saving.');
   }
   if($withPerson){
    self::checkPersonVersion($input,$person);
    self::write(self::personData($input,Repository::voter($voterId)),$voterId);
    PhotoService::save($voterId,$photoBytes,$remove);
    Auth::audit('voter.updated','voter',$voterId,['via'=>'member','membership_id'=>$id]);
   }
   if(\App\Membership\WingService::ready()){
    $firstWing=Database::scalar('SELECT MIN(joined_on) FROM member_wing_enrollments WHERE member_id=?',[$id]);
    if($firstWing!==null&&$joined>$firstWing){throw new \InvalidArgumentException('Party membership cannot start after an existing wing enrollment.');}
   }
   $after=['status'=>$status,'joined_on'=>$joined];$old=['status'=>$before['status'],'joined_on'=>$before['joined_on']];
   if(self::officialIdsReady()){
    $newOfficial=array_key_exists('official_membership_id',$input)?$official:$before['official_membership_id'];
    Database::execute('UPDATE members SET status=?,joined_on=?,official_membership_id=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$status,$joined,$newOfficial,$id]);
    $old['official_membership_id']=$before['official_membership_id'];$after['official_membership_id']=$newOfficial;
   }else{Database::execute('UPDATE members SET status=?,joined_on=? WHERE id=?',[$status,$joined,$id]);}
   MemberProfileService::save($id,$profile);
   Auth::audit('member.updated','member',$id,['before'=>$old,'after'=>$after,'person_updated'=>$withPerson]);
  });
 }
 public static function reviewImportRow(array $raw,bool $update,bool $lock=false): array {
  $identifier=Validation::text($raw,'voter_id',64);
  $existing=Database::one('SELECT * FROM voters WHERE voter_id=?'.($lock?' FOR UPDATE':''),[$identifier]);
  if($existing){\App\Voters\TrashService::check($existing);}
  $base=['identifier'=>$identifier,'name'=>trim((string)($raw['first_name']??$raw['given_names']??'').' '.(string)($raw['last_name']??$raw['surname']??''))?:($raw['full_name']??''),
   'existing_name'=>$existing['full_name']??null,'target_id'=>$existing?(int)$existing['id']:null,'version'=>$existing['updated_at']??null,'fingerprint'=>null];
  if($existing&&Database::scalar('SELECT id FROM members WHERE voter_record_id=?'.($lock?' FOR UPDATE':''),[$existing['id']])){
   return $base+['status'=>'member','message'=>'Member protected: update through the member profile.'];
  }
  if($existing&&!$update){return $base+['status'=>'skip','message'=>'Existing voter: updates were not enabled.'];}
  if(!LocationService::ready()){throw new \InvalidArgumentException('Set up electoral locations before importing voters.');}
  if(Validation::text($raw,'polling_station_code',64,false)===null&&Validation::text($raw,'polling_station',250,false)===null){throw new \InvalidArgumentException('Polling station is required: supply polling_station_code or the exact station name with its constituency and region.');}
  $data=self::normalize($raw);
  if($data['polling_station_code']!==null&&!LocationService::stationCodesReady()){throw new \InvalidArgumentException('Enable official polling station IDs with migration 04 first.');}
  if($existing){
   $previous=self::values(Repository::voter((int)$existing['id']));
   foreach($data as $key=>$value){
    if(!in_array($key,['voter_id','full_name','first_name','last_name','polling_station_code','electoral_area'],true)&&(!array_key_exists($key,$raw)||trim((string)$raw[$key])==='')){$data[$key]=$previous[$key]??null;}
   }
   if($data['first_name']===null&&$data['last_name']===null&&$data['full_name']===$existing['full_name']){$data['first_name']=$existing['first_name']??null;$data['last_name']=$existing['last_name']??null;}
  }
  if($data['polling_station_code']!==null){foreach(['region','constituency','electoral_area','polling_station'] as $key){if(trim((string)($raw[$key]??''))===''){$data[$key]=null;}}}
  if(LocationService::ready()){
   $provided=false;foreach(['region','constituency','electoral_area','polling_station','polling_station_code'] as $key){if(trim((string)($raw[$key]??''))!==''){$provided=true;}}
   if($existing&&!$provided){foreach(['region_id','constituency_id','electoral_area_id','polling_station_id'] as $key){$data[$key]=$existing[$key];}}
   else{$data=array_replace($data,LocationService::fromNames($data));}
  }
  $data=array_replace($data,AddressService::selection($data));
  $changed=[];
  if($existing){foreach($data as $key=>$value){if(in_array($key,['polling_station_code','electoral_area','electoral_area_id'],true)){continue;}if((string)$value!==(string)($previous[$key]??'')){$changed[]=label($key);}}}
  $base['name']=$data['full_name'];$base['fingerprint']=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
  return $base+['status'=>$existing?'update':'new','message'=>$existing?($changed?'Changes: '.implode(', ',$changed).'. Blank optional cells are preserved.':'No field changes detected; existing values will be retained.'):'New voter. No membership will be created.','data'=>$data];
 }
 public static function applyReviewedImport(array $rows,array $sourceRows,bool $update,string $filename): array {
  self::requireManage();
  if(!Database::connection()->inTransaction()){throw new \LogicException('A reviewed import requires a transaction.');}
  $counts=['added'=>0,'updated'=>0,'skipped'=>0,'members_skipped'=>0];
  foreach($rows as $index=>$row){
   if(in_array($row['status'],['new','update'],true)){
    $review=self::reviewImportRow($sourceRows[$index]['raw'],$update,true);
    if($review['status']!==$row['status']||$review['version']!==$row['version']||$review['target_id']!==$row['target_id']||$review['fingerprint']!==$row['fingerprint']){throw new \InvalidArgumentException('A record changed during confirmation. No rows were imported; refresh the preview.');}
    self::write($review['data'],$review['target_id']);$counts[$row['status']==='new'?'added':'updated']++;
   }else{$counts['skipped']++;if($row['status']==='member'){$counts['members_skipped']++;}}
  }
  Database::execute('INSERT INTO voter_imports(filename,added_count,updated_count,skipped_count,imported_by_user_id) VALUES(?,?,?,?,?)',[$filename,$counts['added'],$counts['updated'],$counts['skipped'],Auth::user()['id']]);
  Auth::audit('voters.uploaded','voter_import',(int)Database::connection()->lastInsertId(),$counts+['reviewed'=>true]);return $counts;
 }
}
