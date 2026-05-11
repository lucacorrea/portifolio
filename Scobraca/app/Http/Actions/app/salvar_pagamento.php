<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_tenant_user();
verify_csrf();

function pagamento_redirect_with_error(string $message): never
{
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    flash('error', $message);
    redirect('/app/pagamentos-cadastro.php');
}

function pagamento_date_from_input(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

    if (!$date || $date->format('Y-m-d') !== trim($value)) {
        return null;
    }

    return $date;
}

function atualizar_status_cobranca(int $empresaId, int $cobrancaId): void
{
    $stmt = db()->prepare(
        "SELECT cb.valor, cb.data_vencimento, COALESCE(SUM(p.valor_pago), 0) AS total_pago
         FROM cobrancas cb
         LEFT JOIN pagamentos p ON p.cobranca_id = cb.id AND p.empresa_id = cb.empresa_id
         WHERE cb.id = :cobranca_id AND cb.empresa_id = :empresa_id
         GROUP BY cb.id, cb.valor, cb.data_vencimento"
    );
    $stmt->execute([
        ':cobranca_id' => $cobrancaId,
        ':empresa_id' => $empresaId,
    ]);
    $cobranca = $stmt->fetch();

    if (!$cobranca) {
        return;
    }

    $novoStatus = ((float) $cobranca['total_pago'] + 0.005) >= (float) $cobranca['valor']
        ? 'Paga'
        : (strtotime((string) $cobranca['data_vencimento']) < strtotime(date('Y-m-d')) ? 'Vencida' : 'Em aberto');

    db()->prepare(
        "UPDATE cobrancas
         SET status = :status, atualizado_em = NOW()
         WHERE id = :id AND empresa_id = :empresa_id"
    )->execute([
        ':status' => $novoStatus,
        ':id' => $cobrancaId,
        ':empresa_id' => $empresaId,
    ]);
}

$empresaId = current_empresa_id();
$clienteId = (int) ($_POST['cliente_id'] ?? 0);
$cobrancaId = (int) ($_POST['cobranca_id'] ?? 0);
$valorPago = decimal_from_input((string) ($_POST['valor_pago'] ?? ''));
$dataPagamento = pagamento_date_from_input((string) ($_POST['data_pagamento'] ?? ''));
$formaPagamento = trim((string) ($_POST['forma_pagamento'] ?? 'PIX'));
$observacao = trim((string) ($_POST['observacao'] ?? ''));

if (!$empresaId) {
    pagamento_redirect_with_error('Sessão da empresa inválida.');
}

if ($valorPago <= 0) {
    pagamento_redirect_with_error('Informe um valor recebido maior que zero.');
}

if (!$dataPagamento) {
    pagamento_redirect_with_error('Informe uma data de pagamento válida.');
}

if (!in_array($formaPagamento, ['PIX', 'Cartão', 'Boleto', 'Dinheiro'], true)) {
    $formaPagamento = 'PIX';
}

try {
    db()->beginTransaction();

    if ($cobrancaId > 0) {
        $stmt = db()->prepare(
            "SELECT id, cliente_id, valor, status
             FROM cobrancas
             WHERE id = :cobranca_id AND empresa_id = :empresa_id
             FOR UPDATE"
        );
        $stmt->execute([
            ':cobranca_id' => $cobrancaId,
            ':empresa_id' => $empresaId,
        ]);
        $cobranca = $stmt->fetch();

        if (!$cobranca) {
            pagamento_redirect_with_error('Cobrança não encontrada para esta empresa.');
        }

        if ((string) $cobranca['status'] === 'Cancelada') {
            pagamento_redirect_with_error('Não é possível registrar pagamento em cobrança cancelada.');
        }

        $stmt = db()->prepare(
            "SELECT COALESCE(SUM(valor_pago), 0)
             FROM pagamentos
             WHERE cobranca_id = :cobranca_id AND empresa_id = :empresa_id"
        );
        $stmt->execute([
            ':cobranca_id' => $cobrancaId,
            ':empresa_id' => $empresaId,
        ]);

        $clienteId = (int) $cobranca['cliente_id'];
        $saldo = round((float) $cobranca['valor'] - (float) $stmt->fetchColumn(), 2);

        if ($saldo <= 0) {
            pagamento_redirect_with_error('Esta cobrança já está quitada.');
        }

        if ($valorPago > $saldo + 0.005) {
            pagamento_redirect_with_error('O valor recebido não pode ser maior que o saldo da cobrança.');
        }
    } else {
        $stmt = db()->prepare("SELECT id FROM clientes WHERE id = :id AND empresa_id = :empresa_id LIMIT 1");
        $stmt->execute([
            ':id' => $clienteId,
            ':empresa_id' => $empresaId,
        ]);

        if (!$stmt->fetch()) {
            pagamento_redirect_with_error('Selecione um cliente válido ou uma cobrança vinculada.');
        }

        $cobrancaId = null;
    }

    $stmt = db()->prepare(
        "INSERT INTO pagamentos (
            empresa_id,
            cliente_id,
            cobranca_id,
            valor_pago,
            data_pagamento,
            forma_pagamento,
            observacao,
            criado_em
        ) VALUES (
            :empresa_id,
            :cliente_id,
            :cobranca_id,
            :valor_pago,
            :data_pagamento,
            :forma_pagamento,
            :observacao,
            NOW()
        )"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':cliente_id' => $clienteId,
        ':cobranca_id' => $cobrancaId,
        ':valor_pago' => $valorPago,
        ':data_pagamento' => $dataPagamento->format('Y-m-d'),
        ':forma_pagamento' => $formaPagamento,
        ':observacao' => $observacao,
    ]);

    if ($cobrancaId !== null) {
        atualizar_status_cobranca($empresaId, (int) $cobrancaId);
    }

    db()->commit();
    flash('success', 'Pagamento registrado com sucesso.');
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    error_log('[SALVAR PAGAMENTO] ' . $e->getMessage());
    flash('error', 'Não foi possível registrar o pagamento.');
    redirect('/app/pagamentos-cadastro.php');
}

redirect('/app/pagamentos.php');
