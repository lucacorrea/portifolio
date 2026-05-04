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

require_once APP_PATH . '/Core/AppLogger.php';
require_once APP_PATH . '/Core/Controller.php';
require_once APP_PATH . '/Core/Csrf.php';
require_once APP_PATH . '/Core/Auth.php';
require_once APP_PATH . '/Core/Database.php';
require_once APP_PATH . '/Core/Model.php';
require_once APP_PATH . '/Core/Router.php';
require_once APP_PATH . '/Helpers/url.php';
require_once APP_PATH . '/Helpers/view.php';
require_once APP_PATH . '/Helpers/flash.php';
require_once APP_PATH . '/Helpers/auth.php';
require_once APP_PATH . '/Middleware/AuthMiddleware.php';
require_once APP_PATH . '/Middleware/RoleMiddleware.php';
require_once APP_PATH . '/Models/Usuario.php';
require_once APP_PATH . '/Controllers/AuthController.php';
