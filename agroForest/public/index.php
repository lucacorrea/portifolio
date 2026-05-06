<?php
require dirname(__DIR__) . '/app/bootstrap.php';

$area = trim($_GET['area'] ?? 'auth');
$pagina = trim($_GET['pagina'] ?? 'login');

if ($area === 'auth') {
    if ($pagina === 'logout') {
        auth_logout_session();
        header('Location: ' . route_url('auth', 'login'));
        exit;
    }

    if ($pagina === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        login_processar();
    }

    login_exibir();
    exit;
}

role_required($area);

$view = router_resolve($area, $pagina);

if ($view === null) {
    http_response_code(404);
    require APP_PATH . '/Views/errors/404.php';
    exit;
}

render_view($view);
