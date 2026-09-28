<?php
declare(strict_types=1);

$paginaAtual = 'dashboard';
$paginaTitulo = 'Visão geral';
$paginaDescricao = 'Acompanhe os ofícios da Casa Civil em tempo real.';

$indicadores = [
    ['titulo' => 'Recebidos hoje', 'valor' => '12', 'detalhe' => '3 nas últimas 2 horas', 'icone' => 'inbox', 'classe' => 'blue'],
    ['titulo' => 'Aguardando encaminhamento', 'valor' => '7', 'detalhe' => 'Precisam de destino', 'icone' => 'send', 'classe' => 'amber'],
    ['titulo' => 'Aguardando recebimento', 'valor' => '5', 'detalhe' => 'Confirmação pendente', 'icone' => 'clock', 'classe' => 'violet'],
    ['titulo' => 'Em andamento', 'valor' => '18', 'detalhe' => 'Com responsáveis', 'icone' => 'workflow', 'classe' => 'cyan'],
    ['titulo' => 'Concluídos', 'valor' => '34', 'detalhe' => 'Neste mês', 'icone' => 'check-circle', 'classe' => 'green'],
    ['titulo' => 'Arquivados', 'valor' => '126', 'detalhe' => 'Documentos localizados', 'icone' => 'archive', 'classe' => 'slate'],
];

$oficios = [
    ['protocolo' => '1146/2026', 'oficio' => '0406/2026', 'origem' => 'SEMAS', 'assunto' => 'Manutenção do sistema de ar-condicionado', 'responsavel' => 'Marcos Almeida', 'status' => 'Aguardando recebimento', 'statusClasse' => 'pending'],
    ['protocolo' => '1145/2026', 'oficio' => '0189/2026', 'origem' => 'SEMED', 'assunto' => 'Solicitação de apoio institucional', 'responsavel' => 'Carla Souza', 'status' => 'Em andamento', 'statusClasse' => 'progress'],
    ['protocolo' => '1144/2026', 'oficio' => '0721/2026', 'origem' => 'SEINFRA', 'assunto' => 'Encaminhamento de documentação técnica', 'responsavel' => 'Paulo Martins', 'status' => 'Recebido', 'statusClasse' => 'received'],
    ['protocolo' => '1143/2026', 'oficio' => '0312/2026', 'origem' => 'SEFAZ', 'assunto' => 'Informações administrativas', 'responsavel' => 'Arquivo Central', 'status' => 'Arquivado', 'statusClasse' => 'archived'],
];

