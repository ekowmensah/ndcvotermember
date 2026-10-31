<?php
declare(strict_types=1);
namespace App;
final class AddressService
{
 public static function ready(): bool {
  static $ready=null;
  return $ready??=((int)Database::scalar("SELECT COUNT(DISTINCT table_name) FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND referenced_table_name='residential_regions' AND referenced_column_name='id' AND ((table_name='districts' AND column_name='region_id') OR (table_name='voters' AND column_name='residential_region_id'))")===2);
 }
 public static function catalog(): array {
  if(!self::ready()){return [];}
  return ['regions'=>Database::all('SELECT id,name FROM residential_regions ORDER BY name'),
   'districts'=>Database::all('SELECT d.id,d.name,d.region_id,r.name AS region_name FROM districts d JOIN residential_regions r ON r.id=d.region_id ORDER BY r.name,d.name'),
   'towns'=>TownService::catalog()];
 }
 public static function selection(array $input): array {
  $town=TownService::normalize(Validation::text($input,'town',180,false));
  $regionName=Validation::text($input,'residential_region',160,false);
  $districtName=Validation::text($input,'residential_district',180,false);
  if(!self::ready()){
   if($town!==null||$regionName!==null||$districtName!==null){throw new \InvalidArgumentException('Apply migrations 09 then 10 in phpMyAdmin to enable the separate residential address directory. Do not reimport an applied migration.');}
   return [];
  }
  $region=null;$district=null;
  if($regionName!==null){$region=Database::one('SELECT id,name FROM residential_regions WHERE name=?',[$regionName])??throw new \InvalidArgumentException('Choose a region from the Residential regions directory.');}
  if($districtName!==null){
   if(!$region){throw new \InvalidArgumentException('Choose the residential region before its district.');}
   $district=Database::one('SELECT id,name FROM districts WHERE region_id=? AND name=?',[$region['id'],$districtName])??throw new \InvalidArgumentException('Choose a district belonging to the selected residential region. Add missing districts in Districts first.');
  }
  if(array_key_exists('town_choice',$input)){
   $choice=Validation::text($input,'town_choice',32,false);
   if($choice===null){$town=null;}
   elseif($choice==='new'){$town=TownService::normalize(Validation::text($input,'town',180));}
   else{
    if(!TownService::ready()){throw new \InvalidArgumentException('Enable the town directory with migration 12, then refresh the voter form.');}
    $townId=Validation::id(['town_id'=>$choice],'town_id');
    $savedTown=Database::one('SELECT name FROM residential_towns WHERE id=? AND residential_region_id<=>? AND district_id<=>?',[$townId,$region['id']??null,$district['id']??null]);
    if(!$savedTown){throw new \InvalidArgumentException('Choose a town belonging to the selected residential region and district.');}
    $town=$savedTown['name'];
   }
  }
  return ['town'=>$town,'residential_region_id'=>$region['id']??null,'residential_district_id'=>$district['id']??null,'residential_region'=>$region['name']??null,'residential_district'=>$district['name']??null];
 }
 public static function saveDistrict(array $input): void {
  VoterService::requireManage();
  if(!self::ready()){throw new \InvalidArgumentException('Apply migrations 09 then 10 in phpMyAdmin first; do not reimport applied migrations.');}
  $name=Validation::text($input,'name',180);$region=Validation::id($input,'region_id');
  if(!Database::one('SELECT id FROM residential_regions WHERE id=?',[$region])){throw new \InvalidArgumentException('Choose an existing residential region.');}
  Database::transaction(static function()use($input,$name,$region){
   if(!empty($input['district_id'])){
    $id=Validation::id($input,'district_id');$before=Database::one('SELECT * FROM districts WHERE id=? FOR UPDATE',[$id])??throw new \InvalidArgumentException('District not found.');
    if((int)$before['region_id']!==$region){throw new \InvalidArgumentException('A district’s region cannot be changed while editing.');}
    if(Validation::text($input,'version',30)!==$before['updated_at']){throw new \InvalidArgumentException('This district changed. Refresh and review it before saving.');}
    Database::execute('UPDATE districts SET name=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$name,$id]);
    Auth::audit('district.updated','district',$id,['before'=>$before['name'],'after'=>$name]);
   }else{Database::execute('INSERT INTO districts(region_id,name) VALUES(?,?)',[$region,$name]);Auth::audit('district.created','district',(int)Database::connection()->lastInsertId(),['name'=>$name,'region_id'=>$region]);}
  });
 }
 public static function listingFilters(array $input,bool $isDistrict): array {
  $filters=['sort'=>Validation::choice(['sort'=>$input['sort']??'name'],'sort',['name','name_desc','newest']),
   'per_page'=>(int)Validation::choice(['per_page'=>$input['per_page']??'25'],'per_page',['25','50','100'])];
  $q=Validation::text($input,'q',100,false);if($q!==null){$filters['q']=$q;}
  if($isDistrict&&!empty($input['region_filter'])){$filters['region_filter']=Validation::id($input,'region_filter');}
  if(Validation::text($input,'coverage',32,false)!==null){$filters['coverage']=Validation::choice($input,'coverage',['with_voters','without_voters']);}
  return $filters;
 }
 public static function directory(array $input): array {return self::residentialDirectory($input,true);}
 public static function regionDirectory(array $input): array {return self::residentialDirectory($input,false);}
 private static function residentialDirectory(array $input,bool $isDistrict): array {
  if(!self::ready()){return ['available'=>false];}
  $filters=self::listingFilters($input,$isDistrict);$table=$isDistrict?'districts':'residential_regions';
  $parentColumn=$isDistrict?'residential_district_id':'residential_region_id';
  $where='1=1';$params=[];
  if(isset($filters['q'])){
   $where.=" AND (l.name LIKE ? ESCAPE '!' OR CAST(l.id AS CHAR)=?)";
   array_push($params,'%'.strtr($filters['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%',$filters['q']);
  }
  if(isset($filters['region_filter'])){$where.=' AND l.region_id=?';$params[]=$filters['region_filter'];}
  $joins=" LEFT JOIN (SELECT v.$parentColumn AS location_id,COUNT(*) AS voter_count,COUNT(m.id) AS member_count FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id WHERE ".\App\Voters\TrashService::active()." AND v.$parentColumn IS NOT NULL GROUP BY v.$parentColumn) usage_counts ON usage_counts.location_id=l.id";
  if($isDistrict){$joins.=' JOIN residential_regions r ON r.id=l.region_id';}
  if(isset($filters['coverage'])){$where.=$filters['coverage']==='with_voters'?' AND COALESCE(usage_counts.voter_count,0)>0':' AND COALESCE(usage_counts.voter_count,0)=0';}
  $from=" FROM $table l".$joins.' WHERE '.$where;
  $summary=array_map('intval',Database::one('SELECT COUNT(*) AS total,COALESCE(SUM(usage_counts.voter_count),0) AS voters,COALESCE(SUM(usage_counts.member_count),0) AS members,COALESCE(SUM(COALESCE(usage_counts.voter_count,0)=0),0) AS unused'.$from,$params)??[]);
  $total=$summary['total'];$perPage=$filters['per_page'];$last=max(1,(int)ceil($total/$perPage));$listingPage=min($last,max(1,(int)($input['p']??1)));$offset=($listingPage-1)*$perPage;$filters['p']=$listingPage;
  $order=match($filters['sort']){'name_desc'=>'l.name DESC,l.id DESC','newest'=>'l.id DESC',default=>'l.name,l.id'};
  $extra=$isDistrict?',r.name AS region_name':', (SELECT COUNT(*) FROM districts dx WHERE dx.region_id=l.id) AS district_count';
  $townColumn=$isDistrict?'district_id':'residential_region_id';
  $extra.=TownService::ready()?",(SELECT COUNT(*) FROM residential_towns tx WHERE tx.$townColumn=l.id) AS town_count":',NULL AS town_count';
  $rows=Database::all('SELECT l.*,COALESCE(usage_counts.voter_count,0) AS voter_count,COALESCE(usage_counts.member_count,0) AS member_count'.$extra.$from." ORDER BY $order LIMIT $perPage OFFSET $offset",$params);
  $editing=empty($input['edit'])?null:(Database::one("SELECT * FROM $table WHERE id=?",[Validation::id($input,'edit')])??throw new HttpException(404,'Location not found.'));
  $entity=$isDistrict?'district':'residential_region';
  $history=$editing?Database::all('SELECT a.action_code,a.occurred_at,u.name AS actor FROM audit_events a LEFT JOIN app_users u ON u.id=a.actor_user_id WHERE a.entity_type=? AND a.entity_id=? ORDER BY a.id DESC LIMIT 5',[$entity,$editing['id']]):[];
  $regions=Database::all('SELECT id,name FROM residential_regions ORDER BY name');
  return ['available'=>true]+compact('filters','summary','total','perPage','last','listingPage','rows','editing','history','regions');
 }
 public static function saveRegion(array $input): void {
  VoterService::requireManage();if(!self::ready()){throw new \InvalidArgumentException('Apply migration 10 in phpMyAdmin first.');}
  $name=Validation::text($input,'name',160);
  Database::transaction(static function()use($input,$name){
   if(!empty($input['residential_region_id'])){
    $id=Validation::id($input,'residential_region_id');$before=Database::one('SELECT * FROM residential_regions WHERE id=? FOR UPDATE',[$id])??throw new \InvalidArgumentException('Residential region not found.');
    if(Validation::text($input,'version',30)!==$before['updated_at']){throw new \InvalidArgumentException('This residential region changed. Refresh and review before saving.');}
    Database::execute('UPDATE residential_regions SET name=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$name,$id]);
    Auth::audit('residential_region.updated','residential_region',$id,['before'=>$before['name'],'after'=>$name]);
   }else{Database::execute('INSERT INTO residential_regions(name) VALUES(?)',[$name]);Auth::audit('residential_region.created','residential_region',(int)Database::connection()->lastInsertId(),['name'=>$name]);}
  });
 }
}
