<?php
declare(strict_types=1);
namespace App\Core;
final class Modules {
 private static ?array $data=null;
 public static function all(): array {return self::$data??=require BASE_PATH.'/config/modules.php';}
 public static function get(string $module): array {if (!isset(self::all()[$module])) \error_response(404,'Módulo inexistente.'); return self::all()[$module];}
 public static function isValid(string $module): bool {return array_key_exists($module,self::all());}
}
