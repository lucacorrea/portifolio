<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';

role_required('dono');

$pagina = trim($_GET['pagina'] ?? 'dashboard');

$rotas = [
    'dashboard'              => 'dono/dashboard',
    'clientes'               => 'dono/clientes',
    'usuarios'               => 'dono/usuarios',
    'usuarioCadastrar'       => 'dono/usuarioCadastrar',
    'usuarioVisualizar'      => 'dono/usuarioVisualizar',
    'usuarioEditar'          => 'dono/usuarioEditar',
    'permissoes'             => 'dono/permissoes',
    'permissaoCadastrar'     => 'dono/permissaoCadastrar',
    'permissaoVisualizar'    => 'dono/permissaoVisualizar',
    'permissaoEditar'        => 'dono/permissaoEditar',
    'tiposServicos'          => 'dono/tiposServicos',
    'tipoServicoCadastrar'   => 'dono/tipoServicoCadastrar',
    'tipoServicoVisualizar'  => 'dono/tipoServicoVisualizar',
    'tipoServicoEditar'      => 'dono/tipoServicoEditar',
    'relatorios'             => 'dono/relatorios',
    'configuracoes'          => 'dono/configuracoes',
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

render_view($rotas[$pagina]);
