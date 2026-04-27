<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once APP_PATH . '/Config/env.php';
require_once APP_PATH . '/Config/database.php';
require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Auth/auth.php';
require_once APP_PATH . '/Auth/guards.php';

if ((env('APP_DEBUG', 'false') === 'true')) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}
