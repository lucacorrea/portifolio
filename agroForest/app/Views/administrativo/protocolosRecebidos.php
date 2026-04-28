<?php
$paginaAtual = 'protocolosRecebidos';
$paginaTitulo = 'Protocolos Recebidos';
$paginaDescricao = 'Acompanhe os protocolos enviados pela recepção e organize a fila de análise do administrativo.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Novo Orçamento';
$linkBotaoAcao = route_url('administrativo', 'orcamentos');
$tituloPagina = 'Administrativo - Protocolos Recebidos';
$cssPagina = 'assets/css/administrativo/styleAdministrativo.css';

$protocolos = [
    [
        'numero' => 'PRT-2026-0501',
        'cliente' => 'Carlos Henrique',
        'servico' => 'Solicitação de orçamento',
        'prioridade' => 'Normal',
        'recebido_em' => '28/04/2026 08:30',
        'status' => 'Em análise'
    ],
    [
        'numero' => 'PRT-2026-0502',
        'cliente' => 'Fernanda Martins',
        'servico' => 'Atendimento prioritário',
        'prioridade' => 'Alta',
        'recebido_em' => '28/04/2026 09:10',
        'status' => 'Urgente'
    ],
    [
        'numero' => 'PRT-2026-0503',
        'cliente' => 'Ana Beatriz Costa',
        'servico' => 'Análise documental',
        'prioridade' => 'Média',
        'recebido_em' => '28/04/2026 10:05',
        'status' => 'Pendente'
    ],
    [
        'numero' => 'PRT-2026-0504',
        'cliente' => 'João Pedro Silva',
        'servico' => 'Cadastro de serviço',
        'prioridade' => 'Normal',
        'recebido_em' => '28/04/2026 10:42',
        'status' => 'Concluído'
    ],
    [
        'numero' => 'PRT-2026-0505',
        'cliente' => 'Raimundo Lopes',
        'servico' => 'Revisão de solicitação',
        'prioridade' => 'Média',
        'recebido_em' => '28/04/2026 11:15',
        'status' => 'Em análise'
    ],
];

function classe_status_admin(string $status): string
{
    return match ($status) {
        'Concluído' => 'ok',
        'Pendente' => 'pending',
        'Urgente' => 'high',
        default => 'progress',
    };
}

function classe_prioridade_admin(string $prioridade): string
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
                    <div class="stat-icon soft-primary">📥</div>
                    <span class="trend up">18 hoje</span>
                </div>
                <h3>18</h3>
                <p>Protocolos recebidos no dia</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">🧾</div>
                    <span class="trend up">11 ativos</span>
                </div>
                <h3>11</h3>
                <p>Em análise no administrativo</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">📎</div>
                    <span class="trend warn">3 revisão</span>
                </div>
                <h3>03</h3>
                <p>Com pendência documental</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-danger">⚠️</div>
                    <span class="trend down">4 altas</span>
                </div>
                <h3>04</h3>
                <p>Protocolos com prioridade alta</p>
            </article>
        </section>

        <section class="card panel compact-card">
            <div class="panel-header">
                <div>
                    <h2>Filtros rápidos</h2>
                    <p>Organize a fila por cliente, status, prioridade ou período.</p>
                </div>
            </div>

            <form class="filters-bar" method="GET" action="">
                <div class="filter-group">
                    <label for="q">Buscar</label>
                    <input type="text" id="q" name="q" placeholder="Cliente, protocolo ou serviço">
                </div>

                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Todos</option>
                        <option value="em_analise">Em análise</option>
                        <option value="pendente">Pendente</option>
                        <option value="urgente">Urgente</option>
                        <option value="concluido">Concluído</option>
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
                    <label for="data">Data</label>
                    <input type="date" id="data" name="data">
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
                    <h2>Fila de protocolos recebidos</h2>
                    <p>Solicitações encaminhadas pela recepção para análise administrativa.</p>
                </div>
                <a href="<?= route_url('administrativo', 'relatorios') ?>" class="chip">Ver relatório</a>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Protocolo</th>
                            <th>Cliente</th>
                            <th>Serviço</th>
                            <th>Prioridade</th>
                            <th>Recebido em</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($protocolos as $protocolo): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($protocolo['numero']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($protocolo['cliente']) ?></td>
                                <td><?= htmlspecialchars($protocolo['servico']) ?></td>
                                <td>
                                    <span class="status <?= classe_prioridade_admin($protocolo['prioridade']) ?>">
                                        <?= htmlspecialchars($protocolo['prioridade']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($protocolo['recebido_em']) ?></td>
                                <td>
                                    <span class="status <?= classe_status_admin($protocolo['status']) ?>">
                                        <?= htmlspecialchars($protocolo['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="#" class="btn-outline">Ver</a>
                                        <a href="<?= route_url('administrativo', 'orcamentos') ?>" class="btn-primary">Orçar</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-info">
                    Mostrando 5 protocolos da fila atual.
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
                        <h3>Pontos de atenção</h3>
                        <p>O que o time precisa tratar primeiro.</p>
                    </div>
                </div>

                <div class="alert-list">
                    <div class="alert-item">
                        <strong>Protocolos urgentes</strong>
                        <p>Existem 4 processos com prioridade alta aguardando resposta do administrativo.</p>
                        <span class="alert-tag urgent">Tratar agora</span>
                    </div>

                    <div class="alert-item">
                        <strong>Documentação incompleta</strong>
                        <p>3 protocolos não podem avançar para orçamento sem conferência dos anexos.</p>
                        <span class="alert-tag attention">Conferência</span>
                    </div>

                    <div class="alert-item">
                        <strong>Fila crescente</strong>
                        <p>O volume recebido hoje está acima da média e pode impactar o prazo de retorno.</p>
                        <span class="alert-tag info">Monitorar</span>
                    </div>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h3>Resumo operacional</h3>
                        <p>Visão rápida da performance da fila.</p>
                    </div>
                </div>

                <div class="config-grid">
                    <div class="setting-block">
                        <h3>Tempo médio de análise</h3>
                        <p>18 minutos por protocolo, mantendo o ritmo esperado do setor.</p>
                    </div>

                    <div class="setting-block">
                        <h3>Taxa de conclusão</h3>
                        <p>39% dos protocolos do dia já foram transformados em orçamento ou finalizados.</p>
                    </div>

                    <div class="setting-block">
                        <h3>Risco atual</h3>
                        <p>O principal gargalo continua sendo documentação pendente enviada pela recepção.</p>
                    </div>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>