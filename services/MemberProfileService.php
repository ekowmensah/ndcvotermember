<?php
declare(strict_types=1);
namespace App;
final class MemberProfileService
{
 public const TEXT_FIELDS=['profession'=>150,'job_title'=>150,'employer'=>180,'skills'=>500,'languages'=>250,'volunteer_interests'=>500];
 public const EDUCATION=['none'=>'No formal education','primary'=>'Primary','junior_secondary'=>'Junior secondary / JHS','senior_secondary'=>'Senior secondary / SHS','vocational'=>'Vocational / technical','diploma'=>'Diploma / certificate','bachelors'=>'Bachelor’s degree','masters'=>'Master’s degree','doctorate'=>'Doctorate','other'=>'Other'];
 public const EMPLOYMENT=['employed'=>'Employed','self_employed'=>'Self-employed','unemployed'=>'Unemployed','student'=>'Student','retired'=>'Retired','other'=>'Other'];
 public const AVAILABILITY=['flexible'=>'Flexible','weekdays'=>'Weekdays','weekends'=>'Weekends','evenings'=>'Evenings','unavailable'=>'Not available'];
 public static function choices(): array {return ['education_level'=>self::EDUCATION,'employment_status'=>self::EMPLOYMENT,'availability'=>self::AVAILABILITY];}
 public static function fields(): array {return array_merge(array_keys(self::TEXT_FIELDS),array_keys(self::choices()));}
 public static function ready(): bool {
  static $ready=null;
  return $ready??=(VoterService::officialIdsReady()&&(int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='member_profiles' AND column_name IN ('member_id','profession','education_level','employment_status','job_title','employer','skills','languages','volunteer_interests','availability','updated_by_user_id','updated_at')")===12);
 }
 public static function normalize(array $input): ?array {
  // Older open forms do not contain profile controls; keep existing details intact.
  if(!isset($input['member_profile_present'])){return null;}
  if(!self::ready()){throw new \InvalidArgumentException('Apply migration 08, then database/11_member_profiles_mysql8.sql in phpMyAdmin to save member background details.');}
  $values=[];
  foreach(self::TEXT_FIELDS as $field=>$max){$text=Validation::text($input,$field,$max,false);$values[$field]=$text===null?null:preg_replace('/\s+/u',' ',$text);}
  foreach(self::choices() as $field=>$options){$value=Validation::text($input,$field,32,false);if($value!==null&&!array_key_exists($value,$options)){throw new \InvalidArgumentException('Choose a valid '.label($field).'.');}$values[$field]=$value;}
  return $values;
 }
 public static function record(int $membership): ?array {
  return self::ready()?Database::one('SELECT * FROM member_profiles WHERE member_id=?',[$membership]):null;
 }
 // Called only inside the existing membership transaction and permission checks.
 public static function save(int $membership,?array $values): void {
  if($values===null){return;}
  $before=self::record($membership);$keys=array_flip(self::fields());$old=$before?array_intersect_key($before,$keys):[];
  if(!array_filter($values,static fn($value)=>$value!==null)){
   if($before){Database::execute('DELETE FROM member_profiles WHERE member_id=?',[$membership]);Auth::audit('member.profile_cleared','member',$membership,['before'=>$old]);}return;
  }
  $fields=array_keys($values);$columns=implode(',',$fields);$placeholders=implode(',',array_fill(0,count($fields),'?'));
  $updates=implode(',',array_map(static fn($field)=>$field.'=?',$fields));$actor=Auth::user()['id'];
  Database::execute("INSERT INTO member_profiles(member_id,$columns,updated_by_user_id) VALUES(?,$placeholders,?) ON DUPLICATE KEY UPDATE $updates,updated_by_user_id=?,updated_at=CURRENT_TIMESTAMP(6)",array_merge([$membership],array_values($values),[$actor],array_values($values),[$actor]));
  if($old!=$values){Auth::audit('member.profile_saved','member',$membership,['before'=>$old,'after'=>$values]);}
 }
 public static function sqlColumns(): string {
  $ready=self::ready();return implode('',array_map(static fn($field)=>','.($ready?'mp.'.$field:'NULL').' AS '.$field,self::fields()));
 }
 public static function sqlJoin(): string {return self::ready()?' LEFT JOIN member_profiles mp ON mp.member_id=m.id':'';}
}
