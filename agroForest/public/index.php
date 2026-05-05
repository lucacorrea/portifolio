<?php
require dirname(__DIR__) . '/app/bootstrap.php';

$area = trim($_GET['area'] ?? 'auth');
$pagina = trim($_GET['pagina'] ?? 'login');

if ($area === 'auth') {
    $controller = new AuthController();

    if ($pagina === 'logout') {
        $controller->logout();
    }

    if ($pagina === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->processarLogin();
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
