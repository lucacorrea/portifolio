<?php
$paginaAtual = 'pendencias';
$paginaTitulo = 'Pendências';
$paginaDescricao = 'Acompanhe os processos com bloqueios, documentação incompleta e itens que impedem a finalização.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Protocolos Recebidos';
$linkBotaoAcao = route_url('administrativo', 'protocolosRecebidos');
$tituloPagina = 'Administrativo - Pendências';
$cssPagina = 'assets/css/administrativo/styleAdministrativo.css';

$pendencias = [
    [
        'protocolo' => 'PRT-2026-0502',
        'cliente' => 'Fernanda Martins',
        'motivo' => 'Documento complementar não enviado',
        'setor_origem' => 'Recepção',
        'aberto_em' => '28/04/2026 09:18',
        'prioridade' => 'Alta',
        'status' => 'Aguardando cliente'
    ],
    [
        'protocolo' => 'PRT-2026-0503',
        'cliente' => 'Ana Beatriz Costa',
        'motivo' => 'Divergência em dados cadastrais',
        'setor_origem' => 'Administrativo',
        'aberto_em' => '28/04/2026 10:22',
        'prioridade' => 'Média',
        'status' => 'Em revisão'
    ],
    [
        'protocolo' => 'PRT-2026-0505',
        'cliente' => 'Raimundo Lopes',
        'motivo' => 'Anexo ilegível para validação',
        'setor_origem' => 'Recepção',
        'aberto_em' => '28/04/2026 11:27',
        'prioridade' => 'Alta',
        'status' => 'Aguardando reenvio'
    ],
    [
        'protocolo' => 'PRT-2026-0507',
        'cliente' => 'Mariana Costa',
        'motivo' => 'Informação incompleta no serviço solicitado',
        'setor_origem' => 'Recepção',
        'aberto_em' => '28/04/2026 13:05',
        'prioridade' => 'Normal',
        'status' => 'Pendente'
    ],
    [
        'protocolo' => 'PRT-2026-0508',
        'cliente' => 'João Batista',
        'motivo' => 'Orçamento devolvido para ajuste',
        'setor_origem' => 'Administrativo',
        'aberto_em' => '28/04/2026 14:12',
        'prioridade' => 'Média',
        'status' => 'Em revisão'
    ],
];

function classe_status_pendencia_admin(string $status): string
{
    return match ($status) {
        'Pendente' => 'pending',
        'Aguardando cliente' => 'high',
        'Aguardando reenvio' => 'high',
        default => 'progress',
    };
}

