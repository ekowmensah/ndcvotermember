<?php
declare(strict_types=1);
namespace App\Membership;
use App\{Auth,Database,Validation};
use App\Reporting\ReportOutput;

final class WingReportService
{
 public static function prepare(array $input): array {
  Auth::requireLogin();$kind=Validation::choice($input,'kind',['enrollments','candidates','history']);
  return Database::transaction(function()use($input,$kind):array{
   $data=match($kind){'history'=>WingHistoryService::page($input,true),'candidates'=>WingEligibilityService::candidates($input,true),default=>WingService::listing($input,true)};
   if(!$data['available']){throw new \InvalidArgumentException('Import database/20_membership_wings_mysql8.sql in phpMyAdmin before generating wing reports.');}
   $records=[];$criteria=[];
   if($kind==='history'){
    $title='Wing participation history';$criteria['Person']=$data['person']['full_name'];$criteria['Wing']='All wings';$criteria['Enrollment']='All records';
    $columns=['Enrollment #','Wing','Name at enrollment','Sys No. at enrollment','Enrolled on','Ended on','End reason','Recorded by','Recorded (UTC)','Ended by','Last updated (UTC)'];
    foreach($data['rows'] as $row){$records[]=[(string)$row['id'],WingService::WINGS[$row['wing_code']],$row['member_name_snapshot'],$row['member_number_snapshot'],$row['joined_on'],$row['ended_on']??'',self::reason($row['end_reason']),$row['enrolled_by']??'Staff',$row['created_at'],$row['ended_by']??'',$row['updated_at']];}
    $notice='Original enrollment identity is preserved. Recorded timestamps are separate from effective participation dates.';
   }elseif($kind==='candidates'){
    $title=WingService::WINGS[$data['wing']].' — eligibility suggestions';
    $columns=['Name','Party ID','Sys No.','Gender','Age','Constituency','Electoral area (branch)','Eligibility'];
    foreach($data['rows'] as $row){$records[]=[$row['full_name'],$row['official_membership_id']??'',$row['member_number'],\label($row['gender']),$row['age']===null?'Not recorded':(string)$row['age'],$row['constituency']??'Not assigned',$row['branch']??'Branch not assigned','Eligible for review; enrollment not yet confirmed'];}
    $notice='Suggestions do not enroll anyone. Youth ages 18–49 inclusive; Women’s Wing requires Female gender. Eligibility is evaluated when this report is generated.';
   }else{
    $title=WingService::WINGS[$data['wing']].' — enrollment directory';
    $columns=['Enrollment #','Name','Party ID','Sys No.','Gender','Constituency','Electoral area (branch)','Enrolled on','Ended on','End reason','Party membership','Life status','Review note','Name at enrollment'];
    foreach($data['rows'] as $row){$records[]=[(string)$row['id'],$row['full_name']??$row['member_name_snapshot'],$row['official_membership_id']??'',$row['member_number_snapshot'],\label($row['gender']??'unknown'),$row['constituency']??'Not assigned',$row['branch']??'Branch not assigned',$row['joined_on'],$row['ended_on']??'',self::reason($row['end_reason']),\label($row['membership_status']??'removed'),$row['gender']===null?'Person record unavailable':(!empty($row['is_deceased'])?'Deceased':'Not marked deceased'),$row['review_issue']??'',$row['member_name_snapshot']];}
    $notice='Ended enrollments are historical records, not distinct members. Location labels reflect the person’s current polling station. A person may belong to both wings.';
   }
   $filters=$data['filters'];$catalog=$data['catalog']??[];
   $labels=['wing'=>'Wing','state'=>'Enrollment','q'=>'Search','review'=>'Review','constituency_filter'=>'Constituency','branch_filter'=>'Branch','residential_region_filter'=>'Residential region','residential_district_filter'=>'Residential district','active_people'=>'People records'];
   $choices=['wing'=>WingService::WINGS,'state'=>['active'=>'Enrolled','ended'=>'Ended enrollments'],'review'=>['needs_review'=>'Needs review','aged_out'=>'Youth aged 50+'],'active_people'=>['1'=>'Undeleted voter records'],'constituency_filter'=>array_column($catalog['constituencies']??[],'name','id'),'branch_filter'=>array_column($catalog['electoral_areas']??[],'name','id')]+WingScopeService::choices($filters);
   foreach($labels as $key=>$label){if(isset($filters[$key])){$criteria[$label]=$choices[$key][$filters[$key]]??(string)$filters[$key];}}
   $criteria['Order']=$kind==='candidates'?'Name A–Z':'Newest enrollment first';
   $route=match($kind){'history'=>'wing_history','candidates'=>'wing_candidates',default=>'wings'};
   return ['kind'=>$kind,'title'=>$title,'columns'=>$columns,'records'=>$records,'criteria'=>$criteria,'total'=>$data['total'],'generated_at'=>gmdate('d M Y H:i').' UTC','notice'=>$notice,'back_url'=>\url($route,$filters)];
  });
 }
 private static function reason(?string $reason): string {return match($reason){'left'=>'Left wing','membership_removed'=>'Party membership removed',default=>''};}
 public static function csv(array $input): never {
  Auth::requireRole(['admin','registrar']);$report=self::prepare($input);ReportOutput::csv($report,'wings-'.$report['kind']);
 }
 public static function print(array $input): void {ReportOutput::print(self::prepare($input));}
}
