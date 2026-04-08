<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$page_title = "Lista de Aquisições";

// Filtros
$where = "TRUE";

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where .= " AND a.status = " . $pdo->quote($_GET['status']);
}

if (isset($_GET['busca']) && trim($_GET['busca']) !== '') {
    $busca = trim($_GET['busca']);
    $buscaSql = '%' . $busca . '%';
    $where .= " AND (
        a.numero_aq LIKE " . $pdo->quote($buscaSql) . "
        OR o.numero LIKE " . $pdo->quote($buscaSql) . "
        OR s.nome LIKE " . $pdo->quote($buscaSql) . "
        OR f.nome LIKE " . $pdo->quote($buscaSql) . "
    )";
}

if (isset($_GET['secretaria_id']) && $_GET['secretaria_id'] != '') {
    $where .= " AND o.secretaria_id = " . (int)$_GET['secretaria_id'];
}

$secretarias_list = $pdo->query("SELECT id, nome FROM secretarias ORDER BY nome")->fetchAll();

// Configurações de Paginação
$itens_por_pagina = 6;
$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$pagina_atual = max(1, $pagina_atual);
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Contagem total para paginação
$stmt_count = $pdo->query("
    SELECT COUNT(*)
    FROM aquisicoes a
    JOIN oficios o ON a.oficio_id = o.id
    JOIN secretarias s ON o.secretaria_id = s.id
    JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE $where
");
$total_registros = (int)$stmt_count->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $itens_por_pagina));

if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
    $offset = ($pagina_atual - 1) * $itens_por_pagina;
}

// Query principal com LIMIT
$stmt = $pdo->query("
    SELECT a.*, o.numero as oficio_num, s.nome as secretaria, f.nome as fornecedor
    FROM aquisicoes a
    JOIN oficios o ON a.oficio_id = o.id
    JOIN secretarias s ON o.secretaria_id = s.id
    JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE $where
    ORDER BY a.criado_em DESC
    LIMIT $itens_por_pagina OFFSET $offset
");
$aquisicoes = $stmt->fetchAll();

// Função auxiliar para manter parâmetros na URL da paginação
function get_pagination_url($page)
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

include 'views/layout/header.php';
?>

<style>
    .filtros-grid {
        display: grid;
        grid-row-gap: 1rem;
        grid-column-gap: 1.5rem;
        grid-template-columns: 1fr 1fr 1fr 42px;
        align-items: end;
    }

    .lista-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .lista-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .lista-table {
        min-width: 1100px;
    }

    .lista-table,
    .lista-table th,
    .lista-table td,
    .lista-table span,
    .lista-table a,
    .lista-table .badge {
        white-space: nowrap !important;
    }

    .acoes-wrap {
        display: flex;
        gap: 8px;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: flex-end;
    }

    .paginacao-box {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: .75rem;
        margin-top: 2rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1.5rem;
        flex-wrap: wrap;
    }

    .paginacao-info {
        font-weight: 600;
        color: var(--text-dark);
    }

    .btn-limpar {
        width: 100%;
    }

    @media (max-width: 768px) {
        .filtros-grid {
            grid-template-columns: 1fr;
        }

        .lista-header {
            flex-direction: column;
            align-items: stretch;
        }

        .lista-header .btn {
            width: 100%;
            justify-content: center;
        }

        .filtros-acoes {
            display: grid;
            grid-template-columns: 1fr;
            gap: .75rem;
        }

        .filtros-acoes .btn {
            width: 100%;
        }

        .paginacao-box {
            flex-direction: column;
        }

        .paginacao-box .btn {
            width: 100%;
            max-width: 260px;
            justify-content: center;
        }
    }

    /* Dropdown Actions Styles */
    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        z-index: 9999;
        display: none !important;
        min-width: 200px;
        padding: 0.5rem 0;
        margin-top: 0.25rem;
        background-color: #fff;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .dropdown-menu.show {
        display: block !important;
        animation: dropdownFadeIn 0.2s ease-out;
    }

    /* Versão Dropup */
    .dropdown-menu.dropup {
        top: auto !important;
        bottom: 100% !important;
        margin-top: 0 !important;
        margin-bottom: 0.25rem !important;
        animation: dropdownFadeUp 0.2s ease-out !important;
    }

    @keyframes dropdownFadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes dropdownFadeUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 0.75rem 1rem;
        color: var(--text-dark) !important;
        text-decoration: none !important;
        font-weight: 500;
        font-size: 0.825rem;
        transition: all 0.2s;
        border: 0;
        background: transparent;
        cursor: pointer;
        padding-right: 2rem;
    }

    .dropdown-item:hover {
        background-color: var(--primary-light);
        color: var(--primary) !important;
    }

    .dropdown-item i {
        width: 16px;
        text-align: center;
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    .dropdown-item:hover i {
        color: var(--primary);
    }

    .btn-three-dots {
        background: #fff;
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        padding: 0;
        cursor: pointer;
    }

    .btn-three-dots:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--primary-light);
    }

    /* Fix table clipping */
    .lista-table-wrap {
        overflow-x: auto !important;
        padding-bottom: 0 !important;
        margin-bottom: 0 !important;
        -webkit-overflow-scrolling: touch;
    }

    .lista-table td {
        position: relative;
    }
