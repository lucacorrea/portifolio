<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['POST']);
wpe_require_permission('revisar');

$input = wpe_input();
wpe_require_csrf($input);

try {
    $id = (int)($input['atualizacao_id'] ?? 0);
    $status = trim((string)($input['status_revisao'] ?? 'aguardando_revisao'));
    $categoria = trim((string)($input['categoria'] ?? ''));
    $resumo = trim((string)($input['resumo_sugerido'] ?? ''));
    $allowed = ['pendente', 'aguardando_revisao', 'aprovado', 'rejeitado', 'concluido'];
    if ($id <= 0 || !in_array($status, $allowed, true)) {
        wpe_json(false, 'Dados de revisao invalidos.', [], [], 422);
    }
    $service = wpe_service($pdo);
    $stmt = $pdo->prepare("
        UPDATE whatsapp_semas_emprego
        SET status_revisao = ?, categoria = COALESCE(NULLIF(?, ''), categoria),
            resumo_sugerido = COALESCE(NULLIF(?, ''), resumo_sugerido),
            revisado_por = ?, revisado_em = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$status, $categoria, $resumo, $service->usuarioNome(), $id]);
    wpe_json(true, 'Revisao registrada.', ['atualizacao_id' => $id]);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel revisar a resposta.', [], [], 500);
}
