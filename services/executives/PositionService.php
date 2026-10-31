<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Auth,Database,HttpException,Validation};
final class PositionService
{
 public const LEVELS=['national'=>'National','region'=>'Region','constituency'=>'Constituency','branch'=>'Electoral area (branch)','polling_station'=>'Polling station'];
 public static function ready(): bool {
  static $ready=null;
  return $ready??=((int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_position_titles' AND column_name IN ('id','name','description','display_order','is_active','created_by_user_id','created_at','updated_at')")===8 && (int)Database::scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('executive_position_levels','app_schema_migrations')")===2 && (bool)Database::scalar("SELECT COUNT(*) FROM app_schema_migrations WHERE migration_key='16_executive_position_levels'"));
 }
 public static function listing(array $input): array {
  Auth::requireLogin();$available=self::ready();if(!$available){return compact('available');}
  $filters=[];$where='1=1';$params=[];
  $q=Validation::text($input,'q',100,false);
  if($q!==null){$filters['q']=$q;$where.=" AND name LIKE ? ESCAPE '!'";$params[]='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}
  if(Validation::text($input,'level',20,false)!==null){$filters['level']=Validation::choice($input,'level',array_keys(self::LEVELS));$where.=' AND EXISTS(SELECT 1 FROM executive_position_levels pl WHERE pl.position_id=executive_position_titles.id AND pl.organization_level=?)';$params[]=$filters['level'];}
  if(Validation::text($input,'status',20,false)!==null){$filters['status']=Validation::choice($input,'status',['active','inactive']);$where.=' AND is_active=?';$params[]=$filters['status']==='active'?1:0;}
  $total=(int)Database::scalar('SELECT COUNT(*) FROM executive_position_titles WHERE '.$where,$params);$last=max(1,(int)ceil($total/25));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*25;
  $rows=Database::all('SELECT id,name,description,display_order,is_active,updated_at,(SELECT GROUP_CONCAT(pl.organization_level) FROM executive_position_levels pl WHERE pl.position_id=executive_position_titles.id) AS level_codes FROM executive_position_titles WHERE '.$where." ORDER BY display_order,name,id LIMIT 25 OFFSET $offset",$params);
  $summary=Database::one('SELECT COUNT(*) AS total,COALESCE(SUM(is_active=1),0) AS active,COALESCE(SUM(is_active=0),0) AS inactive FROM executive_position_titles');
  return compact('available','filters','total','last','p','rows','summary');
 }
 public static function editor(?int $id): array {
  Auth::requireRole(['admin','registrar']);$available=self::ready();$position=null;
  if($available&&$id){$position=Database::one('SELECT * FROM executive_position_titles WHERE id=?',[$id])??throw new HttpException(404,'Executive position not found.');}
  $values=$position??['name'=>'','description'=>'','display_order'=>0,'is_active'=>1];
  $values['levels']=$available?array_column(Database::all('SELECT organization_level FROM executive_position_levels WHERE position_id=?',[$id??0]),'organization_level'):[];if(!$id){$values['levels']=array_keys(self::LEVELS);}
  $saved=$_SESSION['executive_position_form']??[];unset($_SESSION['executive_position_form']);
  if((int)($saved['id']??0)===(int)($id??0)&&(!$position||($saved['version']??'')===$position['updated_at'])){$values=array_replace($values,$saved);}
  return compact('available','position','values');
 }
 public static function save(array $input): int {
  Auth::requireRole(['admin','registrar']);
  if(!self::ready()){throw new \InvalidArgumentException('Import database/16_executive_position_levels_mysql8.sql in phpMyAdmin first.');}
  $id=Validation::text($input,'id',20,false)===null?null:Validation::id($input,'id');
  $name=Validation::text($input,'name',160);$name=preg_replace('/\s+/u',' ',$name);
  if($name===null||trim($name)===''){throw new \InvalidArgumentException('Enter a valid position name.');}$name=trim($name);
  $levels=$input['levels']??[];
  if(!is_array($levels)||!$levels||count($levels)>count(self::LEVELS)){throw new \InvalidArgumentException('Select at least one applicable level.');}
  foreach($levels as $level){if(!is_string($level)||!array_key_exists($level,self::LEVELS)){throw new \InvalidArgumentException('Choose valid organizational levels.');}}
  $levels=array_values(array_intersect(array_keys(self::LEVELS),$levels));
  $description=Validation::text($input,'description',1000,false);
  $order=filter_var($input['display_order']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>65535]]);
  if($order===false){throw new \InvalidArgumentException('Display order must be a whole number from 0 to 65535.');}
  $active=Validation::choice($input,'is_active',['0','1']);
  return Database::transaction(function() use($input,$id,$name,$levels,$description,$order,$active):int{
   $before=null;
   if($id){
    $before=Database::one('SELECT * FROM executive_position_titles WHERE id=? FOR UPDATE',[$id])??throw new \InvalidArgumentException('This position no longer exists.');
    if(Validation::text($input,'version',30)!==$before['updated_at']){throw new \InvalidArgumentException('This position changed since you opened it. Review the latest values and try again.');}
    $before['levels']=array_column(Database::all('SELECT organization_level FROM executive_position_levels WHERE position_id=? ORDER BY organization_level FOR UPDATE',[$id]),'organization_level');
   }
   if(Database::scalar('SELECT id FROM executive_position_titles WHERE name=? AND id<>?',[$name,$id??0])){throw new \InvalidArgumentException('This position title already exists, including inactive entries. Edit or reactivate the existing position.');}
    if(AppointmentService::baseReady()){
     $used=Database::all('SELECT DISTINCT organization_level FROM executive_appointments WHERE position_id=? AND '.AppointmentService::openClause(),[$id]);
     foreach($used as $role){if(!in_array($role['organization_level'],$levels,true)){throw new \InvalidArgumentException('This level has current or scheduled appointments. End those appointments before removing the level.');}}
    }
   $after=['name'=>$name,'levels'=>$levels,'description'=>$description,'display_order'=>$order,'is_active'=>(int)$active];
   if($before){
    $changed=false;foreach($after as $key=>$value){if($key==='levels'?array_diff($before[$key],$value)||array_diff($value,$before[$key]):(string)$before[$key]!== (string)$value){$changed=true;break;}}
    if(!$changed){return $id;}
    Database::execute('UPDATE executive_position_titles SET name=?,description=?,display_order=?,is_active=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$name,$description,$order,$active,$id]);
   }else{
    Database::execute('INSERT INTO executive_position_titles(name,description,display_order,is_active,created_by_user_id) VALUES(?,?,?,?,?)',[$name,$description,$order,$active,Auth::user()['id']]);$id=(int)Database::connection()->lastInsertId();
   }
   Database::execute('DELETE FROM executive_position_levels WHERE position_id=?',[$id]);foreach($levels as $level){Database::execute('INSERT INTO executive_position_levels(position_id,organization_level) VALUES(?,?)',[$id,$level]);}
   $previous=$before?array_intersect_key($before,$after):null;
   Auth::audit($before?'executive_position.updated':'executive_position.created','executive_position',$id,['before'=>$previous,'after'=>$after]);return $id;
  });
 }
}
