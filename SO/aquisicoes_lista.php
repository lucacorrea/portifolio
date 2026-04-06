<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$page_title = "Lista de Aquisições";

// Configurações de Paginação
$itens_por_pagina = 6;
$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Contagem total para paginação
$stmt_count = $pdo->query("SELECT COUNT(*) FROM aquisicoes");
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $itens_por_pagina);

// Query principal com LIMIT
$stmt = $pdo->query("
    SELECT a.*, o.numero as oficio_num, s.nome as secretaria, f.nome as fornecedor
    FROM aquisicoes a
    JOIN oficios o ON a.oficio_id = o.id
    JOIN secretarias s ON o.secretaria_id = s.id
    JOIN fornecedores f ON a.fornecedor_id = f.id
    ORDER BY a.criado_em DESC
    LIMIT $itens_por_pagina OFFSET $offset
");
$aquisicoes = $stmt->fetchAll();

// Função auxiliar para manter parâmetros na URL da paginação
function get_pagination_url($page) {
    if (empty($_GET)) return "?page=$page";
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

include 'views/layout/header.php';
?>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1.25rem;">
                <i class="fas fa-file-invoice-dollar" style="margin-right: 10px; color: var(--primary);"></i> Aquisições Geradas
            </h3>
        </div>

        <?php display_flash(); ?>

        <div class="table-responsive">
            <table class="table-vcenter">
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
                    <?php foreach($aquisicoes as $aq): ?>
                    <tr>
                        <td><strong style="color: var(--text-dark);"><?php echo $aq['numero_aq']; ?></strong></td>
                        <td><span class="text-muted"><?php echo $aq['oficio_num']; ?></span></td>
                        <td><span style="font-weight: 600;"><?php echo $aq['secretaria']; ?></span></td>
                        <td><?php echo $aq['fornecedor']; ?></td>
                        <td style="text-align: right; font-weight: 700; color: var(--primary);"><?php echo format_money($aq['valor_total']); ?></td>
                        <td style="text-align: center;">
                            <span class="badge badge-<?php echo strtolower($aq['status'] == 'AGUARDANDO ENTREGA' ? 'pending' : 'finalized'); ?>" style="padding: 0.4rem 1rem;">
                                <?php echo $aq['status']; ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="aquisicoes_visualizar.php?id=<?php echo $aq['id']; ?>" class="btn btn-outline btn-sm" title="Visualizar Detalhes"><i class="fas fa-eye"></i></a>
                            <?php if($aq['status'] === 'AGUARDANDO ENTREGA'): ?>
                                <a href="aquisicao_editar.php?id=<?php echo $aq['id']; ?>" class="btn btn-outline btn-sm" style="color: #2fb344; border-color: #2fb344;" title="Lançar Valores (Orçamento)">
                                    <i class="fas fa-dollar-sign"></i>
                                </a>
                                <a href="aquisicao_finalizar.php?id=<?php echo $aq['id']; ?>" class="btn btn-outline btn-sm" style="color: var(--status-finalized); border-color: var(--status-finalized);" title="Marcar como Recebido" onclick="return confirm('Confirmar o recebimento desta aquisição?')">
                                    <i class="fas fa-check-circle"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($aquisicoes)): ?>
                        <tr><td colspan="7" style="text-align:center; padding: 3rem; color: var(--text-muted);">Nenhuma aquisição gerada até o momento.</td></tr>
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
