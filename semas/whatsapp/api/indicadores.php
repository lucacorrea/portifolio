<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['GET', 'POST']);
wpe_require_permission('visualizar');

try {
    $service = wpe_service($pdo);
    $status = $service->statusWhatsapp();
    $dados = $service->indicadores(wpe_input());
    $dados['whatsapp'] = [
        'sucesso' => $status['sucesso'],
        'mensagem' => $status['mensagem'],
        'status' => $status['dados']['status'] ?? 'indisponivel',
        'conectado' => !empty($status['dados']['conectado']),
        'verificado_em' => date('d/m/Y H:i:s'),
    ];
    $dados['csrf_token'] = $service->csrfToken();
    wpe_json(true, 'Indicadores carregados.', $dados);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel carregar os indicadores.', [], [], 500);
}
