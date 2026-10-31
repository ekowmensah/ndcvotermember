<?php
declare(strict_types=1);
namespace App\Security\Workspace;
use App\{Auth,Database,Validation,PhotoService,VoterService,AddressService,MemberProfileService};
use App\Security\ExecutiveAccess;
use App\Membership\BranchWorkflow;
final class ExecutivePeople
{
 private static function from(): string {return ' FROM voters v LEFT JOIN members m ON m.voter_record_id=v.id LEFT JOIN polling_stations ps ON ps.id=v.polling_station_id LEFT JOIN constituencies c ON c.id=ps.constituency_id LEFT JOIN regions r ON r.id=c.region_id LEFT JOIN electoral_areas b ON b.id=ps.electoral_area_id';}
 public static function listing(array $input): array {
  Auth::requireLogin();$members=($input['page']??'')==='members';$route=$members?'members':'voters';$filters=[];$params=[];
  $base='v.deleted_at IS NULL AND ('.ExecutiveAccess::condition().')'.($members?' AND m.id IS NOT NULL':'');$where=$base;
  $q=Validation::text($input,'q',100,false)??'';
  if($q!==''){$filters['q']=$q;$term='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where.=" AND (v.full_name LIKE ? ESCAPE '!' OR v.voter_id LIKE ? ESCAPE '!' OR m.official_membership_id LIKE ? ESCAPE '!' OR m.member_number LIKE ? ESCAPE '!')";$params=[$term,$term,$term,$term];}
  foreach(['gender'=>['v.gender',['female','male','other','unknown']],'life_status'=>['v.is_deceased',['0','1']]] as $key=>[$column,$allowed]){if(Validation::text($input,$key,32,false)!==null){$filters[$key]=Validation::choice($input,$key,$allowed);$where.=" AND $column=?";$params[]=$filters[$key];}}
  if($members&&Validation::text($input,'status',16,false)!==null){$filters['status']=Validation::choice($input,'status',['active','inactive']);$where.=' AND m.status=?';$params[]=$filters['status'];}
  if(!$members&&Validation::text($input,'membership',20,false)!==null){$filters['membership']=Validation::choice($input,'membership',['member','nonmember']);$where.=$filters['membership']==='member'?' AND m.id IS NOT NULL':' AND m.id IS NULL';}
  foreach(['constituency'=>'c.id','branch'=>'b.id','station'=>'ps.id'] as $key=>$column){if(Validation::text($input,$key,20,false)!==null){$filters[$key]=Validation::id($input,$key);$where.=" AND $column=?";$params[]=$filters[$key];}}
  $photo=PhotoService::ready()?'EXISTS(SELECT 1 FROM voter_photos vp WHERE vp.voter_record_id=v.id)':'0';
  if(Validation::text($input,'photo',16,false)!==null){$filters['photo']=Validation::choice($input,'photo',['with','without']);$where.=' AND ('.$photo.')='.($filters['photo']==='with'?'1':'0');}
  $named=VoterService::namesReady();$sorts=['name'=>'v.full_name,v.id','newest'=>$members?'m.created_at DESC,m.id DESC':'v.created_at DESC,v.id DESC'];if($named){$sorts+=['last_name'=>'v.last_name IS NULL,v.last_name,v.first_name,v.id','first_name'=>'v.first_name IS NULL,v.first_name,v.last_name,v.id'];}
  $filters['sort']=Validation::choice(['sort'=>$input['sort']??'name'],'sort',array_keys($sorts));$filters['per_page']=(int)Validation::choice(['per_page'=>$input['per_page']??'25'],'per_page',['25','50','100']);
  $summary=array_map('intval',Database::one("SELECT COUNT(*) AS total,COUNT(m.id) AS members,COALESCE(SUM(m.id IS NULL),0) AS nonmembers,COALESCE(SUM(v.is_deceased=1),0) AS deceased,COALESCE(SUM($photo),0) AS with_photo".self::from().' WHERE '.$where,$params)??[]);
  $total=$summary['total'];$perPage=$filters['per_page'];$last=max(1,(int)ceil($total/$perPage));$p=min($last,max(1,(int)($input['p']??1)));$offset=($p-1)*$perPage;
  $names=$named?'v.first_name,v.last_name':'NULL AS first_name,NULL AS last_name';
  $rows=Database::all("SELECT v.id,v.voter_id,v.full_name,$names,v.gender,v.is_deceased,m.id AS membership_id,m.member_number,m.official_membership_id,m.status AS membership_status,r.name AS region,c.name AS constituency,b.name AS branch,ps.name AS station,ps.station_code,($photo) AS has_photo".self::from().' WHERE '.$where.' ORDER BY '.$sorts[$filters['sort']]." LIMIT $perPage OFFSET $offset",$params);
  $memberExecutiveRoles=$members?\App\Executives\AppointmentService::memberDirectoryRoles(array_column($rows,'membership_id')):[];
  // Choices derive only from permitted records, not the entire location directory.
  $locations=Database::all('SELECT DISTINCT c.id AS constituency_id,c.name AS constituency,b.id AS branch_id,b.name AS branch,ps.id AS station_id,ps.name AS station,ps.station_code'.self::from().' WHERE '.$base.' AND ps.id IS NOT NULL ORDER BY c.name,b.name,ps.name,ps.id');
  $choices=['constituency'=>[],'branch'=>[],'station'=>[]];foreach($locations as $location){foreach(array_keys($choices) as $key){if($location[$key.'_id']){$choices[$key][$location[$key.'_id']]=$key==='station'?($location['station_code']!==null&&$location['station_code']!==''?$location['station_code'].' · ':'').$location['station']:$location[$key];}}}
  $pagination=['total'=>$total,'per_page'=>$perPage,'page'=>$p,'last'=>$last,'route'=>$route,'filters'=>$filters];
  return compact('members','route','filters','q','rows','summary','choices','pagination','sorts','memberExecutiveRoles');
 }
 public static function profile(array $person): array {
  $id=(int)$person['id'];$contacts=ExecutiveAccess::contacts($id);$review=ExecutiveAccess::canReview($id);
  $canEdit=BranchWorkflow::canOnPerson($id,'can_edit_voter_contacts','branch')&&$contacts;
  $canNominate=BranchWorkflow::canOnPerson($id,'can_nominate','nomination');$member=(bool)$person['membership_id'];
  $background=$member&&MemberProfileService::ready()?Database::one('SELECT profession,education_level,employment_status,job_title,employer,skills,languages,volunteer_interests,availability FROM member_profiles WHERE member_id=?',[$person['membership_id']]):null;
  if($background&&!array_filter($background,static fn($value)=>$value!==null&&$value!=='')){$background=null;}
  $canApprove=BranchWorkflow::canOnPerson($id,'can_approve_membership','constituency');
  $nomination=null;if(($canNominate||$canApprove)&&BranchWorkflow::ready()){$nomination=Database::one("SELECT id,created_at,nominated_by_user_id FROM membership_nominations WHERE voter_record_id=? AND status='pending'",[$id]);}
  $back=$member?'members':'voters';
  return compact('person','contacts','review','canEdit','canNominate','canApprove','member','background','nomination','back');
 }
}
