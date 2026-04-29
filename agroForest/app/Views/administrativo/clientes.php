<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Clientes';
$paginaDescricao = 'Consulte os clientes vinculados aos protocolos e acompanhe a situação administrativa de cada cadastro.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Cadastrar Cliente';
$linkBotaoAcao = route_url('administrativo', 'clienteCadastrar');
$tituloPagina = 'Administrativo - Clientes';
$cssPagina = 'assets/css/administrativo/styleadm.css';

$clientes = [
    [
        'nome' => 'Carlos Henrique',
        'telefone' => '(92) 99999-1020',
        'documento' => '123.456.789-00',
        'ultimo_protocolo' => 'PRT-2026-0501',
        'ultimo_servico' => 'Solicitação de orçamento',
        'status' => 'Ativo'
    ],
    [
        'nome' => 'Fernanda Martins',
        'telefone' => '(92) 99123-4088',
        'documento' => '987.654.321-00',
        'ultimo_protocolo' => 'PRT-2026-0502',
        'ultimo_servico' => 'Atendimento prioritário',
        'status' => 'Prioritário'
    ],
    [
        'nome' => 'Ana Beatriz Costa',
        'telefone' => '(92) 98888-2451',
        'documento' => '741.852.963-00',
        'ultimo_protocolo' => 'PRT-2026-0503',
        'ultimo_servico' => 'Análise documental',
        'status' => 'Pendente'
    ],
    [
        'nome' => 'João Pedro Silva',
        'telefone' => '(92) 99777-8874',
        'documento' => '369.258.147-00',
        'ultimo_protocolo' => 'PRT-2026-0504',
        'ultimo_servico' => 'Cadastro de serviço',
        'status' => 'Ativo'
    ],
    [
        'nome' => 'Raimundo Lopes',
        'telefone' => '(92) 99456-7721',
        'documento' => '852.456.951-00',
        'ultimo_protocolo' => 'PRT-2026-0505',
        'ultimo_servico' => 'Revisão de solicitação',
        'status' => 'Em análise'
    ],
];

function classe_status_cliente_admin(string $status): string
{
    return match ($status) {
        'Ativo' => 'ok',
        'Pendente' => 'pending',
        'Prioritário' => 'high',
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
                    <div class="stat-icon soft-primary">👥</div>
                    <span class="trend up">+8 mês</span>
                </div>
                <h3>184</h3>
                <p>Clientes cadastrados</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">📂</div>
                    <span class="trend up">126 ativos</span>
                </div>
                <h3>126</h3>
                <p>Com processos ativos</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">📎</div>
                    <span class="trend warn">14 revisão</span>
                </div>
                <h3>14</h3>
                <p>Cadastros com pendência</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-danger">⚠️</div>
                    <span class="trend down">6 alta</span>
                </div>
                <h3>06</h3>
                <p>Clientes prioritários</p>
            </article>
        </section>

        <section class="card panel compact-card">
            <div class="panel-header">
                <div>
                    <h2>Filtros de clientes</h2>
                    <p>Busque e organize os cadastros acompanhados pelo administrativo.</p>
                </div>
            </div>

            <form class="filters-bar" method="GET" action="">
                <div class="filter-group">
                    <label for="q">Buscar</label>
                    <input type="text" id="q" name="q" placeholder="Nome, telefone, documento ou protocolo">
                </div>

                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Todos</option>
                        <option value="ativo">Ativo</option>
                        <option value="pendente">Pendente</option>
                        <option value="prioritario">Prioritário</option>
                        <option value="em_analise">Em análise</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="periodo">Período</label>
                    <select id="periodo" name="periodo">
                        <option value="">Todos</option>
                        <option value="hoje">Hoje</option>
                        <option value="semana">Esta semana</option>
                        <option value="mes">Este mês</option>
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
                    <h2>Clientes acompanhados</h2>
                    <p>Lista dos clientes vinculados aos protocolos e orçamentos do setor.</p>
                </div>
                <a href="<?= route_url('administrativo', 'relatorios') ?>" class="chip">Ver relatório</a>
                <a href="<?= route_url('administrativo', 'clienteCadastrar') ?>" class="btn-primary">Cadastrar Cliente</a>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Telefone</th>
                            <th>Documento</th>
                            <th>Último protocolo</th>
                            <th>Último serviço</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td>
                                    <div class="client-name"><?= htmlspecialchars($cliente['nome']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($cliente['telefone']) ?></td>
                                <td><?= htmlspecialchars($cliente['documento']) ?></td>
                                <td><?= htmlspecialchars($cliente['ultimo_protocolo']) ?></td>
                                <td><?= htmlspecialchars($cliente['ultimo_servico']) ?></td>
                                <td>
                                    <span class="status <?= classe_status_cliente_admin($cliente['status']) ?>">
                                        <?= htmlspecialchars($cliente['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="<?= route_url('administrativo', 'clienteVisualizar') ?>" class="btn-outline">Ver</a>
                                        <a href="<?= route_url('administrativo', 'clienteEditar') ?>" class="btn-primary">Editar</a>
                                        <button type="button" class="btn-danger" data-delete-name="<?= htmlspecialchars($cliente['nome']) ?>">Excluir</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-info">
                    Mostrando 5 clientes da listagem atual.
                </div>

                <div class="pagination-nav">
                    <a href="<?= route_url('administrativo', 'clientes') ?>" class="page-link active">1</a>
                    <a href="<?= route_url('administrativo', 'clientes') ?>" class="page-link">2</a>
                    <a href="<?= route_url('administrativo', 'clientes') ?>" class="page-link">3</a>
                </div>
            </div>
        </section>

     
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<div class="modal-backdrop" id="deleteModal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="deleteTitle">
        <div class="modal-header"><h3 id="deleteTitle">Confirmar exclusão</h3></div>
        <div class="modal-body"><p>Deseja excluir <strong id="deleteItemName">este cliente</strong>? A exclusão acontece somente após esta confirmação.</p></div>
        <div class="modal-footer"><button type="button" class="btn-secondary" data-modal-close>Cancelar</button><button type="button" class="btn-danger" data-modal-close>Excluir</button></div>
    </div>
</div>
<script>
document.querySelectorAll('[data-delete-name]').forEach((button) => {
    button.addEventListener('click', () => {
        document.getElementById('deleteItemName').textContent = button.dataset.deleteName;
        document.getElementById('deleteModal').classList.add('active');
    });
});
document.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', () => document.getElementById('deleteModal').classList.remove('active'));
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
