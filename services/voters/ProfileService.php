<?php
declare(strict_types=1);
namespace App\Voters;

use App\Auth;
use App\VoterService;

final class ProfileService
{
 public static function present(array $voter): array {
  $values=VoterService::values($voter);$canManage=Auth::canManage();
  $editUrl=\url('voter',['id'=>$voter['id'],'edit'=>1]);
  $display=static fn($value): string=>trim((string)($value??''))!==''?(string)$value:'Not recorded';
  $personal=['First name'=>$display($values['first_name']??null),'Last name'=>$display($values['last_name']??null),
   'Gender'=>match($values['gender']??'unknown'){'female'=>'Female','male'=>'Male','other'=>'Other',default=>'Not recorded'},
   'Date of birth'=>!empty($values['date_of_birth'])?\date_display($values['date_of_birth']):'Not recorded'];
  $residence=['Address / house or street'=>$display($values['address']??null),'Town'=>$display($values['town']??null),
   'District'=>$display($values['residential_district']??null),'Residential region'=>$display($values['residential_region']??null)];
  $electoral=['Electoral region'=>$display($values['region']??null),'Constituency'=>$display($values['constituency']??null),
   'Polling station'=>$display($values['polling_station']??null),'Official polling station ID'=>$display($values['polling_station_code']??null),
   'Electoral area / branch'=>!empty($values['electoral_area'])?$values['electoral_area']:'Branch not assigned'];
  $suggestions=[];
  if($canManage){
   if(empty($voter['has_photo'])){$suggestions[]=['Add a photo','#voter-personal'];}
   if(empty($values['first_name'])||empty($values['last_name'])){$suggestions[]=['Complete first and last names','#voter-personal'];}
   if(empty($values['phone'])&&empty($values['email'])){$suggestions[]=['Add a contact method','#voter-contact'];}
   if(empty($values['town'])){$suggestions[]=['Record a residential town','#voter-residence'];}
   if(empty($voter['polling_station_id'])){$suggestions[]=['Assign a polling station','#voter-electoral'];}
  }
  return compact('voter','values','canManage','editUrl','display','personal','residence','electoral','suggestions');
 }
}
