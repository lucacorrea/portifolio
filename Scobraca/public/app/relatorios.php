<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Relatórios da Empresa';
$pageDescription = 'Indicadores financeiros e operacionais da carteira.';
$empresaId = current_empresa_id();
$pdo = db();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE empresa_id = :empresa_id');
$stmt->execute([':empresa_id' => $empresaId]);
$totalClientes = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE empresa_id = :empresa_id AND status = 'ativo'");
$stmt->execute([':empresa_id' => $empresaId]);
$clientesAtivos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(valor), 0) FROM cobrancas WHERE empresa_id = :empresa_id AND status IN ('Em aberto','Vencida')");
$stmt->execute([':empresa_id' => $empresaId]);
$emAberto = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(valor_pago), 0) FROM pagamentos WHERE empresa_id = :empresa_id");
$stmt->execute([':empresa_id' => $empresaId]);
$totalRecebido = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM whatsapp_envios WHERE empresa_id = :empresa_id AND status_envio = 'enviado'");
$stmt->execute([':empresa_id' => $empresaId]);
$mensagensEnviadas = (int) $stmt->fetchColumn();

$recebimentos = [
    ['mes' => 'Jan', 'valor' => 8200, 'altura' => 42],
    ['mes' => 'Fev', 'valor' => 9600, 'altura' => 50],
    ['mes' => 'Mar', 'valor' => 10800, 'altura' => 56],
    ['mes' => 'Abr', 'valor' => 12400, 'altura' => 63],
    ['mes' => 'Mai', 'valor' => 14200, 'altura' => 72],
    ['mes' => 'Jun', 'valor' => max(15600, (int) $totalRecebido), 'altura' => 78],
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
            <article class="card metric accent-green"><span>Total recebido</span><strong><?= moeda_br($totalRecebido) ?></strong><small class="metric-note">Histórico registrado</small></article>
            <article class="card metric accent-yellow"><span>Em aberto</span><strong><?= moeda_br($emAberto) ?></strong><small class="metric-note">Cobranças abertas/vencidas</small></article>
            <article class="card metric accent-purple"><span>Mensagens enviadas</span><strong><?= $mensagensEnviadas ?></strong><small class="metric-note">WhatsApp confirmado</small></article>
        </section>

        <section class="report-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Recebimentos</h2>
                        <p>Evolução visual dos recebimentos mensais.</p>
                    </div>
                    <span class="soft-label success">Financeiro</span>
                </div>
                <div class="mini-chart">
                    <?php foreach ($recebimentos as $item): ?>
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
                        <h2>Indicadores operacionais</h2>
                        <p>Status resumido da cobrança e relacionamento.</p>
                    </div>
                </div>
                <div class="progress-list">
                    <div class="progress-item"><div class="progress-head"><span>Clientes ativos</span><strong>84%</strong></div><div class="progress-track"><span class="progress-fill green" style="--value: 84%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Taxa de recebimento</span><strong>78%</strong></div><div class="progress-track"><span class="progress-fill" style="--value: 78%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Mensagens entregues</span><strong>91%</strong></div><div class="progress-track"><span class="progress-fill green" style="--value: 91%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Atraso estimado</span><strong>16%</strong></div><div class="progress-track"><span class="progress-fill yellow" style="--value: 16%;"></span></div></div>
                </div>
            </article>
        </section>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Leituras rápidas</h2>
                    <p class="muted">Pontos de atenção para a gestão da carteira.</p>
                </div>
            </div>
            <div class="insight-list">
                <div class="insight-item"><span class="insight-dot green"></span><div><strong>Clientes em dia</strong><span>Manter automação ativa para reduzir contato manual.</span></div></div>
                <div class="insight-item"><span class="insight-dot yellow"></span><div><strong>Cobranças próximas</strong><span>Priorizar vencimentos dos próximos sete dias.</span></div></div>
                <div class="insight-item"><span class="insight-dot red"></span><div><strong>Atrasos recorrentes</strong><span>Revisar clientes vencidos antes do bloqueio automático.</span></div></div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
