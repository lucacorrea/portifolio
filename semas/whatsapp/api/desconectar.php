<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['POST']);
wpe_require_permission('visualizar');

$input = wpe_input();
wpe_require_csrf($input);

try {
    $service = new WhatsappService();
    $retorno = $service->desconectarConta();
    if (!$retorno['sucesso']) {
        wpe_json(false, $retorno['mensagem'], $retorno['dados'] ?? [], [], 503);
    }

    wpe_json(true, 'Conta SEMAS desconectada.', $retorno['dados']);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel desconectar a conta SEMAS.', [], [], 503);
}
