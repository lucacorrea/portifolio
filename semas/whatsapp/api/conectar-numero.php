<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['POST']);
wpe_require_permission('visualizar');

$input = wpe_input();
wpe_require_csrf($input);

$telefone = (string)($input['telefone'] ?? ($input['phone'] ?? ''));
if ($telefone === '') {
    $ddi = preg_replace('/\D+/', '', (string)($input['ddi'] ?? '55'));
    $ddd = preg_replace('/\D+/', '', (string)($input['ddd'] ?? ''));
    $numero = preg_replace('/\D+/', '', (string)($input['numero'] ?? ''));
    $telefone = $ddi . $ddd . $numero;
}

try {
    $service = new WhatsappService();
    $retorno = $service->solicitarPareamento($telefone);
    if (!$retorno['sucesso']) {
        wpe_json(false, $retorno['mensagem'], $retorno['dados'] ?? [], [], (int)($retorno['http_code'] ?: 422));
    }

    wpe_json(true, 'Codigo de pareamento gerado.', $retorno['dados']);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel gerar o codigo de pareamento.', [], [], 503);
}
