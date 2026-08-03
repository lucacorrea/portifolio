<?php
declare(strict_types=1);
namespace Sigesp\Core;
final class Csrf
{
    public static function token(): string { return Session::get('_csrf') ?? self::regenerate(); }
    public static function regenerate(): string { $token = bin2hex(random_bytes(32)); Session::put('_csrf', $token); return $token; }
    public static function validate(?string $token): bool { return is_string($token) && hash_equals((string) Session::get('_csrf', ''), $token); }
}
