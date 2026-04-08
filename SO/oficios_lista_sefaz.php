<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
sefaz_check();

$page_title = "Lista de Solicitações - SEMFAZ";

// Filtros
$where = "TRUE";

if (isset($_GET['status']) && $_GET['status'] != '') {
    $where .= " AND o.status = " . $pdo->quote($_GET['status']);
}
if (isset($_GET['busca']) && $_GET['busca'] != '') {
    $where .= " AND (o.numero LIKE " . $pdo->quote('%' . $_GET['busca'] . '%') . " OR s.nome LIKE " . $pdo->quote('%' . $_GET['busca'] . '%') . ")";
}
if (isset($_GET['secretaria']) && $_GET['secretaria'] != '') {
    $where .= " AND o.secretaria_id = " . (int)$_GET['secretaria'];
}
if (isset($_GET['data_inicio']) && $_GET['data_inicio'] != '') {
    $where .= " AND DATE(o.criado_em) >= " . $pdo->quote($_GET['data_inicio']);
}
if (isset($_GET['data_fim']) && $_GET['data_fim'] != '') {
    $where .= " AND DATE(o.criado_em) <= " . $pdo->quote($_GET['data_fim']);
}

// Paginação
$itens_por_pagina = 10;
$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$pagina_atual = max(1, $pagina_atual);
$offset = ($pagina_atual - 1) * $itens_por_pagina;

$stmt_count = $pdo->query("
    SELECT COUNT(*)
    FROM oficios o
    JOIN secretarias s ON o.secretaria_id = s.id
    WHERE $where
");
$total_registros = (int)$stmt_count->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $itens_por_pagina));

if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
    $offset = ($pagina_atual - 1) * $itens_por_pagina;
}

