<?php
declare(strict_types=1);
namespace App\Reporting;
use App\{Auth,View};
final class ReportsController
{
 public static function get(bool $print=false): void {
  Auth::requireLogin();
  if(!$print&&empty($_GET['kind'])){View::render('reports/index',['reportData'=>null,'canExport'=>PeopleReports::canExport()],'Reports Centre');return;}
  try{
   $data=PeopleReports::prepare($_GET,$print);
   if($print){ReportOutput::print($data['report']);return;}
   View::render('reports/index',['reportData'=>$data,'canExport'=>PeopleReports::canExport()],'Reports Centre');
  }catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());self::back($_GET);}
 }
 public static function csv(): never {
  PeopleReports::requireExport();
  try{$data=PeopleReports::prepare($_POST,true);ReportOutput::peopleCsv($data['report'],'people-'.$data['filters']['kind']);}
  catch(\InvalidArgumentException $error){\flash('danger',$error->getMessage());self::back($_POST);}
 }
 private static function back(array $input): never {
  // Keep valid oversized-report filters; invalid criteria return to the hub.
  try{$filters=PeopleReports::filters($input);}catch(\InvalidArgumentException $error){$filters=[];}
  if(isset($filters['kind'])&&in_array($filters['kind'],OperationalReports::KINDS,true)&&!OperationalReports::available($filters['kind'])){$filters=[];}
  // A readiness failure must not redirect endlessly to the same report.
  if(!\App\LocationService::ready()||!\App\LocationService::stationCodesReady()||!\App\VoterService::officialIdsReady()||!\App\People\LifeStatusService::ready()||!\App\Voters\TrashService::ready()){$filters=[];}
  \redirect('reports',$filters);
 }
}