function classe_prioridade_pendencia_admin(string $prioridade): string
{
    return match ($prioridade) {
        'Alta' => 'high',
        'Média' => 'pending',
        default => 'progress',
    };
}

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-danger">⏳</div>
                    <span class="trend down">5 abertas</span>
                </div>
                <h3>05</h3>
                <p>Pendências ativas no setor</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">📎</div>
                    <span class="trend warn">2 docs</span>
                </div>
                <h3>02</h3>
                <p>Dependem de documentação</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-primary">🔁</div>
                    <span class="trend up">2 revisão</span>
                </div>
                <h3>02</h3>
                <p>Em revisão interna</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">⚠️</div>
                    <span class="trend down">2 altas</span>
                </div>
                <h3>02</h3>
                <p>Com prioridade alta</p>
            </article>
        </section>

        <section class="card panel compact-card">
            <div class="panel-header">
                <div>
                    <h2>Filtros de pendências</h2>
                    <p>Organize a fila por status, prioridade, origem ou data.</p>
                </div>
            </div>

            <form class="filters-bar" method="GET" action="">
                <div class="filter-group">
                    <label for="q">Buscar</label>
                    <input type="text" id="q" name="q" placeholder="Cliente, protocolo ou motivo">
                </div>

                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Todos</option>
                        <option value="pendente">Pendente</option>
                        <option value="aguardando_cliente">Aguardando cliente</option>
                        <option value="aguardando_reenvio">Aguardando reenvio</option>
                        <option value="em_revisao">Em revisão</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="prioridade">Prioridade</label>
                    <select id="prioridade" name="prioridade">
                        <option value="">Todas</option>
                        <option value="normal">Normal</option>
                        <option value="media">Média</option>
                        <option value="alta">Alta</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="origem">Origem</label>
                    <select id="origem" name="origem">
                        <option value="">Todos</option>
                        <option value="recepcao">Recepção</option>
                        <option value="administrativo">Administrativo</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-primary">Filtrar</button>
                </div>
            </form>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div>
                    <h2>Fila de pendências</h2>
                    <p>Processos que precisam de correção, retorno ou complementação antes da conclusão.</p>
                </div>
                <a href="<?= route_url('administrativo', 'relatorios') ?>" class="chip">Ver relatório</a>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Protocolo</th>
                            <th>Cliente</th>
                            <th>Motivo</th>
                            <th>Origem</th>
                            <th>Aberto em</th>
                            <th>Prioridade</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendencias as $pendencia): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($pendencia['protocolo']) ?></strong></td>
                                <td><?= htmlspecialchars($pendencia['cliente']) ?></td>
                                <td><?= htmlspecialchars($pendencia['motivo']) ?></td>
                                <td><?= htmlspecialchars($pendencia['setor_origem']) ?></td>
                                <td><?= htmlspecialchars($pendencia['aberto_em']) ?></td>
                                <td>
                                    <span class="status <?= classe_prioridade_pendencia_admin($pendencia['prioridade']) ?>">
                                        <?= htmlspecialchars($pendencia['prioridade']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status <?= classe_status_pendencia_admin($pendencia['status']) ?>">
                                        <?= htmlspecialchars($pendencia['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="#" class="btn-outline">Ver</a>
                                        <a href="#" class="btn-primary">Resolver</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-info">
                    Mostrando 5 pendências da fila atual.
                </div>

                <div class="pagination-nav">
                    <a href="#" class="page-link">1</a>
                    <a href="#" class="page-link">2</a>
                    <a href="#" class="page-link">3</a>
                </div>
            </div>
        </section>

        <section class="bottom-grid">
            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h3>Pontos críticos</h3>
                        <p>Itens que mais impactam prazo e produtividade do setor.</p>
                    </div>
                </div>

                <div class="alert-list">
                    <div class="alert-item">
                        <strong>Dependência de retorno do cliente</strong>
                        <p>Parte das pendências está parada aguardando documento ou confirmação externa.</p>
                        <span class="alert-tag urgent">Travando fluxo</span>
                    </div>

                    <div class="alert-item">
                        <strong>Retrabalho interno</strong>
                        <p>Dados incompletos enviados pela recepção aumentam o tempo de revisão do administrativo.</p>
                        <span class="alert-tag attention">Ajustar processo</span>
                    </div>

                    <div class="alert-item">
                        <strong>Prioridade alta acumulando</strong>
                        <p>Pendências urgentes precisam de tratamento imediato para não contaminar a fila normal.</p>
                        <span class="alert-tag info">Monitorar</span>
                    </div>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h3>Resumo de pendências</h3>
                        <p>Leitura rápida da situação atual.</p>
                    </div>
                </div>

                <div class="config-grid">
                    <div class="setting-block">
                        <h3>Maior causa atual</h3>
                        <p>Documentação incompleta e anexos inválidos seguem como principal motivo de bloqueio.</p>
                    </div>

                    <div class="setting-block">
                        <h3>Ponto de melhoria</h3>
                        <p>Padronizar checklist entre recepção e administrativo deve reduzir retrabalho e atrasos.</p>
                    </div>

                    <div class="setting-block">
                        <h3>Meta operacional</h3>
                        <p>Reduzir a fila aberta e priorizar casos de alta urgência ainda dentro do mesmo turno.</p>
                    </div>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>