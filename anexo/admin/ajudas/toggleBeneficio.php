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
$to = isset($_GET['to']) ? trim((string)$_GET['to']) : 'Ativa';
if (!$id || !in_array($to, ['Ativa','Inativa'], true)) {
    header('Location: ../beneficiosCadastrados.php?err=' . rawurlencode('Parâmetros inválidos'));
    exit;
}

$st = $pdo->prepare("UPDATE ajudas_tipos SET status=:s WHERE id=:id");
$ok = $st->execute([':s'=>$to, ':id'=>$id]);

$qs = $ok ? 'ok=1' : ('err=' . rawurlencode('Falha ao atualizar status'));
header('Location: ../beneficiosCadastrados.php?' . $qs);