</style>

<div class="card no-print">
    <div class="card-body">
        <h3 class="card-title" style="margin-bottom: 1rem; font-weight: 700; font-size: 1rem;">
            <i class="fas fa-filter" style="margin-right: 5px; color: var(--primary);"></i> Filtros de Busca
        </h3>

        <form action="" method="GET" class="filtros-grid">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Termo de busca</label>
                <input
                    type="text"
                    name="busca"
                    class="form-control"
                    placeholder="Nº aquisição, ofício, secretaria ou fornecedor..."
                    value="<?php echo htmlspecialchars($_GET['busca'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">Todos Status</option>
                    <option value="AGUARDANDO ENTREGA" <?php echo ($_GET['status'] ?? '') === 'AGUARDANDO ENTREGA' ? 'selected' : ''; ?>>AGUARDANDO ENTREGA</option>
                    <option value="FINALIZADO" <?php echo ($_GET['status'] ?? '') === 'FINALIZADO' ? 'selected' : ''; ?>>FINALIZADO</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Secretaria</label>
                <select name="secretaria_id" class="form-control">
                    <option value="">Todas as Secretarias</option>
                    <?php foreach ($secretarias_list as $sec): ?>
                        <option value="<?php echo $sec['id']; ?>" <?php echo ($_GET['secretaria_id'] ?? '') == $sec['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sec['nome'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group filtros-acoes" style="margin-bottom: 0;">
                <a href="aquisicoes_lista.php" class="btn" style="width: 42px; height: 40px; padding: 0; display: flex; justify-content: center; align-items: center; border: 1px solid #cbd5e1; border-radius: 6px; color: #64748b; background: white;" title="Limpar Filtros">
                    <i class="fas fa-eraser" style="margin: 0;"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="lista-header">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1.25rem; margin: 0;">
                <i class="fas fa-file-invoice-dollar" style="margin-right: 10px; color: var(--primary);"></i> Aquisições Geradas
            </h3>
        </div>

        <?php display_flash(); ?>

        <div class="table-responsive lista-table-wrap">
            <table class="table-vcenter text-nowrap lista-table">
                <thead>
                    <tr>
                        <th>Nº Aquisição</th>
                        <th>Ref. Ofício</th>
                        <th>Secretaria</th>
                        <th>Fornecedor</th>
                        <th style="text-align: right;">Valor Total</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aquisicoes as $aq): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-dark);">
                                    <?php echo htmlspecialchars($aq['numero_aq'], ENT_QUOTES, 'UTF-8'); ?>
                                </strong>
                            </td>
                            <td>
                                <span class="text-muted">
                                    <?php echo htmlspecialchars($aq['oficio_num'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 600;">
                                    <?php echo htmlspecialchars($aq['secretaria'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($aq['fornecedor'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--primary);">
                                <?php echo format_money($aq['valor_total']); ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?php echo strtolower($aq['status'] === 'AGUARDANDO ENTREGA' ? 'pending' : 'finalized'); ?>" style="padding: 0.4rem 1rem;">
                                    <?php echo htmlspecialchars($aq['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="acoes-wrap">
                                    <div class="dropdown">
                                        <button class="btn-three-dots" data-dropdown-toggle title="Ações">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="aquisicoes_visualizar.php?id=<?php echo (int)$aq['id']; ?>">
                                                <i class="fas fa-eye"></i> Visualizar
                                            </a>
                                            
                                            <?php 
                                                $nivel_user = strtoupper($_SESSION['nivel'] ?? '');
                                                if ($aq['status'] === 'AGUARDANDO ENTREGA' && ($nivel_user === 'ADMIN' || $nivel_user === 'SUPORTE')): 
                                            ?>
                                                <a class="dropdown-item" href="aquisicao_finalizar.php?id=<?php echo (int)$aq['id']; ?>" style="color: var(--status-finalized) !important;" onclick="return confirm('Confirmar o recebimento desta aquisição?')">
                                                    <i class="fas fa-check-circle"></i> Marcar como Recebido
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($aquisicoes)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding: 3rem; color: var(--text-muted);">
                                Nenhuma aquisição gerada até o momento.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
            <div class="paginacao-box">
                <a href="<?php echo $pagina_atual > 1 ? get_pagination_url($pagina_atual - 1) : '#'; ?>"
                    class="btn btn-outline btn-sm <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                    <i class="fas fa-angle-left"></i> Anterior
                </a>

                <span class="paginacao-info">
                    Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                </span>

                <a href="<?php echo $pagina_atual < $total_paginas ? get_pagination_url($pagina_atual + 1) : '#'; ?>"
                    class="btn btn-outline btn-sm <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                    Próxima <i class="fas fa-angle-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>