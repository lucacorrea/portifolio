<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');

$GLOBALS['app_config'] = require APP_PATH . '/Config/app.php';

if (!is_array($GLOBALS['app_config'])) {
    throw new RuntimeException('O arquivo app/Config/app.php deve retornar um array.');
}

require_once APP_PATH . '/Core/Router.php';
require_once APP_PATH . '/Core/View.php';
require_once APP_PATH . '/Core/Model.php';
require_once APP_PATH . '/Helpers/url.php';
require_once APP_PATH . '/Helpers/flash.php';

$router = new Router();

require_once APP_PATH . '/Modules/Auth/routes.php';
require_once APP_PATH . '/Modules/Admin/routes.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI'] ?? '/';

$router->dispatch($method, $uri);