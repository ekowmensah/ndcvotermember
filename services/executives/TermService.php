<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Auth,Database,Validation,HttpException};
final class TermService
{
 public static function ready(): bool {
  static $ready=null;return $ready??=(AppointmentService::ready()&&(int)Database::scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('executive_leadership_terms','executive_recognitions')")===2&&(int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='executive_appointments' AND column_name IN ('leadership_term_id','leadership_term_name_snapshot')")===2);
 }
 public static function date(array $input,string $key): string {
  $value=Validation::text($input,$key,10);$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
  if(!preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D',$value)||!$date||$date->format('Y-m-d')!==$value){throw new \InvalidArgumentException('Enter a valid '.\label($key).'.');}return $value;
 }
 public static function catalog(): array {return self::ready()?Database::all('SELECT id,name,starts_on,ends_on,is_active,updated_at FROM executive_leadership_terms ORDER BY starts_on DESC,id DESC'):[];}
 public static function filter(array $input,array &$filters,string &$where,array &$params): void {
  if(Validation::text($input,'leadership_term_id',20,false)===null){return;}
  if(!self::ready()){throw new \InvalidArgumentException('Import migration 19 before filtering leadership terms.');}
  if($input['leadership_term_id']==='unassigned'){$filters['leadership_term_id']='unassigned';$where.=' AND a.leadership_term_id IS NULL';return;}
  $filters['leadership_term_id']=Validation::id($input,'leadership_term_id');$where.=' AND a.leadership_term_id=?';$params[]=$filters['leadership_term_id'];
 }
 // Called inside the appointment transaction. Existing inactive groupings may be retained.
 public static function selection(array $input,string $start,?array $existing=null): array {
  if(!self::ready()){return [];}
  $raw=array_key_exists('leadership_term_id',$input)?Validation::text($input,'leadership_term_id',20,false):($existing['leadership_term_id']??null);
  if($raw===null||$raw===''){return ['leadership_term_id'=>null,'leadership_term_name_snapshot'=>null];}
  $id=Validation::id(['leadership_term_id'=>$raw],'leadership_term_id');$term=Database::one('SELECT * FROM executive_leadership_terms WHERE id=? FOR SHARE',[$id]);
  $same=(string)$id===(string)($existing['leadership_term_id']??'');
  if(!$term||(!$term['is_active']&&!$same)||$start<$term['starts_on']||$start>$term['ends_on']){throw new \InvalidArgumentException('Choose an active leadership term containing the appointment start, or leave the grouping unassigned.');}
  return ['leadership_term_id'=>$id,'leadership_term_name_snapshot'=>$same?$existing['leadership_term_name_snapshot']:$term['name']];
 }
 public static function page(array $input): array {
  Auth::requireLogin();$available=self::ready();if(!$available){return compact('available');}
  $q=Validation::text($input,'q',100,false);$where='1=1';$params=[];
  if($q!==null){$where.=" AND t.name LIKE ? ESCAPE '!'";$params[]='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}
  $total=(int)Database::scalar('SELECT COUNT(*) FROM executive_leadership_terms t WHERE '.$where,$params);$last=max(1,(int)ceil($total/25));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*25;
  $rows=Database::all('SELECT t.*,(SELECT COUNT(*) FROM executive_appointments a WHERE a.leadership_term_id=t.id) AS appointments FROM executive_leadership_terms t WHERE '.$where." ORDER BY t.starts_on DESC,t.id DESC LIMIT 25 OFFSET $offset",$params);
  return compact('available','q','rows','total','p','last');
 }
 public static function editor(?int $id): array {
  Auth::requireRole(['admin','registrar']);$available=self::ready();$term=$available&&$id?Database::one('SELECT * FROM executive_leadership_terms WHERE id=?',[$id]):null;if($available&&$id&&!$term){throw new HttpException(404,'Leadership term not found.');}
  $values=$term??['name'=>'','starts_on'=>'','ends_on'=>'','description'=>'','is_active'=>1];$saved=$_SESSION['exec_term_form']??[];unset($_SESSION['exec_term_form']);
  if((int)($saved['id']??0)===(int)($id??0)&&(!$term||($saved['version']??'')===$term['updated_at'])){$values=array_replace($values,$saved);}return compact('available','term','values');
 }
 public static function save(array $input): void {
  Auth::requireRole(['admin','registrar']);if(!self::ready()){throw new \InvalidArgumentException('Import migration 19 in phpMyAdmin first.');}
  $id=empty($input['id'])?null:Validation::id($input,'id');$name=Validation::text($input,'name',160);$name=preg_replace('/\s+/u',' ',$name);if(!$name){throw new \InvalidArgumentException('Enter a valid term name.');}
  $start=self::date($input,'starts_on');$end=self::date($input,'ends_on');if($end<$start){throw new \InvalidArgumentException('Term end must be on or after its start.');}
  $description=Validation::text($input,'description',1000,false);$active=Validation::choice($input,'is_active',['0','1']);
  Database::transaction(function()use($input,$id,$name,$start,$end,$description,$active):void{
   $old=$id?Database::one('SELECT * FROM executive_leadership_terms WHERE id=? FOR UPDATE',[$id]):null;
   if($id&&(!$old||Validation::text($input,'version',30)!==$old['updated_at'])){throw new \InvalidArgumentException('This term changed. Review the latest values before saving.');}
   if($id&&Database::scalar('SELECT id FROM executive_appointments WHERE leadership_term_id=? AND (starts_on<? OR starts_on>?) LIMIT 1',[$id,$start,$end])){throw new \InvalidArgumentException('These dates would exclude the start of an assigned appointment. Reclassify it first or retain the term dates.');}
   $after=['name'=>trim($name),'starts_on'=>$start,'ends_on'=>$end,'description'=>$description,'is_active'=>$active];
   if($old){$changed=false;foreach($after as $key=>$value){if((string)$old[$key]!== (string)$value){$changed=true;break;}}if(!$changed){return;}Database::execute('UPDATE executive_leadership_terms SET name=?,starts_on=?,ends_on=?,description=?,is_active=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[...array_values($after),$id]);}
   else{Database::execute('INSERT INTO executive_leadership_terms(name,starts_on,ends_on,description,is_active,created_by_user_id) VALUES(?,?,?,?,?,?)',[...array_values($after),Auth::user()['id']]);$id=(int)Database::connection()->lastInsertId();}
   Auth::audit('executive.leadership_term_saved','executive_term',$id,['before'=>$old?array_intersect_key($old,$after):null,'after'=>$after]);
  });
 }
 public static function organize(array $input): void {
  Auth::requireRole(['admin','registrar']);if(!self::ready()){throw new \InvalidArgumentException('Import migration 19 first.');}
  $id=Validation::id($input,'appointment_id');$termId=Validation::text($input,'leadership_term_id',20,false)===null?null:Validation::id($input,'leadership_term_id');
  Database::transaction(function()use($input,$id,$termId):void{
   $a=Database::one('SELECT * FROM executive_appointments WHERE id=? FOR UPDATE',[$id])??throw new \InvalidArgumentException('Appointment not found.');
   if(Validation::text($input,'version',30)!==$a['updated_at']){throw new \InvalidArgumentException('The appointment changed. Refresh its details before organizing.');}
   if((string)$a['leadership_term_id']===(string)$termId){return;}
   $term=$termId?Database::one('SELECT * FROM executive_leadership_terms WHERE id=? FOR SHARE',[$termId]):null;
   if($termId&&(!$term||!$term['is_active']||$a['starts_on']<$term['starts_on']||$a['starts_on']>$term['ends_on'])){throw new \InvalidArgumentException('Choose an active leadership term that contains this appointment’s start date.');}
   Database::execute('UPDATE executive_appointments SET leadership_term_id=?,leadership_term_name_snapshot=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$termId,$term['name']??null,$id]);
   Auth::audit('executive.term_classified','executive_appointment',$id,['before_term_id'=>$a['leadership_term_id'],'after_term_id'=>$termId]);
  });
 }
}
