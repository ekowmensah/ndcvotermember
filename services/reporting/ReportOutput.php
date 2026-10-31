<?php
declare(strict_types=1);
namespace App\Reporting;
use App\{Auth,View};

final class ReportOutput
{
 public const MAX_ROWS=5000;
 public static function checkSize(int $total): void {
  if($total>self::MAX_ROWS){throw new \InvalidArgumentException('This report exceeds 5,000 rows. Narrow the filters before printing or exporting.');}
 }
 public static function csv(array $report,string $filename): never {
  Auth::requireRole(['admin','registrar']);self::checkSize((int)$report['total']);self::checkSize(count($report['records']));
  if(!preg_match('/\A[a-z0-9-]+\z/D',$filename)){throw new \LogicException('Invalid internal report filename.');}
  Auth::audit('report.csv_generated','report',null,['kind'=>$filename,'records'=>count($report['records'])]);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$filename.'-'.gmdate('Ymd-His').'.csv"');
  $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$report['columns'],',','"','');
  foreach($report['records'] as $record){
   $safe=array_map(static function($value):string{
    $text=(string)$value;
    return preg_match('/^[\s\x{FEFF}]*[=+\-@]|^[\t\r\n]/u',$text)?"'".$text:$text;
   },$record);
   fputcsv($out,$safe,',','"','');
  }
  fclose($out);exit;
 }
 public static function print(array $report): void {
  Auth::requireLogin();self::checkSize((int)$report['total']);self::checkSize(count($report['records']));Auth::audit('report.print_generated','report',null,['kind'=>$report['kind']??'report','records'=>count($report['records'])]);require View::path('shared/reports/print');
 }
}
