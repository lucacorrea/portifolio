<?php
$paginaAtual = 'dashboard';
$paginaTitulo = 'Dashboard do Dono';
$paginaDescricao = 'Visão consolidada de operação, acessos, serviços e resultados.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Configurações';
$linkBotaoAcao = route_url('dono', 'configuracoes');
$tituloPagina = 'Dono - Dashboard do Dono';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="card stat-card">
                <div class="stat-top"><div class="stat-icon soft-primary">📂</div><span class="trend up">+18%</span></div>
                <h3>342</h3>
                <p>Protocolos no trimestre</p>
            </article>
            <article class="card stat-card">
                <div class="stat-top"><div class="stat-icon soft-secondary">👤</div><span class="trend up">9 ativos</span></div>
                <h3>12</h3>
                <p>Usuários cadastrados</p>
            </article>
            <article class="card stat-card">
                <div class="stat-top"><div class="stat-icon soft-accent">🧾</div><span class="trend warn">3 revisão</span></div>
                <h3>08</h3>
                <p>Tipos de serviços</p>
            </article>
            <article class="card stat-card">
                <div class="stat-top"><div class="stat-icon soft-info">💰</div><span class="trend up">R$ 84k</span></div>
                <h3>89</h3>
                <p>Orçamentos finalizados</p>
            </article>
        </section>

        <section class="main-grid">
            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h2>Atalhos de gestão</h2>
                        <p>Ações principais para manter o sistema organizado.</p>
                    </div>
                </div>
                <div class="owner-actions-grid">
                    <a href="<?= route_url('recepcao', 'dashboard') ?>" class="owner-action">
                        <span>🏢</span>
                        <strong>Entrar na recepção</strong>
                        <small>Veja clientes, documentos, protocolos e cadastros iniciais.</small>
                    </a>
                    <a href="<?= route_url('administrativo', 'dashboard') ?>" class="owner-action">
                        <span>📋</span>
                        <strong>Entrar no administrativo</strong>
                        <small>Acompanhe análises, orçamentos, pendências e relatórios.</small>
                    </a>
                    <a href="<?= route_url('dono', 'usuarioCadastrar') ?>" class="owner-action">
                        <span>👤</span>
                        <strong>Cadastrar usuário</strong>
                        <small>Crie acessos para recepção, administrativo e dono.</small>
                    </a>
                    <a href="<?= route_url('dono', 'tipoServicoCadastrar') ?>" class="owner-action">
                        <span>🧾</span>
                        <strong>Cadastrar serviço</strong>
                        <small>Defina tipos, prazos, valores base e setor responsável.</small>
                    </a>
                    <a href="<?= route_url('dono', 'permissaoCadastrar') ?>" class="owner-action">
                        <span>🛡️</span>
                        <strong>Nova permissão</strong>
                        <small>Ajuste o que cada perfil pode visualizar e alterar.</small>
                    </a>
                    <a href="<?= route_url('dono', 'configuracoes') ?>" class="owner-action">
                        <span>⚙️</span>
                        <strong>Parâmetros gerais</strong>
                        <small>Controle negócio, notificações, SLA e segurança.</small>
                    </a>
                </div>
            </article>

            <aside class="card panel">
                <div class="panel-header">
                    <div>
                        <h2>Pontos de atenção</h2>
                        <p>Itens que merecem acompanhamento.</p>
                    </div>
                </div>
                <div class="alert-list">
                    <div class="alert-item"><strong>3 serviços sem valor base</strong><p>Complete a parametrização para facilitar orçamentos.</p><span class="alert-tag attention">Configuração</span></div>
                    <div class="alert-item"><strong>2 usuários em revisão</strong><p>Confirme permissões antes de liberar acesso total.</p><span class="alert-tag info">Acesso</span></div>
                    <div class="alert-item"><strong>14 pendências abertas</strong><p>Acompanhe a fila do administrativo nesta semana.</p><span class="alert-tag urgent">Operação</span></div>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
