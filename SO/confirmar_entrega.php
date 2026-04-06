<?php
require_once 'config/database.php';
require_once 'config/functions.php';

if (!isset($_SESSION['secretaria_id'])) {
    header("Location: login.php");
    exit();
}

$sec_id = $_SESSION['secretaria_id'];
$page_title = "Confirmar Entrega de Produtos";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = strtoupper(trim($_POST['codigo_entrega']));
    
    // Buscar aquisição pelo código e que pertença a esta secretaria
    $stmt = $pdo->prepare("
        SELECT a.*, o.numero as oficio_num 
        FROM aquisicoes a 
        JOIN oficios o ON a.oficio_id = o.id 
        WHERE a.codigo_entrega = ? AND o.secretaria_id = ?
    ");
    $stmt->execute([$codigo, $sec_id]);
    $aq = $stmt->fetch();
    
    if (!$aq) {
        $error = "Código INVÁLIDO ou não pertence a esta secretaria!";
    } elseif ($aq['status'] === 'FINALIZADO') {
        $error = "Esta entrega já foi confirmada anteriormente em " . format_date($aq['data_finalizacao']) . "!";
    } else {
        // Confirmar entrega
        $stmt_upd = $pdo->prepare("UPDATE aquisicoes SET status = 'FINALIZADO', data_finalizacao = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt_upd->execute([$aq['id']]);
        
        log_action($pdo, "CONFIRMAR_ENTREGA", "Entrega AQ {$aq['numero_aq']} confirmada via código");
        flash_message('success', "Entrega da Aquisição {$aq['numero_aq']} (Ofício {$aq['oficio_num']}) CONFIRMADA com SUCESSO!");
        header("Location: acompanhamento.php");
        exit();
    }
}

include 'views/layout/header.php';
?>

<div class="card" style="max-width: 600px; margin: 2rem auto;">
    <div class="card-body" style="padding: 3rem;">
        <div style="text-align: center; margin-bottom: 2.5rem;">
            <div style="background: var(--primary-light); color: var(--primary); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 1.5rem;">
                <i class="fas fa-barcode"></i>
            </div>
            <h3 style="color: var(--text-dark); font-weight: 800; font-size: 1.5rem; margin-bottom: 0.5rem;">Validar Recebimento</h3>
            <p style="color: var(--text-muted); font-size: 0.9375rem; line-height: 1.5;">
                Digite o <strong>Código Único de Entrega</strong> fornecido na Ordem de Fornecimento para confirmar o recebimento dos produtos em sua secretaria.
            </p>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 2rem; border-radius: 8px;">
                <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label" style="text-align: center; display: block; margin-bottom: 1rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">Código de Entrega (ENT-XXXX-XXXXX)</label>
                <input type="text" name="codigo_entrega" class="form-control" placeholder="EX: ENT-0A1B-2C3D" required 
                       style="text-align: center; font-size: 1.75rem; letter-spacing: 4px; text-transform: uppercase; font-family: 'Inter', monospace; font-weight: 800; height: 4.5rem; border-width: 2px; border-color: var(--primary-light); color: var(--primary);">
            </div>
            
            <div style="margin-top: 3rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%; height: 4rem; font-size: 1.125rem; font-weight: 700; box-shadow: 0 4px 15px rgba(32, 107, 196, 0.2);">
                    <i class="fas fa-check-double" style="margin-right: 8px;"></i> Confirmar e Finalizar Entrega
                </button>
            </div>
        </form>
        
        <div style="margin-top: 2rem; text-align: center;">
            <a href="acompanhamento.php" class="btn btn-outline btn-sm" style="border: none; color: var(--text-muted);"><i class="fas fa-arrow-left"></i> Voltar para a lista</a>
        </div>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
