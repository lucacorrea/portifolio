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

$page_title = "Solicitação: " . $oficio['numero'];
include 'views/layout/header.php';
?>

<div class="no-print" style="margin-bottom: 2rem; display: flex; gap: 1rem; align-items: center;">
    <a href="oficios_lista.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Voltar para Lista</a>
    <div style="flex-grow: 1;"></div>
    
    <?php if ($oficio['status'] == 'ENVIADO' && ($_SESSION['nivel'] == 'ADMIN' || $_SESSION['nivel'] == 'SUPORTE')): ?>
        <a href="analisar_oficio.php?id=<?php echo $oficio['id']; ?>" class="btn btn-outline btn-sm" style="color: var(--status-pending); border-color: var(--status-pending);"><i class="fas fa-gavel"></i> Analisar Solicitação</a>
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
                <span class="badge badge-<?php echo strtolower($oficio['status'] == 'ENVIADO' ? 'pending' : ($oficio['status'] == 'APROVADO' ? 'approved' : 'rejected')); ?>" style="font-size: 0.9375rem; padding: 0.625rem 1.5rem; display: inline-block;">
                    <?php echo $oficio['status']; ?>
                </span>
            </div>
        </div>

        <div class="row" style="margin-bottom: 2.5rem;">
            <div class="col-md-6" style="margin-bottom: 1.5rem;">
                <div style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 1.25rem; border-radius: 8px; height: 100%;">
                    <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;"><i class="fas fa-building"></i> Centro de Custo / Secretaria</label>
                    <div style="font-weight: 700; font-size: 1.125rem; color: var(--text-dark);"><?php echo $oficio['secretaria']; ?></div>
                    <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 8px;">Responsável Direto: <strong><?php echo $oficio['sec_responsavel']; ?></strong></div>
                </div>
            </div>
            <div class="col-md-6" style="margin-bottom: 1.5rem;">
                <div style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 1.25rem; border-radius: 8px; height: 100%;">
                    <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;"><i class="fas fa-user-check"></i> Criado Por / Setor</label>
                    <div style="font-weight: 700; font-size: 1.125rem; color: var(--text-dark);"><?php echo $oficio['usuario']; ?></div>
                    <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 8px;">Departamento: <strong>Setor Adjunto Administrativo</strong></div>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 2.5rem;">
            <h3 style="font-size: 0.875rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-list-ul" style="color: var(--primary);"></i> Itens Solicitados
            </h3>
            <div class="table-responsive">
                <table class="table-vcenter" style="background: #fff; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="width: 60px; padding: 12px;">#</th>
                            <th style="padding: 12px;">Produto / Serviço</th>
                            <th style="text-align: right; width: 120px; padding: 12px;">Quantidade</th>
                            <th style="width: 100px; padding: 12px;">Unidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $idx => $item): ?>
                        <tr>
                            <td style="color: var(--text-muted); font-size: 0.75rem; padding: 12px;"><?php echo str_pad($idx + 1, 2, '0', STR_PAD_LEFT); ?></td>
                            <td style="font-weight: 600; color: var(--text-dark); padding: 12px;"><?php echo $item['produto']; ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--primary); padding: 12px;"><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></td>
                            <td style="padding: 12px;"><span style="font-size: 0.75rem; font-weight: 700; background: var(--bg-body); padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border-color);"><?php echo $item['unidade']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="background: #f8f9fa; border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
            <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 12px;">Justificativa e Finalidade do Pedido</label>
            <p style="font-size: 0.9375rem; margin: 0; color: var(--text-dark); line-height: 1.7; text-align: justify;"><?php echo nl2br($oficio['justificativa']); ?></p>
        </div>

        <?php if($oficio['arquivo_orcamento']): ?>
        <div style="display: flex; align-items: center; gap: 15px; padding: 1rem; background: var(--bg-body); border: 1px dashed var(--primary); border-radius: 8px;">
            <div style="font-size: 1.5rem; color: var(--primary);"><i class="fas fa-file-pdf"></i></div>
            <div style="flex-grow: 1;">
                <div style="font-weight: 700; font-size: 0.875rem; color: var(--text-dark);">Orçamento Anexo</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Documento carregado pelo solicitante</div>
            </div>
            <a href="<?php echo $oficio['arquivo_orcamento']; ?>" target="_blank" class="btn btn-outline btn-sm" style="color: var(--primary); border-color: var(--primary);"><i class="fas fa-eye"></i> Visualizar Arquivo</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
