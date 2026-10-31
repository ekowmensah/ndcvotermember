<?php
declare(strict_types=1);
namespace App\People;
use App\{Auth,Database,Validation,Repository};
use App\Voters\TrashService;
final class LifeStatusService
{
 public static function ready(): bool {
  static $ready=null;
  return $ready??=((int)Database::scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='voters' AND column_name IN ('is_deceased','date_of_death')")===2);
 }
 public static function page(int $id): array {
  Auth::requireRole(['admin','registrar']);$person=Repository::voter($id);$available=self::ready();return compact('person','available');
 }
 public static function save(array $input): void {
  Auth::requireRole(['admin','registrar']);
  if(!self::ready()){throw new \InvalidArgumentException('Import database/14_deceased_status_mysql8.sql in phpMyAdmin first.');}
  $id=Validation::id($input,'id');$status=Validation::choice($input,'life_status',['not_deceased','deceased']);
  $date=Validation::date($input,'date_of_death');
  if($status!=='deceased'&&$date!==null){throw new \InvalidArgumentException('Clear the date of death when correcting a deceased status.');}
  Database::transaction(function()use($input,$id,$status,$date):void{
   $before=Database::one('SELECT * FROM voters WHERE id=? FOR UPDATE',[$id])??throw new \InvalidArgumentException('Person not found.');TrashService::check($before);
   if(Validation::text($input,'version',30)!==$before['updated_at']){throw new \InvalidArgumentException('This person changed. Review their latest record before updating the status.');}
   $member=Database::one('SELECT id FROM members WHERE voter_record_id=? FOR UPDATE',[$id]);
   if((string)($member['id']??'')!==(string)($input['membership_id']??'')){throw new \InvalidArgumentException('Membership changed. Open the current profile and try again.');}
   if($date&&!empty($before['date_of_birth'])&&$date<$before['date_of_birth']){throw new \InvalidArgumentException('Date of death cannot precede date of birth.');}
   $deceased=$status==='deceased'?1:0;
   if((int)$before['is_deceased']===$deceased&&($before['date_of_death']??null)===$date){return;}
   Database::execute('UPDATE voters SET is_deceased=?,date_of_death=?,updated_at=CURRENT_TIMESTAMP(6) WHERE id=?',[$deceased,$date,$id]);
   Auth::audit('person.life_status_updated','voter',$id,['before'=>['is_deceased'=>(int)$before['is_deceased'],'date_of_death'=>$before['date_of_death']],'after'=>['is_deceased'=>$deceased,'date_of_death'=>$date],'membership_id'=>$member['id']??null]);
  });
 }
}
