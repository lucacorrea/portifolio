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
require_once APP_PATH . '/Controllers/AdministrativoController.php';

RoleMiddleware::handle('administrativo');

$pagina = trim($_GET['pagina'] ?? 'dashboard');

$rotas = [
    'dashboard'           => 'dashboard',

    'protocolosRecebidos' => 'protocolosRecebidos',
    'protocoloVisualizar' => 'protocoloVisualizar',

    'orcamentos'          => 'orcamentos',
    'orcamentoCadastrar'  => 'orcamentoCadastrar',
    'orcamentoEditar'     => 'orcamentoEditar',
    'orcamentoVisualizar' => 'orcamentoVisualizar',

    'clientes'            => 'clientes',
    'clienteCadastrar'    => 'clienteCadastrar',
    'clienteEditar'       => 'clienteEditar',
    'clienteVisualizar'   => 'clienteVisualizar',

    'documentos'          => 'documentos',
    'documentoVisualizar' => 'documentoVisualizar',

    'pendencias'          => 'pendencias',
    'pendenciaCadastrar'  => 'pendenciaCadastrar',
    'pendenciaEditar'     => 'pendenciaEditar',
    'pendenciaVisualizar' => 'pendenciaVisualizar',

    'relatorios'          => 'relatorios',
    'configuracoes'       => 'configuracoes',
];

if (!isset($rotas[$pagina])) {
    http_response_code(404);

    $arquivo404 = APP_PATH . '/Views/errors/404.php';
    if (file_exists($arquivo404)) {
        require $arquivo404;
    } else {
        exit('Página não encontrada.');
    }
    exit;
}

$controller = new AdministrativoController();
$metodo = $rotas[$pagina];

if (!method_exists($controller, $metodo)) {
    http_response_code(500);
    exit('Método não encontrado no AdministrativoController: ' . $metodo);
}

$controller->$metodo();
