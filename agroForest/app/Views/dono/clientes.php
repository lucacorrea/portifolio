<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Clientes e Contratos';
$paginaDescricao = 'Visão consolidada dos clientes e contratos vinculados, disponível para o nível dono.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Relatórios';
$linkBotaoAcao = route_url('dono', 'relatorios');
$tituloPagina = 'Dono - Clientes e Contratos';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
$clientes = clientes_contratos_lista();
$indicadores = clientes_contratos_indicadores($clientes);

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="card stat-card">
                <div class="stat-top"><div class="stat-icon soft-primary">👥</div><span class="trend up">base</span></div>
                <h3><?= htmlspecialchars((string) $indicadores['clientes']) ?></h3>
                <p>Clientes acompanhados</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top"><div class="stat-icon soft-secondary">📄</div><span class="trend up"><?= htmlspecialchars((string) $indicadores['contratos_ativos']) ?> ativos</span></div>
                <h3><?= htmlspecialchars((string) $indicadores['contratos']) ?></h3>
                <p>Contratos vinculados</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top"><div class="stat-icon soft-accent">✍️</div><span class="trend warn">pendências</span></div>
                <h3><?= htmlspecialchars((string) $indicadores['contratos_pendentes']) ?></h3>
                <p>Contratos em atenção</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top"><div class="stat-icon soft-info">💰</div><span class="trend up">carteira</span></div>
                <h3><?= contrato_valor_formatado((float) $indicadores['valor_total']) ?></h3>
                <p>Valor contratado</p>
            </article>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div>
                    <h2>Clientes com contratos</h2>
                    <p>Consulta consolidada para acompanhamento gerencial em todos os níveis operacionais.</p>
                </div>
                <a href="<?= route_url('administrativo', 'clientes') ?>" class="chip">Abrir administrativo</a>
            </div>

            <div class="table-responsive">
                <table class="client-contract-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Contato</th>
                            <th>Último protocolo</th>
                            <th>Contratos</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <?php $contratoPrincipal = $cliente['contratos'][0] ?? null; ?>
                            <tr>
                                <td data-label="Cliente">
                                    <div class="client-name"><?= htmlspecialchars($cliente['nome']) ?></div>
                                    <div class="client-sub"><?= htmlspecialchars($cliente['documento']) ?></div>
                                </td>
                                <td data-label="Contato">
                                    <?= htmlspecialchars($cliente['telefone']) ?><br>
                                    <span class="client-sub"><?= htmlspecialchars($cliente['email']) ?></span>
                                </td>
                                <td data-label="Último protocolo">
                                    <strong><?= htmlspecialchars($cliente['ultimo_protocolo']) ?></strong><br>
                                    <span class="client-sub"><?= htmlspecialchars($cliente['ultimo_servico']) ?></span>
                                </td>
                                <td class="contracts-cell" data-label="Contratos">
                                    <?php
                                    $areaContrato = 'dono';
                                    $contratosCliente = $cliente['contratos'] ?? [];
                                    require APP_PATH . '/Views/shared/clienteContratosResumo.php';
                                    ?>
                                </td>
                                <td data-label="Status"><span class="status <?= cliente_status_classe($cliente['status']) ?>"><?= htmlspecialchars($cliente['status']) ?></span></td>
                                <td data-label="Ações">
                                    <div class="table-actions">
                                        <a href="<?= route_url('administrativo', 'clienteVisualizar') ?>" class="btn-outline">Ver cadastro</a>
                                        <?php if ($contratoPrincipal): ?>
                                            <a href="<?= htmlspecialchars(contrato_visualizar_url('dono', $contratoPrincipal['numero'])) ?>" class="btn-outline">Ver contrato</a>
                                        <?php endif; ?>
                                        <a href="<?= route_url('administrativo', 'orcamentos') ?>" class="btn-primary">Orçamentos</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
