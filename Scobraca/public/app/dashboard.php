<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Dashboard da Empresa';
$pageDescription = 'Resumo operacional de clientes, cobranças, pagamentos e mensagens.';
$empresaId = current_empresa_id();
$pdo = db();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE empresa_id = :empresa_id');
$stmt->execute([':empresa_id' => $empresaId]);
$totalClientes = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE empresa_id = :empresa_id AND status = 'ativo'");
$stmt->execute([':empresa_id' => $empresaId]);
$clientesAtivos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM cobrancas WHERE empresa_id = :empresa_id AND status = 'Em aberto'");
$stmt->execute([':empresa_id' => $empresaId]);
$cobrancasAbertas = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM cobrancas WHERE empresa_id = :empresa_id AND status = 'Vencida'");
$stmt->execute([':empresa_id' => $empresaId]);
$cobrancasVencidas = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(GREATEST(cb.valor - COALESCE(pg.total_pago, 0), 0)), 0)
     FROM cobrancas cb
     LEFT JOIN (
        SELECT empresa_id, cobranca_id, SUM(valor_pago) AS total_pago
        FROM pagamentos
        WHERE empresa_id = :empresa_pagamentos
        GROUP BY empresa_id, cobranca_id
     ) pg ON pg.cobranca_id = cb.id AND pg.empresa_id = cb.empresa_id
     WHERE cb.empresa_id = :empresa_id AND cb.status IN ('Em aberto','Vencida')"
);
$stmt->execute([
    ':empresa_id' => $empresaId,
    ':empresa_pagamentos' => $empresaId,
]);
$valorEmAberto = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(valor_pago), 0) FROM pagamentos WHERE empresa_id = :empresa_id AND MONTH(data_pagamento) = MONTH(CURDATE()) AND YEAR(data_pagamento) = YEAR(CURDATE())");
$stmt->execute([':empresa_id' => $empresaId]);
$totalRecebido = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT
        c.nome AS cliente,
        cb.referencia,
        cb.valor,
        cb.data_vencimento,
        cb.status,
        cb.tipo,
        cb.numero_parcela,
        cb.total_parcelas,
        COALESCE(pg.total_pago, 0) AS total_pago,
        cb.valor - COALESCE(pg.total_pago, 0) AS saldo
     FROM cobrancas cb
     INNER JOIN clientes c ON c.id = cb.cliente_id
     LEFT JOIN (
        SELECT empresa_id, cobranca_id, SUM(valor_pago) AS total_pago
        FROM pagamentos
        WHERE empresa_id = :empresa_pagamentos
        GROUP BY empresa_id, cobranca_id
     ) pg ON pg.cobranca_id = cb.id AND pg.empresa_id = cb.empresa_id
     WHERE cb.empresa_id = :empresa_id AND cb.status IN ('Em aberto','Vencida')
       AND cb.valor - COALESCE(pg.total_pago, 0) > 0
     ORDER BY cb.data_vencimento ASC
     LIMIT 6"
);
$stmt->execute([
    ':empresa_id' => $empresaId,
    ':empresa_pagamentos' => $empresaId,
]);
$proximasCobrancas = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT p.valor_pago, p.data_pagamento, p.forma_pagamento, c.nome AS cliente
     FROM pagamentos p
     INNER JOIN clientes c ON c.id = p.cliente_id
     WHERE p.empresa_id = :empresa_id
     ORDER BY p.data_pagamento DESC, p.id DESC
     LIMIT 5"
);
$stmt->execute([':empresa_id' => $empresaId]);
$ultimosPagamentos = $stmt->fetchAll();

