<?php
declare(strict_types=1);
namespace Sigesp\Modules\Auth\Services;
use Sigesp\Core\Auth;
use Sigesp\Core\Database;
use Sigesp\Modules\Auth\Repositories\UsuarioRepository;
final class AuthService
{
    public function attempt(string $identifier, string $password, string $ip): bool
    {
        $repo = new UsuarioRepository(); $user = $repo->findByIdentifier(trim($identifier));
        $valid = $user && password_verify($password, $user['senha_hash']);
        $q = Database::connection()->prepare('INSERT INTO tentativas_login (identificador_hash,ip,sucesso,motivo) VALUES (?,?,?,?)'); $q->execute([hash('sha256', mb_strtolower(trim($identifier))), $ip, $valid ? 1 : 0, $valid ? null : 'credenciais_invalidas']);
        if (!$valid) return false;
        if (password_needs_rehash($user['senha_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) { Database::connection()->prepare('UPDATE usuarios SET senha_hash=?,updated_at=NOW() WHERE id=?')->execute([password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT), $user['id']]); }
        Database::connection()->prepare('UPDATE usuarios SET ultimo_login_em=NOW() WHERE id=?')->execute([$user['id']]); Auth::login($user); return true;
    }
}
