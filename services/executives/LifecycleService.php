<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Auth,Database,Validation,HttpException};
final class LifecycleService
{
 public const REASONS=['term_completed'=>'Term completed','resigned'=>'Resigned','replaced'=>'Replaced','removed'=>'Removed from office','deceased'=>'Deceased','other'=>'Other'];
 public static function open(int $id): array {
  Auth::requireRole(['admin','registrar']);
  if(!AppointmentService::ready()){throw new \InvalidArgumentException('Import database/18_executive_service_lifecycle_mysql8.sql in phpMyAdmin first.');}
  $appointment=Database::one('SELECT * FROM executive_appointments WHERE id=?',[$id])??throw new HttpException(404,'Appointment not found.');
  if($appointment['ended_on']||$appointment['cancelled_at']){throw new \InvalidArgumentException('This appointment is already closed. Open its details to review the saved history.');}return $appointment;
 }
 public static function page(int $id): array {
  $appointment=self::open($id);$values=['ended_on'=>gmdate('Y-m-d'),'end_reason'=>'term_completed','end_notes'=>''];
  $saved=$_SESSION['executive_lifecycle_form']??[];unset($_SESSION['executive_lifecycle_form']);
  if((int)($saved['id']??0)===$id&&($saved['version']??'')===$appointment['updated_at']){$values=array_replace($values,$saved);}
  return compact('appointment','values');
 }
 private static function endDate(array $input,string $field,array $appointment): string {
  $date=Validation::date($input,$field,true);
  if($date<$appointment['starts_on']){throw new \InvalidArgumentException('Actual end date cannot precede the appointment start. Cancel a scheduled appointment instead.');}return $date;
 }
 private static function verify(array $appointment,array $input,string $field='version'): void {
  if($appointment['ended_on']||$appointment['cancelled_at']){throw new \InvalidArgumentException('This appointment has already been ended or cancelled.');}
  if(Validation::text($input,$field,30)!==$appointment['updated_at']){throw new \InvalidArgumentException('The appointment changed. Open its current details and review again.');}
 }
 public static function finish(array $input): void {
  Auth::requireRole(['admin','registrar']);$id=Validation::id($input,'id');$lookup=self::open($id);
  $action=Validation::choice($input,'operation',['end','cancel','amend']);
  if(!isset($input['confirm_action'])){throw new \InvalidArgumentException('Confirm the selected action before saving.');}
  Database::transaction(function()use($input,$id,$lookup,$action):void{
   // Same order as enrollment/removal and appointment creation.
   if($lookup['voter_record_id']){Database::one('SELECT id FROM voters WHERE id=? FOR UPDATE',[$lookup['voter_record_id']]);}
   if($lookup['member_id']){Database::one('SELECT id FROM members WHERE id=? FOR UPDATE',[$lookup['member_id']]);}
   Database::one('SELECT id FROM executive_position_titles WHERE id=? FOR UPDATE',[$lookup['position_id']]);
   $appointment=Database::one('SELECT * FROM executive_appointments WHERE id=? FOR UPDATE',[$id])??throw new HttpException(404,'Appointment not found.');self::verify($appointment,$input);
   if($action==='amend'){
    $expected=Validation::text($input,'expected_ends_on',10,false);
    if($expected!==null){$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$expected);if(!$parsed||$parsed->format('Y-m-d')!==$expected||$expected<$appointment['starts_on']){throw new \InvalidArgumentException('Expected end must be a valid date on or after the start date.');}}
    $type=Validation::choice($input,'appointment_type',array_keys(AppointmentService::TYPES));$notes=Validation::text($input,'notes',2000,false);
    if($type===$appointment['appointment_type']&&$expected===$appointment['expected_ends_on']&&$notes===$appointment['notes']){return;}
    Database::execute('UPDATE executive_appointments SET appointment_type=?,expected_ends_on=?,notes=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$type,$expected,$notes,$id]);
    Auth::audit('executive.appointment_amended','executive_appointment',$id,['before'=>['appointment_type'=>$appointment['appointment_type'],'expected_ends_on'=>$appointment['expected_ends_on']],'after'=>['appointment_type'=>$type,'expected_ends_on'=>$expected],'notes_changed'=>$notes!==$appointment['notes']]);return;
   }
   $notes=Validation::text($input,'end_notes',2000,false);
   if($action==='cancel'){
    if($appointment['starts_on']<=gmdate('Y-m-d')){throw new \InvalidArgumentException('This appointment has started. End its service instead of cancelling it.');}
    if($notes===null){throw new \InvalidArgumentException('Enter a reason for cancelling this scheduled appointment.');}
    Database::execute("UPDATE executive_appointments SET cancelled_at=CURRENT_TIMESTAMP(6),end_reason='cancelled',end_notes=?,ended_by_user_id=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?",[$notes,Auth::user()['id'],$id]);
    Auth::audit('executive.appointment_cancelled','executive_appointment',$id,['member_id'=>$appointment['member_id']]);return;
   }
   $date=self::endDate($input,'ended_on',$appointment);$reason=Validation::choice($input,'end_reason',array_keys(self::REASONS));
   if($reason==='other'&&$notes===null){throw new \InvalidArgumentException('Add a note explaining the Other end reason.');}
   self::close($appointment,$date,$reason,$notes);
  });
 }
 private static function close(array $appointment,string $date,string $reason,?string $notes): void {
  Database::execute('UPDATE executive_appointments SET ended_on=?,end_reason=?,end_notes=?,ended_by_user_id=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$date,$reason,$notes,Auth::user()['id'],$appointment['id']]);
  Auth::audit('executive.appointment_ended','executive_appointment',(int)$appointment['id'],['member_id'=>$appointment['member_id'],'ended_on'=>$date,'end_reason'=>$reason]);
 }
 // Caller owns the transaction and has locked voters/members and the position.
 public static function closeForReplacement(array $input,array $scope,int $positionId,string $level,string $start,string $organization='main'): array {
  $id=Validation::id($input,'replace_id');
  $old=Database::one('SELECT * FROM executive_appointments WHERE id=? FOR UPDATE',[$id])??throw new \InvalidArgumentException('Previous appointment not found.');
  self::verify($old,$input,'replace_version');
  if(OrganizationService::code($old)!==$organization){throw new \InvalidArgumentException('A replacement must stay in the same party or wing organization.');}
  if($old['starts_on']>gmdate('Y-m-d')){throw new \InvalidArgumentException('Cancel the scheduled appointment before creating another reservation.');}
  if((int)$old['position_id']!==$positionId||$old['organization_level']!==$level||(int)$old['jurisdiction_id']!==$scope['jurisdiction_id']){throw new \InvalidArgumentException('A replacement must use the same position, level and jurisdiction.');}
  if(!isset($input['confirm_replacement'])){throw new \InvalidArgumentException('Confirm the replacement of the existing office holder.');}
  $date=self::endDate($input,'replacement_ended_on',$old);
  if($start<$date){throw new \InvalidArgumentException('The successor must start on or after the previous holder’s actual end date. A linked same-day handover is allowed.');}
  $reason=Validation::choice($input,'replacement_reason',['replaced','term_completed']);$notes=Validation::text($input,'replacement_notes',2000,false);
  self::close($old,$date,$reason,$notes);return $old;
 }
 public static function history(int $person,array $input): array {
  if(!AppointmentService::ready()){return ['rows'=>[],'total'=>0,'p'=>1,'last'=>1];}
  $where='voter_record_id=? AND (ended_on IS NOT NULL OR cancelled_at IS NOT NULL)';
  $total=(int)Database::scalar('SELECT COUNT(*) FROM executive_appointments WHERE '.$where,[$person]);$last=max(1,(int)ceil($total/10));$p=min($last,max(1,(int)($input['executive_history_p']??1)));$offset=($p-1)*10;
  $rows=Database::all('SELECT '.OrganizationService::column('executive_appointments').',id,member_name_snapshot,position_name_snapshot,jurisdiction_snapshot,organization_level,appointment_type,starts_on,ended_on,end_reason,cancelled_at FROM executive_appointments WHERE '.$where." ORDER BY COALESCE(cancelled_at,ended_on) DESC,id DESC LIMIT 10 OFFSET $offset",[$person]);return compact('rows','total','p','last');
 }
}
