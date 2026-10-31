<?php
declare(strict_types=1);
namespace App\Membership;
use App\{Auth,Database,HttpException,Validation,VoterService,MemberProfileService};
final class EnrollmentWriter
{
 // Internal writer: authorized caller must own the transaction. Not a public route.
 public static function create(int $voter,array $input,?int $nomination=null): int {
  if(!Database::connection()->inTransaction()){throw new \LogicException('Enrollment requires a caller-owned transaction.');}
  $joined=Validation::date($input,'joined_on',true);$status=Validation::choice($input,'status',['active','inactive']);
  $official=Validation::text($input,'official_membership_id',64,false);
  if($official!==null&&(!VoterService::officialIdsReady()||preg_match('/[\x00-\x1F\x7F]/',$official))){throw new \InvalidArgumentException('Supply a valid Party ID and complete migration 08 first.');}
  $profile=MemberProfileService::normalize($input);
  $person=Database::one('SELECT * FROM voters WHERE id=? FOR UPDATE',[$voter])??throw new HttpException(404,'Voter not found.');\App\Voters\TrashService::check($person);
  $existing=Database::scalar('SELECT id FROM members WHERE voter_record_id=?',[$voter]);if($existing){return (int)$existing;}
  Database::execute('INSERT INTO members(voter_record_id,joined_on,status,marked_by_user_id) VALUES(?,?,?,?)',[$voter,$joined,$status,Auth::user()['id']]);
  $id=(int)Database::connection()->lastInsertId();$number='MEM-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
  Database::execute('UPDATE members SET member_number=? WHERE id=?',[$number,$id]);
  if(VoterService::officialIdsReady()){Database::execute('UPDATE members SET official_membership_id=? WHERE id=?',[$official,$id]);}
  MemberProfileService::save($id,$profile);Database::execute('UPDATE voters SET updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$voter]);
  if(BranchWorkflow::ready()){
   $pending=Database::all("SELECT id FROM membership_nominations WHERE voter_record_id=? AND status='pending' AND id<>? FOR UPDATE",[$voter,$nomination??0]);
   foreach($pending as $row){Database::execute("UPDATE membership_nominations SET status='superseded',membership_id=?,decided_by_user_id=?,decided_at=UTC_TIMESTAMP(6),revision=revision+1 WHERE id=?",[$id,Auth::user()['id'],$row['id']]);Auth::audit('membership.nomination_superseded','membership_nomination',(int)$row['id']);}
  }
  Auth::audit('member.marked','member',$id,['voter_record_id'=>$voter,'member_number'=>$number,'official_membership_id'=>$official,'joined_on'=>$joined,'status'=>$status]);return $id;
 }
}
