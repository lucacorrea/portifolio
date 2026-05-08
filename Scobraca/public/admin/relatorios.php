<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Relatórios';
$pageDescription = 'Indicadores gerenciais da plataforma FluxPay.';

$pdo = db();
$totais = [
    'empresas' => (int) $pdo->query('SELECT COUNT(*) FROM empresas')->fetchColumn(),
    'ativas' => (int) $pdo->query("SELECT COUNT(*) FROM empresas WHERE status IN ('ativa','teste')")->fetchColumn(),
    'bloqueadas' => (int) $pdo->query("SELECT COUNT(*) FROM empresas WHERE status = 'bloqueada'")->fetchColumn(),
    'usuarios' => (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo IN ('empresa_admin','operador')")->fetchColumn(),
    'admins' => (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'platform_admin'")->fetchColumn(),
    'planos' => (int) $pdo->query('SELECT COUNT(*) FROM planos WHERE ativo = 1')->fetchColumn(),
];

$assinaturas = $pdo->query('SELECT status, valor, data_vencimento FROM assinaturas')->fetchAll();
$assinaturasAtivas = count(array_filter($assinaturas, static fn (array $assinatura): bool => in_array($assinatura['status'], ['ativa', 'teste'], true)));
$assinaturasEmRisco = count(array_filter($assinaturas, static fn (array $assinatura): bool => in_array($assinatura['status'], ['vencida', 'bloqueada'], true)));
$valorMensal = array_sum(array_map(
    static fn (array $assinatura): float => in_array($assinatura['status'], ['ativa', 'teste'], true) ? (float) $assinatura['valor'] : 0.00,
    $assinaturas
));
$valorEmRisco = array_sum(array_map(
    static fn (array $assinatura): float => in_array($assinatura['status'], ['vencida', 'bloqueada'], true) ? (float) $assinatura['valor'] : 0.00,
    $assinaturas
));

$receitaProjetada = max($valorMensal, 18420.00);
$receitaPorMes = [
    ['mes' => 'Jan', 'valor' => 12400, 'altura' => 46],
    ['mes' => 'Fev', 'valor' => 13900, 'altura' => 52],
    ['mes' => 'Mar', 'valor' => 15800, 'altura' => 59],
    ['mes' => 'Abr', 'valor' => 17100, 'altura' => 64],
    ['mes' => 'Mai', 'valor' => (int) $receitaProjetada, 'altura' => 69],
    ['mes' => 'Jun', 'valor' => 21100, 'altura' => 78],
];
$mixPlanos = [
    ['nome' => 'Entrada', 'valor' => 32, 'classe' => ''],
    ['nome' => 'Crescimento', 'valor' => 46, 'classe' => 'green'],
    ['nome' => 'Premium', 'valor' => 22, 'classe' => 'yellow'],
];
$agenda = [
    ['data' => 'Hoje', 'titulo' => 'Revisar empresas em teste', 'texto' => 'Priorizar contas com alto uso e sem assinatura ativa.'],
    ['data' => '7 dias', 'titulo' => 'Renovações previstas', 'texto' => 'Acompanhar vencimentos próximos e contratos em risco.'],
    ['data' => '30 dias', 'titulo' => 'Meta comercial', 'texto' => 'Converter novas empresas em planos pagos e reduzir bloqueios.'],
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
    <?php require APP_PATH . '/Includes/admin_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="grid four">
            <article class="card metric accent-blue"><span>Empresas</span><strong><?= $totais['empresas'] ?></strong><small class="metric-note"><?= $totais['ativas'] ?> ativas/teste</small></article>
            <article class="card metric accent-green"><span>Receita mensal</span><strong><?= moeda_br($valorMensal) ?></strong><small class="metric-note"><?= $assinaturasAtivas ?> assinatura(s) ativas/teste</small></article>
            <article class="card metric accent-yellow"><span>Receita em risco</span><strong><?= moeda_br($valorEmRisco) ?></strong><small class="metric-note"><?= $assinaturasEmRisco ?> contrato(s) críticos</small></article>
            <article class="card metric accent-purple"><span>Usuários SaaS</span><strong><?= $totais['usuarios'] ?></strong><small class="metric-note"><?= $totais['admins'] ?> admins internos</small></article>
        </section>

        <section class="report-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Receita recorrente</h2>
                        <p>Projeção mensal consolidada para acompanhar evolução do SaaS.</p>
                    </div>
                    <span class="soft-label success">Estimado</span>
                </div>
                <div class="mini-chart">
                    <?php foreach ($receitaPorMes as $item): ?>
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
                        <h2>Saúde comercial</h2>
                        <p>Indicadores para orientar operação, cobrança e retenção.</p>
                    </div>
                </div>
                <div class="progress-list">
                    <div class="progress-item"><div class="progress-head"><span>Conversão de testes</span><strong>68%</strong></div><div class="progress-track"><span class="progress-fill green" style="--value: 68%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Assinaturas saudáveis</span><strong>86%</strong></div><div class="progress-track"><span class="progress-fill" style="--value: 86%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Risco de cancelamento</span><strong>14%</strong></div><div class="progress-track"><span class="progress-fill yellow" style="--value: 14%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Uso de WhatsApp</span><strong>74%</strong></div><div class="progress-track"><span class="progress-fill green" style="--value: 74%;"></span></div></div>
                </div>
            </article>
        </section>

        <section class="report-grid reverse">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Distribuição por plano</h2>
                        <p>Leitura estimada da procura por categoria comercial.</p>
                    </div>
                    <span class="soft-label"><?= $totais['planos'] ?> plano(s)</span>
                </div>
                <div class="mini-chart compact-chart">
                    <?php foreach ($mixPlanos as $item): ?>
                        <div class="mini-chart-item">
                            <div class="mini-chart-bar <?= e($item['classe']) ?>" style="--h: <?= (int) $item['valor'] ?>%;"></div>
                            <span class="mini-chart-label"><?= e($item['nome']) ?></span>
                            <span class="mini-chart-value"><?= (int) $item['valor'] ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Agenda executiva</h2>
                        <p>Prioridades para análise e rotina administrativa.</p>
                    </div>
                    <span class="soft-label warning">Acompanhar</span>
                </div>
                <div class="timeline">
                    <?php foreach ($agenda as $evento): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?= e($evento['data']) ?></div>
                            <div class="timeline-body"><strong><?= e($evento['titulo']) ?></strong><span><?= e($evento['texto']) ?></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>
    </main>
</div>
</body>
</html>
