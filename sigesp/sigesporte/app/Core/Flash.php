<?php
declare(strict_types=1);
namespace Sigesp\Core;
final class Flash
{
    public static function add(string $type, string $message): void { Session::put('_flash', ['type' => $type, 'message' => $message]); }
    public static function pull(): ?array { $value = Session::get('_flash'); Session::forget('_flash'); return is_array($value) ? $value : null; }
}
