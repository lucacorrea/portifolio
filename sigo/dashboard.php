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
    <meta name="theme-color" content="#e9eef7">
    <title>SIGO - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260928-4">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=20260928-4">
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
                    <div class="filter-control">
                        <button class="ghost-button" type="button" data-filter-toggle aria-expanded="false">
                            <i data-lucide="sliders-horizontal"></i>
                            Filtros
                            <span class="filter-count" data-filter-count hidden>1</span>
                        </button>

                        <div class="filter-popover" data-filter-panel hidden>
                            <div class="filter-popover-head">
                                <div>
                                    <strong>Filtrar ofícios</strong>
                                    <small>Refine o histórico por situação.</small>
                                </div>
                                <button class="round-button compact" type="button" data-filter-close aria-label="Fechar filtros">
                                    <i data-lucide="x"></i>
                                </button>
                            </div>

                            <div class="filter-options" data-filter-options>
                                <button class="filter-option active" type="button" data-status-filter="all">
                                    <span>Todos</span><b>4</b>
                                </button>
                                <button class="filter-option" type="button" data-status-filter="pending">
                                    <span>Aguardando</span><b>1</b>
                                </button>
                                <button class="filter-option" type="button" data-status-filter="progress">
                                    <span>Em andamento</span><b>1</b>
                                </button>
                                <button class="filter-option" type="button" data-status-filter="received">
                                    <span>Recebidos</span><b>1</b>
                                </button>
                                <button class="filter-option" type="button" data-status-filter="archived">
                                    <span>Arquivados</span><b>1</b>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button class="ghost-button" type="button" data-export>
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
                        <button class="period-button" type="button" data-period-toggle aria-expanded="false">
                            <span data-period-label>Esta semana</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                    </div>

                    <div class="period-menu" data-period-menu hidden>
                        <button type="button" data-period="Hoje">Hoje</button>
                        <button class="active" type="button" data-period="Esta semana">Esta semana</button>
                        <button type="button" data-period="Este mês">Este mês</button>
                    </div>

                    <div class="summary-metrics">
                        <button class="summary-metric metric-button" type="button" data-status-filter="all">
                            <span class="metric-icon"><i data-lucide="inbox"></i></span>
                            <span>
                                <small>Recebidos hoje</small>
                                <strong>12</strong>
                            </span>
                        </button>
                        <div class="summary-divider"></div>
                        <button class="summary-metric metric-button" type="button" data-status-filter="progress">
                            <span class="metric-icon"><i data-lucide="send"></i></span>
                            <span>
                                <small>Encaminhados</small>
                                <strong>7</strong>
                            </span>
                        </button>
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
                            <button class="round-button" type="button" data-filter-toggle aria-label="Filtrar atividade">
                                <i data-lucide="sliders-horizontal"></i>
                            </button>
                            <a class="round-button" href="movimentacoes/index.php" aria-label="Abrir movimentações">
                                <i data-lucide="arrow-up-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="activity-grid">
                        <button class="activity-mini-card" type="button" data-status-filter="pending">
                            <span class="mini-icon warning"><i data-lucide="clock-3"></i></span>
                            <span class="activity-title">Aguardando recebimento</span>
                            <small>Confirmação pendente</small>
                            <strong>5</strong>
                            <div class="sparkline warning">
                                <span style="height: 34%"></span><span style="height: 48%"></span><span style="height: 41%"></span>
                                <span style="height: 66%"></span><span style="height: 58%"></span><span style="height: 76%"></span>
                                <span style="height: 63%"></span><span style="height: 70%"></span><span style="height: 54%"></span>
                            </div>
                            <span class="card-action">Ver ofícios <i data-lucide="arrow-right"></i></span>
                        </button>

                        <button class="activity-mini-card" type="button" data-status-filter="progress">
                            <span class="mini-icon info"><i data-lucide="workflow"></i></span>
                            <span class="activity-title">Em andamento</span>
                            <small>Com responsáveis</small>
                            <strong>18</strong>
                            <div class="sparkline info">
                                <span style="height: 45%"></span><span style="height: 38%"></span><span style="height: 58%"></span>
                                <span style="height: 52%"></span><span style="height: 69%"></span><span style="height: 62%"></span>
                                <span style="height: 74%"></span><span style="height: 67%"></span><span style="height: 79%"></span>
                            </div>
                            <span class="card-action">Ver ofícios <i data-lucide="arrow-right"></i></span>
                        </button>

                        <button class="activity-mini-card attention" type="button" data-status-filter="pending">
                            <span class="mini-icon"><i data-lucide="circle-alert"></i></span>
                            <span class="activity-title">Precisam de atenção</span>
                            <small>Parados há mais de 24h</small>
                            <strong>3</strong>
                            <div class="sparkline">
                                <span style="height: 67%"></span><span style="height: 59%"></span><span style="height: 63%"></span>
                                <span style="height: 45%"></span><span style="height: 52%"></span><span style="height: 39%"></span>
                                <span style="height: 43%"></span><span style="height: 30%"></span><span style="height: 22%"></span>
                            </div>
                            <span class="card-action">Revisar agora <i data-lucide="arrow-right"></i></span>
                        </button>
                    </div>
                </article>

                <div class="left-stats">
                    <button class="small-stat-card stat-success" type="button" data-status-filter="received">
                        <div class="small-stat-label">
                            <span><i data-lucide="check-circle-2"></i></span>
                            Concluídos no mês
                        </div>
                        <strong>34</strong>
                        <small>+11,5% em relação ao mês anterior</small>
                    </button>

                    <button class="small-stat-card stat-neutral" type="button" data-status-filter="archived">
                        <div class="small-stat-label">
                            <span><i data-lucide="archive"></i></span>
                            Arquivados
                        </div>
                        <strong>126</strong>
                        <small>100% com localização registrada</small>
                    </button>

                    <article class="management-card">
                        <div class="management-icon"><i data-lucide="shield-alert"></i></div>
                        <div>
                            <strong>3 pendências precisam de acompanhamento</strong>
                            <small>Documentos sem confirmação há mais de 24 horas.</small>
                        </div>
                        <a href="movimentacoes/index.php" aria-label="Ver pendências">
                            <i data-lucide="arrow-right"></i>
                        </a>
                    </article>
                </div>

                <article class="dashboard-card history-card" id="historico">
                    <div class="card-heading history-heading">
                        <div>
                            <h2>Histórico de ofícios</h2>
                            <p><span data-visible-count><?= count($oficios) ?></span> registros exibidos.</p>
                        </div>
                        <div class="history-tools">
                            <label class="table-search">
                                <i data-lucide="search"></i>
                                <input type="search" placeholder="Buscar na tabela" data-table-search>
                            </label>
                            <button class="round-button" type="button" data-filter-toggle aria-label="Filtrar histórico">
                                <i data-lucide="sliders-horizontal"></i>
                            </button>
                        </div>
                    </div>

                    <div class="active-filter" data-active-filter hidden>
                        <span>Filtro: <strong data-active-filter-label></strong></span>
                        <button type="button" data-clear-filter>Limpar</button>
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
                            <tbody data-history-body>
                                <?php foreach ($oficios as $oficio): ?>
                                    <tr
                                        data-office-row
                                        data-status="<?= htmlspecialchars($oficio['classe'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-search="<?= htmlspecialchars(strtolower($oficio['protocolo'] . ' ' . $oficio['oficio'] . ' ' . $oficio['status'] . ' ' . $oficio['responsavel']), ENT_QUOTES, 'UTF-8') ?>"
                                    >
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

                        <div class="table-empty" data-table-empty hidden>
                            <i data-lucide="search-x"></i>
                            <strong>Nenhum ofício encontrado</strong>
                            <span>Ajuste a busca ou remova os filtros.</span>
                        </div>
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

<div class="toast" data-toast hidden>
    <i data-lucide="circle-check"></i>
    <span data-toast-text>Concluído</span>
</div>

<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script src="assets/js/app.js?v=20260928-4"></script>
</body>
</html>
