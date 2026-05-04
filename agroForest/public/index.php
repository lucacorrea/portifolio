<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');

require_once APP_PATH . '/Core/Controller.php';
require_once APP_PATH . '/Core/Csrf.php';
require_once APP_PATH . '/Core/Auth.php';
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

$area = trim($_GET['area'] ?? 'auth');
$pagina = trim($_GET['pagina'] ?? 'login');

if ($area === 'auth') {
    $controller = new AuthController();

    if ($pagina === 'logout') {
        $controller->logout();
    }

    $controller->login();
    exit;
}

RoleMiddleware::handle($area);

$view = Router::resolve($area, $pagina);

if ($view === null) {
    http_response_code(404);
    require APP_PATH . '/Views/errors/404.php';
    exit;
}

render_view($view);
