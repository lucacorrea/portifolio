<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Dashboard da Plataforma';
$pageDescription = 'Visão geral do SaaS, empresas locatárias e assinaturas.';

$pdo = db();
$totais = [
    'empresas' => (int) $pdo->query('SELECT COUNT(*) FROM empresas')->fetchColumn(),
    'ativas' => (int) $pdo->query("SELECT COUNT(*) FROM empresas WHERE status IN ('ativa','teste')")->fetchColumn(),
    'bloqueadas' => (int) $pdo->query("SELECT COUNT(*) FROM empresas WHERE status = 'bloqueada'")->fetchColumn(),
    'usuarios' => (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo IN ('empresa_admin','operador')")->fetchColumn(),
];

$receitaMes = 18420.00;
$mrrPrevisto = 27150.00;
$inadimplencia = 8.4;
$ticketMedio = 149.90;
$receitaPorMes = [
    ['mes' => 'Jan', 'valor' => 12400, 'altura' => 46],
    ['mes' => 'Fev', 'valor' => 13900, 'altura' => 52],
    ['mes' => 'Mar', 'valor' => 15800, 'altura' => 59],
    ['mes' => 'Abr', 'valor' => 17100, 'altura' => 64],
    ['mes' => 'Mai', 'valor' => 18420, 'altura' => 69],
    ['mes' => 'Jun', 'valor' => 21100, 'altura' => 78],
];
$eventos = [
    ['data' => 'Hoje', 'titulo' => '3 empresas em teste precisam de follow-up', 'texto' => 'Priorizar empresas sem primeiro pagamento registrado.'],
    ['data' => '7 dias', 'titulo' => 'Renovações previstas', 'texto' => 'R$ 8.940,00 em assinaturas com vencimento no período.'],
    ['data' => '30 dias', 'titulo' => 'Meta comercial', 'texto' => 'Converter 12 empresas em teste para planos pagos.'],
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
            <article class="card metric accent-blue"><span>Empresas</span><strong><?= $totais['empresas'] ?></strong><small class="metric-note">Base cadastrada no SaaS</small></article>
            <article class="card metric accent-green"><span>Ativas/Teste</span><strong><?= $totais['ativas'] ?></strong><small class="metric-note">Contas com acesso liberado</small></article>
            <article class="card metric accent-red"><span>Bloqueadas</span><strong><?= $totais['bloqueadas'] ?></strong><small class="metric-note">Exigem contato financeiro</small></article>
            <article class="card metric accent-yellow"><span>Usuários locatários</span><strong><?= $totais['usuarios'] ?></strong><small class="metric-note">Operadores e admins de empresa</small></article>
        </section>

        <section class="grid four">
            <article class="card metric accent-green"><span>Receita recebida</span><strong><?= moeda_br($receitaMes) ?></strong><small class="metric-note">Projeção operacional do mês</small></article>
            <article class="card metric accent-blue"><span>MRR previsto</span><strong><?= moeda_br($mrrPrevisto) ?></strong><small class="metric-note">Projeção com contratos ativos</small></article>
            <article class="card metric accent-yellow"><span>Inadimplência</span><strong><?= number_format($inadimplencia, 1, ',', '.') ?>%</strong><small class="metric-note">Estimativa para acompanhamento</small></article>
            <article class="card metric accent-purple"><span>Ticket médio</span><strong><?= moeda_br($ticketMedio) ?></strong><small class="metric-note">Referência comercial dos planos</small></article>
        </section>

        <section class="report-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Receita recorrente</h2>
                        <p>Projeção mensal para visualizar a evolução da receita recorrente.</p>
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
                        <h2>Saúde da plataforma</h2>
                        <p>Indicadores de operação para guiar a rotina administrativa.</p>
                    </div>
                </div>
                <div class="progress-list">
                    <div class="progress-item">
                        <div class="progress-head"><span>Conversão de testes</span><strong>68%</strong></div>
                        <div class="progress-track"><span class="progress-fill green" style="--value: 68%;"></span></div>
                    </div>
                    <div class="progress-item">
                        <div class="progress-head"><span>Assinaturas em dia</span><strong>86%</strong></div>
                        <div class="progress-track"><span class="progress-fill" style="--value: 86%;"></span></div>
                    </div>
                    <div class="progress-item">
                        <div class="progress-head"><span>Risco de cancelamento</span><strong>14%</strong></div>
                        <div class="progress-track"><span class="progress-fill yellow" style="--value: 14%;"></span></div>
                    </div>
                    <div class="progress-item">
                        <div class="progress-head"><span>Uso de WhatsApp</span><strong>74%</strong></div>
                        <div class="progress-track"><span class="progress-fill green" style="--value: 74%;"></span></div>
                    </div>
                </div>
            </article>
        </section>

        <section class="report-grid reverse">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Agenda executiva</h2>
                        <p>Prioridades do ciclo para orientar a rotina administrativa.</p>
                    </div>
                    <span class="soft-label warning">Acompanhar</span>
                </div>
                <div class="timeline">
                    <?php foreach ($eventos as $evento): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?= e($evento['data']) ?></div>
                            <div class="timeline-body"><strong><?= e($evento['titulo']) ?></strong><span><?= e($evento['texto']) ?></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Próximos passos</h2>
                        <p>Resumo operacional para evoluir o painel administrativo.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-item"><span class="insight-dot green"></span><div><strong>Padronizar cadastro comercial</strong><span>Manter planos, limites e benefícios alinhados com a landing.</span></div></div>
                    <div class="insight-item"><span class="insight-dot"></span><div><strong>Monitorar empresas em teste</strong><span>Usar o status para acompanhar conversão e risco de perda.</span></div></div>
                    <div class="insight-item"><span class="insight-dot yellow"></span><div><strong>Conectar relatórios reais</strong><span>Substituir as projeções por consultas do banco e filtros por período.</span></div></div>
                </div>
            </article>
        </section>
    </main>
</div>
</body>
</html>
