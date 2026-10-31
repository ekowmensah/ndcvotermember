<?php
declare(strict_types=1);
namespace App\Security;
use App\{Auth,Validation,View};
final class StaffController
{
 public static function get(bool $edit=false): void {
  Auth::requireRole(['admin']);
  if($edit){SessionSecurity::guard('staff_save',$_GET);}
  if($edit){$id=empty($_GET['id'])?null:Validation::id($_GET,'id');View::render('security/staff/edit',StaffService::editor($id),$id?'Edit staff account':'Add staff account');}
  else{View::render('security/staff/index',StaffService::listing($_GET),'Staff accounts');}
 }
 public static function save(): never {
  Auth::requireRole(['admin']);
  try{StaffService::save($_POST);\flash('success','Staff account saved. Existing sessions are revoked when an account is updated or signed out.');}
  catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());}
  catch(\PDOException $error){error_log((string)$error);\flash('danger',($error->errorInfo[1]??0)===1062?'That email or member already has a login account.':'The account could not be saved. No changes were made.');}
  // Never retain passwords in session recovery or URLs.
  \redirect('staff');
 }
}
