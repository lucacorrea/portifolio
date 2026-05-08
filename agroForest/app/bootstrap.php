<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}

if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}

$appConfig = require APP_PATH . '/Config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'America/Manaus');

require_once APP_PATH . '/Helpers/url.php';
require_once APP_PATH . '/Helpers/view.php';
require_once APP_PATH . '/Helpers/flash.php';
require_once APP_PATH . '/Helpers/auth.php';
require_once APP_PATH . '/Helpers/procedural_auth.php';
require_once APP_PATH . '/Helpers/clientes_contratos.php';
require_once APP_PATH . '/Helpers/terrenos.php';

app_log_init();
