<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['POST']);
wpe_require_permission('visualizar');

$input = wpe_input();
wpe_require_csrf($input);

try {
    $service = new WhatsappService();
    $retorno = $service->reiniciarCliente();
    if (!$retorno['sucesso']) {
        wpe_json(false, $retorno['mensagem'], $retorno['dados'] ?? [], [], 503);
    }

    wpe_json(true, 'Cliente SEMAS reiniciado.', $retorno['dados']);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel reiniciar o cliente SEMAS.', [], [], 503);
}
