<?php
$paginaAtual = 'orcamentos';
$paginaTitulo = 'Orçamentos';
$paginaDescricao = 'Criação e gestão dos orçamentos da área administrativa.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = '';
$linkBotaoAcao = '#';
$tituloPagina = 'Administrativo - Orçamentos';
$cssPagina = 'assets/css/administrativo/administrativo.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="placeholder-card">
            <h2>Orçamentos</h2>
            <p>Criação e gestão dos orçamentos da área administrativa.</p>
            <p>Arquivo base criado para continuar a construção do sistema com organização.</p>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
<?php
$paginaAtual = 'orcamentos';
$paginaTitulo = 'Orçamentos';
$paginaDescricao = 'Monte, acompanhe e finalize os orçamentos gerados a partir dos protocolos recebidos.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Protocolos Recebidos';
$linkBotaoAcao = route_url('administrativo', 'protocolosRecebidos');
$tituloPagina = 'Administrativo - Orçamentos';
$cssPagina = 'assets/css/administrativo/styleAdministrativo.css';

$orcamentos = [
    [
        'codigo' => 'ORC-2026-0101',
        'protocolo' => 'PRT-2026-0501',
        'cliente' => 'Carlos Henrique',
        'servico' => 'Solicitação de orçamento',
        'valor' => 'R$ 2.450,00',
        'status' => 'Em elaboração',
        'prazo' => '29/04/2026'
    ],
    [
        'codigo' => 'ORC-2026-0102',
        'protocolo' => 'PRT-2026-0502',
        'cliente' => 'Fernanda Martins',
        'servico' => 'Atendimento prioritário',
        'valor' => 'R$ 3.980,00',
        'status' => 'Urgente',
        'prazo' => '28/04/2026'
    ],
    [
        'codigo' => 'ORC-2026-0103',
        'protocolo' => 'PRT-2026-0503',
        'cliente' => 'Ana Beatriz Costa',
        'servico' => 'Análise documental',
        'valor' => 'R$ 1.280,00',
        'status' => 'Aguardando aprovação',
        'prazo' => '30/04/2026'
    ],
    [
        'codigo' => 'ORC-2026-0104',
        'protocolo' => 'PRT-2026-0504',
        'cliente' => 'João Pedro Silva',
        'servico' => 'Cadastro de serviço',
        'valor' => 'R$ 890,00',
        'status' => 'Concluído',
        'prazo' => '27/04/2026'
    ],
];

