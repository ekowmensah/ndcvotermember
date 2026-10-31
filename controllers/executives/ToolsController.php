<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Auth,Validation,View};
final class ToolsController
{
 public static function get(string $page): void {
  switch($page){
   case 'executive_terms': View::render('executives/terms/index',TermService::page($_GET),'Leadership terms');break;
   case 'executive_term_edit': View::render('executives/terms/edit',TermService::editor(empty($_GET['id'])?null:Validation::id($_GET,'id')),'Leadership term');break;
   case 'executive_recognition': View::render('executives/recognition/index',RecognitionService::page($_GET),'Hall of Fame');break;
   case 'executive_recognition_edit': View::render('executives/recognition/edit',RecognitionService::editor(Validation::id($_GET,'appointment_id')),'Recognize executive service');break;
   case 'executive_reports': View::render('executives/reports/index',[],'Executive reports');break;
   case 'executive_report_print': self::report($_GET,false);break;
  }
 }
 public static function post(string $action): never {
  if($action==='executive_report_csv'){self::report($_POST,true);exit;}
  $destination='executive_terms';$params=[];$session='exec_term_form';
  if($action==='executive_term_save'){Auth::requireRole(['admin','registrar']);$destination='executive_term_edit';if(!empty($_POST['id'])){$params=['id'=>Validation::id($_POST,'id')];}}
  elseif($action==='executive_term_assign'){Auth::requireRole(['admin','registrar']);$destination='executive_appointment';$params=['id'=>Validation::id($_POST,'appointment_id')];$session='';}
  elseif($action==='executive_recognition_save'){Auth::requireRole(['admin']);$destination='executive_recognition_edit';$params=['appointment_id'=>Validation::id($_POST,'appointment_id')];$session='exec_recognition_form';}
  else{throw new \App\HttpException(400,'Unknown executive action.');}
  try{
   if($action==='executive_term_save'){TermService::save($_POST);$destination='executive_terms';$params=[];}
   elseif($action==='executive_term_assign'){TermService::organize($_POST);}
   else{RecognitionService::save($_POST);$destination='executive_recognition';$params=['status'=>'all'];}
   if($session){unset($_SESSION[$session]);}\flash('success','Executive details saved.');\redirect($destination,$params);
  }catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());}
  catch(\PDOException $error){error_log((string)$error);\flash('danger',($error->errorInfo[1]??0)===1062?'An entry already exists. Open the existing record to update it.':'No changes were saved. Review the latest details and retry.');}
  if($session){$values=[];foreach(['id','appointment_id','version','name','starts_on','ends_on','description','is_active','title','citation','achievements','recognized_on','display_order','is_published'] as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$values[$field]=mb_substr((string)$_POST[$field],0,4000);}}$_SESSION[$session]=$values;}
  \redirect($destination,$params);
 }
 private static function report(array $input,bool $csv): void {
  if($csv){Auth::requireRole(['admin','registrar']);}else{Auth::requireLogin();}
  try{
   if($csv){ReportService::csv($input);}else{ReportService::print($input);}
   return;
  }catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());}
  catch(\PDOException $error){error_log((string)$error);\flash('danger','The report could not be generated. Check the required executive migrations in phpMyAdmin and retry.');}
  $params=[];
  foreach(['organization_scope','q','state','level','type','reason','year','sort','position_id','region_id','constituency_id','branch_id','polling_station_id','leadership_term_id','review','active_people','residential_region_filter','residential_district_filter'] as $field){
   if(isset($input[$field])&&is_scalar($input[$field])){$params[$field]=mb_substr((string)$input[$field],0,500);}
  }
  \redirect(($input['kind']??'')==='history'?'executive_history':'executive_appointments',$params);
 }
}
