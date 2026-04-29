<?php
$paginaAtual = 'configuracoes';
$paginaTitulo = 'Configurações Gerais';
$paginaDescricao = 'Parâmetros globais do sistema, serviços, segurança e operação.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Tipos de Serviços';
$linkBotaoAcao = route_url('dono', 'tiposServicos');
$tituloPagina = 'Dono - Configurações Gerais';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid stats-grid-mini">
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-primary">⚙️</div><span class="trend up">Ativo</span></div><h3>18</h3><p>Parâmetros configurados</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-accent">🧾</div><span class="trend warn">3 revisão</span></div><h3>08</h3><p>Tipos de serviços</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-success">🛡️</div><span class="trend up">Seguro</span></div><h3>05</h3><p>Regras de acesso</p></article>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div>
                    <h2>Configurações do negócio</h2>
                    <p>Dados usados em documentos, relatórios e comunicação com clientes.</p>
                </div>
            </div>
            <form class="form-grid" method="POST" action="">
                <div class="form-group"><label for="empresa">Nome da empresa</label><input id="empresa" name="empresa" type="text" value="Agro Forest Amazon"></div>
                <div class="form-group"><label for="cnpj">CNPJ</label><input id="cnpj" name="cnpj" type="text" placeholder="00.000.000/0001-00"></div>
                <div class="form-group"><label for="email">E-mail principal</label><input id="email" name="email" type="email" value="contato@agroforest.com"></div>
                <div class="form-group"><label for="telefone">Telefone</label><input id="telefone" name="telefone" type="text" placeholder="(00) 00000-0000"></div>
                <div class="form-group col-2"><label for="endereco">Endereço</label><input id="endereco" name="endereco" type="text" placeholder="Rua, número, bairro, cidade"></div>
                <div class="form-actions col-2"><button class="btn-primary" type="submit">Salvar configurações</button></div>
            </form>
        </section>

        <section class="bottom-grid compact-card">
            <article class="card panel">
                <div class="panel-header">
                    <div><h2>Operação e SLA</h2><p>Prazos padrão para atendimento.</p></div>
                </div>
                <div class="config-grid">
                    <div class="switch-field"><div class="switch-field-info"><strong>Encaminhamento automático</strong><small>Enviar novos protocolos para o administrativo.</small></div><label class="switch"><input type="checkbox" checked><span class="switch-slider"></span></label></div>
                    <div class="form-group"><label for="sla-normal">Prazo padrão</label><select id="sla-normal"><option>2 dias úteis</option><option>3 dias úteis</option><option>5 dias úteis</option></select></div>
                    <div class="form-group"><label for="sla-urgente">Prazo urgente</label><select id="sla-urgente"><option>4 horas</option><option>8 horas</option><option>1 dia útil</option></select></div>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div><h2>Módulos de configuração</h2><p>Áreas administrativas mantidas pelo dono.</p></div>
                </div>
                <div class="owner-actions-grid owner-actions-grid-compact">
                    <a href="<?= route_url('recepcao', 'dashboard') ?>" class="owner-action"><span>🏢</span><strong>Recepção</strong><small>Acesso completo aos atendimentos, clientes e protocolos.</small></a>
                    <a href="<?= route_url('administrativo', 'dashboard') ?>" class="owner-action"><span>📋</span><strong>Administrativo</strong><small>Acesso completo às análises, orçamentos e documentos.</small></a>
                    <a href="<?= route_url('dono', 'tiposServicos') ?>" class="owner-action"><span>🧾</span><strong>Tipos de serviços</strong><small>Cadastro, valor base, prazo e setor.</small></a>
                    <a href="<?= route_url('dono', 'permissoes') ?>" class="owner-action"><span>🛡️</span><strong>Permissões</strong><small>Perfis e regras de acesso.</small></a>
                    <a href="<?= route_url('dono', 'usuarios') ?>" class="owner-action"><span>👤</span><strong>Usuários</strong><small>Equipe, status e cargos.</small></a>
                    <a href="<?= route_url('dono', 'relatorios') ?>" class="owner-action"><span>📊</span><strong>Relatórios</strong><small>Indicadores globais do sistema.</small></a>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
