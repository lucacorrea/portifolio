<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$ordensAbertas = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status NOT IN ('finalizada','cancelada')")->fetchColumn();
$emExecucao = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status = 'em_servico'")->fetchColumn();
$aguardandoPeca = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status = 'aguardando_peca'")->fetchColumn();
$finalizadasMes = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status = 'finalizada' AND data_finalizacao >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
$faturamentoMes = (float) $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM pagamentos WHERE status IN ('pago','parcial') AND pago_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
$entradasHoje = (int) $pdo->query('SELECT COUNT(*) FROM ordens_servico WHERE DATE(data_entrada) = CURDATE()')->fetchColumn();
$servicosHoje = (int) $pdo->query('SELECT COUNT(*) FROM ordem_servico_servicos WHERE DATE(iniciado_em) = CURDATE()')->fetchColumn();
$entregasHoje = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE DATE(previsao_entrega) = CURDATE() AND status NOT IN ('finalizada','cancelada')")->fetchColumn();
$estoqueBaixo = (int) $pdo->query('SELECT COUNT(*) FROM pecas WHERE deleted_at IS NULL AND ativo = 1 AND estoque_atual <= estoque_minimo')->fetchColumn();

$meses = [];
for ($i = 1; $i <= 12; $i++) {
    $meses[$i] = 0.0;
}

$fatStmt = $pdo->query("SELECT MONTH(pago_em) AS mes, COALESCE(SUM(valor), 0) AS total FROM pagamentos WHERE status IN ('pago','parcial') AND YEAR(pago_em) = YEAR(CURDATE()) GROUP BY MONTH(pago_em)");
foreach ($fatStmt->fetchAll() as $linha) {
    $meses[(int) $linha['mes']] = (float) $linha['total'];
}
$maiorFaturamento = max($meses) > 0 ? max($meses) : 1;

$recentesStmt = $pdo->query("SELECT o.id, o.numero, o.status, o.data_entrada, o.total, c.nome_razao AS cliente, CONCAT(v.marca, ' ', v.modelo) AS veiculo FROM ordens_servico o INNER JOIN clientes c ON c.id = o.cliente_id INNER JOIN veiculos v ON v.id = o.veiculo_id ORDER BY o.data_entrada DESC LIMIT 5");
$recentes = $recentesStmt->fetchAll();

$distStmt = $pdo->query("SELECT status, COUNT(*) AS total FROM ordens_servico WHERE data_entrada >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY status");
$dist = [];
$totalMes = 0;
foreach ($distStmt->fetchAll() as $linha) {
    $dist[$linha['status']] = (int) $linha['total'];
    $totalMes += (int) $linha['total'];
}
$finalizadas = $dist['finalizada'] ?? 0;
$emServico = $dist['em_servico'] ?? 0;
$outras = max(0, $totalMes - $finalizadas - $emServico);
$percentFinalizadas = $totalMes > 0 ? round(($finalizadas / $totalMes) * 100, 1) : 0;
$percentEmServico = $totalMes > 0 ? round(($emServico / $totalMes) * 100, 1) : 0;
$percentOutras = $totalMes > 0 ? round(($outras / $totalMes) * 100, 1) : 0;
$faixa2 = $percentFinalizadas + $percentEmServico;

$statusLabels = [
    'aberta' => ['Aberta', 'info'],
    'em_diagnostico' => ['Em diagnóstico', 'info'],
    'aguardando_aprovacao' => ['Aguardando aprovação', 'warning'],
    'em_servico' => ['Em serviço', 'info'],
    'aguardando_peca' => ['Aguardando peça', 'warning'],
    'aguardando_retirada' => ['Aguardando retirada', 'warning'],
    'finalizada' => ['Finalizada', 'success'],
    'cancelada' => ['Cancelada', 'danger'],
];

