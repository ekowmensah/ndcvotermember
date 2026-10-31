<?php
declare(strict_types=1);
namespace App\Membership;
use App\{AddressService,Database,Validation};

final class WingScopeService
{
 // Shared by wing dashboards and drill-down directories. Alias v is the person.
 public static function apply(array $input,array &$filters,string &$where,array &$params): void {
  $region=null;
  foreach(['residential_region_filter'=>'residential_region_id','residential_district_filter'=>'residential_district_id'] as $key=>$column){
   if(Validation::text($input,$key,20,false)===null){continue;}
   if(!AddressService::ready()){throw new \InvalidArgumentException('Complete residential migrations 09–10 before using residential scope.');}
   $id=Validation::id($input,$key);
   if($key==='residential_region_filter'){
    if(!Database::scalar('SELECT id FROM residential_regions WHERE id=?',[$id])){throw new \InvalidArgumentException('The residential region no longer exists.');}
    $region=$id;
   }else{
    $parent=Database::scalar('SELECT region_id FROM districts WHERE id=?',[$id]);
    if($parent===null||($region!==null&&(int)$parent!==$region)){throw new \InvalidArgumentException('Choose a residential district in the selected region.');}
   }
   $filters[$key]=$id;$where.=" AND v.$column=?";$params[]=$id;
  }
 }
 public static function choices(array $filters): array {
  $choices=[];
  foreach(['residential_region_filter'=>'residential_regions','residential_district_filter'=>'districts'] as $key=>$table){
   if(isset($filters[$key])){$choices[$key]=[$filters[$key]=>Database::scalar("SELECT name FROM $table WHERE id=?",[$filters[$key]])??'Unknown location'];}
  }
  return $choices;
 }
}
