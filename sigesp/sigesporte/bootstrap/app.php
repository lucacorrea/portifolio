<?php
declare(strict_types=1);

use Sigesp\Core\Application;

$vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
} else {
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'Sigesp' . chr(92))) {
            $file = dirname(__DIR__) . '/app/' . str_replace(chr(92), '/', substr($class, 7)) . '.php';
            if (is_file($file)) require $file;
        }
    });
}

Application::boot(dirname(__DIR__))->run();
