<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Auth,Validation,View};
final class LifecycleController
{
 public static function get(): void {View::render('executives/appointments/manage',LifecycleService::page(Validation::id($_GET,'id')),'Manage executive service');}
 public static function save(): never {
  Auth::requireRole(['admin','registrar']);$id=Validation::id($_POST,'id');
  try{LifecycleService::finish($_POST);unset($_SESSION['executive_lifecycle_form']);\flash('success','Appointment updated. Service history has been preserved.');\redirect('executive_appointment',['id'=>$id]);}
  catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());}
  catch(\PDOException $error){error_log((string)$error);\flash('danger','No changes were saved. Review the latest appointment and try again.');}
  $values=[];foreach(['id','version','ended_on','end_reason','end_notes','appointment_type','expected_ends_on','notes'] as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$values[$field]=mb_substr((string)$_POST[$field],0,2000);}}
  $_SESSION['executive_lifecycle_form']=$values;try{LifecycleService::open($id);\redirect('executive_appointment_manage',['id'=>$id]);}catch(\InvalidArgumentException $error){\redirect('executive_appointment',['id'=>$id]);}
 }
}
