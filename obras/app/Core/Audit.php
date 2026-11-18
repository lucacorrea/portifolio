<?php
declare(strict_types=1);
namespace App\Core;
final class Audit {
 public static function log(string $action,string $module,?int $recordId=null,array $details=[]): void {
  try { $q=Database::pdo()->prepare('INSERT INTO audit_logs (user_id,action,module,record_id,details,ip) VALUES (?,?,?,?,?,?)');
   $q->execute([Auth::id(),$action,$module,$recordId,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),substr((string)($_SERVER['REMOTE_ADDR']??'cli'),0,45)]);
  } catch(\Throwable $e){error_log('Falha ao registrar auditoria: '.$e->getMessage());}
 }
}
