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
            font-size: 11px;
            margin: 0;
        }

        table.sheet {
            border-collapse: collapse;
            table-layout: fixed;
            width: 1280px;
        }

        col.col-a { width: 150px; }
        col.col-b { width: 150px; }
        col.col-c { width: 150px; }
        col.col-d { width: 150px; }
        col.col-e { width: 155px; }
        col.col-f { width: 380px; }
        col.col-g { width: 145px; }

        td,
        th {
            border: 1px solid #d9e2ec;
            padding: 7px 9px;
            text-align: left;
            vertical-align: middle;
            white-space: normal;
        }

        th {
            background: #edf3f8;
            color: #142033;
            font-weight: bold;
        }

        .title {
            background: #0f1f35;
            border-color: #0f1f35;
            color: #ffffff;
            font-size: 24px;
            font-weight: bold;
            padding: 20px 18px;
        }

        .subtitle {
            background: #173b4d;
            border-color: #173b4d;
            color: #dff7ee;
            font-size: 13px;
            font-weight: bold;
            padding: 10px 18px;
        }

        .meta-label {
            background: #f6f8fb;
            color: #667085;
            font-weight: bold;
        }

        .meta-value {
            background: #ffffff;
            color: #142033;
            font-weight: bold;
        }

        .section {
            background: #22324a;
            border-color: #22324a;
            color: #ffffff;
            font-size: 14px;
            font-weight: bold;
            padding: 10px 12px;
        }

        .section-soft {
            background: #e7f7f1;
            border-color: #b8ead3;
            color: #166f5b;
            font-weight: bold;
        }

        .spacer {
            background: #ffffff;
            border-color: #ffffff;
            height: 12px;
            padding: 4px;
        }

        .kpi-label {
            background: #f6f8fb;
            color: #667085;
            font-weight: bold;
        }

        .kpi-value {
            background: #ffffff;
            font-size: 13px;
            font-weight: bold;
        }

        .note {
            background: #f8fbfd;
            color: #667085;
        }

        .money,
        .number,
        .percent {
            text-align: right;
            white-space: nowrap;
        }

        .positive {
            color: #0d8a63;
            font-weight: bold;
        }

        .negative {
            color: #c33f3f;
            font-weight: bold;
        }

        .center {
            text-align: center;
        }

        .empty {
            color: #667085;
            font-style: italic;
            text-align: center;
        }

        .wrap {
            white-space: normal;
        }
    </style>
