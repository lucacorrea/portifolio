<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$dataInicial = trim((string) ($_GET['inicio'] ?? date('Y-m-01')));
$dataFinal = trim((string) ($_GET['fim'] ?? date('Y-m-d')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicial)) {
    $dataInicial = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFinal)) {
    $dataFinal = date('Y-m-d');
}
if ($dataInicial > $dataFinal) {
    $tmp = $dataInicial;
    $dataInicial = $dataFinal;
    $dataFinal = $tmp;
}

$inicioSql = $dataInicial . ' 00:00:00';
$fimSql = $dataFinal . ' 23:59:59';

$stmt = $pdo->prepare("SELECT COALESCE(SUM(valor), 0) FROM pagamentos WHERE status IN ('pago','parcial') AND pago_em BETWEEN ? AND ?");
$stmt->execute([$inicioSql, $fimSql]);
$faturamento = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ordens_servico WHERE status = 'finalizada' AND data_finalizacao BETWEEN ? AND ?");
$stmt->execute([$inicioSql, $fimSql]);
$osFinalizadas = (int) $stmt->fetchColumn();

$ticketMedio = $osFinalizadas > 0 ? $faturamento / $osFinalizadas : 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ordem_servico_servicos s INNER JOIN ordens_servico o ON o.id = s.ordem_servico_id WHERE s.status = 'concluido' AND COALESCE(s.concluido_em, o.data_finalizacao, o.updated_at) BETWEEN ? AND ?");
$stmt->execute([$inicioSql, $fimSql]);
$servicosConcluidos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(p.quantidade), 0) FROM ordem_servico_pecas p INNER JOIN ordens_servico o ON o.id = p.ordem_servico_id WHERE o.data_entrada BETWEEN ? AND ?');
$stmt->execute([$inicioSql, $fimSql]);
$pecasUtilizadas = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(DISTINCT cliente_id) FROM ordens_servico WHERE data_entrada BETWEEN ? AND ?');
$stmt->execute([$inicioSql, $fimSql]);
$clientesAtendidos = (int) $stmt->fetchColumn();

$sqlMecanicos = "SELECT m.id, m.nome, COUNT(DISTINCT CASE WHEN o.status = 'finalizada' THEN o.id END) AS os_finalizadas, COUNT(DISTINCT s.id) AS servicos, COALESCE(SUM(DISTINCT CASE WHEN o.status = 'finalizada' THEN o.total ELSE 0 END), 0) AS valor_movimentado FROM mecanicos m LEFT JOIN ordens_servico o ON o.mecanico_responsavel_id = m.id AND o.data_entrada BETWEEN ? AND ? LEFT JOIN ordem_servico_servicos s ON s.mecanico_id = m.id AND s.ordem_servico_id = o.id WHERE m.deleted_at IS NULL GROUP BY m.id, m.nome ORDER BY valor_movimentado DESC, m.nome";
$stmt = $pdo->prepare($sqlMecanicos);
$stmt->execute([$inicioSql, $fimSql]);
$mecanicos = $stmt->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio-biauto-' . $dataInicial . '-a-' . $dataFinal . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Período', date_br($dataInicial) . ' a ' . date_br($dataFinal)], ';');
    fputcsv($out, ['Faturamento', money_br($faturamento)], ';');
    fputcsv($out, ['OS finalizadas', $osFinalizadas], ';');
    fputcsv($out, ['Ticket médio', money_br($ticketMedio)], ';');
    fputcsv($out, ['Serviços concluídos', $servicosConcluidos], ';');
    fputcsv($out, ['Peças utilizadas', number_format($pecasUtilizadas, 3, ',', '.')], ';');
    fputcsv($out, ['Clientes atendidos', $clientesAtendidos], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Mecânico', 'OS finalizadas', 'Serviços', 'Valor movimentado'], ';');
    foreach ($mecanicos as $mecanico) {
        fputcsv($out, [$mecanico['nome'], $mecanico['os_finalizadas'], $mecanico['servicos'], money_br($mecanico['valor_movimentado'])], ';');
    }
    fclose($out);
    exit;
}

$pageTitle = 'Relatórios';
$currentPage = 'relatorios';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Relatórios', 'Indicadores financeiros, operacionais e de produtividade.', [
    ['label' => 'Exportar CSV', 'href' => 'relatorios.php?inicio=' . urlencode($dataInicial) . '&fim=' . urlencode($dataFinal) . '&export=csv', 'icon' => 'report', 'class' => 'btn-primary']
]) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="form-group"><label>Data inicial</label><input class="input" type="date" name="inicio" value="<?= h($dataInicial) ?>"></div>
        <div class="form-group"><label>Data final</label><input class="input" type="date" name="fim" value="<?= h($dataFinal) ?>"></div>
        <div style="align-self:end"><button class="btn btn-primary" type="submit">Aplicar período</button></div>
        <div style="align-self:end"><button class="btn" type="button" onclick="window.print()">Imprimir</button></div>
    </form>
</div>

<div class="grid stats" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    <div class="card stat-card"><div class="stat-label">Faturamento</div><div class="stat-value" style="font-size:28px"><?= money_br($faturamento) ?></div><div class="stat-note">Pagamentos no período</div></div>
    <div class="card stat-card"><div class="stat-label">OS finalizadas</div><div class="stat-value"><?= $osFinalizadas ?></div><div class="stat-note">Ordens concluídas</div></div>
    <div class="card stat-card"><div class="stat-label">Ticket médio</div><div class="stat-value" style="font-size:28px"><?= money_br($ticketMedio) ?></div><div class="stat-note">Faturamento por OS finalizada</div></div>
    <div class="card stat-card"><div class="stat-label">Serviços concluídos</div><div class="stat-value"><?= $servicosConcluidos ?></div><div class="stat-note">Itens de serviço</div></div>
    <div class="card stat-card"><div class="stat-label">Peças utilizadas</div><div class="stat-value"><?= number_format($pecasUtilizadas, 3, ',', '.') ?></div><div class="stat-note">Quantidade total</div></div>
    <div class="card stat-card"><div class="stat-label">Clientes atendidos</div><div class="stat-value"><?= $clientesAtendidos ?></div><div class="stat-note">Clientes distintos</div></div>
</div>

<div class="card section-card">
    <div class="section-title"><div><h2>Produtividade por mecânico</h2><p><?= date_br($dataInicial) ?> a <?= date_br($dataFinal) ?></p></div></div>
    <div class="table-shell">
        <table class="table">
            <thead><tr><th>Mecânico</th><th>OS finalizadas</th><th>Serviços</th><th>Valor movimentado</th><th>Ticket por OS</th></tr></thead>
            <tbody>
            <?php if (!$mecanicos): ?><tr><td colspan="5" class="muted">Nenhum dado encontrado no período.</td></tr><?php endif; ?>
            <?php foreach ($mecanicos as $mecanico): ?>
                <?php $ticket = (int) $mecanico['os_finalizadas'] > 0 ? (float) $mecanico['valor_movimentado'] / (int) $mecanico['os_finalizadas'] : 0; ?>
                <tr>
                    <td><strong><?= h($mecanico['nome']) ?></strong></td>
                    <td><?= (int) $mecanico['os_finalizadas'] ?></td>
                    <td><?= (int) $mecanico['servicos'] ?></td>
                    <td class="money"><?= money_br($mecanico['valor_movimentado']) ?></td>
                    <td><?= money_br($ticket) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
