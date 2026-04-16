<?php
/**
 * api/fiscal_sync.php — Sincronização de numeração fiscal
 */
require_once dirname(__DIR__) . '/nfce/config.php';

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

if ($action === 'getMaxNumber') {
    $serie = (int)($_GET['serie'] ?? 1);
    $ambiente = (int)($_GET['ambiente'] ?? 2);
    $filial_id = $_GET['filial_id'] ?? null;

    try {
        $sql = "SELECT MAX(numero) as max_num FROM nfce_emitidas WHERE ambiente = :amb AND serie = :serie";
        $params = [':amb' => $ambiente, ':serie' => $serie];

        if ($filial_id) {
            $sql .= " AND empresa_id = :fid";
            $params[':fid'] = $filial_id;
        }

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $res = $st->fetch();

        echo json_encode([
            'ok' => true,
            'max' => (int)($res['max_num'] ?? 0)
        ]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Ação inválida']);
