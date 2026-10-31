<?php
declare(strict_types=1);
namespace App;
use PDO;
final class Database
{
 private static ?PDO $pdo=null;
 public static function connection(): PDO {
  if(self::$pdo===null){self::$pdo=new PDO('mysql:host='.Config::get('DB_HOST','127.0.0.1').';port='.Config::get('DB_PORT','3306').';dbname='.Config::get('DB_DATABASE').';charset=utf8mb4',Config::get('DB_USERNAME'),Config::get('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);self::$pdo->exec("SET time_zone='+00:00',SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");}
  return self::$pdo;
 }
 public static function all(string $sql,array $params=[]): array {$q=self::connection()->prepare($sql);$q->execute($params);return $q->fetchAll();}
 public static function one(string $sql,array $params=[]): ?array {return self::all($sql,$params)[0]??null;}
 public static function scalar(string $sql,array $params=[]): mixed {return array_values(self::one($sql,$params)??[])[0]??null;}
 public static function execute(string $sql,array $params=[]): int {$q=self::connection()->prepare($sql);$q->execute($params);return $q->rowCount();}
 public static function transaction(callable $fn): mixed {self::connection()->beginTransaction();try{$result=$fn();self::connection()->commit();return $result;}catch(\Throwable $e){if(self::connection()->inTransaction()){self::connection()->rollBack();}throw $e;}}
}
