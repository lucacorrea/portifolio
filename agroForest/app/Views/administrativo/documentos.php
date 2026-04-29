<?php
$paginaAtual = 'documentos';
$paginaTitulo = 'Documentos';
$paginaDescricao = 'Gerencie os documentos recebidos com os protocolos e acompanhe a situação de validação de cada arquivo.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Protocolos Recebidos';
$linkBotaoAcao = route_url('administrativo', 'protocolosRecebidos');
$tituloPagina = 'Administrativo - Documentos';
$cssPagina = 'assets/css/administrativo/styleadm.css';

$documentos = [
    [
        'protocolo' => 'PRT-2026-0501',
        'cliente' => 'Carlos Henrique',
        'arquivo' => 'documentos_cliente_carlos.pdf',
        'tipo' => 'PDF',
        'enviado_em' => '28/04/2026 08:42',
        'status' => 'Validado'
    ],
    [
        'protocolo' => 'PRT-2026-0502',
        'cliente' => 'Fernanda Martins',
        'arquivo' => 'anexo_prioritario_fernanda.jpg',
        'tipo' => 'Imagem',
        'enviado_em' => '28/04/2026 09:16',
        'status' => 'Pendente'
    ],
    [
        'protocolo' => 'PRT-2026-0503',
        'cliente' => 'Ana Beatriz Costa',
        'arquivo' => 'analise_documental_ana.pdf',
        'tipo' => 'PDF',
        'enviado_em' => '28/04/2026 10:11',
        'status' => 'Em revisão'
    ],
    [
        'protocolo' => 'PRT-2026-0504',
        'cliente' => 'João Pedro Silva',
        'arquivo' => 'cadastro_servico_joao.png',
        'tipo' => 'Imagem',
        'enviado_em' => '28/04/2026 10:48',
        'status' => 'Validado'
    ],
    [
        'protocolo' => 'PRT-2026-0505',
        'cliente' => 'Raimundo Lopes',
        'arquivo' => 'revisao_solicitacao_raimundo.pdf',
        'tipo' => 'PDF',
        'enviado_em' => '28/04/2026 11:22',
        'status' => 'Pendente'
    ],
];

function classe_status_documento_admin(string $status): string
{
    return match ($status) {
        'Validado' => 'ok',
        'Pendente' => 'pending',
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
                    <div class="stat-icon soft-primary">📎</div>
                    <span class="trend up">24 hoje</span>
                </div>
                <h3>24</h3>
                <p>Documentos recebidos no dia</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">✅</div>
                    <span class="trend up">17 validados</span>
                </div>
                <h3>17</h3>
                <p>Arquivos validados</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">⏳</div>
                    <span class="trend warn">5 pendentes</span>
                </div>
                <h3>05</h3>
                <p>Aguardando conferência</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-danger">⚠️</div>
                    <span class="trend down">2 revisão</span>
                </div>
                <h3>02</h3>
                <p>Com inconsistência</p>
            </article>
        </section>

        <section class="card panel compact-card">
            <div class="panel-header">
                <div>
                    <h2>Filtros de documentos</h2>
                    <p>Busque documentos por protocolo, cliente, tipo ou situação.</p>
                </div>
            </div>

            <form class="filters-bar" method="GET" action="">
                <div class="filter-group">
                    <label for="q">Buscar</label>
                    <input type="text" id="q" name="q" placeholder="Cliente, protocolo ou nome do arquivo">
                </div>

                <div class="filter-group">
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo">
                        <option value="">Todos</option>
                        <option value="pdf">PDF</option>
                        <option value="imagem">Imagem</option>
                        <option value="documento">Documento</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Todos</option>
                        <option value="validado">Validado</option>
                        <option value="pendente">Pendente</option>
                        <option value="revisao">Em revisão</option>
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
                    <h2>Documentos recebidos</h2>
                    <p>Arquivos vinculados aos protocolos para conferência e validação do administrativo.</p>
                </div>
                <a href="<?= route_url('administrativo', 'relatorios') ?>" class="chip">Ver relatório</a>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Protocolo</th>
                            <th>Cliente</th>
                            <th>Arquivo</th>
                            <th>Tipo</th>
                            <th>Enviado em</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documentos as $documento): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($documento['protocolo']) ?></strong></td>
                                <td><?= htmlspecialchars($documento['cliente']) ?></td>
                                <td><?= htmlspecialchars($documento['arquivo']) ?></td>
                                <td><?= htmlspecialchars($documento['tipo']) ?></td>
                                <td><?= htmlspecialchars($documento['enviado_em']) ?></td>
                                <td>
                                    <span class="status <?= classe_status_documento_admin($documento['status']) ?>">
                                        <?= htmlspecialchars($documento['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="<?= route_url('administrativo', 'documentoVisualizar') ?>" class="btn-outline">Visualizar</a>
                                        <a href="<?= route_url('administrativo', 'documentoVisualizar') ?>" class="btn-primary">Validar</a>
                                        <button type="button" class="btn-danger" data-delete-name="<?= htmlspecialchars($documento['arquivo']) ?>">Excluir</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-info">
                    Mostrando 5 documentos da listagem atual.
                </div>

                <div class="pagination-nav">
                    <a href="<?= route_url('administrativo', 'documentos') ?>" class="page-link active">1</a>
                    <a href="<?= route_url('administrativo', 'documentos') ?>" class="page-link">2</a>
                    <a href="<?= route_url('administrativo', 'documentos') ?>" class="page-link">3</a>
                </div>
            </div>
        </section>

       

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<div class="modal-backdrop" id="deleteModal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="deleteTitle">
        <div class="modal-header"><h3 id="deleteTitle">Confirmar exclusão</h3></div>
        <div class="modal-body"><p>Deseja excluir <strong id="deleteItemName">este documento</strong>? O arquivo não possui página de exclusão separada.</p></div>
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
