<?php
declare(strict_types=1);
namespace App;
final class View
{
 public static function path(string $template): string {
  if(!preg_match('~\A[a-z][a-z0-9_]*(?:/[a-z][a-z0-9_]*)*\z~D',$template)){
   throw new \LogicException('Invalid view name. Use a feature-relative template path.');
  }
  $path=BASE_PATH.'/views/'.$template.'.php';
  if(!is_file($path)){throw new \LogicException('View not found: '.$template);}
  return $path;
 }
 public static function render(string $template,array $data=[],string $title=''): void {
  extract($data,EXTR_SKIP);$viewFile=self::path($template);$user=Auth::user();
  $appName=Config::get('APP_NAME','Membership Office');$currentPage=(string)($_GET['page']??'dashboard');
  if($template==='auth/login'){require $viewFile;return;}require self::path('layouts/app');
 }
}
