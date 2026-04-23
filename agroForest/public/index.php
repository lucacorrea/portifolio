<?php
declare(strict_types=1);

$rota = $_GET['pagina'] ?? 'dashboard';

$viewsPermitidas = [
    'dashboard'      => dirname(__DIR__) . '/app/Views/recepcao/dashboard.php',
    'novoProtocolo'  => dirname(__DIR__) . '/app/Views/recepcao/novoProtocolo.php',
    'clientes'       => dirname(__DIR__) . '/app/Views/recepcao/clientes.php',
    'protocolos'     => dirname(__DIR__) . '/app/Views/recepcao/protocolos.php',
    'documentos'     => dirname(__DIR__) . '/app/Views/recepcao/documentos.php',
    'encaminhar'     => dirname(__DIR__) . '/app/Views/recepcao/encaminhar.php',
    'pendencias'     => dirname(__DIR__) . '/app/Views/recepcao/pendencias.php',
    'relatorios'     => dirname(__DIR__) . '/app/Views/recepcao/relatorios.php',
    'configuracoes'  => dirname(__DIR__) . '/app/Views/recepcao/configuracoes.php',
];

if (!isset($viewsPermitidas[$rota])) {
    http_response_code(404);
    exit('Página não encontrada.');
}

require $viewsPermitidas[$rota];