<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');

require_once APP_PATH . '/Core/Controller.php';
require_once APP_PATH . '/Core/Auth.php';
require_once APP_PATH . '/Helpers/url.php';
require_once APP_PATH . '/Helpers/view.php';
require_once APP_PATH . '/Helpers/flash.php';
require_once APP_PATH . '/Helpers/auth.php';
require_once APP_PATH . '/Middleware/AuthMiddleware.php';
require_once APP_PATH . '/Middleware/RoleMiddleware.php';
require_once APP_PATH . '/Controllers/DonoController.php';

RoleMiddleware::handle('dono');

$pagina = trim($_GET['pagina'] ?? 'dashboard');

$rotas = [
    'dashboard'              => 'dashboard',
    'usuarios'               => 'usuarios',
    'usuarioCadastrar'       => 'usuarioCadastrar',
    'usuarioVisualizar'      => 'usuarioVisualizar',
    'usuarioEditar'          => 'usuarioEditar',
    'permissoes'             => 'permissoes',
    'permissaoCadastrar'     => 'permissaoCadastrar',
    'permissaoVisualizar'    => 'permissaoVisualizar',
    'permissaoEditar'        => 'permissaoEditar',
    'tiposServicos'          => 'tiposServicos',
    'tipoServicoCadastrar'   => 'tipoServicoCadastrar',
    'tipoServicoVisualizar'  => 'tipoServicoVisualizar',
    'tipoServicoEditar'      => 'tipoServicoEditar',
    'relatorios'             => 'relatorios',
    'configuracoes'          => 'configuracoes',
];

if (!isset($rotas[$pagina])) {
    http_response_code(404);

    $arquivo404 = APP_PATH . '/Views/errors/404.php';
    if (file_exists($arquivo404)) {
        require $arquivo404;
    } else {
        exit('Pagina nao encontrada.');
    }
    exit;
}

$controller = new DonoController();
$metodo = $rotas[$pagina];

if (!method_exists($controller, $metodo)) {
    http_response_code(500);
    exit('Metodo nao encontrado no DonoController: ' . $metodo);
}

$controller->$metodo();