$nomesMeses = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header('Dashboard', 'Acompanhe os principais números, o faturamento e a movimentação da oficina.', [
    ['label' => 'Relatórios', 'href' => 'relatorios.php', 'icon' => 'filter', 'class' => 'btn-secondary'],
    ['label' => 'Nova OS', 'href' => 'ordem_nova.php', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="grid stats">
    <div class="card stat-card">
        <div class="stat-top"><div><div class="stat-label">Ordens abertas</div><div class="stat-value"><?= $ordensAbertas ?></div><div class="stat-note">Ordens ainda não finalizadas</div></div><div class="stat-chip"><?= ui_icon('os') ?></div></div>
    </div>
    <div class="card stat-card">
        <div class="stat-top"><div><div class="stat-label">Em execução</div><div class="stat-value"><?= $emExecucao ?></div><div class="stat-note">Serviços sendo executados</div></div><div class="stat-chip"><?= ui_icon('mechanic') ?></div></div>
    </div>
    <div class="card stat-card">
        <div class="stat-top"><div><div class="stat-label">Aguardando peça</div><div class="stat-value"><?= $aguardandoPeca ?></div><div class="stat-note">Ordens paradas por material</div></div><div class="stat-chip"><?= ui_icon('parts') ?></div></div>
    </div>
    <div class="card stat-card">
        <div class="stat-top"><div><div class="stat-label">Finalizadas no mês</div><div class="stat-value"><?= $finalizadasMes ?></div><div class="stat-note">Concluídas desde o primeiro dia do mês</div></div><div class="stat-chip"><?= ui_icon('report') ?></div></div>
    </div>
</div>

<div class="dashboard-layout">
    <div class="card section-card">
        <div class="chart-meta">
            <div>
                <h2 style="margin:0;font-size:20px;letter-spacing:-.03em">Faturamento</h2>
                <div class="chart-value"><?= money_br($faturamentoMes) ?></div>
                <div class="chart-subtitle">Pagamentos registrados no mês atual</div>
            </div>
            <span class="badge info"><?= date('Y') ?></span>
        </div>

        <div class="bar-chart" style="grid-template-columns:repeat(12,1fr)">
            <?php foreach ($meses as $numeroMes => $valor): ?>
                <?php $altura = max(4, round(($valor / $maiorFaturamento) * 100, 1)); ?>
                <div class="bar-wrap"><div class="bar <?= (int) date('n') === $numeroMes ? 'current' : '' ?>" style="height:<?= $altura ?>%"></div><span class="bar-label"><?= h($nomesMeses[$numeroMes]) ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card section-card">
        <div class="section-title"><div><h2>Operação de hoje</h2><p>Resumo rápido do turno atual</p></div><span class="muted"><?= date('d/m/Y') ?></span></div>
        <div class="operation-list">
            <div class="operation-item"><div class="operation-main"><div class="operation-icon"><?= ui_icon('vehicle') ?></div><div class="operation-copy"><strong>Entradas de veículos</strong><span>Recebidos hoje</span></div></div><div class="operation-value"><?= $entradasHoje ?></div></div>
            <div class="operation-item"><div class="operation-main"><div class="operation-icon"><?= ui_icon('mechanic') ?></div><div class="operation-copy"><strong>Serviços iniciados</strong><span>Execuções iniciadas hoje</span></div></div><div class="operation-value"><?= $servicosHoje ?></div></div>
            <div class="operation-item"><div class="operation-main"><div class="operation-icon"><?= ui_icon('os') ?></div><div class="operation-copy"><strong>Entregas previstas</strong><span>Programadas para hoje</span></div></div><div class="operation-value"><?= $entregasHoje ?></div></div>
            <div class="operation-item"><div class="operation-main"><div class="operation-icon"><?= ui_icon('parts') ?></div><div class="operation-copy"><strong>Estoque baixo</strong><span>Itens para reposição</span></div></div><div class="operation-value"><?= $estoqueBaixo ?></div></div>
        </div>
    </div>
</div>

<div class="bottom-layout">
    <div class="card section-card">
        <div class="section-title"><div><h2>Atividade recente</h2><p>Últimas ordens registradas</p></div><a class="btn" href="ordens.php">Ver todas</a></div>
        <div class="activity-table">
            <div class="activity-row header"><div>Cliente</div><div>Status</div><div>Veículo</div><div>Entrada</div><div>Valor</div><div></div></div>
            <?php if (!$recentes): ?><div class="empty-state">Nenhuma ordem cadastrada.</div><?php endif; ?>
            <?php foreach ($recentes as $ordem): ?>
                <?php $statusInfo = $statusLabels[$ordem['status']] ?? [ucfirst(str_replace('_', ' ', $ordem['status'])), 'info']; ?>
                <div class="activity-row">
                    <div class="activity-user"><div class="activity-avatar"><?= h(strtoupper(substr($ordem['cliente'], 0, 2))) ?></div><div><strong><?= h($ordem['cliente']) ?></strong><span><?= h($ordem['numero']) ?></span></div></div>
                    <div><span class="badge <?= h($statusInfo[1]) ?>"><span class="dot"></span><?= h($statusInfo[0]) ?></span></div>
                    <div><?= h($ordem['veiculo']) ?></div>
                    <div><?= datetime_br($ordem['data_entrada']) ?></div>
                    <div class="money"><?= money_br($ordem['total']) ?></div>
                    <div><a class="btn" href="ordem_detalhe.php?id=<?= (int) $ordem['id'] ?>">Abrir</a></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card section-card">
        <div class="section-title"><div><h2>Distribuição das OS</h2><p>Situação do mês atual</p></div></div>
        <div class="donut-area">
            <div class="donut" style="background:conic-gradient(var(--accent) 0 <?= $percentFinalizadas ?>%, #9ca3af <?= $percentFinalizadas ?>% <?= $faixa2 ?>%, #e5e7eb <?= $faixa2 ?>% 100%)">
                <div class="donut-center"><div><strong><?= $totalMes ?></strong><span>ordens no mês</span></div></div>
            </div>
            <div class="legend">
                <div class="legend-item"><div class="legend-left"><span class="legend-color primary"></span> Finalizadas</div><strong><?= number_format($percentFinalizadas, 1, ',', '.') ?>%</strong></div>
                <div class="legend-item"><div class="legend-left"><span class="legend-color gray"></span> Em serviço</div><strong><?= number_format($percentEmServico, 1, ',', '.') ?>%</strong></div>
                <div class="legend-item"><div class="legend-left"><span class="legend-color light"></span> Outras</div><strong><?= number_format($percentOutras, 1, ',', '.') ?>%</strong></div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
