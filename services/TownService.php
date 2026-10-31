<?php
declare(strict_types=1);
namespace App;
final class TownService
{
 private static function version(array $town): string {
  return hash('sha256',json_encode([(int)$town['id'],$town['name'],$town['residential_region_id'],$town['district_id']],JSON_THROW_ON_ERROR));
 }
 public static function editor(int $id): array {
  VoterService::requireManage();
  if(!self::ready()){throw new \InvalidArgumentException('Complete residential town migration 12 in phpMyAdmin first.');}
  $town=Database::one('SELECT id,name,residential_region_id,district_id FROM residential_towns WHERE id=?',[$id])??throw new HttpException(404,'Town not found.');
  $version=self::version($town);$catalog=AddressService::catalog();
  $counts=Database::one('SELECT COUNT(*) AS voters,COUNT(m.id) AS members FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id WHERE v.residential_town_id=?',[$id]);
  $values=['name'=>$town['name'],'residential_region_filter'=>$town['residential_region_id'],'residential_district_filter'=>$town['district_id']];
  $saved=$_SESSION['town_form']??[];unset($_SESSION['town_form']);
  if((string)($saved['id']??'')===(string)$id&&($saved['version']??'')===$version){$values=array_replace($values,$saved);}
  $merge=$_SESSION['town_merge']??null;
  if(!$merge||(int)$merge['source_id']!==$id||$merge['source_version']!==$version||($saved['version']??'')!==$version){$merge=null;}
  return compact('town','version','catalog','counts','values','merge');
 }
 public static function save(array $input): bool {
  VoterService::requireManage();
  if(!self::ready()){throw new \InvalidArgumentException('Complete residential town migration 12 in phpMyAdmin first.');}
  $id=Validation::id($input,'id');$version=Validation::text($input,'version',64);
  $name=self::normalize(Validation::text($input,'name',180));
  if($name===null){throw new \InvalidArgumentException('Enter a town name.');}
  $region=Validation::text($input,'residential_region_filter',20,false)===null?null:Validation::id($input,'residential_region_filter');
  $district=Validation::text($input,'residential_district_filter',20,false)===null?null:Validation::id($input,'residential_district_filter');
  if($district&&!$region){throw new \InvalidArgumentException('Select a residential region before choosing a district.');}
  return Database::transaction(function() use($id,$version,$name,$region,$district,$input): bool {
   // Match person saves: lock linked voters before the town. Recheck links after the town lock.
   Database::all('SELECT id FROM voters WHERE residential_town_id=? ORDER BY id FOR UPDATE',[$id]);
   $before=Database::one('SELECT id,name,residential_region_id,district_id FROM residential_towns WHERE id=? FOR UPDATE',[$id])??throw new \InvalidArgumentException('This town was removed or merged. Return to Residential towns to find the current record.');
   if(!hash_equals(self::version($before),$version)){throw new \InvalidArgumentException('This town changed since you opened the editor. Review its current location and try again.');}
   if($region&&!Database::one('SELECT id FROM residential_regions WHERE id=? FOR SHARE',[$region])){throw new \InvalidArgumentException('Choose an existing residential region.');}
   if($district&&!Database::one('SELECT id FROM districts WHERE id=? AND region_id=? FOR SHARE',[$district,$region])){throw new \InvalidArgumentException('Choose a district belonging to the selected residential region.');}
   // Use the database collation: case/accent-equivalent names in the same scope merge.
   $target=Database::one('SELECT id,name,residential_region_id,district_id FROM residential_towns WHERE name=? AND residential_region_id<=>? AND district_id<=>? AND id<>? FOR UPDATE',[$name,$region,$district,$id]);
   if(!$target&&$before['name']===$name&&(string)$before['residential_region_id']===(string)$region&&(string)$before['district_id']===(string)$district){return false;}
   $linked=Database::all('SELECT id,updated_at FROM voters WHERE residential_town_id=? ORDER BY id FOR UPDATE',[$id]);
   if($target){
    $fingerprint=hash('sha256',json_encode([$id,$version,$name,$region,$district,self::version($target),$linked],JSON_THROW_ON_ERROR));
    $pending=$_SESSION['town_merge']??[];$token=Validation::text($input,'merge_token',64,false);
    if(!$token||!hash_equals((string)($pending['token']??''),$token)||!hash_equals((string)($pending['fingerprint']??''),$fingerprint)){
     $_SESSION['town_merge']=['source_id'=>$id,'source_version'=>$version,'target'=>$target,'people'=>count($linked),'fingerprint'=>$fingerprint,'token'=>bin2hex(random_bytes(32))];
     throw new \InvalidArgumentException('A matching town exists. Review the merge below and confirm to continue. No changes have been saved.');
    }
   }
   $targetId=$target?(int)$target['id']:$id;$canonical=$target['name']??$name;
   if(!$target){Database::execute('UPDATE residential_towns SET name=?,residential_region_id=?,district_id=? WHERE id=?',[$canonical,$region,$district,$id]);}
   Database::execute('UPDATE voters SET residential_town_id=?,residential_region_id=?,residential_district_id=?,town=?,updated_at=CURRENT_TIMESTAMP(6) WHERE residential_town_id=?',[$targetId,$region,$district,$canonical,$id]);
   if($target){Database::execute('DELETE FROM residential_towns WHERE id=?',[$id]);}
   Auth::audit($target?'residential_town.merged':'residential_town.updated','residential_town',$id,['before'=>$before,'after'=>['id'=>$targetId,'name'=>$canonical,'residential_region_id'=>$region,'district_id'=>$district],'linked_people'=>count($linked)]);
   return $target!==null;
  });
 }
 public static function ready(): bool {
  static $ready=null;
  return $ready??=(AddressService::ready()&&(bool)Database::scalar("SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name='voters' AND column_name='residential_town_id' AND referenced_table_name='residential_towns'"));
 }
 public static function normalize(?string $name): ?string {
  if($name===null){return null;}$name=preg_replace('/\s+/u',' ',$name);
  if($name===null){throw new \InvalidArgumentException('Town must contain valid text.');}
  $name=trim($name);return $name===''?null:$name;
 }
 // Called inside the voter transaction, after validating district/region.
 public static function resolve(?string $name,?int $region,?int $district): ?array {
  $name=self::normalize($name);if($name===null){return null;}
  $changed=Database::execute('INSERT INTO residential_towns(name,residential_region_id,district_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',[$name,$region,$district]);
  $id=(int)Database::connection()->lastInsertId();
  $town=Database::one('SELECT id,name FROM residential_towns WHERE id=?',[$id])??throw new \RuntimeException('Town could not be saved.');
  if($changed===1){Auth::audit('residential_town.created','residential_town',$id,['name'=>$town['name'],'residential_region_id'=>$region,'district_id'=>$district]);}
  return $town;
 }
 public static function catalog(): array {
  if(!self::ready()){return [];}
  return Database::all('SELECT t.id,t.name,t.residential_region_id,t.district_id,r.name AS region_name,d.name AS district_name,(SELECT COUNT(*) FROM voters v WHERE v.residential_town_id=t.id AND '.\App\Voters\TrashService::active().') AS voter_count FROM residential_towns t LEFT JOIN residential_regions r ON r.id=t.residential_region_id LEFT JOIN districts d ON d.id=t.district_id ORDER BY t.name,r.name,d.name,t.id');
 }
 public static function directory(array $input): array {
  if(!self::ready()){return ['available'=>false];}
  $catalog=AddressService::catalog();$filters=[];$where='1=1';$params=[];
  $q=Validation::text($input,'q',180,false);if($q!==null){$filters['q']=$q;$where.=" AND t.name LIKE ? ESCAPE '!'";$params[]='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}
  foreach(['residential_region_filter'=>'t.residential_region_id','residential_district_filter'=>'t.district_id'] as $key=>$column){if(!empty($input[$key])){$filters[$key]=Validation::id($input,$key);$where.=" AND $column=?";$params[]=$filters[$key];}}
  $total=(int)Database::scalar('SELECT COUNT(*) FROM residential_towns t WHERE '.$where,$params);$last=max(1,(int)ceil($total/25));$townPage=min($last,max(1,(int)($input['p']??1)));$offset=($townPage-1)*25;
  $rows=Database::all('SELECT t.id,t.name,r.name AS region_name,d.name AS district_name,(SELECT COUNT(*) FROM voters v WHERE v.residential_town_id=t.id AND '.\App\Voters\TrashService::active().') AS voter_count FROM residential_towns t LEFT JOIN residential_regions r ON r.id=t.residential_region_id LEFT JOIN districts d ON d.id=t.district_id WHERE '.$where." ORDER BY t.name,r.name,d.name,t.id LIMIT 25 OFFSET $offset",$params);
  return ['available'=>true]+compact('catalog','filters','total','last','townPage','rows');
 }
}