$recebimentoMes = [
    ['mes' => 'Jan', 'valor' => 8200, 'altura' => 42],
    ['mes' => 'Fev', 'valor' => 9600, 'altura' => 50],
    ['mes' => 'Mar', 'valor' => 10800, 'altura' => 56],
    ['mes' => 'Abr', 'valor' => 12400, 'altura' => 63],
    ['mes' => 'Mai', 'valor' => max(14200, (int) $totalRecebido), 'altura' => 72],
    ['mes' => 'Jun', 'valor' => 15600, 'altura' => 78],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
</head>
<body>
<div class="layout">
    <?php require APP_PATH . '/Includes/tenant_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="grid four">
            <article class="card metric accent-blue"><span>Clientes</span><strong><?= $totalClientes ?></strong><small class="metric-note"><?= $clientesAtivos ?> ativos</small></article>
            <article class="card metric accent-yellow"><span>Cobranças abertas</span><strong><?= $cobrancasAbertas ?></strong><small class="metric-note"><?= moeda_br($valorEmAberto) ?> em aberto</small></article>
            <article class="card metric accent-green"><span>Recebido no mês</span><strong><?= moeda_br($totalRecebido) ?></strong><small class="metric-note">Pagamentos confirmados</small></article>
            <article class="card metric accent-red"><span>Vencidas</span><strong><?= $cobrancasVencidas ?></strong><small class="metric-note">Exigem acompanhamento</small></article>
        </section>

        <section class="report-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Recebimentos por mês</h2>
                        <p>Projeção visual para acompanhar a evolução da cobrança.</p>
                    </div>
                    <span class="soft-label success">Operação</span>
                </div>
                <div class="mini-chart">
                    <?php foreach ($recebimentoMes as $item): ?>
                        <div class="mini-chart-item">
                            <div class="mini-chart-bar <?= $item['altura'] >= 70 ? 'green' : '' ?>" style="--h: <?= (int) $item['altura'] ?>%;"></div>
                            <span class="mini-chart-label"><?= e($item['mes']) ?></span>
                            <span class="mini-chart-value"><?= moeda_br((float) $item['valor']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Rotina de cobrança</h2>
                        <p>Acompanhamento dos principais pontos da operação.</p>
                    </div>
                </div>
                <div class="progress-list">
                    <div class="progress-item"><div class="progress-head"><span>Clientes em dia</span><strong>82%</strong></div><div class="progress-track"><span class="progress-fill green" style="--value: 82%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Mensagens entregues</span><strong>91%</strong></div><div class="progress-track"><span class="progress-fill" style="--value: 91%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Pagamentos por PIX</span><strong>76%</strong></div><div class="progress-track"><span class="progress-fill green" style="--value: 76%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Risco de atraso</span><strong>18%</strong></div><div class="progress-track"><span class="progress-fill yellow" style="--value: 18%;"></span></div></div>
                </div>
            </article>
        </section>

        <section class="report-grid reverse">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Próximas cobranças</h2>
                        <p class="muted">Vencimentos ordenados por prioridade.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Cliente</th><th>Tipo</th><th>Referência</th><th>Saldo</th><th>Vencimento</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($proximasCobrancas as $cobranca): ?>
                            <?php
                            $tipoCobranca = (string) ($cobranca['tipo'] ?? 'mensalidade');
                            $labelCobranca = 'Mensalidade';

                            if ($tipoCobranca === 'parcelada') {
                                $labelCobranca = ((int) ($cobranca['numero_parcela'] ?? 1)) === 0
                                    ? 'Entrada'
                                    : 'Parcela ' . (int) $cobranca['numero_parcela'] . '/' . (int) $cobranca['total_parcelas'];
                            }

                            $statusLabel = (float) ($cobranca['total_pago'] ?? 0) > 0 ? 'Parcial' : (string) $cobranca['status'];
                            $statusClass = $statusLabel === 'Parcial' ? 'parcial' : ($cobranca['status'] === 'Vencida' ? 'vencida' : 'pendente');
                            ?>
                            <tr>
                                <td><strong><?= e($cobranca['cliente']) ?></strong></td>
                                <td><span class="soft-label <?= $tipoCobranca === 'parcelada' ? 'warning' : 'success' ?>"><?= e($labelCobranca) ?></span></td>
                                <td><?= e($cobranca['referencia']) ?></td>
                                <td><?= moeda_br((float) $cobranca['saldo']) ?></td>
                                <td><?= e(data_br($cobranca['data_vencimento'])) ?></td>
                                <td><span class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$proximasCobrancas): ?>
                            <tr><td colspan="6">Nenhuma cobrança cadastrada.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Últimos pagamentos</h2>
                        <p class="muted">Recebimentos confirmados recentemente.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <?php foreach ($ultimosPagamentos as $pagamento): ?>
                        <div class="insight-item"><span class="insight-dot green"></span><div><strong><?= e($pagamento['cliente']) ?> · <?= moeda_br((float) $pagamento['valor_pago']) ?></strong><span><?= e(data_br($pagamento['data_pagamento'])) ?> por <?= e($pagamento['forma_pagamento']) ?></span></div></div>
                    <?php endforeach; ?>
                    <?php if (!$ultimosPagamentos): ?>
                        <div class="empty-state">Nenhum pagamento registrado ainda.</div>
                    <?php endif; ?>
                </div>
            </article>
        </section>
    </main>
</div>
</body>
</html>