</head>
<body>
    <table class="sheet">
        <colgroup>
            <col class="col-a">
            <col class="col-b">
            <col class="col-c">
            <col class="col-d">
            <col class="col-e">
            <col class="col-f">
            <col class="col-g">
        </colgroup>

        <tr>
            <td class="title" colspan="7">Relatório financeiro detalhado</td>
        </tr>
        <tr>
            <td class="subtitle" colspan="7"><?= View::e((string) $churchName) ?></td>
        </tr>
        <tr>
            <td class="meta-label">Período</td>
            <td class="meta-value" colspan="2"><?= View::e((string) ($report['periodoLabel'] ?? '')) ?></td>
            <td class="meta-label">Gerado em</td>
            <td class="meta-value" colspan="3"><?= View::e((string) ($report['generatedAt'] ?? '')) ?></td>
        </tr>
        <tr>
            <td class="meta-label">Origem</td>
            <td class="meta-value" colspan="6">Dados reais do banco</td>
        </tr>

        <tr><td class="spacer" colspan="7"></td></tr>

        <tr>
            <td class="section" colspan="7">Resumo executivo</td>
        </tr>
        <tr>
            <th colspan="2">Indicador</th>
            <th>Valor</th>
            <th>Quantidade</th>
            <th colspan="3">Observação</th>
        </tr>
        <tr>
            <td class="kpi-label" colspan="2">Total de entradas</td>
            <td class="kpi-value money positive"><?= View::e($formatCurrency((float) ($summary['entradas'] ?? 0))) ?></td>
            <td class="number"><?= View::e((string) ((int) ($summary['quantidade_entradas'] ?? 0))) ?></td>
            <td class="note" colspan="3">Ticket médio: <?= View::e($formatCurrency((float) ($summary['ticket_medio_entrada'] ?? 0))) ?></td>
        </tr>
        <tr>
            <td class="kpi-label" colspan="2">Total de saídas</td>
            <td class="kpi-value money negative"><?= View::e($formatCurrency((float) ($summary['saidas'] ?? 0))) ?></td>
            <td class="number"><?= View::e((string) ((int) ($summary['quantidade_saidas'] ?? 0))) ?></td>
            <td class="note" colspan="3">Ticket médio: <?= View::e($formatCurrency((float) ($summary['ticket_medio_saida'] ?? 0))) ?></td>
        </tr>
        <tr>
            <td class="kpi-label" colspan="2">Saldo do período</td>
            <td class="kpi-value money <?= (float) ($summary['saldo'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                <?= View::e($formatCurrency((float) ($summary['saldo'] ?? 0))) ?>
            </td>
            <td class="number"><?= View::e((string) ((int) ($summary['quantidade_total'] ?? 0))) ?></td>
            <td class="note" colspan="3">Comprometimento: <?= View::e($formatPercent((float) ($summary['comprometimento'] ?? 0))) ?></td>
        </tr>
        <tr>
            <td class="kpi-label" colspan="2">Maiores lançamentos</td>
            <td class="kpi-value money positive"><?= View::e($formatCurrency((float) ($summary['maior_entrada'] ?? 0))) ?></td>
            <td class="kpi-value money negative"><?= View::e($formatCurrency((float) ($summary['maior_saida'] ?? 0))) ?></td>
            <td class="note" colspan="3">Maior entrada / maior saída</td>
        </tr>

        <tr><td class="spacer" colspan="7"></td></tr>

        <tr>
            <td class="section" colspan="7">Fluxo diário</td>
        </tr>
        <tr>
            <th>Data</th>
            <th colspan="2">Entradas</th>
            <th colspan="2">Saídas</th>
            <th colspan="2">Saldo</th>
        </tr>
        <?php if ($daily === []): ?>
            <tr><td class="empty" colspan="7">Nenhum fluxo diário encontrado para o período.</td></tr>
        <?php else: ?>
            <?php foreach ($daily as $day): ?>
                <tr>
                    <td><?= View::e($formatDate($day['data'] ?? null)) ?></td>
                    <td class="money positive" colspan="2"><?= View::e($formatCurrency((float) ($day['entradas'] ?? 0))) ?></td>
                    <td class="money negative" colspan="2"><?= View::e($formatCurrency((float) ($day['saidas'] ?? 0))) ?></td>
                    <td class="money <?= (float) ($day['saldo'] ?? 0) >= 0 ? 'positive' : 'negative' ?>" colspan="2">
                        <?= View::e($formatCurrency((float) ($day['saldo'] ?? 0))) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>

        <tr><td class="spacer" colspan="7"></td></tr>

        <tr>
            <td class="section" colspan="7">Saídas por categoria</td>
        </tr>
        <tr>
            <th colspan="3">Categoria</th>
            <th>Quantidade</th>
            <th>Total</th>
            <th colspan="2">Percentual</th>
        </tr>
        <?php if ($categorias === []): ?>
            <tr><td class="empty" colspan="7">Nenhuma saída por categoria encontrada para o período.</td></tr>
        <?php else: ?>
            <?php foreach ($categorias as $categoria): ?>
                <tr>
                    <td colspan="3"><?= View::e($categoria['nome']) ?></td>
                    <td class="number"><?= View::e((string) $categoria['quantidade']) ?></td>
                    <td class="money negative"><?= View::e($formatCurrency((float) $categoria['total'])) ?></td>
                    <td class="percent" colspan="2"><?= View::e($formatPercent((float) $categoria['percentual'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>

        <tr><td class="spacer" colspan="7"></td></tr>

        <tr>
            <td class="section" colspan="7">Formas de pagamento</td>
        </tr>
        <tr>
            <th colspan="3">Forma</th>
            <th>Quantidade</th>
            <th colspan="3">Total movimentado</th>
        </tr>
        <?php if ($formasPagamento === []): ?>
            <tr><td class="empty" colspan="7">Nenhuma forma de pagamento encontrada para o período.</td></tr>
        <?php else: ?>
            <?php foreach ($formasPagamento as $payment): ?>
                <tr>
                    <td colspan="3"><?= View::e($payment['nome']) ?></td>
                    <td class="number"><?= View::e((string) $payment['quantidade']) ?></td>
                    <td class="money" colspan="3"><?= View::e($formatCurrency((float) $payment['total'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>

        <tr><td class="spacer" colspan="7"></td></tr>

        <tr>
            <td class="section" colspan="7">Movimentações detalhadas</td>
        </tr>
        <tr>
            <td class="section-soft" colspan="7">Entradas aparecem em verde; saídas aparecem em vermelho para facilitar auditoria visual.</td>
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
            <tr><td class="empty" colspan="7">Nenhuma movimentação encontrada para o período.</td></tr>
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
