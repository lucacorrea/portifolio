<?php
declare(strict_types=1);

$paginaAtual = 'dashboard';
$paginaTitulo = 'Dashboard';
$paginaDescricao = 'Visão geral dos ofícios e movimentações.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGO - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="page-content">
            <div class="page-heading">
                <div>
                    <span class="eyebrow">Casa Civil</span>
                    <h1>Dashboard</h1>
                    <p>Acompanhe a situação e a movimentação dos ofícios.</p>
                </div>

                <a class="primary-button" href="oficios/index.php">Novo ofício</a>
            </div>

            <section class="stats-grid" aria-label="Resumo dos ofícios">
                <article class="stat-card">
                    <span>Recebidos hoje</span>
                    <strong>0</strong>
                </article>
                <article class="stat-card">
                    <span>Aguardando encaminhamento</span>
                    <strong>0</strong>
                </article>
                <article class="stat-card">
                    <span>Aguardando recebimento</span>
                    <strong>0</strong>
                </article>
                <article class="stat-card">
                    <span>Em andamento</span>
                    <strong>0</strong>
                </article>
                <article class="stat-card">
                    <span>Concluídos</span>
                    <strong>0</strong>
                </article>
                <article class="stat-card">
                    <span>Arquivados</span>
                    <strong>0</strong>
                </article>
            </section>

            <section class="dashboard-grid">
                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <span class="eyebrow">Acompanhamento</span>
                            <h2>Ofícios recentes</h2>
                        </div>
                    </div>
                    <div class="empty-state">
                        <strong>Nenhum ofício cadastrado ainda.</strong>
                        <span>Os registros mais recentes aparecerão aqui.</span>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <span class="eyebrow">Histórico</span>
                            <h2>Movimentações recentes</h2>
                        </div>
                    </div>
                    <div class="empty-state">
                        <strong>Nenhuma movimentação registrada.</strong>
                        <span>Encaminhamentos e confirmações aparecerão aqui.</span>
                    </div>
                </article>
            </section>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
