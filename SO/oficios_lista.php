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

// Configurações de Paginação
$itens_por_pagina = 6;
$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Contagem total para paginação
$stmt_count = $pdo->query("SELECT COUNT(*) FROM oficios o JOIN secretarias s ON o.secretaria_id = s.id WHERE $where");
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $itens_por_pagina);

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

<div class="card no-print">
    <div class="card-body">
        <h3 class="card-title" style="margin-bottom: 1rem; font-weight: 700; font-size: 1rem;">
            <i class="fas fa-filter" style="margin-right: 5px; color: var(--primary);"></i> Filtros de Busca
        </h3>
        <form action="" method="GET" style="display: grid; grid-row-gap: 1rem; grid-column-gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Termo de busca</label>
                <input type="text" name="busca" class="form-control" placeholder="Número ou secretaria..." value="<?php echo $_GET['busca'] ?? ''; ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">Todos Status</option>
                    <option value="ENVIADO" <?php echo($_GET['status'] ?? '') == 'ENVIADO' ? 'selected' : ''; ?>>ENVIADO</option>
                    <option value="APROVADO" <?php echo($_GET['status'] ?? '') == 'APROVADO' ? 'selected' : ''; ?>>APROVADO</option>
                    <option value="REPROVADO" <?php echo($_GET['status'] ?? '') == 'REPROVADO' ? 'selected' : ''; ?>>REPROVADO</option>
                    <option value="ARQUIVADO" <?php echo($_GET['status'] ?? '') == 'ARQUIVADO' ? 'selected' : ''; ?>>ARQUIVADO</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-search"></i> Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1rem;">
                <i class="fas fa-list-ul" style="margin-right: 10px; color: var(--primary);"></i> Solicitações Recebidas
            </h3>
            <a href="oficios_novo.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Novo Cadastro</a>
        </div>

        <?php display_flash(); ?>

        <div class="table-responsive">
            <table class="table-vcenter">
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
                        <td style="font-weight: 600; color: var(--primary);"><?php echo $o['numero']; ?></td>
                        <td><span class="text-muted"><?php echo $o['secretaria']; ?></span></td>
                        <td><?php echo format_date($o['criado_em']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($o['status'] == 'ENVIADO' ? 'pending' : ($o['status'] == 'APROVADO' ? 'approved' : 'rejected')); ?>">
                                <?php echo $o['status']; ?>
                            </span>
                        </td>
                        <td><span class="text-muted"><?php echo $o['usuario']; ?></span></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <a href="oficios_visualizar.php?id=<?php echo $o['id']; ?>" class="btn btn-outline btn-sm" title="Visualizar/Imprimir"><i class="fas fa-eye"></i></a>
                                
                                <?php if ($o['status'] == 'ENVIADO' && ($_SESSION['nivel'] == 'ADMIN' || $_SESSION['nivel'] == 'SUPORTE')): ?>
                                    <a href="analisar_oficio.php?id=<?php echo $o['id']; ?>" class="btn btn-outline btn-sm" title="Analisar"><i class="fas fa-gavel"></i> Analisar</a>
                                <?php endif; ?>

                                <?php if ($o['status'] == 'APROVADO' && ($_SESSION['nivel'] == 'ADMIN' || $_SESSION['nivel'] == 'SUPORTE')): ?>
                                    <a href="gerar_aquisicao.php?id=<?php echo $o['id']; ?>" class="btn btn-outline btn-sm" title="Gerar Aquisição"><i class="fas fa-shopping-cart"></i> Gerar</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                        <?php if (empty($oficios)): ?>
                            <tr><td colspan="6" style="text-align:center; padding: 2rem; color: var(--text-muted);">Nenhuma solicitação encontrada.</td></tr>
                        <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                <a href="<?php echo get_pagination_url(1); ?>" class="btn btn-outline btn-sm <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>" title="Primeira Página">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="<?php echo get_pagination_url($pagina_atual - 1); ?>" class="btn btn-outline btn-sm <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                    <i class="fas fa-angle-left"></i> Anterior
                </a>

                <?php
                $start = max(1, $pagina_atual - 2);
                $end = min($total_paginas, $pagina_atual + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="<?php echo get_pagination_url($i); ?>" class="btn <?php echo $i === $pagina_atual ? 'btn-primary' : 'btn-outline'; ?> btn-sm" style="min-width: 35px;">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <a href="<?php echo get_pagination_url($pagina_atual + 1); ?>" class="btn btn-outline btn-sm <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                    Próxima <i class="fas fa-angle-right"></i>
                </a>
                <a href="<?php echo get_pagination_url($total_paginas); ?>" class="btn btn-outline btn-sm <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>" title="Última Página">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
