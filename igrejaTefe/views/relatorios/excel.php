<?php

use App\Core\View;

$report = is_array($report ?? null) ? $report : [];
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$categorias = is_array($report['categorias'] ?? null) ? $report['categorias'] : [];
$formasPagamento = is_array($report['formasPagamento'] ?? null) ? $report['formasPagamento'] : [];
$movimentos = is_array($report['movimentos'] ?? null) ? $report['movimentos'] : [];
$formatCurrency = static fn (float $value): string => number_format($value, 2, ',', '.');
$formatDate = static function (?string $date): string {
    $timestamp = $date ? strtotime($date) : false;

    return $timestamp ? date('d/m/Y', $timestamp) : '-';
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 24px; }
        th, td { border: 1px solid #d9e2ec; padding: 8px; text-align: left; }
        th { background: #eef4f8; color: #142033; }
        h1, h2 { color: #142033; }
    </style>
</head>
<body>
    <h1><?= View::e((string) $churchName) ?> - Relatório financeiro</h1>
    <p>Período: <?= View::e((string) ($report['periodoLabel'] ?? '')) ?></p>
    <p>Gerado em: <?= View::e((string) ($report['generatedAt'] ?? '')) ?></p>
    <p>Origem: Dados reais do banco</p>

    <h2>Resumo</h2>
    <table>
        <tr><th>Indicador</th><th>Valor</th></tr>
        <tr><td>Entradas</td><td><?= View::e($formatCurrency((float) ($summary['entradas'] ?? 0))) ?></td></tr>
        <tr><td>Saídas</td><td><?= View::e($formatCurrency((float) ($summary['saidas'] ?? 0))) ?></td></tr>
        <tr><td>Saldo</td><td><?= View::e($formatCurrency((float) ($summary['saldo'] ?? 0))) ?></td></tr>
        <tr><td>Movimentações</td><td><?= View::e((string) ((int) ($summary['quantidade_total'] ?? 0))) ?></td></tr>
    </table>

    <h2>Saídas por categoria</h2>
    <table>
        <tr><th>Categoria</th><th>Quantidade</th><th>Total</th><th>Percentual</th></tr>
        <?php foreach ($categorias as $categoria): ?>
            <tr>
                <td><?= View::e($categoria['nome']) ?></td>
                <td><?= View::e((string) $categoria['quantidade']) ?></td>
                <td><?= View::e($formatCurrency((float) $categoria['total'])) ?></td>
                <td><?= View::e(number_format((float) $categoria['percentual'], 1, ',', '.')) ?>%</td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Formas de pagamento</h2>
    <table>
        <tr><th>Forma</th><th>Quantidade</th><th>Total</th></tr>
        <?php foreach ($formasPagamento as $payment): ?>
            <tr>
                <td><?= View::e($payment['nome']) ?></td>
                <td><?= View::e((string) $payment['quantidade']) ?></td>
                <td><?= View::e($formatCurrency((float) $payment['total'])) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Movimentações</h2>
    <table>
        <tr>
            <th>Data</th>
            <th>Tipo</th>
            <th>Categoria</th>
            <th>Origem/Fornecedor</th>
            <th>Pagamento</th>
            <th>Descrição</th>
            <th>Valor</th>
        </tr>
        <?php foreach ($movimentos as $item): ?>
            <tr>
                <td><?= View::e($formatDate($item['data'])) ?></td>
                <td><?= $item['movimento'] === 'entrada' ? 'Entrada' : 'Saída' ?></td>
                <td><?= View::e($item['categoria_nome']) ?></td>
                <td><?= View::e($item['pessoa']) ?></td>
                <td><?= View::e($item['forma_pagamento']) ?></td>
                <td><?= View::e($item['descricao']) ?></td>
                <td><?= ($item['movimento'] === 'entrada' ? '' : '-') . View::e($formatCurrency((float) $item['valor'])) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
