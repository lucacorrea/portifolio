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

<!-- VIA PREFEITURA -->
<div class="card printable-page" id="via-prefeitura">
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; border-bottom: 2px solid #000; padding-bottom: 1.25rem; margin-bottom: 2rem; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #000; text-transform: uppercase;">PREFEITURA MUNICIPAL</h1>
                <h2 style="font-size: 0.8rem; font-weight: 700; margin: 2px 0 0; color: #333; text-transform: uppercase;">Secretaria de Administração e Finanças</h2>
                <div style="font-size: 0.7rem; margin-top: 4px; color: #666; font-weight: 600;">[SUA CIDADE] - [UF] | CNPJ: 00.000.000/0001-00</div>
            </div>
            <div style="text-align: center;">
                <img src="assets/img/prefeitura.png" alt="Logo Prefeitura" style="max-height: 80px; max-width: 200px; object-fit: contain;">
            </div>
            <div style="text-align: right;">
                <div style="font-weight: 800; color: #999; font-size: 0.65rem; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.1em;">Uso Administrativo</div>
                <div style="border: 1.5px solid #000; padding: 0.4rem 1rem; display: inline-block; text-align: center;">
                    <div style="font-size: 0.6rem; font-weight: 800; color: #000; text-transform: uppercase;">Solicitação Nº</div>
                    <div style="font-size: 1.25rem; font-weight: 900; color: #000; line-height: 1.1;"><?php echo str_replace('OF-', '', $oficio['numero']); ?></div>
                </div>
                <div style="font-size: 0.7rem; color: #666; margin-top: 8px; font-weight: 600; text-transform: uppercase;">
                    DATA: <?php echo date('d/m/Y', strtotime($oficio['criado_em'])); ?> | <?php echo date('H:i', strtotime($oficio['criado_em'])); ?>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2.5rem;">
            <div style="background: #fcfdfe; border: 1px solid var(--border-color); padding: 1.25rem; border-radius: 8px;">
                <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 5px;">Secretaria Solicitante</label>
                <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-dark);"><?php echo $oficio['secretaria']; ?></div>
                <div style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 5px;">Responsável: <strong><?php echo $oficio['sec_responsavel']; ?></strong></div>
            </div>
            <div style="background: #fcfdfe; border: 1px solid var(--border-color); padding: 1.25rem; border-radius: 8px; text-align: center;">
                <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;">Situação Atual</label>
                <span class="badge badge-<?php echo strtolower($oficio['status'] == 'ENVIADO' ? 'pending' : ($oficio['status'] == 'APROVADO' ? 'approved' : 'rejected')); ?>" style="font-size: 0.85rem; padding: 0.5rem 1.25rem; display: block; width: 100%;">
                    <?php echo $oficio['status']; ?>
                </span>
            </div>
        </div>

        <h3 style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 1rem;">Itens da Solicitação</h3>
        <div class="table-responsive" style="margin-bottom: 2.5rem;">
            <table class="table-vcenter" style="border: 1px solid var(--border-color);">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="width: 60px;">#</th>
                        <th>Produto / Serviço</th>
                        <th style="text-align: right; width: 120px;">Qtd</th>
                        <th style="width: 100px;">Unidade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $idx => $item): ?>
                    <tr>
                        <td style="color: var(--text-muted); font-size: 0.75rem;"><?php echo str_pad($idx + 1, 2, '0', STR_PAD_LEFT); ?></td>
                        <td style="font-weight: 700; color: var(--text-dark);"><?php echo $item['produto']; ?></td>
                        <td style="text-align: right; font-weight: 700; color: var(--primary);"><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></td>
                        <td><span style="font-size: 0.75rem; font-weight: 700; background: var(--bg-body); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--border-color);"><?php echo $item['unidade']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="background: #fcfdfe; border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 10px;">Justificativa / Observações</label>
            <p style="font-size: 0.9375rem; margin: 0; color: var(--text-dark); line-height: 1.6; text-align: justify;"><?php echo nl2br($oficio['justificativa']); ?></p>
        </div>

        <?php if($oficio['arquivo_orcamento']): ?>
        <div style="margin-bottom: 5rem;">
            <a href="<?php echo $oficio['arquivo_orcamento']; ?>" target="_blank" class="btn btn-outline btn-sm" style="color: var(--primary); border-color: var(--primary);">
                <i class="fas fa-file-pdf"></i> Visualizar Orçamento Anexo
            </a>
        </div>
        <?php else: ?>
            <div style="margin-bottom: 5rem;"></div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; text-align: center; margin-top: auto;">
            <div>
                <div style="border-top: 1.5px solid var(--text-dark); padding-top: 1rem;">
                    <div style="font-weight: 800; color: var(--text-dark);"><?php echo $oficio['usuario']; ?></div>
                    <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Setor de Planejamento</div>
                </div>
            </div>
            <div>
                <div style="border-top: 1.5px solid var(--text-dark); padding-top: 1rem;">
                    <div style="font-weight: 800; color: var(--text-dark);">PROTOCOLO INTERNO</div>
                    <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Data e Carimbo de Recebimento</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
