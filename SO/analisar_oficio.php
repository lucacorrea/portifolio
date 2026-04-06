<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
admin_check();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("
    SELECT o.*, s.nome as secretaria, s.responsavel as sec_responsavel 
    FROM oficios o 
    JOIN secretarias s ON o.secretaria_id = s.id 
    WHERE o.id = ?
");
$stmt->execute([$id]);
$oficio = $stmt->fetch();

if (!$oficio) {
    die("Ofício não encontrado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $justificativa_admin = $_POST['justificativa_admin'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE oficios SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    
    log_action($pdo, "ANALISAR_OFICIO", "Ofício {$oficio['numero']} alterado para $status");
    
    if ($status === 'APROVADO') {
        flash_message('success', "Ofício {$oficio['numero']} APROVADO! Agora você pode gerar a aquisição.");
        header("Location: gerar_aquisicao.php?id=$id");
    } else {
        flash_message('danger', "Ofício {$oficio['numero']} foi REPROVADO e ARQUIVADO.");
        header("Location: oficios_lista.php");
    }
    exit();
}

$page_title = "Analisar Ofício: " . $oficio['numero'];
include 'views/layout/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Análise de Solicitação - <?php echo $oficio['numero']; ?></h2>
        <a href="oficios_visualizar.php?id=<?php echo $id; ?>" class="btn btn-primary btn-sm">Ver Detalhes</a>
    </div>

    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid var(--secondary);">
        <p><strong>Secretaria:</strong> <?php echo $oficio['secretaria']; ?></p>
        <p><strong>Justificativa do Solicitante:</strong> <?php echo nl2br($oficio['justificativa']); ?></p>
        <p><strong>Data de Envio:</strong> <?php echo format_date($oficio['criado_em']); ?></p>
    </div>

    <form action="" method="POST">
        <div class="form-group">
            <label class="form-label">Parecer / Observação da Administração</label>
            <textarea name="justificativa_admin" class="form-control" placeholder="Descreva o motivo da aprovação ou reprovação..."></textarea>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px; justify-content: flex-end;">
            <button type="submit" name="status" value="REPROVADO" class="btn btn-danger" style="padding: 12px 30px;" onclick="return confirm('Tem certeza que deseja REPROVAR este ofício?')">
                <i class="fas fa-times"></i> REPROVAR E ARQUIVAR
            </button>
            <button type="submit" name="status" value="APROVADO" class="btn btn-success" style="padding: 12px 30px;">
                <i class="fas fa-check"></i> APROVAR SOLICITAÇÃO
            </button>
        </div>
    </form>
</div>

<?php include 'views/layout/footer.php'; ?>
