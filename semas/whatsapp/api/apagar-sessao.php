<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['POST']);
wpe_require_permission('visualizar');

$input = wpe_input();
wpe_require_csrf($input);

$confirmacao = strtoupper(trim((string)($input['confirmacao'] ?? '')));
if ($confirmacao !== 'APAGAR SESSAO SEMAS') {
    wpe_json(false, 'Confirmacao reforcada invalida.', [], [], 422);
}

try {
    $service = new WhatsappService();
    $retorno = $service->apagarSessao();
    if (!$retorno['sucesso']) {
        wpe_json(false, $retorno['mensagem'], $retorno['dados'] ?? [], [], 503);
    }

    wpe_json(true, 'Sessao SEMAS apagada.', $retorno['dados']);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel apagar a sessao SEMAS.', [], [], 503);
}
