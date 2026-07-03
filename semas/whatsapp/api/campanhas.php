<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['GET']);
wpe_require_permission('visualizar');

try {
    $service = wpe_service($pdo);
    $rows = $pdo->query("
        SELECT c.*,
               COUNT(d.id) AS destinatarios,
               SUM(d.status = 'na_fila') AS fila,
               SUM(d.status IN ('aguardando_resposta','profissao_identificada','resumo_atualizado','concluido')) AS enviados,
               SUM(d.status = 'falha_envio') AS falhas
        FROM whatsapp_semas_campanhas c
        LEFT JOIN whatsapp_semas_destinatarios d ON d.campanha_id = c.id
        GROUP BY c.id
        ORDER BY c.id DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    wpe_json(true, 'Campanhas carregadas.', ['campanhas' => $rows, 'csrf_token' => $service->csrfToken()]);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel carregar campanhas.', [], [], 500);
}
