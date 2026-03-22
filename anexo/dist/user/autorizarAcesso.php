<?php
// autorizarAcesso.php
// Recebe POST com id, valida e marca autorizado='sim'. Mensagens via alert().
// Erros: alert + history.back(). Sucesso: alert + redirect para contasPendentes.php

require_once __DIR__ . '/../assets/conexao.php'; // $pdo (PDO)

header('Content-Type: text/html; charset=utf-8');

function js_alert_back(string $msg): void {
    $m = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    echo "<script>alert('{$m}'); history.back();</script>";
    exit;
}
function js_alert_redirect(string $msg, string $to): void {
    $m = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    $t = htmlspecialchars($to, ENT_QUOTES, 'UTF-8');
    echo "<script>alert('{$m}'); window.location.href='{$t}';</script>";
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    js_alert_back('Erro: conexão com o banco não encontrada.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    js_alert_back('Método inválido.');
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    js_alert_back('ID inválido.');
}

try {
    // Verifica se existe e se já não está autorizado
    $stmt = $pdo->prepare("SELECT id, nome, autorizado FROM contas_acesso WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        js_alert_back('Conta não encontrada.');
    }
    if ($row['autorizado'] === 'sim') {
        js_alert_redirect('Esta conta já está autorizada.', '../usuariosNaoPermitidos.php');
    }

    // Atualiza para autorizado = 'sim'
    $up = $pdo->prepare("UPDATE contas_acesso 
                            SET autorizado='sim', updated_at=NOW() 
                          WHERE id=:id");
    $up->execute([':id' => $id]);

    if ($up->rowCount() < 1) {
        // Nenhuma linha alterada (concorrência ou mesmo valor)
        js_alert_redirect('Nada a atualizar: a conta já estava autorizada ou não houve alteração.', '../usuariosNaoPermitidos.php');
    }

    js_alert_redirect('Acesso autorizado com sucesso!', '../usuariosNaoPermitidos.php');

} catch (Throwable $e) {
    js_alert_back('Erro ao autorizar: ' . $e->getMessage());
}

?>