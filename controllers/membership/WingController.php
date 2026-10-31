<?php
declare(strict_types=1);
namespace App\Membership;
use App\{Auth,Validation,View};
final class WingController
{
 public static function get(bool $edit=false): void {
  if($edit){View::render('membership/wings/edit',WingService::editor(Validation::id($_GET,'id')),'Manage wing membership');}
  else{View::render('membership/wings/index',WingService::listing($_GET),'Membership wings');}
 }
 public static function save(): never {
  Auth::requireRole(['admin','registrar']);$id=Validation::id($_POST,'member_id');
  try{WingService::save($_POST);\flash('success','Wing membership saved.');\redirect('member',['id'=>$id]);}
  catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());}
  catch(\PDOException $error){error_log((string)$error);\flash('danger','No changes were saved. Reload the latest wing membership details and retry.');}
  \redirect('member_wings',['id'=>$id]);
 }
 public static function report(array $input,bool $csv=false): void {
  if($csv){Auth::requireRole(['admin','registrar']);}else{Auth::requireLogin();}
  try{
   if($csv){WingReportService::csv($input);}else{WingReportService::print($input);}
   return;
  }catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());}
  catch(\PDOException $error){error_log((string)$error);\flash('danger','The wing report could not be generated. Check the required migrations in phpMyAdmin and retry.');}
  $kind=is_string($input['kind']??null)?$input['kind']:'enrollments';
  if($kind==='history'){
   $id=filter_var($input['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
   if($id===false){\redirect('members');}
   $route='wing_history';$params=['id'=>$id];
  }else{
   $wing=is_string($input['wing']??null)?$input['wing']:'';
   if(!isset(WingService::WINGS[$wing])){\redirect('members');}
   $route=$kind==='candidates'?'wing_candidates':'wings';$params=['wing'=>$wing];
  }
  // Revalidate before carrying filters back. Invalid/deleted location filters
  // must not turn a recoverable report error into another failing page.
  try{
   $data=match($route){'wing_history'=>WingHistoryService::page($input),'wing_candidates'=>WingEligibilityService::candidates($input),default=>WingService::listing($input)};
   if(!empty($data['available'])){$params=$data['filters'];}
  }catch(\InvalidArgumentException|\PDOException $ignored){/* Return to the valid base directory. */}
  \redirect($route,$params);
 }
}
