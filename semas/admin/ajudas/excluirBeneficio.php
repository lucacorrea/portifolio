<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/authGuard.php';
auth_guard();

require_once __DIR__ . '/../../dist/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    header('Location: ../beneficiosCadastrados.php?err=' . rawurlencode('Sem conexão'));
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: ../beneficiosCadastrados.php?err=' . rawurlencode('ID inválido'));
    exit;
}

/* bloqueia se houver entregas usando este tipo */
$st = $pdo->prepare("SELECT COUNT(*) FROM ajudas_entregas WHERE ajuda_tipo_id = :id");
$st->execute([':id'=>$id]);
if ((int)$st->fetchColumn() > 0) {
    header('Location: ../beneficiosCadastrados.php?err=' . rawurlencode('Existem entregas vinculadas; não pode excluir.'));
    exit;
}

/* exclui */
$del = $pdo->prepare("DELETE FROM ajudas_tipos WHERE id = :id");
$ok = $del->execute([':id'=>$id]);

$qs = $ok ? 'ok=1' : ('err=' . rawurlencode('Falha ao excluir'));
header('Location: ../beneficiosCadastrados.php?' . $qs);
