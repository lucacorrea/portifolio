<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("
    SELECT o.*, s.nome as secretaria, s.responsavel as sec_responsavel, u.nome as usuario 
    FROM oficios o 
    JOIN secretarias s ON o.secretaria_id = s.id 
    JOIN usuarios u ON o.usuario_id = u.id 
    WHERE o.id = ?
");
$stmt->execute([$id]);
$oficio = $stmt->fetch();

if (!$oficio) {
    die("Solicitação não encontrada.");
}

$stmt_items = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ?");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

// Buscar todos os anexos vinculados
$stmt_anexos = $pdo->prepare("SELECT * FROM oficio_anexos WHERE oficio_id = ? ORDER BY criado_em ASC");
$stmt_anexos->execute([$id]);
$anexos = $stmt_anexos->fetchAll();

$anexos_orcamento = array_filter($anexos, function ($a) {
    return $a['tipo'] === 'ORCAMENTO';
});
$anexos_oficio    = array_filter($anexos, function ($a) {
    return $a['tipo'] === 'OFICIO';
});

$page_title = "Solicitação: " . $oficio['numero'];
include 'views/layout/header.php';
?>

<div class="no-print" style="margin-bottom: 2rem; display: flex; gap: 1rem; align-items: center;">
    <?php
    $nivel = strtoupper($_SESSION['nivel'] ?? '');
    $back_url = ($nivel === 'SEMFAZ') ? 'oficios_lista_sefaz.php' : 'oficios_lista.php';
    ?>
    <a href="<?php echo $back_url; ?>" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Voltar para Lista</a>
    <div style="flex-grow: 1;"></div>

    <?php if ($oficio['status'] == 'ENVIADO' && ($nivel == 'ADMIN' || $nivel == 'SUPORTE')): ?>
        <a href="analisar_oficio.php?id=<?php echo $oficio['id']; ?>" class="btn btn-outline btn-sm" style="color: var(--status-pending); border-color: var(--status-pending);"><i class="fas fa-gavel"></i> Analisar Solicitação</a>
    <?php endif; ?>

    <?php if ($oficio['status'] == 'PENDENTE_ITENS' && ($nivel == 'SEFAZ' || $nivel == 'ADMIN' || $nivel == 'SUPORTE')): ?>
        <a href="atribuir_itens.php?id=<?php echo $oficio['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle"></i> Atribuir Itens</a>
    <?php endif; ?>
</div>

<?php display_flash(); ?>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 2.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin: 0;">Solicitação Interna</h1>
                <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 5px;">
                    Referência: <strong style="color: var(--primary);"><?php echo $oficio['numero']; ?></strong> |
                    Data de Registro: <?php echo date('d/m/Y H:i', strtotime($oficio['criado_em'])); ?>
                </div>
            </div>
            <div style="text-align: right;">
                <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;">Situação do Pedido</label>
                <?php
                $badge_class = 'badge-pending';
                if ($oficio['status'] == 'ENVIADO') $badge_class = 'badge-primary';
                if ($oficio['status'] == 'APROVADO') $badge_class = 'badge-approved';
                if ($oficio['status'] == 'REPROVADO') $badge_class = 'badge-rejected';
                ?>
                <span class="badge <?php echo $badge_class; ?>" style="font-size: 0.9375rem; padding: 0.625rem 1.5rem; display: inline-block;">
                    <?php echo $oficio['status']; ?>
                </span>
            </div>
        </div>

        <div class="row" style="margin-bottom: 2.5rem;">
            <div class="col-md-4" style="margin-bottom: 1.5rem;">
                <div style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 1.25rem; border-radius: 8px; height: 100%;">
                    <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;">
                        <i class="fas fa-building"></i> Secretaria Solicitante
                    </label>

                    <div style="font-weight: 700; font-size: 1.125rem; color: var(--text-dark);">
                        <?php echo htmlspecialchars($oficio['secretaria']); ?>
                    </div>

                    <?php if (!empty($oficio['local'])): ?>
                        <div style="font-size: 0.875rem; color: var(--text-dark); margin-top: 8px;">
                            <span style="font-weight: 800; text-transform: uppercase; color: var(--text-muted);">Local:</span>
                            <strong><?php echo htmlspecialchars($oficio['local']); ?></strong>
                        </div>
                    <?php endif; ?>

                    <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 8px;">
                        Responsável: <strong><?php echo htmlspecialchars($oficio['sec_responsavel']); ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4" style="margin-bottom: 1.5rem;">
                <div style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 1.25rem; border-radius: 8px; height: 100%;">
                    <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;"><i class="fas fa-user-check"></i> Cadastrado Por</label>
                    <div style="font-weight: 700; font-size: 1.125rem; color: var(--text-dark);"><?php echo $oficio['usuario']; ?></div>
                </div>
            </div>
            <div class="col-md-4" style="margin-bottom: 1.5rem;">
                <div style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 1.25rem; border-radius: 8px; height: 100%;">
                    <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;"><i class="fas fa-money-bill-wave"></i> Orçamento Previsto</label>
                    <div style="font-weight: 700; font-size: 1.125rem; color: var(--primary);">
                        <?php echo $oficio['valor_orcamento'] ? format_money($oficio['valor_orcamento']) : '---'; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($items)): ?>
            <div style="margin-bottom: 2.5rem;">
                <h3 style="font-size: 0.875rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-list-ul" style="color: var(--primary);"></i> Itens Detalhados
                </h3>
                <div class="table-responsive">
                    <table class="table-vcenter" style="background: #fff; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 12px;">Produto / Serviço</th>
                                <th style="text-align: right; width: 100px; padding: 12px;">Qtd</th>
                                <th style="width: 80px; padding: 12px;">Unid</th>
                                <th style="text-align: right; width: 150px; padding: 12px;">Valor Unit.</th>
                                <th style="text-align: right; width: 150px; padding: 12px;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalGeral = 0;
                            foreach ($items as $item):
                                $sub = $item['quantidade'] * $item['valor_unitario'];
                                $totalGeral += $sub;
                            ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--text-dark); padding: 12px;"><?php echo $item['produto']; ?></td>
                                    <td style="text-align: right; padding: 12px;"><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></td>
                                    <td style="padding: 12px;"><span class="badge badge-outline" style="font-size: 0.7rem;"><?php echo $item['unidade']; ?></span></td>
                                    <td style="text-align: right; padding: 12px;"><?php echo format_money($item['valor_unitario']); ?></td>
                                    <td style="text-align: right; font-weight: 700; color: var(--primary); padding: 12px;"><?php echo format_money($sub); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="background: #f8f9fa; font-weight: 800;">
                            <tr>
                                <td colspan="4" style="text-align: right; padding: 12px;">TOTAL GERAL:</td>
                                <td style="text-align: right; color: var(--secondary); padding: 12px; font-size: 1.1rem;"><?php echo format_money($totalGeral); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Aguardando atribuição de itens pela SEMFAZ.</div>
        <?php endif; ?>

        <div style="background: #f8f9fa; border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
            <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 12px;">Justificativa e Finalidade</label>
            <p style="font-size: 0.9375rem; margin: 0; color: var(--text-dark); line-height: 1.7;"><?php echo nl2br($oficio['justificativa']); ?></p>
        </div>

        <?php if (!empty($anexos_orcamento)): ?>
            <div style="margin-bottom: 1.5rem;">
                <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 12px;">Orçamentos / Cotações Anexos</label>
                <?php foreach ($anexos_orcamento as $anexo): ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 0.75rem; background: var(--bg-body); border: 1px dashed var(--primary); border-radius: 8px; margin-bottom: 0.5rem;">
                        <div style="font-size: 1.2rem; color: var(--primary);"><i class="fas fa-file-invoice"></i></div>
                        <div style="flex-grow: 1;">
                            <div style="font-weight: 700; font-size: 0.875rem; color: var(--text-dark);"><?php echo htmlspecialchars($anexo['nome_original'] ?: 'Orçamento'); ?></div>
                        </div>
                        <a href="<?php echo $anexo['caminho']; ?>" target="_blank" class="btn btn-outline btn-sm" style="color: var(--primary); border-color: var(--primary);"><i class="fas fa-eye"></i> Ver</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($anexos_oficio)): ?>
            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 12px;">Ofícios de Solicitação Anexos</label>
                <?php foreach ($anexos_oficio as $anexo): ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 0.75rem; background: var(--bg-body); border: 1px dashed var(--secondary); border-radius: 8px; margin-bottom: 0.5rem;">
                        <div style="font-size: 1.2rem; color: var(--secondary);"><i class="fas fa-file-alt"></i></div>
                        <div style="flex-grow: 1;">
                            <div style="font-weight: 700; font-size: 0.875rem; color: var(--text-dark);"><?php echo htmlspecialchars($anexo['nome_original'] ?: 'Ofício de Solicitação'); ?></div>
                        </div>
                        <a href="<?php echo $anexo['caminho']; ?>" target="_blank" class="btn btn-outline btn-sm" style="color: var(--secondary); border-color: var(--secondary);"><i class="fas fa-eye"></i> Ver</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>