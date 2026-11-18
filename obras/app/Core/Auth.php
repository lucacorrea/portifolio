<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
final class Auth {
 private static ?array $cached=null;
 private static bool $loaded=false;
 private const ARGON=['memory_cost'=>65536,'time_cost'=>3,'threads'=>1];
 public static function hash(string $password): string {
  if (!defined('PASSWORD_ARGON2ID')) throw new \RuntimeException('Esta instalação PHP não oferece Argon2id. Ative suporte a Argon2.');
  return password_hash($password,PASSWORD_ARGON2ID,self::ARGON);
 }
 public static function verify(string $password,string $hash): bool {return password_verify($password,$hash);}
 public static function validPassword(string $password): bool {return (function_exists('mb_strlen')?mb_strlen($password,'UTF-8'):(function_exists('iconv_strlen')?iconv_strlen($password,'UTF-8'):strlen($password)))>=15 && strlen($password)<=256 && !in_array(mb_case_fallback($password),['password123456789','123456789012345','senha1234567890','admin1234567890'],true);}
 public static function user(): ?array {
  if (PHP_SAPI==='cli') return null;
  if (self::$loaded) return self::$cached;
  self::$loaded=true;$id=(int)($_SESSION['uid']??0);if (!$id) return null;
  $now=time();$idle=(int)Env::get('SESSION_IDLE_MINUTES','30')*60;$absolute=(int)Env::get('SESSION_MAX_HOURS','12')*3600;
  if ($now-(int)($_SESSION['last_seen']??0)>$idle || $now-(int)($_SESSION['started']??0)>$absolute) {self::clear();return null;}
  $q=Database::pdo()->prepare('SELECT id,name,email,role,active,must_change_password,last_login_at FROM users WHERE id=?');$q->execute([$id]);$u=$q->fetch();
  if (!$u || !(bool)$u['active']) {self::clear();return null;}
  $_SESSION['last_seen']=$now;return self::$cached=$u;
 }
 public static function id(): ?int {return PHP_SAPI==='cli'?null:((int)(self::user()['id']??0)?:null);}
 public static function requireLogin(): void {if (!self::user()) \redirect('login');}
 public static function canWrite(): bool {return in_array(self::user()['role']??'', ['admin','gestor','tecnico'],true);}
 public static function canDelete(): bool {return in_array(self::user()['role']??'', ['admin','gestor'],true);}
 public static function isAdmin(): bool {return (self::user()['role']??'')==='admin';}
 public static function requireWrite(): void {if (!self::canWrite()) \error_response(403,'Sem permissão para alterar dados.');}
 public static function requireDelete(): void {if (!self::canDelete()) \error_response(403,'Sem permissão para excluir registros.');}
 public static function requireAdmin(): void {if (!self::isAdmin()) \error_response(403,'Acesso reservado ao administrador.');}
 public static function attempt(string $email,string $password): bool {
  $db=Database::pdo();$email=mb_case_fallback(trim($email));$ip=substr((string)($_SERVER['REMOTE_ADDR']??'0.0.0.0'),0,45);
  $since=gmdate('Y-m-d H:i:s',time()-15*60);
  $q=$db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip=? AND succeeded=0 AND created_at>=?');$q->execute([$ip,$since]);
  if ((int)$q->fetchColumn()>=15) return false;
  $q=$db->prepare('SELECT * FROM users WHERE email=? LIMIT 1');$q->execute([$email]);$user=$q->fetch();
  $valid=$user && (bool)$user['active'] && (!$user['locked_until'] || $user['locked_until']<=gmdate('Y-m-d H:i:s')) && self::verify($password,$user['password_hash']);
  $db->prepare('INSERT INTO login_attempts(email,ip,succeeded) VALUES (?,?,?)')->execute([$email,$ip,(int)(bool)$valid]);
  if (!$valid) {
   if ($user) {
    $now=gmdate('Y-m-d H:i:s');
    if (!empty($user['locked_until']) && $user['locked_until']>$now) {
     // Conta já bloqueada: falhas adicionais NÃO devem remover o bloqueio.
    } else {
     $failed=(int)$user['failed_attempts']+1;
     $lock=$failed>=5?gmdate('Y-m-d H:i:s',time()+15*60):null;
     $db->prepare('UPDATE users SET failed_attempts=?,locked_until=? WHERE id=?')->execute([$lock?0:$failed,$lock,(int)$user['id']]);
    }
   }
   Audit::log('login_failure','auth',null,['email_sha256'=>hash('sha256',$email)]);
   return false;
  }
  if (password_needs_rehash($user['password_hash'],PASSWORD_ARGON2ID,self::ARGON)) $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([self::hash($password),$user['id']]);
  $db->prepare('UPDATE users SET failed_attempts=0,locked_until=NULL,last_login_at=? WHERE id=?')->execute([gmdate('Y-m-d H:i:s'),$user['id']]);
  session_regenerate_id(true);$_SESSION=['uid'=>(int)$user['id'],'started'=>time(),'last_seen'=>time(),'_csrf'=>bin2hex(random_bytes(32))];
  self::$loaded=false;self::$cached=null;Audit::log('login','auth',(int)$user['id']);return true;
 }
 public static function changePassword(int $id,string $newPassword): void {
  if (!self::validPassword($newPassword)) throw new \InvalidArgumentException('A senha deve ter entre 15 e 256 caracteres e não pode ser trivial.');
  Database::pdo()->prepare('UPDATE users SET password_hash=?,must_change_password=0,failed_attempts=0,locked_until=NULL,updated_at=? WHERE id=?')
   ->execute([self::hash($newPassword),gmdate('Y-m-d H:i:s'),$id]);
  if (self::id()===$id) {self::$loaded=false;self::$cached=null;session_regenerate_id(true);}
  Audit::log('password_change','users',$id);
 }
 public static function generatePassword(): string {
  return substr(bin2hex(random_bytes(16)),0,24);
 }
 public static function logout(): void {if (self::id()) Audit::log('logout','auth',self::id());self::clear();}
 private static function clear(): void {self::$loaded=true;self::$cached=null;$_SESSION=[];if (ini_get('session.use_cookies'))setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);session_destroy();}
}
