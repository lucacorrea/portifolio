<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_tenant_user();
verify_csrf();

function cobranca_redirect_with_error(string $message): never
{
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    flash('error', $message);
    redirect('/app/cobrancas-cadastro.php');
}

function date_from_input(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

    if (!$date || $date->format('Y-m-d') !== trim($value)) {
        return null;
    }

    return $date;
}

function add_months_preserving_day(DateTimeImmutable $date, int $months): DateTimeImmutable
{
    $day = (int) $date->format('d');
    $targetMonth = $date->modify('first day of this month')->modify('+' . $months . ' months');
    $lastDay = (int) $targetMonth->format('t');

    return $targetMonth->setDate(
        (int) $targetMonth->format('Y'),
        (int) $targetMonth->format('m'),
        min($day, $lastDay)
    );
}

function insert_cobranca(array $data): void
{
    $stmt = db()->prepare(
        "INSERT INTO cobrancas (
            empresa_id,
            cliente_id,
            referencia,
            tipo,
            descricao,
            grupo_parcelamento_id,
            numero_parcela,
            total_parcelas,
            valor_total,
            valor_entrada,
            valor,
            data_vencimento,
            status,
            criado_em
        ) VALUES (
            :empresa_id,
            :cliente_id,
            :referencia,
            :tipo,
            :descricao,
            :grupo_parcelamento_id,
            :numero_parcela,
            :total_parcelas,
            :valor_total,
            :valor_entrada,
            :valor,
            :data_vencimento,
            :status,
            NOW()
        )"
    );
    $stmt->execute($data);
}

$empresaId = current_empresa_id();
$clienteId = (int) ($_POST['cliente_id'] ?? 0);
$tipo = (string) ($_POST['tipo'] ?? 'mensalidade');
$status = (string) ($_POST['status'] ?? 'Em aberto');
$descricao = trim((string) ($_POST['descricao'] ?? ''));

if (!$empresaId || $clienteId <= 0) {
    cobranca_redirect_with_error('Selecione um cliente válido.');
}

if (!in_array($tipo, ['mensalidade', 'parcelada'], true)) {
    cobranca_redirect_with_error('Selecione o tipo de cobrança.');
}

if (!in_array($status, ['Em aberto', 'Paga', 'Vencida', 'Cancelada'], true)) {
    $status = 'Em aberto';
}

$stmt = db()->prepare("SELECT id FROM clientes WHERE id = :id AND empresa_id = :empresa_id LIMIT 1");
$stmt->execute([
    ':id' => $clienteId,
    ':empresa_id' => $empresaId,
]);

if (!$stmt->fetch()) {
    cobranca_redirect_with_error('Cliente não encontrado para esta empresa.');
}

