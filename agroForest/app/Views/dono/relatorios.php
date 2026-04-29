<?php
$paginaAtual = 'relatorios';
$paginaTitulo = 'Relatórios Gerenciais';
$paginaDescricao = 'Indicadores globais de protocolos, usuários e operação.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$tituloPagina = 'Dono - Relatórios Gerenciais';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="stats-grid">
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-primary">📂</div><span class="trend up">+18%</span></div><h3>342</h3><p>Protocolos no trimestre</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-secondary">💰</div><span class="trend up">+11%</span></div><h3>89</h3><p>Orçamentos finalizados</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-accent">⏳</div><span class="trend warn">14</span></div><h3>14</h3><p>Pendências abertas</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-info">👤</div><span class="trend up">9 ativos</span></div><h3>12</h3><p>Usuários cadastrados</p></article>
        </section>
        <section class="card panel">
            <div class="panel-header"><div><h2>Resumo por área</h2><p>Relatório consolidado para acompanhamento do dono.</p></div></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Área</th><th>Volume</th><th>Concluídos</th><th>Pendências</th><th>Leitura</th></tr></thead>
                    <tbody>
                        <tr><td>Recepção</td><td>128 atendimentos</td><td>89 encaminhados</td><td>17</td><td><span class="status progress">Operação estável</span></td></tr>
                        <tr><td>Administrativo</td><td>94 análises</td><td>72 finalizadas</td><td>11</td><td><span class="status ok">Boa entrega</span></td></tr>
                        <tr><td>Gestão</td><td>12 usuários</td><td>9 ativos</td><td>3 revisões</td><td><span class="status pending">Acompanhar acessos</span></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
