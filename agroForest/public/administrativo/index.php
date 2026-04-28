<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Helpers/url.php';

$pagina = $_GET['pagina'] ?? 'dashboard';

$viewsPermitidas = [
    'dashboard'           => dirname(__DIR__, 2) . '/app/Views/administrativo/dashboard.php',
    'protocolosRecebidos' => dirname(__DIR__, 2) . '/app/Views/administrativo/protocolosRecebidos.php',
    'orcamentos'          => dirname(__DIR__, 2) . '/app/Views/administrativo/orcamentos.php',
    'clientes'            => dirname(__DIR__, 2) . '/app/Views/administrativo/clientes.php',
    'documentos'          => dirname(__DIR__, 2) . '/app/Views/administrativo/documentos.php',
    'pendencias'          => dirname(__DIR__, 2) . '/app/Views/administrativo/pendencias.php',
    'relatorios'          => dirname(__DIR__, 2) . '/app/Views/administrativo/relatorios.php',
    'configuracoes'       => dirname(__DIR__, 2) . '/app/Views/administrativo/configuracoes.php',
    'verProtocolo'        => dirname(__DIR__, 2) . '/app/Views/administrativo/verProtocolo.php',
];

if (!isset($viewsPermitidas[$pagina])) {
    http_response_code(404);
    exit('Página não encontrada.');
}

$arquivoView = $viewsPermitidas[$pagina];

if (!file_exists($arquivoView)) {
    http_response_code(500);
    exit('View não encontrada: ' . basename($arquivoView));
}

require $arquivoView;