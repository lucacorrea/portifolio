<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['GET', 'POST']);
wpe_require_permission('ver_conversa');

try {
    $input = wpe_input();
    $solicitanteId = (int)($input['solicitante_id'] ?? 0);
    $solicitacaoId = (int)($input['solicitacao_id'] ?? 0);
    if ($solicitanteId <= 0 || $solicitacaoId <= 0) {
        wpe_json(false, 'Pessoa e solicitacao sao obrigatorias.', [], [], 422);
    }
    $service = wpe_service($pdo);
    wpe_json(true, 'Conversa carregada.', $service->buscarConversa($solicitanteId, $solicitacaoId));
} catch (RuntimeException $e) {
    wpe_json(false, $e->getMessage(), [], [], 404);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel carregar a conversa.', [], [], 500);
}
