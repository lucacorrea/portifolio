<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['POST']);
wpe_require_permission('visualizar');

try {
    $service = new WhatsappService();
    $retorno = $service->iniciarQrCode();
    if (!$retorno['sucesso']) {
        wpe_json(false, $retorno['mensagem'], [], [], 503);
    }
    wpe_json(true, 'QR Code consultado.', $retorno['dados']);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel consultar o QR Code.', [], [], 503);
}
