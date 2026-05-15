<?php

use App\Core\View;

$report = is_array($report ?? null) ? $report : [];
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$categorias = is_array($report['categorias'] ?? null) ? $report['categorias'] : [];
$movimentos = is_array($report['movimentos'] ?? null) ? $report['movimentos'] : [];
$formatCurrency = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
$formatDate = static function (?string $date): string {
    $timestamp = $date ? strtotime($date) : false;

    return $timestamp ? date('d/m/Y', $timestamp) : '-';
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($title ?? 'Relatório financeiro') ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef4f8; color: #142033; font-family: Inter, Arial, sans-serif; }
        .print-actions { position: sticky; top: 0; display: flex; justify-content: flex-end; gap: 10px; padding: 14px 24px; background: #fff; border-bottom: 1px solid #e5eaf1; }
        .print-actions button { border: 0; border-radius: 12px; background: #2faf8f; color: #fff; cursor: pointer; font-weight: 800; padding: 10px 16px; }
        .sheet { width: min(1120px, calc(100% - 32px)); margin: 24px auto; background: #fff; border-radius: 18px; box-shadow: 0 18px 50px rgba(20,32,51,.12); padding: 34px; }
        header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #e5eaf1; padding-bottom: 18px; }
        h1, h2 { margin: 0; }
        h1 { font-size: 28px; }
        h2 { font-size: 18px; margin: 28px 0 12px; }
        .muted { color: #667085; }
        .badge { display: inline-flex; border-radius: 999px; background: #ddf8ec; color: #21866e; font-weight: 800; padding: 7px 11px; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 22px; }
        .summary div { border: 1px solid #e5eaf1; border-radius: 14px; padding: 14px; }
        .summary span { display: block; color: #667085; font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .summary strong { display: block; margin-top: 6px; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        th, td { border-bottom: 1px solid #e5eaf1; padding: 10px 8px; text-align: left; vertical-align: top; }
        th { background: #f6f8fb; color: #667085; font-size: 11px; text-transform: uppercase; }
        tr { page-break-inside: avoid; }
        .positive { color: #20966f; font-weight: 800; }
        .negative { color: #c84d4d; font-weight: 800; }
        @media print {
            body { background: #fff; }
            .print-actions { display: none; }
            .sheet { width: 100%; margin: 0; border-radius: 0; box-shadow: none; padding: 0; }
            @page { margin: 14mm; }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button type="button" onclick="window.print()">Salvar como PDF</button>
    </div>

    <main class="sheet">
        <header>
            <div>
                <p class="muted">Relatório financeiro</p>
                <h1><?= View::e((string) $churchName) ?></h1>
                <p class="muted">Período: <?= View::e((string) ($report['periodoLabel'] ?? '')) ?></p>
            </div>
            <div>
                <span class="badge">Dados reais</span>
                <p class="muted">Gerado em <?= View::e((string) ($report['generatedAt'] ?? '')) ?></p>
            </div>
        </header>

        <section class="summary">
            <div><span>Entradas</span><strong class="positive"><?= View::e($formatCurrency((float) ($summary['entradas'] ?? 0))) ?></strong></div>
            <div><span>Saídas</span><strong class="negative"><?= View::e($formatCurrency((float) ($summary['saidas'] ?? 0))) ?></strong></div>
            <div><span>Saldo</span><strong><?= View::e($formatCurrency((float) ($summary['saldo'] ?? 0))) ?></strong></div>
            <div><span>Movimentos</span><strong><?= View::e((string) ((int) ($summary['quantidade_total'] ?? 0))) ?></strong></div>
        </section>

        <h2>Saídas por categoria</h2>
        <table>
            <thead><tr><th>Categoria</th><th>Quantidade</th><th>Total</th><th>Percentual</th></tr></thead>
            <tbody>
                <?php foreach ($categorias as $categoria): ?>
                    <tr>
                        <td><?= View::e($categoria['nome']) ?></td>
                        <td><?= View::e((string) $categoria['quantidade']) ?></td>
                        <td><?= View::e($formatCurrency((float) $categoria['total'])) ?></td>
                        <td><?= View::e(number_format((float) $categoria['percentual'], 1, ',', '.')) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Movimentações detalhadas</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Categoria</th>
                    <th>Origem/Fornecedor</th>
                    <th>Descrição</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimentos as $item): ?>
                    <tr>
                        <td><?= View::e($formatDate($item['data'])) ?></td>
                        <td><?= $item['movimento'] === 'entrada' ? 'Entrada' : 'Saída' ?></td>
                        <td><?= View::e($item['categoria_nome']) ?></td>
                        <td><?= View::e($item['pessoa']) ?></td>
                        <td><?= View::e($item['descricao']) ?></td>
                        <td class="<?= $item['movimento'] === 'entrada' ? 'positive' : 'negative' ?>">
                            <?= $item['movimento'] === 'entrada' ? '+' : '-' ?><?= View::e($formatCurrency((float) $item['valor'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
