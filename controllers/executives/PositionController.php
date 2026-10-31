<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Auth,Validation,View};
final class PositionController
{
 public static function get(bool $edit): void {
  if($edit){$id=isset($_GET['id'])?Validation::id($_GET,'id'):null;View::render('executives/positions/edit',PositionService::editor($id),$id?'Edit executive position':'Add executive position');}
  else{View::render('executives/positions/index',PositionService::listing($_GET),'Executive positions');}
 }
 public static function save(): never {
  Auth::requireRole(['admin','registrar']);$id=Validation::text($_POST,'id',20,false)===null?null:Validation::id($_POST,'id');
  try{PositionService::save($_POST);unset($_SESSION['executive_position_form']);\flash('success','Executive position saved.');\redirect('executive_positions');}
  catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());}
  catch(\PDOException $error){error_log((string)$error);\flash('danger',($error->errorInfo[1]??0)===1062?'This position title already exists. Edit the existing entry.':'The position could not be saved. No changes were made. Please try again.');}
  $values=[];foreach(['id','version','name','organization_level','description','display_order','is_active'] as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$values[$field]=mb_substr((string)$_POST[$field],0,1000);}}
  $values['levels']=[];foreach((is_array($_POST['levels']??null)?$_POST['levels']:[]) as $level){if(is_string($level)&&array_key_exists($level,PositionService::LEVELS)){$values['levels'][]=$level;}}
  $_SESSION['executive_position_form']=$values;\redirect('executive_position_edit',$id?['id'=>$id]:[]);
 }
}
