<?php
declare(strict_types=1);
namespace App;

final class DirectoryUi
{
 public static function chips(string $route,array $filters,array $labels,array $choices=[]): array {
  $chips=[];
  foreach($labels as $key=>$label){
   if(!isset($filters[$key])||(string)$filters[$key]===''){continue;}
   $remaining=$filters;unset($remaining[$key],$remaining['p'],$remaining['edit']);
   $value=$choices[$key][(string)$filters[$key]]??(string)$filters[$key];
   $chips[]=['text'=>$label.': '.$value,'href'=>url($route,$remaining)];
  }
  return $chips;
 }
}