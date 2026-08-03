<?php
declare(strict_types=1);
namespace Sigesp\Core;
use Sigesp\Modules\Auth\Repositories\UsuarioRepository;
final class Auth
{
    public static function check(): bool { return is_array(Session::get('usuario')); }
    public static function user(): ?array { $user = Session::get('usuario'); return is_array($user) ? $user : null; }
    public static function login(array $user): void { session_regenerate_id(true); unset($user['senha_hash']); Session::put('usuario', $user); }
    public static function logout(): void { Session::forget('usuario'); Csrf::regenerate(); session_regenerate_id(true); }
    public static function can(string $permission): bool { $user = self::user(); return $user !== null && (bool) (new UsuarioRepository())->hasPermission((int) $user['id'], $permission); }
}
