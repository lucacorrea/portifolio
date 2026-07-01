<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['GET', 'POST']);
wpe_require_permission('visualizar');

try {
    $service = new WhatsappService();
    $retorno = $service->verificarConexao();
    if (!$retorno['sucesso']) {
        $http = (int)($retorno['http_code'] ?? 0);
        if ($http < 400 || $http > 599) {
            $http = 502;
        }
        wpe_json(false, $retorno['mensagem'], $retorno, [], $http);
    }

    wpe_json(true, 'Status consultado.', $retorno);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel consultar o status.', [], [], 503);
}