function classe_status_orcamento(string $status): string
{
    return match ($status) {
        'Concluído' => 'ok',
        'Urgente' => 'high',
        'Aguardando aprovação' => 'pending',
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
                    <div class="stat-icon soft-primary">💰</div>
                    <span class="trend up">11 ativos</span>
                </div>
                <h3>11</h3>
                <p>Orçamentos em elaboração</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">✅</div>
                    <span class="trend up">7 fechados</span>
                </div>
                <h3>07</h3>
                <p>Finalizados no período</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">🕒</div>
                    <span class="trend warn">3 aguardando</span>
                </div>
                <h3>03</h3>
                <p>Aguardando aprovação</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-danger">⚠️</div>
                    <span class="trend down">2 urgentes</span>
                </div>
                <h3>02</h3>
                <p>Com prazo crítico</p>
            </article>
        </section>

        <section class="card panel compact-card">
            <div class="panel-header">
                <div>
                    <h2>Novo orçamento</h2>
                    <p>Preencha os dados principais para iniciar um orçamento administrativo.</p>
                </div>
            </div>

            <form class="form-grid" method="POST" action="">
                <div class="form-group">
                    <label for="protocolo">Protocolo</label>
                    <select id="protocolo" name="protocolo">
                        <option value="">Selecione o protocolo</option>
                        <option value="PRT-2026-0501">PRT-2026-0501</option>
                        <option value="PRT-2026-0502">PRT-2026-0502</option>
                        <option value="PRT-2026-0503">PRT-2026-0503</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cliente">Cliente</label>
                    <input type="text" id="cliente" name="cliente" placeholder="Nome do cliente">
                </div>

                <div class="form-group col-2">
                    <label for="servico">Serviço</label>
                    <input type="text" id="servico" name="servico" placeholder="Descrição do serviço orçado">
                </div>

                <div class="form-group">
                    <label for="valor">Valor do orçamento</label>
                    <input type="text" id="valor" name="valor" placeholder="R$ 0,00">
                </div>

                <div class="form-group">
                    <label for="prazo">Prazo de retorno</label>
                    <input type="date" id="prazo" name="prazo">
                </div>

                <div class="form-group col-2">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes" rows="4" placeholder="Informações adicionais para o orçamento"></textarea>
                </div>

                <div class="form-actions col-2">
                    <a href="<?= route_url('administrativo', 'dashboard') ?>" class="btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-primary">Salvar Orçamento</button>
                </div>
            </form>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div>
                    <h2>Orçamentos cadastrados</h2>
                    <p>Lista de orçamentos em andamento, aguardando aprovação ou concluídos.</p>
                </div>
                <a href="<?= route_url('administrativo', 'relatorios') ?>" class="chip">Ver relatório</a>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Protocolo</th>
                            <th>Cliente</th>
                            <th>Serviço</th>
                            <th>Valor</th>
                            <th>Prazo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orcamentos as $orcamento): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($orcamento['codigo']) ?></strong></td>
                                <td><?= htmlspecialchars($orcamento['protocolo']) ?></td>
                                <td><?= htmlspecialchars($orcamento['cliente']) ?></td>
                                <td><?= htmlspecialchars($orcamento['servico']) ?></td>
                                <td><?= htmlspecialchars($orcamento['valor']) ?></td>
                                <td><?= htmlspecialchars($orcamento['prazo']) ?></td>
                                <td>
                                    <span class="status <?= classe_status_orcamento($orcamento['status']) ?>">
                                        <?= htmlspecialchars($orcamento['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="#" class="btn-outline">Ver</a>
                                        <a href="#" class="btn-primary">Editar</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bottom-grid">
            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h3>Pontos de atenção</h3>
                        <p>Itens que podem impactar prazo e fechamento.</p>
                    </div>
                </div>

                <div class="alert-list">
                    <div class="alert-item">
                        <strong>Prazo crítico</strong>
                        <p>2 orçamentos precisam ser concluídos ainda hoje para não estourar o prazo acordado.</p>
                        <span class="alert-tag urgent">Prioridade máxima</span>
                    </div>

                    <div class="alert-item">
                        <strong>Aguardando aprovação</strong>
                        <p>Existem 3 propostas já montadas aguardando validação final antes do envio.</p>
                        <span class="alert-tag attention">Validar</span>
                    </div>

                    <div class="alert-item">
                        <strong>Volume de trabalho</strong>
                        <p>A fila atual exige atenção especial na distribuição das análises entre o time.</p>
                        <span class="alert-tag info">Acompanhar</span>
                    </div>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h3>Resumo do setor</h3>
                        <p>Indicadores rápidos dos orçamentos administrativos.</p>
                    </div>
                </div>

                <div class="config-grid">
                    <div class="setting-block">
                        <h3>Ticket médio</h3>
                        <p>O valor médio dos orçamentos ativos está em R$ 2.150,00.</p>
                    </div>

                    <div class="setting-block">
                        <h3>Eficiência operacional</h3>
                        <p>O setor está mantendo boa taxa de entrega, mas precisa reduzir urgências de última hora.</p>
                    </div>

                    <div class="setting-block">
                        <h3>Melhor oportunidade</h3>
                        <p>Ganhar velocidade na validação documental para liberar mais orçamentos no mesmo dia.</p>
                    </div>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>