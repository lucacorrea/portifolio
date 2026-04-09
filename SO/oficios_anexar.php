<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM oficios WHERE id = ?");
$stmt->execute([$id]);
$oficio = $stmt->fetch();

if (!$oficio) {
    die("Solicitação não encontrada.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['arquivo_oficio']) && $_FILES['arquivo_oficio']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['arquivo_oficio']['tmp_name'];
        $file_name = $_FILES['arquivo_oficio']['name'];
        $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed)) {
            $error = "Apenas arquivos PDF, JPG e PNG são permitidos.";
        } else {
            // Gerar nome único
            $new_name = "OFI_" . date("Ymd_His") . "_" . uniqid() . "." . $ext;
            $upload_dir = "assets/uploads/oficios/";

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                $caminho_arquivo = $upload_dir . $new_name;
                
                // Atualizar no banco
                $stmt_upd = $pdo->prepare("UPDATE oficios SET arquivo_oficio = ? WHERE id = ?");
                $stmt_upd->execute([$caminho_arquivo, $id]);

                log_action($pdo, "ANEXAR_OFICIO", "Ofício anexado à solicitação " . $oficio['numero']);
                flash_message('success', "Documento anexado com sucesso!");
                header("Location: oficios_visualizar.php?id=$id");
                exit();
            } else {
                $error = "Erro ao mover o arquivo para o diretório de destino.";
            }
        }
    } else {
        $error = "Selecione um arquivo válido para anexar.";
    }
}

$page_title = "Anexar Ofício: " . $oficio['numero'];
include 'views/layout/header.php';
?>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="margin: 0; font-weight: 700; color: var(--text-dark);">
                <i class="fas fa-file-upload" style="margin-right: 10px; color: var(--primary);"></i>
                Anexar Ofício de Solicitação
            </h3>
            <a href="oficios_lista.php" class="btn btn-outline btn-sm">Voltar</a>
        </div>

        <div class="alert alert-info">
            Você está anexando o <strong>Ofício de Solicitação</strong> para o processo <strong><?php echo htmlspecialchars($oficio['numero']); ?></strong>.
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" style="margin-top: 2rem;">
            <div class="form-group">
                <label class="form-label">Selecione o arquivo (PDF, JPG, PNG)</label>
                <input type="file" name="arquivo_oficio" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                <small class="text-muted">Este documento será anexado aos "Ofícios de solicitações das aquisições".</small>
            </div>

            <div style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; text-align: right;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Anexo
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
