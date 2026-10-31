<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Auth,Database,Validation,HttpException};
final class RecognitionService
{
 public static function page(array $input): array {
  Auth::requireLogin();$available=TermService::ready();if(!$available){return compact('available');}
  $filters=[];$where='a.ended_on IS NOT NULL AND a.cancelled_at IS NULL';$params=[];
  $filters['status']=Auth::admin()?Validation::choice(['status'=>$input['status']??'published'],'status',['published','draft','all']):'published';
  if($filters['status']!=='all'){$where.=' AND r.is_published=?';$params[]=$filters['status']==='published'?1:0;}
  $q=Validation::text($input,'q',100,false);if($q!==null){$filters['q']=$q;$term='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where.=" AND (a.member_name_snapshot LIKE ? ESCAPE '!' OR r.title LIKE ? ESCAPE '!' OR r.citation LIKE ? ESCAPE '!')";array_push($params,$term,$term,$term);}
  if(Validation::text($input,'level',30,false)!==null){$filters['level']=Validation::choice($input,'level',array_keys(PositionService::LEVELS));$where.=' AND a.organization_level=?';$params[]=$filters['level'];}
  OrganizationService::filter($input,$filters,$where,$params);
  $from=' FROM executive_recognitions r JOIN executive_appointments a ON a.id=r.appointment_id';$total=(int)Database::scalar('SELECT COUNT(*)'.$from.' WHERE '.$where,$params);$last=max(1,(int)ceil($total/12));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*12;
  $rows=Database::all('SELECT '.OrganizationService::column().',r.*,a.member_name_snapshot,a.position_name_snapshot,a.jurisdiction_snapshot,a.organization_level,a.starts_on,a.ended_on'.$from.' WHERE '.$where." ORDER BY r.display_order,r.recognized_on DESC,r.id DESC LIMIT 12 OFFSET $offset",$params);
  return compact('available','filters','total','last','p','rows');
 }
 public static function editor(int $appointmentId): array {
  Auth::requireRole(['admin']);if(!TermService::ready()){throw new \InvalidArgumentException('Import migration 19 first.');}
  $appointment=Database::one('SELECT * FROM executive_appointments WHERE id=? AND ended_on IS NOT NULL AND cancelled_at IS NULL',[$appointmentId])??throw new HttpException(404,'Choose a completed service term from Hall of service.');
  $recognition=Database::one('SELECT * FROM executive_recognitions WHERE appointment_id=?',[$appointmentId]);
  $values=$recognition??['title'=>'','citation'=>'','achievements'=>'','recognized_on'=>gmdate('Y-m-d'),'display_order'=>0,'is_published'=>0];
  $saved=$_SESSION['exec_recognition_form']??[];unset($_SESSION['exec_recognition_form']);
  if((int)($saved['appointment_id']??0)===$appointmentId&&($saved['version']??'')===($recognition['updated_at']??'')){$values=array_replace($values,$saved);}
  return compact('appointment','recognition','values');
 }
 public static function save(array $input): void {
  Auth::requireRole(['admin']);if(!TermService::ready()){throw new \InvalidArgumentException('Import migration 19 first.');}
  $id=Validation::id($input,'appointment_id');$title=Validation::text($input,'title',160);$citation=Validation::text($input,'citation',2000);$achievements=Validation::text($input,'achievements',4000,false);$date=Validation::date($input,'recognized_on',true);$published=Validation::choice($input,'is_published',['0','1']);
  $order=filter_var($input['display_order']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>65535]]);if($order===false){throw new \InvalidArgumentException('Display order must be a whole number from 0 to 65535.');}
  Database::transaction(function()use($input,$id,$title,$citation,$achievements,$date,$published,$order):void{
   $a=Database::one('SELECT * FROM executive_appointments WHERE id=? FOR UPDATE',[$id]);if(!$a||!$a['ended_on']||$a['cancelled_at']){throw new \InvalidArgumentException('Recognition requires a completed service term.');}
   $old=Database::one('SELECT * FROM executive_recognitions WHERE appointment_id=? FOR UPDATE',[$id]);
   if(Validation::text($input,'version',30,false)!==($old['updated_at']??null)){throw new \InvalidArgumentException('This recognition changed. Review the latest version.');}
   $values=[$title,$citation,$achievements,$date,$order,$published];
   if($old){$fields=['title','citation','achievements','recognized_on','display_order','is_published'];$changed=false;foreach($fields as $i=>$field){if((string)$old[$field]!== (string)$values[$i]){$changed=true;break;}}if(!$changed){return;}}
   if($old){Database::execute('UPDATE executive_recognitions SET title=?,citation=?,achievements=?,recognized_on=?,display_order=?,is_published=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[...$values,$old['id']]);$recognitionId=(int)$old['id'];}
   else{Database::execute('INSERT INTO executive_recognitions(title,citation,achievements,recognized_on,display_order,is_published,appointment_id,created_by_user_id) VALUES(?,?,?,?,?,?,?,?)',[...$values,$id,Auth::user()['id']]);$recognitionId=(int)Database::connection()->lastInsertId();}
   Auth::audit('executive.recognition_saved','executive_recognition',$recognitionId,['appointment_id'=>$id,'before_published'=>$old['is_published']??null,'after_published'=>(int)$published]);
  });
 }
}