$movimentacoes = [
    ['hora' => '10:18', 'titulo' => 'Recebimento confirmado', 'texto' => 'Paulo Martins confirmou o recebimento do Ofício 0721/2026.', 'classe' => 'green'],
    ['hora' => '09:52', 'titulo' => 'Ofício encaminhado', 'texto' => 'Ofício 0406/2026 encaminhado para Marcos Almeida.', 'classe' => 'blue'],
    ['hora' => '09:31', 'titulo' => 'Novo cadastro', 'texto' => 'Protocolo 1146/2026 cadastrado no sistema.', 'classe' => 'violet'],
    ['hora' => '08:47', 'titulo' => 'Documento arquivado', 'texto' => 'Ofício 0312/2026 enviado ao Arquivo Central.', 'classe' => 'slate'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b1739">
    <title>SIGO - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260928-1">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=20260928-1">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <div class="app-main">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <main class="page-content">
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">Domingo, 28 de setembro</span>
                    <h1>Bom dia, Administrador</h1>
                    <p>Veja o que precisa da sua atenção hoje na Casa Civil.</p>
                </div>

                <div class="welcome-actions">
                    <a class="secondary-button" href="recebimento/index.php">
                        <span class="button-icon">✓</span>
                        Recebimento rápido
                    </a>
                    <a class="primary-button" href="oficios/index.php">
                        <span class="button-icon">＋</span>
                        Novo ofício
                    </a>
                </div>
            </section>

            <section class="stats-grid" aria-label="Resumo dos ofícios">
                <?php foreach ($indicadores as $item): ?>
                    <article class="stat-card stat-<?= htmlspecialchars($item['classe'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="stat-card-top">
                            <span class="stat-icon" aria-hidden="true">
                                <?php if ($item['icone'] === 'inbox'): ?>↓<?php endif; ?>
                                <?php if ($item['icone'] === 'send'): ?>↗<?php endif; ?>
                                <?php if ($item['icone'] === 'clock'): ?>◷<?php endif; ?>
                                <?php if ($item['icone'] === 'workflow'): ?>⌘<?php endif; ?>
                                <?php if ($item['icone'] === 'check-circle'): ?>✓<?php endif; ?>
                                <?php if ($item['icone'] === 'archive'): ?>□<?php endif; ?>
                            </span>
                            <span class="stat-label"><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <strong><?= htmlspecialchars($item['valor'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <small><?= htmlspecialchars($item['detalhe'], ENT_QUOTES, 'UTF-8') ?></small>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="attention-panel">
                <div class="attention-copy">
                    <span class="attention-icon">!</span>
                    <div>
                        <strong>3 ofícios precisam de atenção</strong>
                        <p>Há documentos aguardando confirmação de recebimento há mais de 24 horas.</p>
                    </div>
                </div>
                <a href="movimentacoes/index.php">Ver pendências <span>→</span></a>
            </section>

            <section class="dashboard-main-grid">
                <article class="panel recent-panel">
                    <div class="panel-heading panel-heading-row">
                        <div>
                            <span class="eyebrow">Acompanhamento</span>
                            <h2>Ofícios recentes</h2>
                        </div>
                        <a class="text-link" href="oficios/index.php">Ver todos <span>→</span></a>
                    </div>

                    <div class="table-wrap">
                        <table class="office-table">
                            <thead>
                                <tr>
                                    <th>Protocolo</th>
                                    <th>Ofício</th>
                                    <th>Origem</th>
                                    <th>Assunto</th>
                                    <th>Responsável atual</th>
                                    <th>Situação</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($oficios as $oficio): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($oficio['protocolo'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td><?= htmlspecialchars($oficio['oficio'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="origin-badge"><?= htmlspecialchars($oficio['origem'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td class="subject-cell"><?= htmlspecialchars($oficio['assunto'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($oficio['responsavel'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="status-badge status-<?= htmlspecialchars($oficio['statusClasse'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($oficio['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><button class="row-action" type="button" aria-label="Abrir ofício">•••</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <aside class="right-column">
                    <article class="panel quick-panel">
                        <div class="panel-heading">
                            <span class="eyebrow">Atalhos</span>
                            <h2>Ações rápidas</h2>
                        </div>

                        <div class="quick-actions">
                            <a href="oficios/index.php">
                                <span class="quick-action-icon blue">＋</span>
                                <span><strong>Cadastrar ofício</strong><small>Novo documento recebido</small></span>
                                <b>→</b>
                            </a>
                            <a href="movimentacoes/index.php">
                                <span class="quick-action-icon violet">↗</span>
                                <span><strong>Encaminhar ofício</strong><small>Definir novo responsável</small></span>
                                <b>→</b>
                            </a>
                            <a href="recebimento/index.php">
                                <span class="quick-action-icon green">✓</span>
                                <span><strong>Confirmar recebimento</strong><small>Acesso rápido por responsável</small></span>
                                <b>→</b>
                            </a>
                        </div>
                    </article>

                    <article class="panel storage-panel">
                        <div class="panel-heading panel-heading-row">
                            <div>
                                <span class="eyebrow">Localização física</span>
                                <h2>Documentos guardados</h2>
                            </div>
                            <span class="storage-total">126</span>
                        </div>

                        <div class="storage-list">
                            <div><span>Arquivo Central</span><strong>78</strong></div>
                            <div><span>Gabinete</span><strong>24</strong></div>
                            <div><span>Assessoria</span><strong>15</strong></div>
                            <div><span>Outros locais</span><strong>9</strong></div>
                        </div>
                    </article>
                </aside>
            </section>

            <section class="bottom-grid">
                <article class="panel activity-panel">
                    <div class="panel-heading panel-heading-row">
                        <div>
                            <span class="eyebrow">Histórico</span>
                            <h2>Movimentações recentes</h2>
                        </div>
                        <a class="text-link" href="movimentacoes/index.php">Histórico completo <span>→</span></a>
                    </div>

                    <div class="timeline">
                        <?php foreach ($movimentacoes as $movimentacao): ?>
                            <div class="timeline-item">
                                <span class="timeline-dot dot-<?= htmlspecialchars($movimentacao['classe'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                <div class="timeline-content">
                                    <div>
                                        <strong><?= htmlspecialchars($movimentacao['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <time><?= htmlspecialchars($movimentacao['hora'], ENT_QUOTES, 'UTF-8') ?></time>
                                    </div>
                                    <p><?= htmlspecialchars($movimentacao['texto'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="panel people-panel">
                    <div class="panel-heading">
                        <span class="eyebrow">Distribuição</span>
                        <h2>Ofícios por responsável</h2>
                    </div>

                    <div class="people-list">
                        <div class="person-row">
                            <span class="person-avatar">MA</span>
                            <span><strong>Marcos Almeida</strong><small>5 ofícios</small></span>
                            <span class="person-count">5</span>
                        </div>
                        <div class="person-row">
                            <span class="person-avatar">CS</span>
                            <span><strong>Carla Souza</strong><small>4 ofícios</small></span>
                            <span class="person-count">4</span>
                        </div>
                        <div class="person-row">
                            <span class="person-avatar">PM</span>
                            <span><strong>Paulo Martins</strong><small>3 ofícios</small></span>
                            <span class="person-count">3</span>
                        </div>
                        <div class="person-row">
                            <span class="person-avatar">AC</span>
                            <span><strong>Arquivo Central</strong><small>2 movimentações hoje</small></span>
                            <span class="person-count muted">2</span>
                        </div>
                    </div>
                </article>
            </section>
        </main>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </div>
</div>

<script src="assets/js/app.js?v=20260928-1"></script>
</body>
</html>
