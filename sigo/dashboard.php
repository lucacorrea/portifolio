<?php
declare(strict_types=1);

$paginaAtual = 'dashboard';
$paginaTitulo = 'Dashboard';

date_default_timezone_set('America/Manaus');

$diasSemana = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
$meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

$agora = new DateTimeImmutable();
$dataExtenso = sprintf(
    '%s, %d de %s',
    $diasSemana[(int) $agora->format('w')],
    (int) $agora->format('j'),
    $meses[(int) $agora->format('n')]
);

$horaAtual = (int) $agora->format('G');
$saudacao = $horaAtual < 12 ? 'Bom dia' : ($horaAtual < 18 ? 'Boa tarde' : 'Boa noite');

$volumeSemana = [
    ['dia' => 'Seg', 'valor' => 56],
    ['dia' => 'Ter', 'valor' => 82],
    ['dia' => 'Qua', 'valor' => 67],
    ['dia' => 'Qui', 'valor' => 74],
    ['dia' => 'Sex', 'valor' => 91],
    ['dia' => 'Sáb', 'valor' => 48],
    ['dia' => 'Dom', 'valor' => 38],
];

$oficios = [
    ['protocolo' => '1146/2026', 'oficio' => '0406/2026', 'status' => 'Aguardando', 'classe' => 'pending', 'responsavel' => 'Marcos Almeida', 'hora' => '09:52'],
    ['protocolo' => '1145/2026', 'oficio' => '0189/2026', 'status' => 'Em andamento', 'classe' => 'progress', 'responsavel' => 'Carla Souza', 'hora' => '09:31'],
    ['protocolo' => '1144/2026', 'oficio' => '0721/2026', 'status' => 'Recebido', 'classe' => 'received', 'responsavel' => 'Paulo Martins', 'hora' => '08:58'],
    ['protocolo' => '1143/2026', 'oficio' => '0312/2026', 'status' => 'Arquivado', 'classe' => 'archived', 'responsavel' => 'Arquivo Central', 'hora' => '08:47'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f3f2ef">
    <title>SIGO - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260928-3">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=20260928-3">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/includes/topbar.php'; ?>

    <div class="app-body">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>

        <main class="page-content">
            <section class="page-hero">
                <div class="page-hero-copy">
                    <button class="round-button mobile-rail-toggle" type="button" data-rail-toggle aria-label="Abrir atalhos">
                        <i data-lucide="menu"></i>
                    </button>
                    <div>
                        <span class="date-label"><?= htmlspecialchars($dataExtenso, ENT_QUOTES, 'UTF-8') ?></span>
                        <h1><?= htmlspecialchars($saudacao, ENT_QUOTES, 'UTF-8') ?>, Administrador!</h1>
                        <p>Controle os ofícios, encaminhamentos e confirmações de recebimento.</p>
                    </div>
                </div>

                <div class="hero-actions">
                    <button class="ghost-button" type="button">
                        <i data-lucide="sliders-horizontal"></i>
                        Filtros
                    </button>
                    <button class="ghost-button" type="button">
                        <i data-lucide="download"></i>
                        Exportar
                    </button>
                    <a class="primary-button" href="oficios/index.php">
                        <i data-lucide="plus"></i>
                        Novo ofício
                    </a>
                </div>
            </section>

            <section class="dashboard-layout">
                <article class="dashboard-card summary-card">
                    <div class="card-heading">
                        <div>
                            <h2>Resumo</h2>
                            <p>Acompanhe a entrada de documentos.</p>
                        </div>
                        <button class="period-button" type="button">
                            Esta semana
                            <i data-lucide="chevron-down"></i>
                        </button>
                    </div>

                    <div class="summary-metrics">
                        <div class="summary-metric">
                            <span class="metric-icon"><i data-lucide="inbox"></i></span>
                            <div>
                                <small>Recebidos hoje</small>
                                <strong>12</strong>
                            </div>
                        </div>
                        <div class="summary-divider"></div>
                        <div class="summary-metric">
                            <span class="metric-icon"><i data-lucide="send"></i></span>
                            <div>
                                <small>Encaminhados</small>
                                <strong>7</strong>
                            </div>
                        </div>
                    </div>

                    <div class="volume-chart" aria-label="Volume semanal de ofícios">
                        <?php foreach ($volumeSemana as $index => $item): ?>
                            <div class="bar-column">
                                <span
                                    class="chart-bar <?= $index < 5 ? 'active' : '' ?>"
                                    style="--bar-height: <?= (int) $item['valor'] ?>%;"
                                    title="<?= htmlspecialchars($item['dia'], ENT_QUOTES, 'UTF-8') ?>"
                                ></span>
                                <small><?= htmlspecialchars($item['dia'], ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="dashboard-card activity-card">
                    <div class="card-heading">
                        <div>
                            <h2>Atividade</h2>
                            <p>Situação dos ofícios neste momento.</p>
                        </div>
                        <div class="heading-actions">
                            <button class="round-button" type="button" aria-label="Ajustar visualização">
                                <i data-lucide="sliders-horizontal"></i>
                            </button>
                            <a class="round-button" href="movimentacoes/index.php" aria-label="Abrir movimentações">
                                <i data-lucide="arrow-up-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="activity-grid">
                        <article class="activity-mini-card">
                            <span class="mini-icon"><i data-lucide="clock-3"></i></span>
                            <h3>Aguardando recebimento</h3>
                            <small>Confirmação pendente</small>
                            <strong>5</strong>
                            <div class="sparkline">
                                <span style="height: 34%"></span><span style="height: 48%"></span><span style="height: 41%"></span>
                                <span style="height: 66%"></span><span style="height: 58%"></span><span style="height: 76%"></span>
                                <span style="height: 63%"></span><span style="height: 70%"></span><span style="height: 54%"></span>
                            </div>
                        </article>

                        <article class="activity-mini-card">
                            <span class="mini-icon"><i data-lucide="workflow"></i></span>
                            <h3>Em andamento</h3>
                            <small>Com responsáveis</small>
                            <strong>18</strong>
                            <div class="sparkline">
                                <span style="height: 45%"></span><span style="height: 38%"></span><span style="height: 58%"></span>
                                <span style="height: 52%"></span><span style="height: 69%"></span><span style="height: 62%"></span>
                                <span style="height: 74%"></span><span style="height: 67%"></span><span style="height: 79%"></span>
                            </div>
                        </article>

                        <article class="activity-mini-card attention">
                            <span class="mini-icon"><i data-lucide="circle-alert"></i></span>
                            <h3>Precisam de atenção</h3>
                            <small>Parados há mais de 24h</small>
                            <strong>3</strong>
                            <div class="sparkline">
                                <span style="height: 67%"></span><span style="height: 59%"></span><span style="height: 63%"></span>
                                <span style="height: 45%"></span><span style="height: 52%"></span><span style="height: 39%"></span>
                                <span style="height: 43%"></span><span style="height: 30%"></span><span style="height: 22%"></span>
                            </div>
                        </article>
                    </div>
                </article>

                <div class="left-stats">
                    <article class="small-stat-card">
                        <div class="small-stat-label">
                            <span><i data-lucide="check-circle-2"></i></span>
                            Concluídos no mês
                        </div>
                        <strong>34</strong>
                        <small>+11,5% em relação ao mês anterior</small>
                    </article>

                    <article class="small-stat-card">
                        <div class="small-stat-label">
                            <span><i data-lucide="archive"></i></span>
                            Arquivados
                        </div>
                        <strong>126</strong>
                        <small>100% com localização registrada</small>
                    </article>

                    <article class="management-card">
                        <div>
                            <strong>Como está o fluxo de documentos?</strong>
                            <small>3 pendências precisam de acompanhamento.</small>
                        </div>
                        <a href="movimentacoes/index.php" aria-label="Ver pendências">
                            <i data-lucide="arrow-right"></i>
                        </a>
                    </article>
                </div>

                <article class="dashboard-card history-card">
                    <div class="card-heading">
                        <div>
                            <h2>Histórico de ofícios</h2>
                            <p>Últimas movimentações registradas.</p>
                        </div>
                        <button class="round-button" type="button" aria-label="Filtrar histórico">
                            <i data-lucide="sliders-horizontal"></i>
                        </button>
                    </div>

                    <div class="history-table-wrap">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Protocolo</th>
                                    <th>Ofício</th>
                                    <th>Situação</th>
                                    <th>Responsável</th>
                                    <th>Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($oficios as $oficio): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($oficio['protocolo'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td><?= htmlspecialchars($oficio['oficio'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="status-pill status-<?= htmlspecialchars($oficio['classe'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($oficio['status'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($oficio['responsavel'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($oficio['hora'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </main>
    </div>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</div>

<div class="search-overlay" data-search-overlay hidden>
    <button class="search-backdrop" type="button" data-search-close aria-label="Fechar busca"></button>
    <div class="search-dialog" role="dialog" aria-modal="true" aria-label="Busca rápida">
        <i data-lucide="search"></i>
        <input type="search" placeholder="Buscar protocolo, ofício ou responsável" data-search-input>
        <kbd>ESC</kbd>
    </div>
</div>

<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script src="assets/js/app.js?v=20260928-3"></script>
</body>
</html>