try {
    db()->beginTransaction();

    if ($tipo === 'mensalidade') {
        $referencia = trim((string) ($_POST['referencia'] ?? ''));
        $valor = decimal_from_input((string) ($_POST['valor_mensalidade'] ?? ''));
        $vencimento = date_from_input((string) ($_POST['data_vencimento'] ?? ''));

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $referencia)) {
            cobranca_redirect_with_error('Informe a referência no formato YYYY-MM.');
        }

        if ($valor <= 0) {
            cobranca_redirect_with_error('Informe um valor de mensalidade maior que zero.');
        }

        if (!$vencimento) {
            cobranca_redirect_with_error('Informe uma data de vencimento válida.');
        }

        $stmt = db()->prepare(
            "SELECT COUNT(*)
             FROM cobrancas
             WHERE empresa_id = :empresa_id
               AND cliente_id = :cliente_id
               AND referencia = :referencia
               AND tipo = 'mensalidade'
               AND status <> 'Cancelada'"
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':referencia' => $referencia,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            cobranca_redirect_with_error('Já existe uma mensalidade ativa para este cliente nesta referência.');
        }

        insert_cobranca([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':referencia' => $referencia,
            ':tipo' => 'mensalidade',
            ':descricao' => $descricao !== '' ? $descricao : 'Mensalidade fixa',
            ':grupo_parcelamento_id' => null,
            ':numero_parcela' => 1,
            ':total_parcelas' => 1,
            ':valor_total' => $valor,
            ':valor_entrada' => 0,
            ':valor' => $valor,
            ':data_vencimento' => $vencimento->format('Y-m-d'),
            ':status' => $status,
        ]);

        db()->commit();
        flash('success', 'Mensalidade cadastrada com sucesso.');
        redirect('/app/cobrancas.php');
    }

    $valorTotal = decimal_from_input((string) ($_POST['valor_total'] ?? ''));
    $valorEntrada = decimal_from_input((string) ($_POST['valor_entrada'] ?? '0'));
    $parcelas = (int) ($_POST['quantidade_parcelas'] ?? 0);
    $primeiroVencimento = date_from_input((string) ($_POST['data_primeiro_vencimento'] ?? ''));

    if ($valorTotal <= 0) {
        cobranca_redirect_with_error('Informe o valor total do parcelamento.');
    }

    if ($valorEntrada < 0 || $valorEntrada >= $valorTotal) {
        cobranca_redirect_with_error('A entrada deve ser menor que o valor total.');
    }

    if ($parcelas < 1 || $parcelas > 60) {
        cobranca_redirect_with_error('Informe entre 1 e 60 parcelas.');
    }

    if (!$primeiroVencimento) {
        cobranca_redirect_with_error('Informe a data de vencimento da entrada ou primeira parcela.');
    }

    $grupoId = bin2hex(random_bytes(16));
    $saldoParcelado = round($valorTotal - $valorEntrada, 2);
    $valorBaseParcela = round($saldoParcelado / $parcelas, 2);
    $descricaoBase = $descricao !== '' ? $descricao : 'Parcelamento';

    if ($valorEntrada > 0) {
        insert_cobranca([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':referencia' => $primeiroVencimento->format('Y-m'),
            ':tipo' => 'parcelada',
            ':descricao' => $descricaoBase . ' - entrada',
            ':grupo_parcelamento_id' => $grupoId,
            ':numero_parcela' => 0,
            ':total_parcelas' => $parcelas,
            ':valor_total' => $valorTotal,
            ':valor_entrada' => $valorEntrada,
            ':valor' => $valorEntrada,
            ':data_vencimento' => $primeiroVencimento->format('Y-m-d'),
            ':status' => $status,
        ]);
    }

    for ($numeroParcela = 1; $numeroParcela <= $parcelas; $numeroParcela++) {
        $offsetMeses = $valorEntrada > 0 ? $numeroParcela : $numeroParcela - 1;
        $vencimento = add_months_preserving_day($primeiroVencimento, $offsetMeses);
        $valorParcela = $numeroParcela === $parcelas
            ? round($saldoParcelado - ($valorBaseParcela * ($parcelas - 1)), 2)
            : $valorBaseParcela;

        insert_cobranca([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':referencia' => $vencimento->format('Y-m'),
            ':tipo' => 'parcelada',
            ':descricao' => $descricaoBase . ' - parcela ' . $numeroParcela . '/' . $parcelas,
            ':grupo_parcelamento_id' => $grupoId,
            ':numero_parcela' => $numeroParcela,
            ':total_parcelas' => $parcelas,
            ':valor_total' => $valorTotal,
            ':valor_entrada' => $valorEntrada,
            ':valor' => $valorParcela,
            ':data_vencimento' => $vencimento->format('Y-m-d'),
            ':status' => $status,
        ]);
    }

    db()->commit();
    flash('success', 'Parcelamento cadastrado com sucesso.');
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    error_log('[SALVAR COBRANÇA] ' . $e->getMessage());
    flash('error', 'Não foi possível salvar a cobrança. Verifique se a migração de parcelamento foi aplicada e tente novamente.');
    redirect('/app/cobrancas-cadastro.php');
}

redirect('/app/cobrancas.php');
