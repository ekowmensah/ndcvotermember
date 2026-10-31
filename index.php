<?php
declare(strict_types=1);
if(is_file(__DIR__.'/storage/maintenance.flag')){http_response_code(503);header('Retry-After: 120');exit('The membership system is being reset. Please refresh shortly.');}
require __DIR__.'/config/bootstrap.php';
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Cache-Control: no-store, private');
try {
 App\Auth::start();$controller=new App\Controller();
 if($_SERVER['REQUEST_METHOD']==='POST'){App\Auth::validateCsrf();$controller->post();}
 elseif($_SERVER['REQUEST_METHOD']==='GET'){$controller->get();}
 else{throw new App\HttpException(405,'Method not allowed.');}
}catch(Throwable $error){$code=$error instanceof App\HttpException?$error->status:($error instanceof InvalidArgumentException?422:500);http_response_code($code);if($code===500){error_log((string)$error);}$message=$code===500?'The request could not be completed. Check the application log.':$error->getMessage();require App\View::path('errors/error');}
