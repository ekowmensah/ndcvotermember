<?php
declare(strict_types=1);
namespace App\Membership;
use App\{Auth,Validation};
final class BranchWorkflowController
{
 public static function get(string $page): void {
  Auth::requireLogin();
  if($page==='branch_voter_edit'){BranchWorkflow::editPage(Validation::id($_GET,'id'));}
  elseif($page==='membership_nomination'){BranchWorkflow::review(Validation::id($_GET,'id'));}
  else{BranchWorkflow::listing($_GET);}
 }
 public static function post(string $action): never {
  Auth::requireLogin();
  try{
   if($action==='branch_contact_save'){BranchWorkflow::saveContact($_POST);\flash('success','Voter contact and residential details updated.');}
   elseif($action==='membership_nominate'){BranchWorkflow::nominate($_POST);\flash('success','Membership nomination submitted. The person remains a voter until approved.');}
   else{BranchWorkflow::decide($_POST);\flash('success','Membership nomination decision saved.');}
  }catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());}
  catch(\PDOException $error){error_log((string)$error);\flash('danger',($error->errorInfo[1]??0)===1062?'A pending nomination, voter ID or Party ID already exists. No changes were made.':'Unable to save. No changes were made. Refresh and review again.');}
  if($action==='branch_contact_save'){\redirect('branch_voter_edit',['id'=>Validation::id($_POST,'voter_id')]);}
  if($action==='membership_nominate'){\redirect('voter',['id'=>Validation::id($_POST,'voter_id')]);}
  \redirect('membership_nomination',['id'=>Validation::id($_POST,'id')]);
 }
}
