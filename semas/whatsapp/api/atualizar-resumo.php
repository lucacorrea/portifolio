<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['POST']);
wpe_require_permission('atualizar_resumo');

$input = wpe_input();
wpe_require_csrf($input);

try {
    $id = (int)($input['atualizacao_id'] ?? 0);
    if ($id <= 0) {
        wpe_json(false, 'Atualizacao obrigatoria.', [], [], 422);
    }
    $resumo = trim((string)($input['resumo_aprovado'] ?? ''));
    $service = wpe_service($pdo);
    wpe_json(true, 'Resumo atualizado com historico preservado.', $service->atualizarResumo($id, $resumo));
} catch (RuntimeException $e) {
    wpe_json(false, $e->getMessage(), [], [], 422);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel atualizar o resumo.', [], [], 500);
}
