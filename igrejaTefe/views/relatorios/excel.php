<?php

use App\Core\View;

$report = is_array($report ?? null) ? $report : [];
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$categorias = is_array($report['categorias'] ?? null) ? $report['categorias'] : [];
$formasPagamento = is_array($report['formasPagamento'] ?? null) ? $report['formasPagamento'] : [];
$daily = is_array($report['daily'] ?? null) ? $report['daily'] : [];
$movimentos = is_array($report['movimentos'] ?? null) ? $report['movimentos'] : [];
$formatCurrency = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
$formatPercent = static fn (float $value): string => number_format($value, 1, ',', '.') . '%';
$formatDate = static function (?string $date): string {
    $timestamp = $date ? strtotime($date) : false;

    return $timestamp ? date('d/m/Y', $timestamp) : '-';
};
$paymentLabel = static function (?string $payment): string {
    $payment = (string) $payment;

    return [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'cartao' => 'Cartão',
        'transferencia' => 'Transferência',
        'boleto' => 'Boleto',
        'outro' => 'Outro',
    ][$payment] ?? ucfirst(str_replace('_', ' ', $payment));
};
?>
<!doctype html>
<html lang="pt-BR" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta charset="utf-8">
    <style>
        body {
            background: #ffffff;
            color: #142033;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 18px;
        }

        th,
        td {
            border: 1px solid #d9e2ec;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
            white-space: normal;
        }

        th {
            background: #eef4f8;
            color: #142033;
            font-weight: bold;
        }

        .cover-title td {
            background: #101b2d;
            border-color: #101b2d;
            color: #ffffff;
            font-size: 22px;
            font-weight: bold;
            padding: 18px 16px;
        }

        .cover-subtitle td {
            background: #123549;
            border-color: #123549;
            color: #ddf8ec;
            font-size: 13px;
            font-weight: bold;
            padding: 10px 16px;
        }

        .meta-label {
            background: #f6f8fb;
            color: #667085;
            font-weight: bold;
            width: 160px;
        }

        .section-title td {
            background: #2faf8f;
            border-color: #2faf8f;
            color: #ffffff;
            font-size: 15px;
            font-weight: bold;
            padding: 10px 12px;
        }

        .section-note td {
            background: #f6f8fb;
            color: #667085;
            font-style: italic;
        }

        .metric-label {
            background: #f6f8fb;
            color: #667085;
            font-weight: bold;
        }

        .money,
        .number,
        .percent {
            text-align: right;
            white-space: nowrap;
        }

        .positive {
            color: #20966f;
            font-weight: bold;
        }

        .negative {
            color: #c84d4d;
            font-weight: bold;
        }

        .muted {
            color: #667085;
        }

        .empty td {
            color: #667085;
            text-align: center;
        }

        .wrap {
            width: 360px;
        }
    </style>
