<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
sefaz_check();

$page_title = "Lista de Solicitações - SEFAZ";

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
$offset = ($pagina_atual - 1) * $itens_por_pagina;

$stmt_count = $pdo->query("SELECT COUNT(*) FROM oficios o JOIN secretarias s ON o.secretaria_id = s.id WHERE $where");
$total_registros = (int)$stmt_count->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $itens_por_pagina));

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

<div class="card no-print" style="margin-bottom: 2rem;">
    <div class="card-body">
        <h4 class="card-title"><i class="fas fa-filter"></i> Filtragem Avançada</h4>
        <form action="" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div class="form-group">
                <label class="form-label">Número do Processo</label>
                <input type="text" name="busca" class="form-control" placeholder="Número..." value="<?php echo htmlspecialchars($_GET['busca'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Secretaria</label>
                <select name="secretaria" class="form-control">
                    <option value="">Todas</option>
                    <?php foreach ($secretarias_list as $sl): ?>
                        <option value="<?php echo $sl['id']; ?>" <?php echo (isset($_GET['secretaria']) && $_GET['secretaria'] == $sl['id']) ? 'selected' : ''; ?>>
                            <?php echo $sl['nome']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Data Início</label>
                <input type="date" name="data_inicio" class="form-control" value="<?php echo htmlspecialchars($_GET['data_inicio'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="<?php echo htmlspecialchars($_GET['data_fim'] ?? ''); ?>">
            </div>
            <div class="form-group" style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fas fa-search"></i> Filtrar</button>
                <a href="oficios_lista_sefaz.php" class="btn btn-outline"><i class="fas fa-eraser"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h3 class="card-title"><i class="fas fa-list"></i> Solicitações em Tempo Real</h3>
        
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
                        <th>Vlr Orçamento</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($oficios as $o): ?>
                        <tr>
                            <td style="font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($o['numero']); ?></td>
                            <td><?php echo htmlspecialchars($o['secretaria']); ?></td>
                            <td><?php echo format_date($o['criado_em']); ?></td>
                            <td>
                                <?php 
                                    $b_class = 'badge-pending';
                                    if($o['status'] == 'ENVIADO') $b_class = 'badge-primary';
                                    if($o['status'] == 'APROVADO') $b_class = 'badge-approved';
                                    if($o['status'] == 'REPROVADO') $b_class = 'badge-rejected';
                                ?>
                                <span class="badge <?php echo $b_class; ?>"><?php echo $o['status']; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($o['usuario']); ?></td>
                            <td><?php echo $o['valor_orcamento'] ? format_money($o['valor_orcamento']) : '---'; ?></td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 5px; justify-content: flex-end;">
                                    <a href="oficios_visualizar.php?id=<?php echo $o['id']; ?>" class="btn btn-outline btn-sm" title="Ver Detalhes"><i class="fas fa-eye"></i></a>
                                    
                                    <?php if ($o['status'] == 'PENDENTE_ITENS'): ?>
                                        <a href="atribuir_itens.php?id=<?php echo $o['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus-circle"></i> Atribuir Itens
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($o['status'] == 'ENVIADO'): ?>
                                        <a href="atribuir_itens.php?id=<?php echo $o['id']; ?>" class="btn btn-outline btn-sm" title="Editar Itens">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
            <div style="display: flex; justify-content: center; margin-top: 1.5rem; gap: 1rem;">
                <a href="?page=<?php echo max(1, $pagina_atual - 1); ?>" class="btn btn-outline btn-sm <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">Anterior</a>
                <span>Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?></span>
                <a href="?page=<?php echo min($total_paginas, $pagina_atual + 1); ?>" class="btn btn-outline btn-sm <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">Próxima</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
