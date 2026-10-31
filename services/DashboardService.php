<?php
declare(strict_types=1);
namespace App;

final class DashboardService
{
 public static function overview(array $input): array {
  $addressReady=AddressService::ready();$townReady=TownService::ready();
  $locationReady=LocationService::ready();$photoReady=PhotoService::ready();
  $officialReady=VoterService::officialIdsReady();$profileReady=MemberProfileService::ready();
  $regions=$addressReady?Database::all('SELECT id,name FROM residential_regions ORDER BY name'):[];
  $districts=$addressReady?Database::all('SELECT d.id,d.name,d.region_id,r.name AS region_name FROM districts d JOIN residential_regions r ON r.id=d.region_id ORDER BY r.name,d.name'):[];
  $filters=[];$scopeName='All residential locations';$where=\App\Voters\TrashService::active();$params=[];
  if($addressReady){
   foreach(['residential_region_filter'=>['residential_regions','residential_region_id'],'residential_district_filter'=>['districts','residential_district_id']] as $key=>[$table,$column]){
    if(empty($input[$key])){continue;}
    $id=Validation::id($input,$key);
    $row=Database::one("SELECT * FROM $table WHERE id=?",[$id])??throw new \InvalidArgumentException('The selected residential location no longer exists.');
    if($table==='districts'&&isset($filters['residential_region_filter'])&&(int)$row['region_id']!==$filters['residential_region_filter']){
     throw new \InvalidArgumentException('Choose a district within the selected residential region.');
    }
    $filters[$key]=$id;$where.=" AND v.$column=?";$params[]=$id;$scopeName=$row['name'];
   }
  }
  $from=' FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id';
  if($locationReady){$from.=' LEFT JOIN polling_stations ps ON ps.id=v.polling_station_id';}
  if($profileReady){$from.=' LEFT JOIN member_profiles mp ON mp.member_id=m.id';}
  $metrics=[
   'voters'=>'COUNT(*)','members'=>'COUNT(m.id)',
   'active'=>"COALESCE(SUM(m.status='active'),0)",
   'inactive'=>"COALESCE(SUM(m.status='inactive'),0)",
   'nonmembers'=>'COALESCE(SUM(m.id IS NULL),0)',
   'phone'=>"COALESCE(SUM(v.phone_encrypted IS NOT NULL AND v.phone_encrypted<>''),0)",
   'dob'=>'COALESCE(SUM(v.date_of_birth IS NOT NULL),0)',
  ];
  if(\App\People\LifeStatusService::ready()){
   $metrics['deceased_voters']='COALESCE(SUM(v.is_deceased=1),0)';
   $metrics['deceased_members']='COALESCE(SUM(v.is_deceased=1 AND m.id IS NOT NULL),0)';
   $metrics['not_deceased_voters']='COALESCE(SUM(v.is_deceased=0),0)';
   $metrics['not_deceased_members']='COALESCE(SUM(v.is_deceased=0 AND m.id IS NOT NULL),0)';
  }
  if($addressReady){
   $metrics['region_recorded']='COALESCE(SUM(v.residential_region_id IS NOT NULL),0)';
   $metrics['district_recorded']='COALESCE(SUM(v.residential_district_id IS NOT NULL),0)';
   $metrics['residential_regions']='COUNT(DISTINCT v.residential_region_id)';
   $metrics['residential_districts']='COUNT(DISTINCT v.residential_district_id)';
  }
  if($townReady){
   $metrics['town_recorded']='COALESCE(SUM(v.residential_town_id IS NOT NULL),0)';
   $metrics['member_town_recorded']='COALESCE(SUM(m.id IS NOT NULL AND v.residential_town_id IS NOT NULL),0)';
   $metrics['towns']='COUNT(DISTINCT v.residential_town_id)';
  }
  if($locationReady){
   $metrics['station_recorded']='COALESCE(SUM(v.polling_station_id IS NOT NULL),0)';
   $metrics['branch_recorded']='COALESCE(SUM(ps.electoral_area_id IS NOT NULL),0)';
   $metrics['constituencies']='COUNT(DISTINCT v.constituency_id)';
   $metrics['stations']='COUNT(DISTINCT v.polling_station_id)';
  }
  if($photoReady){$metrics['photos']='COALESCE(SUM(EXISTS(SELECT 1 FROM voter_photos vp WHERE vp.voter_record_id=v.id)),0)';}
  if($officialReady){$metrics['official_ids']='COALESCE(SUM(m.official_membership_id IS NOT NULL),0)';}
  if($profileReady){$metrics['profiles']='COUNT(mp.member_id)';}
  $columns=implode(',',array_map(static fn($name,$sql)=>"$sql AS $name",array_keys($metrics),array_values($metrics)));
  $stats=array_map('intval',Database::one('SELECT '.$columns.$from.' WHERE '.$where,$params)??[]);
  $gender=Database::all('SELECT v.gender,COUNT(*) AS voters,COUNT(m.id) AS members'.$from.' WHERE '.$where.' GROUP BY v.gender ORDER BY voters DESC,v.gender',$params);
  $start=new \DateTimeImmutable('first day of this month 00:00:00',new \DateTimeZone('UTC'));
  $start=$start->modify('-5 months');$end=$start->modify('+6 months');
  $trend=[];
  for($i=0;$i<6;$i++){
   $month=$start->modify("+$i months");
   $trend[$month->format('Y-m')]=['label'=>$month->format('M'),'full_label'=>$month->format('F Y'),'voters'=>0,'members'=>0];
  }
  foreach(['voters'=>'v.created_at','members'=>'m.created_at'] as $kind=>$column){
   $rows=Database::all("SELECT DATE_FORMAT($column,'%Y-%m') AS month,COUNT(*) AS total".$from." WHERE $where AND $column>=? AND $column<? GROUP BY month",array_merge($params,[$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')]));
   foreach($rows as $row){if(isset($trend[$row['month']])){$trend[$row['month']][$kind]=(int)$row['total'];}}
  }
  $residential=[];$residentialRegions=[];
  if($addressReady){
   $residentialRegions=Database::all('SELECT r.id,r.name,COUNT(*) AS voters,COUNT(m.id) AS members'.$from.' JOIN residential_regions r ON r.id=v.residential_region_id WHERE '.$where.' GROUP BY r.id,r.name ORDER BY voters DESC,r.name,r.id LIMIT 6',$params);
   $residential=Database::all('SELECT d.id,d.name,r.name AS region_name,COUNT(*) AS voters,COUNT(m.id) AS members'.$from.' JOIN districts d ON d.id=v.residential_district_id JOIN residential_regions r ON r.id=d.region_id WHERE '.$where.' GROUP BY d.id,d.name,r.name ORDER BY voters DESC,d.name,d.id LIMIT 8',$params);
  }
  $townTags=['voters'=>[],'members'=>[]];
  if($townReady&&!empty($filters['residential_district_filter'])){
   foreach(array_keys($townTags) as $kind){
    $townTags[$kind]=Database::all('SELECT t.id,t.name,COUNT(*) AS total'.$from.' JOIN residential_towns t ON t.id=v.residential_town_id WHERE '.$where.($kind==='members'?' AND m.id IS NOT NULL':'').' GROUP BY t.id,t.name ORDER BY total DESC,t.name,t.id LIMIT 15',$params);
   }
  }
  $electoral=[];
  if($locationReady){
   $electoral=Database::all('SELECT c.id,c.name,r.name AS region_name,COUNT(*) AS voters,COUNT(m.id) AS members'.$from.' JOIN constituencies c ON c.id=v.constituency_id JOIN regions r ON r.id=c.region_id WHERE '.$where.' GROUP BY c.id,c.name,r.name ORDER BY voters DESC,c.name,c.id LIMIT 6',$params);
  }
  $professions=[];$education=[];
  if($profileReady){
   $professions=Database::all("SELECT mp.profession AS name,COUNT(*) AS total".$from." WHERE $where AND m.id IS NOT NULL AND mp.profession IS NOT NULL AND TRIM(mp.profession)<>'' GROUP BY mp.profession ORDER BY total DESC,mp.profession LIMIT 6",$params);
   $education=Database::all("SELECT mp.education_level AS name,COUNT(*) AS total".$from." WHERE $where AND m.id IS NOT NULL GROUP BY mp.education_level ORDER BY total DESC,mp.education_level",$params);
  }
  $townLabel=$townReady?'rt.name':($addressReady?'v.town':'NULL');
  $recentFrom=$from.($townReady?' LEFT JOIN residential_towns rt ON rt.id=v.residential_town_id':'');
  $officialColumn=$officialReady?'m.official_membership_id':'NULL AS official_membership_id';
  $recentVoters=Database::all("SELECT v.id,v.full_name,v.voter_id,v.created_at,m.id AS membership_id,m.member_number,$officialColumn,$townLabel AS town".$recentFrom." WHERE $where ORDER BY v.created_at DESC,v.id DESC LIMIT 6",$params);
  $recentMembers=Database::all("SELECT v.full_name,m.id,m.member_number,$officialColumn,m.status,m.joined_on,m.created_at,$townLabel AS town".$recentFrom." WHERE $where AND m.id IS NOT NULL ORDER BY m.created_at DESC,m.id DESC LIMIT 5",$params);
  $executiveStats=\App\Executives\HistoryService::dashboard($filters);
  $wingStats=\App\Membership\WingDashboardService::overview($filters);
  return compact('wingStats','executiveStats','addressReady','townReady','locationReady','photoReady','officialReady','profileReady','regions','districts','filters','scopeName','stats','gender','trend','residential','residentialRegions','townTags','electoral','professions','education','recentVoters','recentMembers');
 }
}