</head>
<body>
    <table>
        <tr class="cover-title">
            <td colspan="7">Relatório financeiro detalhado</td>
        </tr>
        <tr class="cover-subtitle">
            <td colspan="7"><?= View::e((string) $churchName) ?></td>
        </tr>
        <tr>
            <td class="meta-label">Período</td>
            <td colspan="6"><?= View::e((string) ($report['periodoLabel'] ?? '')) ?></td>
        </tr>
        <tr>
            <td class="meta-label">Gerado em</td>
            <td colspan="6"><?= View::e((string) ($report['generatedAt'] ?? '')) ?></td>
        </tr>
        <tr>
            <td class="meta-label">Origem</td>
            <td colspan="6">Dados reais do banco</td>
        </tr>
    </table>

    <table>
        <tr class="section-title"><td colspan="4">Resumo executivo</td></tr>
        <tr>
            <th>Indicador</th>
            <th>Valor</th>
            <th>Quantidade</th>
            <th>Observação</th>
        </tr>
        <tr>
            <td class="metric-label">Total de entradas</td>
            <td class="money positive"><?= View::e($formatCurrency((float) ($summary['entradas'] ?? 0))) ?></td>
            <td class="number"><?= View::e((string) ((int) ($summary['quantidade_entradas'] ?? 0))) ?></td>
            <td>Ticket médio: <?= View::e($formatCurrency((float) ($summary['ticket_medio_entrada'] ?? 0))) ?></td>
        </tr>
        <tr>
            <td class="metric-label">Total de saídas</td>
            <td class="money negative"><?= View::e($formatCurrency((float) ($summary['saidas'] ?? 0))) ?></td>
            <td class="number"><?= View::e((string) ((int) ($summary['quantidade_saidas'] ?? 0))) ?></td>
            <td>Ticket médio: <?= View::e($formatCurrency((float) ($summary['ticket_medio_saida'] ?? 0))) ?></td>
        </tr>
        <tr>
            <td class="metric-label">Saldo do período</td>
            <td class="money <?= (float) ($summary['saldo'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                <?= View::e($formatCurrency((float) ($summary['saldo'] ?? 0))) ?>
            </td>
            <td class="number"><?= View::e((string) ((int) ($summary['quantidade_total'] ?? 0))) ?></td>
            <td>Comprometimento: <?= View::e($formatPercent((float) ($summary['comprometimento'] ?? 0))) ?></td>
        </tr>
        <tr>
            <td class="metric-label">Maiores lançamentos</td>
            <td class="money positive"><?= View::e($formatCurrency((float) ($summary['maior_entrada'] ?? 0))) ?></td>
            <td class="money negative"><?= View::e($formatCurrency((float) ($summary['maior_saida'] ?? 0))) ?></td>
            <td>Maior entrada / maior saída</td>
        </tr>
    </table>

    <table>
        <tr class="section-title"><td colspan="4">Fluxo diário</td></tr>
        <tr>
            <th>Data</th>
            <th>Entradas</th>
            <th>Saídas</th>
            <th>Saldo</th>
        </tr>
        <?php if ($daily === []): ?>
            <tr class="empty"><td colspan="4">Nenhum fluxo diário encontrado para o período.</td></tr>
        <?php else: ?>
            <?php foreach ($daily as $day): ?>
                <tr>
                    <td><?= View::e($formatDate($day['data'] ?? null)) ?></td>
                    <td class="money positive"><?= View::e($formatCurrency((float) ($day['entradas'] ?? 0))) ?></td>
                    <td class="money negative"><?= View::e($formatCurrency((float) ($day['saidas'] ?? 0))) ?></td>
                    <td class="money <?= (float) ($day['saldo'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                        <?= View::e($formatCurrency((float) ($day['saldo'] ?? 0))) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <table>
        <tr class="section-title"><td colspan="4">Saídas por categoria</td></tr>
        <tr>
            <th>Categoria</th>
            <th>Quantidade</th>
            <th>Total</th>
            <th>Percentual</th>
        </tr>
        <?php if ($categorias === []): ?>
            <tr class="empty"><td colspan="4">Nenhuma saída por categoria encontrada para o período.</td></tr>
        <?php else: ?>
            <?php foreach ($categorias as $categoria): ?>
                <tr>
                    <td><?= View::e($categoria['nome']) ?></td>
                    <td class="number"><?= View::e((string) $categoria['quantidade']) ?></td>
                    <td class="money negative"><?= View::e($formatCurrency((float) $categoria['total'])) ?></td>
                    <td class="percent"><?= View::e($formatPercent((float) $categoria['percentual'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <table>
        <tr class="section-title"><td colspan="3">Formas de pagamento</td></tr>
        <tr>
            <th>Forma</th>
            <th>Quantidade</th>
            <th>Total movimentado</th>
        </tr>
        <?php if ($formasPagamento === []): ?>
            <tr class="empty"><td colspan="3">Nenhuma forma de pagamento encontrada para o período.</td></tr>
        <?php else: ?>
            <?php foreach ($formasPagamento as $payment): ?>
                <tr>
                    <td><?= View::e($payment['nome']) ?></td>
                    <td class="number"><?= View::e((string) $payment['quantidade']) ?></td>
                    <td class="money"><?= View::e($formatCurrency((float) $payment['total'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <table>
        <tr class="section-title"><td colspan="7">Movimentações detalhadas</td></tr>
        <tr class="section-note">
            <td colspan="7">Entradas aparecem em verde; saídas aparecem em vermelho para facilitar auditoria visual.</td>
        </tr>
        <tr>
            <th>Data</th>
            <th>Tipo</th>
            <th>Categoria</th>
            <th>Origem/Fornecedor</th>
            <th>Pagamento</th>
            <th>Descrição</th>
            <th>Valor</th>
        </tr>
        <?php if ($movimentos === []): ?>
            <tr class="empty"><td colspan="7">Nenhuma movimentação encontrada para o período.</td></tr>
        <?php else: ?>
            <?php foreach ($movimentos as $item): ?>
                <?php $isEntrada = ($item['movimento'] ?? '') === 'entrada'; ?>
                <tr>
                    <td><?= View::e($formatDate($item['data'] ?? null)) ?></td>
                    <td class="<?= $isEntrada ? 'positive' : 'negative' ?>"><?= $isEntrada ? 'Entrada' : 'Saída' ?></td>
                    <td><?= View::e($item['categoria_nome'] ?? '-') ?></td>
                    <td><?= View::e($item['pessoa'] ?? '-') ?></td>
                    <td><?= View::e($paymentLabel($item['forma_pagamento'] ?? null)) ?></td>
                    <td class="wrap"><?= View::e($item['descricao'] ?? '-') ?></td>
                    <td class="money <?= $isEntrada ? 'positive' : 'negative' ?>">
                        <?= $isEntrada ? '+' : '-' ?><?= View::e($formatCurrency((float) ($item['valor'] ?? 0))) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</body>
</html>
