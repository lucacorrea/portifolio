<?php
declare(strict_types=1);
namespace App\Core;
final class Env {
 private static array $values=[];
 public static function load(string $file): void {
  if (!is_file($file)) return;
  foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
   $line=trim($line); if ($line==='' || str_starts_with($line,'#') || !str_contains($line,'=')) continue;
   [$key,$value]=explode('=',$line,2); $key=trim($key); $value=trim($value);
   if ($value!=='' && (($value[0]==='"'&&str_ends_with($value,'"'))||($value[0]==="'"&&str_ends_with($value,"'")))) $value=substr($value,1,-1);
   self::$values[$key]=$value;
  }
 }
 public static function get(string $name, ?string $default=null): ?string {return getenv($name) !== false ? (string)getenv($name) : (self::$values[$name]??$default);}
}
