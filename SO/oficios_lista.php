<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$page_title = "Lista de Solicitações";

// Filtros simples
$where = "TRUE";
if (isset($_GET['status']) && $_GET['status'] != '') {
    $where .= " AND o.status = '" . $_GET['status'] . "'";
}
if (isset($_GET['busca']) && $_GET['busca'] != '') {
    $where .= " AND (o.numero LIKE '%" . $_GET['busca'] . "%' OR s.nome LIKE '%" . $_GET['busca'] . "%')";
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
$stmt_count = $pdo->query("SELECT COUNT(*) FROM oficios o JOIN secretarias s ON o.secretaria_id = s.id WHERE $where");
$total_registros = (int)$stmt_count->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $itens_por_pagina));

if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
    $offset = ($pagina_atual - 1) * $itens_por_pagina;
}

// Query principal com LIMIT
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

// Função auxiliar para manter parâmetros na URL da paginação
function get_pagination_url($page) {
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
        margin-bottom: 1.5rem;
    }

    .lista-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .lista-table,
    .lista-table th,
    .lista-table td,
    .lista-table span,
    .lista-table a,
    .lista-table .badge {
        white-space: nowrap !important;
    }

    .lista-table {
        min-width: 980px;
    }

    .acoes-wrap {
        display: flex;
        gap: 8px;
        flex-wrap: nowrap;
        align-items: center;
    }

    .paginacao-box {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.75rem;
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
        .lista-header {
            flex-direction: column;
            align-items: stretch;
        }

        .lista-header .btn {
            width: 100%;
            justify-content: center;
        }

        .filtros-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .filtros-acoes {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
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
</style>
<link rel="shortcut icon" href="./assets/img/logo-pmc.png" type="image/x-icon">nk
<div class="card no-print">
    <div class="card-body">
        <h3 class="card-title" style="margin-bottom: 1rem; font-weight: 700; font-size: 1rem;">
            <i class="fas fa-filter" style="margin-right: 5px; color: var(--primary);"></i> Filtros de Busca
        </h3>

        <form action="" method="GET" class="filtros-grid">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Termo de busca</label>
                <input type="text" name="busca" class="form-control" placeholder="Número ou secretaria..." value="<?php echo htmlspecialchars($_GET['busca'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">Todos Status</option>
                    <option value="ENVIADO" <?php echo ($_GET['status'] ?? '') == 'ENVIADO' ? 'selected' : ''; ?>>ENVIADO</option>
                    <option value="APROVADO" <?php echo ($_GET['status'] ?? '') == 'APROVADO' ? 'selected' : ''; ?>>APROVADO</option>
                    <option value="REPROVADO" <?php echo ($_GET['status'] ?? '') == 'REPROVADO' ? 'selected' : ''; ?>>REPROVADO</option>
                    <option value="ARQUIVADO" <?php echo ($_GET['status'] ?? '') == 'ARQUIVADO' ? 'selected' : ''; ?>>ARQUIVADO</option>
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
                <a href="oficios_lista.php" class="btn" style="width: 42px; height: 40px; padding: 0; display: flex; justify-content: center; align-items: center; border: 1px solid #cbd5e1; border-radius: 6px; color: #64748b; background: white;" title="Limpar Filtros">
                    <i class="fas fa-eraser" style="margin: 0;"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="lista-header">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1rem; margin: 0;">
                <i class="fas fa-list-ul" style="margin-right: 10px; color: var(--primary);"></i> Solicitações Recebidas
            </h3>
            <?php if (strtoupper($_SESSION['nivel'] ?? '') !== 'SECRETARIO'): ?>
                <a href="oficios_novo.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Novo Cadastro
                </a>
            <?php endif; ?>
        </div>

        <?php display_flash(); ?>

        <div class="table-responsive lista-table-wrap">
            <table class="table-vcenter text-nowrap lista-table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Secretaria</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th>Cadastrado por</th>
                        <th class="w-1">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($oficios as $o): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--primary);">
                                <?php echo htmlspecialchars($o['numero'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td>
                                <span class="text-muted"><?php echo htmlspecialchars($o['secretaria'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td><?php echo format_date($o['criado_em']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($o['status'] == 'ENVIADO' ? 'pending' : ($o['status'] == 'APROVADO' ? 'approved' : 'rejected')); ?>">
                                    <?php echo htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-muted"><?php echo htmlspecialchars($o['usuario'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td>
                                <div class="acoes-wrap">
                                    <a href="oficios_visualizar.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-outline btn-sm" title="Visualizar/Imprimir">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <?php 
                                        $nivel_user = strtoupper($_SESSION['nivel'] ?? '');
                                        if ($o['status'] == 'ENVIADO' && ($nivel_user == 'ADMIN' || $nivel_user == 'SUPORTE')): 
                                    ?>
                                        <a href="analisar_oficio.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-outline btn-sm" title="Analisar">
                                            <i class="fas fa-gavel"></i> Analisar
                                        </a>
                                    <?php endif; ?>
 
                                    <a href="oficios_anexar.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-outline btn-sm" title="Anexar Ofício de Solicitação" style="color: var(--secondary); border-color: var(--secondary);">
                                        <i class="fas fa-paperclip"></i>
                                    </a>

                                    <?php if ($o['status'] == 'APROVADO' && ($nivel_user == 'ADMIN' || $nivel_user == 'SUPORTE')): ?>
                                        <a href="gerar_aquisicao.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-outline btn-sm" title="Gerar Aquisição">
                                            <i class="fas fa-shopping-cart"></i> Gerar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($oficios)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 2rem; color: var(--text-muted);">
                                Nenhuma solicitação encontrada.
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