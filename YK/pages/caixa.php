<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$date = trim((string) ($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$movements = $application->cashManagement()->listByDate($date);

$totals = ['entrada' => 0.0, 'saida' => 0.0, 'estorno_entrada' => 0.0, 'estorno_saida' => 0.0];
$byForm = [];
foreach ($movements as $movement) {
    $type = (string) $movement['tipo'];
    $value = (float) $movement['valor'];
    if (isset($totals[$type])) $totals[$type] += $value;
    $form = (string) ($movement['forma_pagamento'] ?? 'sem_forma');
    $byForm[$form] = ($byForm[$form] ?? 0.0) + $value;
}
$balance = $totals['entrada'] - $totals['saida'] - $totals['estorno_entrada'] + $totals['estorno_saida'];
?>

<div class="page-body cash-page">
<?php metric_grid([
    ['Entradas', money(number_format($totals['entrada'], 2, '.', '')), 'bi-arrow-down-circle', '#16A34A', 'no dia'],
    ['Saidas', money(number_format($totals['saida'], 2, '.', '')), 'bi-arrow-up-circle', '#DC2626', 'no dia'],
    ['Estornos', money(number_format($totals['estorno_entrada'] + $totals['estorno_saida'], 2, '.', '')), 'bi-arrow-counterclockwise', '#D97706', 'correcoes'],
    ['Saldo', money(number_format($balance, 2, '.', '')), 'bi-cash-coin', '#2563EB', 'movimentacoes'],
]); ?>

<form class="filter-bar" method="get" action="caixa.php">
    <input class="filter-select input-date" type="date" name="date" value="<?= h($date) ?>">
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
</form>

<section class="panel">
    <div class="panel-header"><div class="panel-title"><i class="bi bi-cash-coin"></i>Movimentacoes do caixa</div></div>
    <?php if ($movements === []): ?>
        <?php empty_state('Nenhuma movimentacao no dia', 'Recebimentos de OS e contas aparecerao aqui.'); ?>
    <?php else: ?>
        <div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Horario</th><th>Tipo</th><th>Origem</th><th>Descricao</th><th>Forma</th><th>Valor</th><th>Usuario</th></tr></thead><tbody>
        <?php foreach ($movements as $movement): ?>
            <tr>
                <td><?= h((new DateTimeImmutable((string) $movement['data_movimento']))->format('H:i')) ?></td>
                <td><?= h((string) $movement['tipo']) ?></td>
                <td><?= h((string) $movement['origem_tipo']) ?> #<?= h((string) $movement['origem_id']) ?></td>
                <td><?= h((string) $movement['descricao']) ?></td>
                <td><?= h((string) ($movement['forma_pagamento'] ?? '-')) ?></td>
                <td><?= money((string) $movement['valor']) ?></td>
                <td><?= h((string) $movement['usuario_nome']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
</div>
