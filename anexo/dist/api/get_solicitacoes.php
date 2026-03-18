<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/authGuard.php';
auth_guard();

require_once __DIR__ . '/../assets/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$sid = (int)($_GET['solicitante_id'] ?? 0);

if ($sid <= 0) {
    echo json_encode(['ok' => false, 'items' => []]);
    exit;
}

try {
    // Check if table exists (graceful degradation if migration not run)
    // We try to select.
    $sql = "
        SELECT s.id, s.resumo_caso, s.data_solicitacao, s.status,
               COALESCE(at.nome, '—') as ajuda_nome,
               COALESCE(at.categoria, '') as ajuda_cat
          FROM solicitacoes s
          LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
         WHERE s.solicitante_id = :sid
         ORDER BY s.data_solicitacao DESC, s.id DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':sid' => $sid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates
    foreach ($rows as &$r) {
        $dt = $r['data_solicitacao'] ?? '';
        if ($dt) {
            $ts = strtotime($dt);
            $r['data_fmt'] = date('d/m/Y H:i', $ts);
        } else {
            $r['data_fmt'] = '—';
        }
    }
    unset($r);

    echo json_encode(['ok' => true, 'items' => $rows]);

} catch (Throwable $e) {
    // Maybe table doesn't exist? Return empty or error logic
    // We'll return error in msg so frontend knows
    echo json_encode(['ok' => false, 'items' => [], 'error' => $e->getMessage()]);
}