$stmt = $pdo->query("
    SELECT o.*, s.nome as secretaria, u.nome as usuario
    FROM oficios o
    JOIN secretarias s ON o.secretaria_id = s.id
    JOIN usuarios u ON o.usuario_id = u.id
    WHERE $where
    ORDER BY o.criado_em DESC
    LIMIT $itens_por_pagina OFFSET $offset
");
$oficios = $stmt->fetchAll();

$secretarias_list = $pdo->query("SELECT id, nome FROM secretarias ORDER BY nome")->fetchAll();

include 'views/layout/header.php';
?>

<style>
    .filtros-grid {
        display: grid;
        grid-template-columns: 1fr 2fr 1fr 1fr 52px;
        gap: 1rem;
        align-items: end;
    }

    .filtros-grid .form-group {
        margin-bottom: 0;
        min-width: 0;
    }

    .filtros-grid .form-control {
        width: 100%;
    }

    .filtros-acoes {
        display: flex;
        justify-content: center;
        align-items: end;
    }

    .btn-limpar-filtros {
        width: 42px;
        height: 40px;
        padding: 0;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        color: #64748b;
        background: #fff;
        text-decoration: none;
        transition: all .2s ease;
    }

    .btn-limpar-filtros:hover {
        background: #f8fafc;
        color: #334155;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .table-vcenter {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
    }

    .table-vcenter th,
    .table-vcenter td {
        vertical-align: middle;
        padding: 0.85rem 0.75rem;
    }

    .table-vcenter thead th {
        white-space: nowrap;
    }

    .text-nowrap {
        white-space: nowrap !important;
    }

    .acoes-nowrap {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        align-items: center;
        flex-wrap: nowrap;
        white-space: nowrap;
    }

    .paginacao-lista {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
        text-align: center;
    }

    @media (max-width: 1200px) {
        .filtros-grid {
            grid-template-columns: 1fr 1fr;
        }

        .filtros-acoes {
            justify-content: flex-start;
        }
    }

    @media (max-width: 768px) {
        .filtros-grid {
            grid-template-columns: 1fr;
            gap: 0.85rem;
        }

        .filtros-acoes {
            justify-content: stretch;
        }

        .btn-limpar-filtros {
            width: 100%;
            height: 42px;
            border-radius: 8px;
        }

        .card-title {
            font-size: 1.05rem;
            line-height: 1.4;
        }

        .paginacao-lista {
            flex-direction: column;
            gap: 0.75rem;
        }
    }
</style>

<div class="card no-print" style="margin-bottom: 2rem;">
    <div class="card-body">
        <h4 class="card-title"><i class="fas fa-filter"></i> Filtragem Avançada</h4>

        <form action="" method="GET" class="filtros-grid">
            <div class="form-group">
                <label class="form-label">Número do Processo</label>
                <input
                    type="text"
                    name="busca"
                    class="form-control"
                    placeholder="Número..."
                    value="<?php echo htmlspecialchars($_GET['busca'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Secretaria</label>
                <select name="secretaria" class="form-control">
                    <option value="">Todas</option>
                    <?php foreach ($secretarias_list as $sl): ?>
                        <option
                            value="<?php echo $sl['id']; ?>"
                            <?php echo (isset($_GET['secretaria']) && $_GET['secretaria'] == $sl['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sl['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Data Início</label>
                <input
                    type="date"
                    name="data_inicio"
                    class="form-control"
                    value="<?php echo htmlspecialchars($_GET['data_inicio'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Data Fim</label>
                <input
                    type="date"
                    name="data_fim"
                    class="form-control"
                    value="<?php echo htmlspecialchars($_GET['data_fim'] ?? ''); ?>">
            </div>

            <div class="form-group filtros-acoes">
                <a
                    href="oficios_lista_sefaz.php"
                    class="btn-limpar-filtros"
                    title="Limpar Filtros">
                    <i class="fas fa-eraser" style="margin: 0;"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h3 class="card-title"><i class="fas fa-list"></i> Solicitações em Tempo Real</h3>

        <?php display_flash(); ?>

        <div class="table-wrap">
            <div class="table-responsive">
                <table class="table-vcenter">
                    <thead>
                        <tr>
                            <th class="text-nowrap">Número</th>
                            <th class="text-nowrap">Secretaria</th>
                            <th class="text-nowrap">Data</th>
                            <th class="text-nowrap">Status</th>
                            <th class="text-nowrap">Cadastrado por</th>
                            <th class="text-nowrap">Vlr Orçamento</th>
                            <th class="text-nowrap" style="text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($oficios)): ?>
                            <?php foreach ($oficios as $o): ?>
                                <tr>
                                    <td class="text-nowrap" style="font-weight: 700; color: var(--primary);">
                                        <?php echo htmlspecialchars($o['numero']); ?>
                                    </td>

                                    <td class="text-nowrap">
                                        <?php echo htmlspecialchars($o['secretaria']); ?>
                                    </td>

                                    <td class="text-nowrap">
                                        <?php echo format_date($o['criado_em']); ?>
                                    </td>

                                    <td class="text-nowrap">
                                        <?php
                                        $b_class = 'badge-pending';

                                        if ($o['status'] == 'ENVIADO') {
                                            $b_class = 'badge-primary';
                                        }
                                        if ($o['status'] == 'APROVADO') {
                                            $b_class = 'badge-approved';
                                        }
                                        if ($o['status'] == 'REPROVADO') {
                                            $b_class = 'badge-rejected';
                                        }
                                        ?>
                                        <span class="badge <?php echo $b_class; ?>">
                                            <?php echo htmlspecialchars($o['status']); ?>
                                        </span>
                                    </td>

                                    <td class="text-nowrap">
                                        <?php echo htmlspecialchars($o['usuario']); ?>
                                    </td>

                                    <td class="text-nowrap">
                                        <?php echo $o['valor_orcamento'] ? format_money($o['valor_orcamento']) : '---'; ?>
                                    </td>

                                    <td class="text-nowrap" style="text-align: right;">
                                        <div class="acoes-nowrap">
                                            <a
                                                href="oficios_visualizar.php?id=<?php echo $o['id']; ?>"
                                                class="btn btn-outline btn-sm"
                                                title="Ver Detalhes">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <?php if ($o['status'] == 'PENDENTE_ITENS'): ?>
                                                <a
                                                    href="atribuir_itens.php?id=<?php echo $o['id']; ?>"
                                                    class="btn btn-primary btn-sm text-nowrap">
                                                    <i class="fas fa-plus-circle"></i> Atribuir Itens
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($o['status'] == 'ENVIADO'): ?>
                                                <a
                                                    href="atribuir_itens.php?id=<?php echo $o['id']; ?>"
                                                    class="btn btn-outline btn-sm"
                                                    title="Editar Itens">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 1.25rem;">
                                    Nenhuma solicitação encontrada.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_paginas > 1): ?>
            <div class="paginacao-lista">
                <a
                    href="?page=<?php echo max(1, $pagina_atual - 1); ?>&busca=<?php echo urlencode($_GET['busca'] ?? ''); ?>&secretaria=<?php echo urlencode($_GET['secretaria'] ?? ''); ?>&data_inicio=<?php echo urlencode($_GET['data_inicio'] ?? ''); ?>&data_fim=<?php echo urlencode($_GET['data_fim'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>"
                    class="btn btn-outline btn-sm <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                    Anterior
                </a>

                <span class="text-nowrap">
                    Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                </span>

                <a
                    href="?page=<?php echo min($total_paginas, $pagina_atual + 1); ?>&busca=<?php echo urlencode($_GET['busca'] ?? ''); ?>&secretaria=<?php echo urlencode($_GET['secretaria'] ?? ''); ?>&data_inicio=<?php echo urlencode($_GET['data_inicio'] ?? ''); ?>&data_fim=<?php echo urlencode($_GET['data_fim'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>"
                    class="btn btn-outline btn-sm <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                    Próxima
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>