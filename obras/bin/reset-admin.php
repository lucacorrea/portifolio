<?php
declare(strict_types=1);
// Redefinição emergencial de senha, com acesso ao console do servidor.
if (PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\{Auth,Database};
$email=strtolower(trim($argv[1]??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Uso: php bin/reset-admin.php email@dominio\n");exit(1);}
$q=Database::pdo()->prepare("SELECT id FROM users WHERE email=? AND role='admin' AND active=1");$q->execute([$email]);$id=(int)$q->fetchColumn();
if(!$id){fwrite(STDERR,"Administrador ativo não encontrado.\n");exit(1);}
$password=Auth::generatePassword();Database::pdo()->prepare('UPDATE users SET password_hash=?,must_change_password=1,locked_until=NULL,failed_attempts=0 WHERE id=?')->execute([Auth::hash($password),$id]);
echo "Credencial temporária (mostrada uma única vez): {$password}\nSubstitua no primeiro acesso.\n";
