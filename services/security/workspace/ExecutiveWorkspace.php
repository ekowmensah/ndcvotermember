<?php
declare(strict_types=1);
namespace App\Security\Workspace;
use App\{Auth,Database,HttpException};
use App\Security\{ExecutiveAccess,AppointmentAccounts};
use App\Membership\BranchWorkflow;
final class ExecutiveWorkspace
{
 public static function overview(): array {
  $user=Auth::requireLogin();$access=ExecutiveAccess::account();
  if(!$access){throw new HttpException(403,'Executive workspace access is required.');}
  $scope=ExecutiveAccess::condition();
  $totals=array_map('intval',Database::one("SELECT COUNT(*) AS voters,COUNT(m.id) AS members,COALESCE(SUM(m.id IS NULL),0) AS nonmembers,COALESCE(SUM(m.status='active'),0) AS active_members FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id WHERE v.deleted_at IS NULL AND ($scope)")??[]);
  $coverage=$totals['voters']?round(100*$totals['members']/$totals['voters'],1):0;
  $eligible=(bool)Database::scalar("SELECT m.id FROM members m JOIN voters v ON v.id=m.voter_record_id WHERE m.id=? AND m.status='active' AND v.is_deceased=0 AND v.deleted_at IS NULL",[$access['member_id']]);
  $managed=!empty($access['preset_managed']);
  $grantJoin=$managed?' LEFT JOIN executive_appointment_access g ON g.appointment_id=a.id AND g.user_id='.(int)$user['id']:'';
  $grantColumns=$managed?",g.access_role AS office_role,g.enabled AS office_enabled,g.can_contacts,g.can_edit_voter_contacts,g.can_nominate,g.can_approve_membership":",NULL AS office_role,1 AS office_enabled";
  $offices=Database::all('SELECT a.id,a.position_name_snapshot,a.organization_level,a.organization_scope,a.jurisdiction_snapshot,a.starts_on,a.expected_ends_on'.$grantColumns.' FROM executive_appointments a'.$grantJoin.' WHERE a.member_id=? AND a.ended_on IS NULL AND a.cancelled_at IS NULL ORDER BY (a.starts_on>UTC_DATE()),a.starts_on DESC,a.id DESC LIMIT 8',[$access['member_id']]);
  $wings=\App\Membership\WingService::ready()?array_column(Database::all('SELECT wing_code FROM member_wing_enrollments WHERE member_id=? AND ended_on IS NULL',[$access['member_id']]),'wing_code'):[];
  foreach($offices as &$office){
   $office['state']=$office['starts_on']>gmdate('Y-m-d')?'scheduled':(!$access['enabled']||!$eligible||!$office['office_enabled']?'unavailable':'current');
   $office['office_role']=$office['office_role']??($managed?'viewer':$access['access_role']);
   if($office['state']==='current'&&$office['organization_scope']!=='main'&&!in_array($office['organization_scope'],$wings,true)){$office['state']='unavailable';}
  }unset($office);
  $officeTotal=(int)Database::scalar('SELECT COUNT(*) FROM executive_appointments WHERE member_id=? AND ended_on IS NULL AND cancelled_at IS NULL',[$access['member_id']]);
  $permissions=['nominate'=>BranchWorkflow::permitted('can_nominate'),'edit'=>BranchWorkflow::permitted('can_edit_voter_contacts'),'approve'=>BranchWorkflow::permitted('can_approve_membership')];
  // Hide unusable actions for manual accounts whose stored grants outlive an office.
  foreach(['nominate'=>['branch','polling_station'],'edit'=>['branch'],'approve'=>['constituency']] as $key=>$levels){
   if(!$permissions[$key]){continue;}$cap=['nominate'=>'can_nominate','edit'=>'can_edit_voter_contacts','approve'=>'can_approve_membership'][$key];
   $permissions[$key]=false;
   foreach($levels as $level){foreach($offices as $office){if($office['state']==='current'&&$office['organization_scope']==='main'&&$office['organization_level']===$level&&(!$managed||($office['office_role']==='reviewer'&&!empty($office[$cap])))){$permissions[$key]=true;break 2;}}}
   // The office cards are only a display limit; check all offices for actions.
   if(!$permissions[$key]&&$officeTotal>count($offices)){
    $marks=implode(',',array_fill(0,count($levels),'?'));$extra=$managed?' JOIN executive_appointment_access g ON g.appointment_id=a.id AND g.user_id='.(int)$user['id']." AND g.enabled=1 AND g.access_role='reviewer' AND g.$cap=1":'';
    $permissions[$key]=(bool)($access['enabled']&&$eligible&&Database::scalar("SELECT a.id FROM executive_appointments a$extra WHERE a.member_id=? AND a.organization_scope='main' AND a.organization_level IN ($marks) AND a.starts_on<=UTC_DATE() AND a.ended_on IS NULL AND a.cancelled_at IS NULL LIMIT 1",[$access['member_id'],...$levels]));
   }
  }
  $nominations=BranchWorkflow::workspaceData();
  return compact('user','access','totals','coverage','offices','officeTotal','permissions','nominations','managed','eligible');
 }
}
