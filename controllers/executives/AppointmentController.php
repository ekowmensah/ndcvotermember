<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Auth,View,Validation};
final class AppointmentController
{
 public static function get(bool $create): void {
  $data=$create?AppointmentService::form($_GET):AppointmentService::listing($_GET);
  $title=$create?(!empty($data['editing'])?'Edit scheduled appointment':(!empty($data['replacement'])?'Replace executive':'Appoint executive')):'Executive appointments';
  View::render($create?'executives/appointments/create':'executives/appointments/index',$data,$title);
 }
 public static function save(): never {
  Auth::requireRole(['admin','registrar']);
  try{
   if(isset($_GET['edit_id'])){
    $routeId=Validation::id($_GET,'edit_id');
    if(!empty($_POST['edit_id'])&&Validation::id($_POST,'edit_id')!==$routeId){throw new \InvalidArgumentException('Edit target mismatch. Reopen the appointment before saving.');}
    $_POST['edit_id']=$routeId;
   }
   $savedAppointment=AppointmentService::create($_POST);unset($_SESSION['executive_appointment_form']);\flash('success',!empty($_POST['edit_id'])?'Scheduled appointment updated.':'Executive appointment saved.');if(!empty($_POST['enable_system_access'])&&\App\Security\AppointmentAccounts::ready()){$account=\App\Database::scalar('SELECT u.id FROM app_users u JOIN executive_appointments a ON a.member_id=u.member_id WHERE a.id=?',[$savedAppointment]);if($account&&\App\Security\AppointmentAccounts::managed((int)$account)){\redirect('executive_account',['id'=>$account]);}}\redirect('executive_appointments',['state'=>'all_open']);
  }
  catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());}
  catch(\PDOException $error){error_log((string)$error);\flash('danger',($error->errorInfo[1]??0)===1062?'This office or sign-in email is already in use. No appointment or account was created. Review both before retrying.':'The appointment could not be saved. No changes were made. Review the latest records and retry.');}
  $values=[];if(isset($_POST['provisioning_present'])){$values['enable_system_access']=isset($_POST['enable_system_access'])?'1':'0';}foreach(['member_id','position_id','organization_scope','organization_level','region_id','constituency_id','branch_id','polling_station_id','appointment_type','starts_on','expected_ends_on','notes','replace_id','replacement_ended_on','replacement_reason','replacement_notes','edit_id','edit_version','leadership_term_id','account_email','enable_system_access'] as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$values[$field]=mb_substr((string)$_POST[$field],0,2000);}}
  $_SESSION['executive_appointment_form']=$values;\redirect('executive_appointment_add',array_filter(['replace_id'=>$values['replace_id']??null,'edit_id'=>$values['edit_id']??null]));
 }
}
