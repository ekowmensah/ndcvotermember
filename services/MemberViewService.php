<?php
declare(strict_types=1);
namespace App;

final class MemberViewService
{
 public static function profile(int $id,bool $forEdit=false): array {
  $voter=Repository::member($id);$values=VoterService::values($voter);
  $canManage=Auth::canManage();$officialReady=VoterService::officialIdsReady();
  $profileReady=MemberProfileService::ready();$addressReady=AddressService::ready();
  $memberForm=['joined_on'=>$voter['joined_on'],'status'=>$voter['membership_status'],'official_membership_id'=>$voter['official_membership_id']??''];
  $memberForm=array_replace($memberForm,array_intersect_key($voter,array_flip(MemberProfileService::fields())));
  $personForm=$values;$recovered=false;$failedSubmission=false;
  if($forEdit&&isset($_SESSION['member_update_form'])){
   $saved=$_SESSION['member_update_form'];unset($_SESSION['member_update_form']);
   $failedSubmission=$canManage&&is_array($saved)&&(int)($saved['membership_id']??0)===(int)$voter['membership_id'];
   if($failedSubmission&&($saved['membership_version']??null)===($voter['membership_version']??null)
      &&(!isset($saved['member_person_present'])||($saved['voter_version']??null)===$voter['updated_at'])){
    $memberForm=array_replace($memberForm,$saved);$recovered=true;
   }
  }
  if($forEdit&&isset($_SESSION['member_person_form'])){
   $saved=json_decode(Crypto::decrypt($_SESSION['member_person_form']),true);unset($_SESSION['member_person_form']);
   if($recovered&&is_array($saved)&&(int)($saved['membership_id']??0)===(int)$voter['membership_id']
      &&(int)($saved['voter_record_id']??0)===(int)$voter['id']&&($saved['voter_version']??null)===$voter['updated_at']
      &&($saved['membership_version']??null)===($voter['membership_version']??null)){$personForm=array_replace($personForm,$saved);}
  }

  $metadata=Database::one('SELECT m.created_at,u.name AS enrolled_by FROM members m LEFT JOIN app_users u ON u.id=m.marked_by_user_id WHERE m.id=?',[$id])??[];
  $activity=$canManage?Database::all("SELECT a.action_code,a.occurred_at,u.name AS actor_name FROM audit_events a LEFT JOIN app_users u ON u.id=a.actor_user_id WHERE a.entity_type='member' AND a.entity_id=? AND a.action_code IN ('member.marked','member.updated','member.profile_saved','member.profile_cleared') ORDER BY a.occurred_at DESC,a.id DESC LIMIT 6",[$id]):[];
  $activityLabels=['member.marked'=>'Membership created','member.updated'=>'Member details updated','member.profile_saved'=>'Background details saved','member.profile_cleared'=>'Background details cleared'];
  $display=static fn($value): string=>isset($value)&&trim((string)$value)!==''?(string)$value:'Not recorded';
  $age=null;
  if(!empty($values['date_of_birth'])){
   $birth=\DateTimeImmutable::createFromFormat('!Y-m-d',$values['date_of_birth']);
   $today=new \DateTimeImmutable('today');
   if($birth&&$birth<=$today){$age=$birth->diff($today)->y;}
  }
  $personal=[
   'First name'=>$display($values['first_name']??null),'Last name'=>$display($values['last_name']??null),
   'Gender'=>match($values['gender']??'unknown'){'female'=>'Female','male'=>'Male','other'=>'Other',default=>'Not recorded'},
   'Date of birth'=>!empty($values['date_of_birth'])?date_display($values['date_of_birth']).($age!==null?' · '.$age.' years':''):'Not recorded',
  ];
  $residence=['Address / house or street'=>$display($values['address']??null),'Town'=>$display($values['town']??null),
   'District'=>$display($values['residential_district']??null),'Residential region'=>$display($values['residential_region']??null)];
  $electoral=['Electoral region'=>$display($values['region']??null),'Constituency'=>$display($values['constituency']??null),
   'Polling station'=>$display($values['polling_station']??null),'Official polling station ID'=>$display($values['polling_station_code']??null),
   'Electoral area / branch'=>!empty($values['electoral_area'])?$values['electoral_area']:'Branch not assigned'];
  $background=['Profession'=>$display($voter['profession']??null),
   'Highest education'=>MemberProfileService::EDUCATION[$voter['education_level']??'']??'Not recorded',
   'Employment status'=>MemberProfileService::EMPLOYMENT[$voter['employment_status']??'']??'Not recorded',
   'Job title'=>$display($voter['job_title']??null),'Employer / organization'=>$display($voter['employer']??null),
   'Volunteer availability'=>MemberProfileService::AVAILABILITY[$voter['availability']??'']??'Not recorded'];
  $backgroundTags=[];
  foreach(['skills'=>'Skills & expertise','languages'=>'Languages','volunteer_interests'=>'Volunteer interests'] as $field=>$title){
   $parts=preg_split('/[,;\r\n]+/u',(string)($voter[$field]??''),-1,PREG_SPLIT_NO_EMPTY)?:[];
   $backgroundTags[$title]=array_values(array_unique(array_filter(array_map('trim',$parts),static fn($item)=>$item!=='')));
  }
  $voterUrl=url('voter',['id'=>$voter['id']]);$editUrl=url('member_edit',['id'=>$id]).'#member-edit';
  $personEditUrl=url('member_edit',['id'=>$id]);
  $suggestions=[];
  if($canManage){
   if(empty($voter['has_photo'])){$suggestions[]=['Add a profile photo',$personEditUrl.'#voter-personal'];}
   if(empty($values['phone'])&&empty($values['email'])){$suggestions[]=['Add a contact method',$personEditUrl.'#voter-contact'];}
   if($officialReady&&trim((string)($voter['official_membership_id']??''))===''){$suggestions[]=['Record the official party ID',$editUrl];}
   if($profileReady&&empty($voter['profession'])&&empty($voter['education_level'])){$suggestions[]=['Add profession or education',$editUrl];}
   if($addressReady&&empty($values['town'])){$suggestions[]=['Add a residential town',$personEditUrl.'#voter-residence'];}
  }
  return compact('voter','values','personForm','canManage','officialReady','profileReady','addressReady','memberForm','recovered','metadata','activity','activityLabels','personal','residence','electoral','background','backgroundTags','voterUrl','editUrl','personEditUrl','suggestions','display');
 }
}
