<?php
declare(strict_types=1);
namespace App\Core;
use PDO; use RuntimeException;
final class Database {
 private static ?PDO $connection=null;
 public static function pdo(): PDO {
  if (self::$connection) return self::$connection;
  $driver=Env::get('DB_DRIVER','sqlite');
  if ($driver==='sqlite') {
   $path=Env::get('DB_PATH','storage/database.sqlite');
   $path = str_starts_with($path,'/') ? $path : dirname(__DIR__,2).'/'.$path;
   if (!is_dir(dirname($path))) throw new RuntimeException('Pasta do banco inexistente');
   $pdo=new PDO('sqlite:'.$path);
   $pdo->exec('PRAGMA foreign_keys=ON'); $pdo->exec('PRAGMA busy_timeout=5000');
  } elseif ($driver==='mysql') {
   $dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',Env::get('DB_HOST','127.0.0.1'),Env::get('DB_PORT','3306'),Env::get('DB_NAME','obras360'));
   $pdo=new PDO($dsn,Env::get('DB_USER',''),Env::get('DB_PASSWORD',''));
   $pdo->exec("SET time_zone = '+00:00'");
  } else throw new RuntimeException('DB_DRIVER deve ser sqlite ou mysql');
  $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
  return self::$connection=$pdo;
 }
 public static function driver(): string {return (string)Env::get('DB_DRIVER','sqlite');}
}
