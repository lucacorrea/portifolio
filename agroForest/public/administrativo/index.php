<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';

role_required('administrativo');

$pagina = trim($_GET['pagina'] ?? 'dashboard');

$rotas = [
    'dashboard'           => 'administrativo/dashboard',

    'protocolosRecebidos' => 'administrativo/protocolosRecebidos',
    'protocoloVisualizar' => 'administrativo/protocoloVisualizar',

    'orcamentos'          => 'administrativo/orcamentos',
    'orcamentoCadastrar'  => 'administrativo/orcamentoCadastrar',
    'orcamentoEditar'     => 'administrativo/orcamentoEditar',
    'orcamentoVisualizar' => 'administrativo/orcamentoVisualizar',

    'clientes'            => 'administrativo/clientes',
    'clienteCadastrar'    => 'administrativo/clienteCadastrar',
    'clienteEditar'       => 'administrativo/clienteEditar',
    'clienteVisualizar'   => 'administrativo/clienteVisualizar',

    'documentos'          => 'administrativo/documentos',
    'documentoVisualizar' => 'administrativo/documentoVisualizar',

    'pendencias'          => 'administrativo/pendencias',
    'pendenciaCadastrar'  => 'administrativo/pendenciaCadastrar',
    'pendenciaEditar'     => 'administrativo/pendenciaEditar',
    'pendenciaVisualizar' => 'administrativo/pendenciaVisualizar',

    'relatorios'          => 'administrativo/relatorios',
    'configuracoes'       => 'administrativo/configurações',
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

render_view($rotas[$pagina]);
