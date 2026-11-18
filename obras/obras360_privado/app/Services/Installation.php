<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Auth,Database};
use RuntimeException;
/** Instalação idempotente de esquema e primeiro administrador. Não imprime nem registra senhas. */
final class Installation {
 public static function runSchema(): void {
  $schema=BASE_PATH.'/database/'.Database::driver().'.sql';
  if (!is_readable($schema)) throw new RuntimeException('Arquivo do esquema ausente.');
  $sql=(string)file_get_contents($schema);
  // Esquema oficial sem procedures/triggers nem ponto-e-vírgula dentro de strings.
  foreach (preg_split('/;[\t ]*(?:\r?\n|$)/',$sql) ?: [] as $stmt) {
   if (trim($stmt)!=='') Database::pdo()->exec($stmt);
  }
 }
 public static function adminCount(): int {
  try { return (int)Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn(); }
  catch (\PDOException $e) {
   if (self::missingUsersTable($e)) return 0;
   throw $e;
  }
 }
 private static function missingUsersTable(\PDOException $e): bool {
  return in_array((string)$e->getCode(),['42S02','HY000'],true) &&
    (str_contains($e->getMessage(),'no such table')||str_contains($e->getMessage(),"doesn't exist"));
 }
 public static function createInitialAdmin(string $name,string $email): ?string {
  $name=trim($name);$email=strtolower(trim($email));
  if ($name===''||strlen($name)>150 ||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)
   throw new \InvalidArgumentException('Informe nome (até 150 caracteres) e e-mail válidos.');
  $db=Database::pdo();
  if (self::adminCount()>0) return null;
  $password=Auth::generatePassword();
  $q=$db->prepare("INSERT INTO users(name,email,password_hash,role,active,must_change_password) VALUES (?,?,?,?,1,1)");
  $q->execute([$name,$email,Auth::hash($password),'admin']);
  return $password;
 }
}
