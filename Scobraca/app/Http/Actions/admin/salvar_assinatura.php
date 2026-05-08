<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_platform_admin();
verify_csrf();

$empresaId = (int) ($_POST['empresa_id'] ?? 0);
$planoId = (int) ($_POST['plano_id'] ?? 0);
$status = (string) ($_POST['status'] ?? 'teste');
$valorBruto = trim((string) ($_POST['valor'] ?? ''));
$dataInicio = trim((string) ($_POST['data_inicio'] ?? ''));
$dataVencimento = trim((string) ($_POST['data_vencimento'] ?? ''));

$permitidos = ['teste', 'ativa', 'vencida', 'cancelada', 'bloqueada'];
if (!in_array($status, $permitidos, true)) {
    $status = 'teste';
}

$valor = decimal_from_input($valorBruto);
$inicioValido = DateTime::createFromFormat('Y-m-d', $dataInicio);
$vencimentoValido = DateTime::createFromFormat('Y-m-d', $dataVencimento);

if (
    $empresaId <= 0 ||
    $planoId <= 0 ||
    $valor <= 0 ||
    !$inicioValido ||
    !$vencimentoValido ||
    $inicioValido->format('Y-m-d') !== $dataInicio ||
    $vencimentoValido->format('Y-m-d') !== $dataVencimento
) {
    flash('error', 'Preencha os dados da assinatura corretamente.');
    redirect('/admin/assinaturas-cadastro.php');
}

try {
    $stmt = db()->prepare(
        "INSERT INTO assinaturas (empresa_id, plano_id, status, valor, data_inicio, data_vencimento, criado_em)
         VALUES (:empresa_id, :plano_id, :status, :valor, :data_inicio, :data_vencimento, NOW())"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':plano_id' => $planoId,
        ':status' => $status,
        ':valor' => $valor,
        ':data_inicio' => $dataInicio,
        ':data_vencimento' => $dataVencimento,
    ]);

    flash('success', 'Assinatura cadastrada com sucesso.');
    redirect('/admin/assinaturas.php');
} catch (Throwable $e) {
    error_log('[SALVAR ASSINATURA] ' . $e->getMessage());
    flash('error', 'Não foi possível cadastrar a assinatura.');
    redirect('/admin/assinaturas-cadastro.php');
}
