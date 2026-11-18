<?php
declare(strict_types=1);
namespace App\Core;
final class Csrf {
 public static function token(): string {return $_SESSION['_csrf']??=bin2hex(random_bytes(32));}
 public static function verify(): void {
  if (!hash_equals(self::token(),(string)($_POST['_csrf']??''))) \error_response(419,'Sessão expirada ou token inválido. Recarregue a página e tente novamente.');
 }
}
