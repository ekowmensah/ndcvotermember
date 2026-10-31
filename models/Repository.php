<?php
declare(strict_types=1);
namespace App;
final class Repository
{
 private static function membershipSql(): string {
  return (VoterService::officialIdsReady()?',m.official_membership_id,m.updated_at AS membership_version':',NULL AS official_membership_id,NULL AS membership_version').MemberProfileService::sqlColumns();
 }
 public static function locationSql(): array {
  $photo=PhotoService::ready()?',EXISTS(SELECT 1 FROM voter_photos vp WHERE vp.voter_record_id=v.id) AS has_photo':',0 AS has_photo';
  $addressJoin=AddressService::ready()?' LEFT JOIN residential_regions rr ON rr.id=v.residential_region_id LEFT JOIN districts rd ON rd.id=v.residential_district_id':'';
  $addressColumns=AddressService::ready()?',rr.name AS residential_region,rd.name AS residential_district':',NULL AS residential_region,NULL AS residential_district';
  if(TownService::ready()){$addressJoin.=' LEFT JOIN residential_towns rt ON rt.id=v.residential_town_id';$addressColumns.=',COALESCE(rt.name,v.town) AS town';}
  if(!LocationService::ready()){return [$addressJoin, ',NULL AS electoral_area'.$photo.$addressColumns];}
  return [' LEFT JOIN regions lr ON lr.id=v.region_id LEFT JOIN constituencies lc ON lc.id=v.constituency_id LEFT JOIN polling_stations ls ON ls.id=v.polling_station_id LEFT JOIN electoral_areas lsb ON lsb.id=ls.electoral_area_id'.$addressJoin,
   ',COALESCE(lr.name,v.region) AS region,COALESCE(lc.name,v.constituency) AS constituency,lsb.name AS electoral_area,COALESCE(ls.name,v.polling_station) AS polling_station,'.(LocationService::stationCodesReady()?'ls.station_code':'NULL').' AS polling_station_code'.$photo.$addressColumns];
 }
 public static function voters(bool $membersOnly=false,?array $input=null): array {
  $input??=$_GET;
  $where=($membersOnly?'m.id IS NOT NULL AND ':' ').\App\Voters\TrashService::active();$params=[];
  $filters=[];
  if($membersOnly&&Validation::text($input,'wing',20,false)!==null){
   if(!\App\Membership\WingService::ready()){throw new \InvalidArgumentException('Import database/20_membership_wings_mysql8.sql in phpMyAdmin before filtering by wing.');}
   $filters['wing']=Validation::choice($input,'wing',array_merge(array_keys(\App\Membership\WingService::WINGS),['none']));
   if($filters['wing']==='none'){$where.=' AND NOT EXISTS(SELECT 1 FROM member_wing_enrollments mw WHERE mw.member_id=m.id AND mw.ended_on IS NULL)';}
   else{$where.=' AND EXISTS(SELECT 1 FROM member_wing_enrollments mw WHERE mw.member_id=m.id AND mw.ended_on IS NULL AND mw.wing_code=?)';$params[]=$filters['wing'];}
  }
  if(\App\People\LifeStatusService::ready()&&Validation::text($input,'life_status',20,false)!==null){
   $filters['life_status']=Validation::choice($input,'life_status',['not_deceased','deceased']);
   $where.=$filters['life_status']==='deceased'?' AND v.is_deceased=1':' AND v.is_deceased=0';
  }
  $q=Validation::text($input,'q',100,false);
  if($q!==null){$filters['q']=$q;foreach(preg_split('/\s+/u',$q,-1,PREG_SPLIT_NO_EMPTY) as $word){
   $term='%'.strtr($word,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$search="v.voter_id LIKE ? ESCAPE '!' OR v.full_name LIKE ? ESCAPE '!' OR m.member_number LIKE ? ESCAPE '!'";array_push($params,$term,$term,$term);
   if(VoterService::officialIdsReady()){$search.=" OR m.official_membership_id LIKE ? ESCAPE '!'";$params[]=$term;}
   if($membersOnly&&MemberProfileService::ready()){foreach(['profession','job_title','employer','skills','languages','volunteer_interests'] as $field){$search.=" OR mp.$field LIKE ? ESCAPE '!'";$params[]=$term;}}
   $where.=' AND ('.$search.')';
  }}
  $filter=(string)($input['membership']??'');
  if(!$membersOnly&&in_array($filter,['member','not_member'],true)){$filters['membership']=$filter;$where.=$filter==='member'?' AND m.id IS NOT NULL':' AND m.id IS NULL';}
  if($membersOnly&&in_array($input['status']??'',['active','inactive'],true)){$filters['status']=$input['status'];$where.=' AND m.status=?';$params[]=$filters['status'];}
  if(LocationService::ready()){
   foreach(['electoral_region_filter'=>'v.region_id','constituency_filter'=>'v.constituency_id','branch_filter'=>'ls.electoral_area_id','polling_station_filter'=>'v.polling_station_id'] as $key=>$column){if(!empty($input[$key])){$filters[$key]=Validation::id($input,$key);$where.=" AND $column=?";$params[]=$filters[$key];}}
  }
  if(in_array($input['photo']??'',['with','without'],true)){
   $filters['photo']=$input['photo'];
   if(PhotoService::ready()){$where.=$filters['photo']==='with'?' AND EXISTS':' AND NOT EXISTS';$where.='(SELECT 1 FROM voter_photos pf WHERE pf.voter_record_id=v.id)';}
   elseif($filters['photo']==='with'){$where.=' AND 1=0';}
  }
  if($membersOnly&&VoterService::officialIdsReady()&&in_array($input['official_id']??'',['recorded','missing'],true)){$filters['official_id']=$input['official_id'];$where.=$filters['official_id']==='recorded'?' AND m.official_membership_id IS NOT NULL':' AND m.official_membership_id IS NULL';}
  $sorts=['name','name_desc','newest'];
  if(VoterService::namesReady()){array_push($sorts,'last_name','last_name_desc','first_name','first_name_desc');}
  $gender=Validation::text($input,'gender',16,false);if($gender!==null){$filters['gender']=Validation::choice($input,'gender',['female','male','other','unknown']);$where.=' AND v.gender=?';$params[]=$gender;}
  $createdMonth=Validation::text($input,'created_month',7,false);
  if($createdMonth!==null){
   if(!preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D',$createdMonth)){throw new \InvalidArgumentException('Choose a valid record creation month.');}
   $start=new \DateTimeImmutable($createdMonth.'-01 00:00:00',new \DateTimeZone('UTC'));
   $filters['created_month']=$createdMonth;$column=$membersOnly?'m.created_at':'v.created_at';
   $where.=" AND $column>=? AND $column<?";array_push($params,$start->format('Y-m-d H:i:s'),$start->modify('+1 month')->format('Y-m-d H:i:s'));
  }
  $missing=Validation::text($input,'missing',32,false);
  if($missing!==null){
   $conditions=['phone'=>"(v.phone_encrypted IS NULL OR v.phone_encrypted='')",'dob'=>'v.date_of_birth IS NULL'];
   if(AddressService::ready()){$conditions['district']='v.residential_district_id IS NULL';}
   if(TownService::ready()){$conditions['town']='v.residential_town_id IS NULL';}
   if(LocationService::ready()){$conditions['station']='v.polling_station_id IS NULL';$conditions['branch']='ls.electoral_area_id IS NULL';}
   $filters['missing']=Validation::choice($input,'missing',array_keys($conditions));$where.=' AND '.$conditions[$missing];
  }
  if($membersOnly&&MemberProfileService::ready()){
   $profileState=Validation::text($input,'profile_status',16,false);if($profileState!==null){$filters['profile_status']=Validation::choice($input,'profile_status',['with','without']);$where.=$profileState==='with'?' AND mp.member_id IS NOT NULL':' AND mp.member_id IS NULL';}
   foreach(['profession'=>'profession','skills'=>'skills','language'=>'languages','employer'=>'employer','volunteer_interest'=>'volunteer_interests'] as $key=>$column){$value=Validation::text($input,$key,150,false);if($value!==null){$filters[$key]=$value;$where.=" AND mp.$column LIKE ? ESCAPE '!'";$params[]='%'.strtr($value,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}}
   foreach(MemberProfileService::choices() as $key=>$options){
    if(Validation::text($input,$key,32,false)===null){continue;}
    $allowed=array_merge(array_keys($options),['missing']);if($key==='education_level'){$allowed[]='degree';}
    $value=Validation::choice($input,$key,$allowed);$filters[$key]=$value;
    if($value==='missing'){$where.=" AND mp.$key IS NULL";}
    elseif($key==='education_level'&&$value==='degree'){$where.=" AND mp.education_level IN ('bachelors','masters','doctorate')";}
    else{$where.=" AND mp.$key=?";$params[]=$value;}
   }
   array_push($sorts,'profession','education');
  }
  if(AddressService::ready()){
   if(TownService::ready()&&!empty($input['residential_town_filter'])){$filters['residential_town_filter']=Validation::id($input,'residential_town_filter');$where.=' AND v.residential_town_id=?';$params[]=$filters['residential_town_filter'];}
   array_push($sorts,'town','district','residential_region');
   foreach(['residential_region_filter'=>'v.residential_region_id','residential_district_filter'=>'v.residential_district_id'] as $key=>$column){if(!empty($input[$key])){$filters[$key]=Validation::id($input,$key);$where.=" AND $column=?";$params[]=$filters[$key];}}
   $town=Validation::text($input,'town',180,false);if($town!==null){$filters['town']=$town;$where.=" AND v.town LIKE ? ESCAPE '!'";$params[]='%'.strtr($town,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}
  }
  $filters['sort']=Validation::choice(['sort'=>$input['sort']??'name'],'sort',$sorts);
  $order=match($filters['sort']){'last_name'=>'v.last_name IS NULL,v.last_name,v.first_name,v.id','last_name_desc'=>'v.last_name IS NULL,v.last_name DESC,v.first_name DESC,v.id DESC','first_name'=>'v.first_name IS NULL,v.first_name,v.last_name,v.id','first_name_desc'=>'v.first_name IS NULL,v.first_name DESC,v.last_name DESC,v.id DESC','name_desc'=>'v.full_name DESC,v.id DESC','newest'=>$membersOnly?'m.id DESC':'v.id DESC','profession'=>'mp.profession,v.full_name,v.id','education'=>'mp.education_level,v.full_name,v.id','town'=>'v.town,v.full_name,v.id','district'=>'rd.name,v.town,v.full_name,v.id','residential_region'=>'rr.name,rd.name,v.town,v.full_name,v.id',default=>'v.full_name,v.id'};
  $perPage=(int)Validation::choice(['per_page'=>$input['per_page']??'25'],'per_page',['25','50','100']);$filters['per_page']=$perPage;
  [$joins,$labels]=self::locationSql();
  $labels.=self::membershipSql();
  $from='voters v LEFT JOIN members m ON m.voter_record_id=v.id'.$joins.MemberProfileService::sqlJoin();
  $photoMetric=PhotoService::ready()?'COALESCE(SUM(EXISTS(SELECT 1 FROM voter_photos px WHERE px.voter_record_id=v.id)),0)':'0';
  $officialMetric=VoterService::officialIdsReady()?'COALESCE(SUM(m.id IS NOT NULL AND m.official_membership_id IS NULL),0)':'0';
  $summary=array_map('intval',Database::one("SELECT COUNT(*) AS total,COUNT(m.id) AS members,COALESCE(SUM(m.id IS NULL),0) AS nonmembers,COALESCE(SUM(m.status='active'),0) AS active,COALESCE(SUM(m.status='inactive'),0) AS inactive,$photoMetric AS with_photo,$officialMetric AS missing_official FROM $from WHERE $where",$params)??[]);
  $total=$summary['total'];$lastPage=max(1,(int)ceil($total/$perPage));
  $listingPage=min($lastPage,max(1,(int)($input['p']??1)));$filters['p']=$listingPage;$offset=($listingPage-1)*$perPage;
  $lifeColumn=\App\People\LifeStatusService::ready()?',v.is_deceased,v.date_of_death':',0 AS is_deceased,NULL AS date_of_death';
  $personColumns='v.id,v.voter_id,v.full_name,v.gender,v.region,v.constituency,v.polling_station,'
   .(VoterService::namesReady()?'v.first_name,v.last_name':'NULL AS first_name,NULL AS last_name').','
   .(AddressService::ready()?'v.town':'NULL AS town');
  $rows=Database::all("SELECT $personColumns$lifeColumn,m.id AS membership_id,m.member_number,m.status AS membership_status,m.joined_on $labels FROM $from WHERE $where ORDER BY $order LIMIT $perPage OFFSET $offset",$params);
  $memberExecutiveRoles=$membersOnly?\App\Executives\AppointmentService::memberDirectoryRoles(array_column($rows,'membership_id')):[];
  return ['rows'=>$rows,'memberExecutiveRoles'=>$memberExecutiveRoles,'total'=>$total,'summary'=>$summary,'filters'=>$filters,'listingPage'=>$listingPage,'lastPage'=>$lastPage,'perPage'=>$perPage,'locationCatalog'=>LocationService::catalog(false),'addressCatalog'=>AddressService::catalog()];
 }
 public static function voter(int $id): array {
  [$joins,$labels]=self::locationSql();
  $labels.=self::membershipSql();
  return Database::one('SELECT v.*,m.id AS membership_id,m.member_number,m.status AS membership_status,m.joined_on'.$labels.' FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id'.$joins.MemberProfileService::sqlJoin().' WHERE v.id=? AND '.\App\Voters\TrashService::active(),[$id])??throw new HttpException(404,'Voter not found.');
 }
 public static function member(int $id): array {
  $voter=Database::scalar('SELECT voter_record_id FROM members WHERE id=?',[$id]);
  if(!$voter){throw new HttpException(404,'Member not found.');}return self::voter((int)$voter);
 }
}
