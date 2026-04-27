<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/Scobraca/app');
define('PUBLIC_PATH', BASE_PATH . '/Scobraca/public');
define('STORAGE_PATH', BASE_PATH . '/Scobraca/storage');

require_once APP_PATH . '/Scobraca/Config/env.php';
require_once APP_PATH . '/Scobraca/Config/database.php';
require_once APP_PATH . '/Scobraca/Helpers/functions.php';
require_once APP_PATH . '/Scobraca/Auth/auth.php';
require_once APP_PATH . '/Scobraca/Auth/guards.php';

if ((env('APP_DEBUG', 'false') === 'true')) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}
