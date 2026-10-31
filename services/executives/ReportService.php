<?php
declare(strict_types=1);
namespace App\Executives;
use App\{Auth,Database,Validation};
final class ReportService
{
 public static function prepare(array $input): array {
  Auth::requireLogin();$kind=Validation::choice($input,'kind',['appointments','history']);
  return Database::transaction(function()use($input,$kind):array{
   $data=$kind==='history'?HistoryService::listing($input,true):AppointmentService::listing($input,true);
   if(!$data['available']){throw new \InvalidArgumentException('Complete executive migrations through 18 first.');}
   $columns=['Appointment #','Name','Party ID','Sys No.','Position','Level','Jurisdiction','Appointment type','Start date','Actual end','Expected end','State','End reason','Leadership term','Organization'];$records=[];
   foreach($data['rows'] as $row){
    $state=!empty($row['cancelled_at'])?'Cancelled — not served':(!empty($row['ended_on'])?'Ended':($row['starts_on']>gmdate('Y-m-d')?'Scheduled':'Current'));
    $records[]=[(string)$row['id'],$row['member_name_snapshot']??$row['full_name'],$row['official_membership_id']??'',$row['member_number'],$row['position_name_snapshot'],PositionService::LEVELS[$row['organization_level']],$row['jurisdiction_snapshot'],AppointmentService::TYPES[$row['appointment_type']],$row['starts_on'],$row['ended_on']??'',$row['expected_ends_on']??'',$state,LifecycleService::REASONS[$row['end_reason']??'']??($row['end_reason']??''),$row['leadership_term_name_snapshot']??'',OrganizationService::label($row)];
   }
   $labels=['organization_scope'=>'Organization','q'=>'Search','state'=>'Appointment state','level'=>'Level','type'=>'Appointment type','reason'=>'End reason','year'=>'Served during year','sort'=>'Order','position_id'=>'Position','region_id'=>'Electoral region','constituency_id'=>'Constituency','branch_id'=>'Branch','polling_station_id'=>'Polling station','leadership_term_id'=>'Administration','review'=>'Expected end review','active_people'=>'People records','residential_region_filter'=>'Residential region','residential_district_filter'=>'Residential district'];
   $choices=['organization_scope'=>OrganizationService::SCOPES,'state'=>['current'=>'Current','scheduled'=>'Scheduled','all_open'=>'All open','ended'=>'Completed','cancelled'=>'Cancelled'],'level'=>PositionService::LEVELS,'type'=>AppointmentService::TYPES,'reason'=>LifecycleService::REASONS,'sort'=>['latest'=>'Latest completed first','oldest'=>'Oldest completed first','name'=>'Historical name','position'=>'Historical position'],'review'=>['due'=>'Within 30 days','overdue'=>'Overdue'],'active_people'=>['1'=>'Active voter records'],'position_id'=>array_column($data['positions'],'name','id'),'leadership_term_id'=>['unassigned'=>'Not assigned']+array_column($data['leadershipTerms'],'name','id')];
   foreach(['region_id'=>'regions','constituency_id'=>'constituencies','branch_id'=>'electoral_areas','polling_station_id'=>'polling_stations'] as $key=>$group){$choices[$key]=array_column($data['catalog'][$group]??[],'name','id');}
   $choices=array_replace($choices,$data['scopeChoices']);$criteria=[];foreach($labels as $key=>$label){if(isset($data['filters'][$key])){$value=$data['filters'][$key];$criteria[$label]=$choices[$key][$value]??(string)$value;}}
   return ['title'=>$kind==='history'?'Executive service history':'Executive appointments','kind'=>$kind,'columns'=>$columns,'records'=>$records,'criteria'=>$criteria,'total'=>$data['total'],'generated_at'=>gmdate('d M Y H:i').' UTC','notice'=>'Appointment records and service history. Cancelled reservations are labelled separately from served terms.','back_url'=>\url($kind==='history'?'executive_history':'executive_appointments',$data['filters'])];
  });
 }
 public static function csv(array $input): never {
  Auth::requireRole(['admin','registrar']);$report=self::prepare($input);\App\Reporting\ReportOutput::csv($report,'executive-'.$report['kind']);
 }
 public static function print(array $input): void {\App\Reporting\ReportOutput::print(self::prepare($input));}
}