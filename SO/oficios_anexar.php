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
    if (isset($_FILES['arquivo_oficio']) && !empty($_FILES['arquivo_oficio']['name'][0])) {
        $files = $_FILES['arquivo_oficio'];
        $upload_dir = "assets/uploads/oficios/";
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $success_count = 0;
        $first_file_path = null;

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp  = $files['tmp_name'][$i];
                $file_name = $files['name'][$i];
                $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    $new_name = "OFI_" . date("Ymd_His") . "_" . uniqid() . "." . $ext;
                    if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                        $caminho = $upload_dir . $new_name;
                        if ($success_count === 0) $first_file_path = $caminho;

                        // Inserir na nova tabela de anexos
                        $stmt_anexo = $pdo->prepare("INSERT INTO oficio_anexos (oficio_id, caminho, tipo, nome_original) VALUES (?, ?, 'OFICIO', ?)");
                        $stmt_anexo->execute([$id, $caminho, $file_name]);
                        $success_count++;
                    }
                }
            }
        }

        if ($success_count > 0) {
            // Atualizar o campo original na tabela oficios (retrocompatibilidade)
            $stmt_upd = $pdo->prepare("UPDATE oficios SET arquivo_oficio = ? WHERE id = ?");
            $stmt_upd->execute([$first_file_path, $id]);

            log_action($pdo, "ANEXAR_OFICIO", "$success_count arquivo(s) anexado(s) à solicitação " . $oficio['numero']);
            flash_message('success', "$success_count documento(s) anexado(s) com sucesso!");
            header("Location: oficios_visualizar.php?id=$id");
            exit();
        } else {
            $error = "Nenhum arquivo válido foi enviado ou ocorreu um erro no upload.";
        }
    } else {
        $error = "Selecione pelo menos um arquivo válido para anexar.";
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
                <label class="form-label">Selecione o(s) arquivo(s) (PDF, JPG, PNG)</label>
                <input type="file" name="arquivo_oficio[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required multiple>
                <small class="text-muted">Você pode selecionar múltiplos arquivos. Eles serão anexados aos "Ofícios de solicitações das aquisições".</small>
            </div>

            <div style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; text-align: right;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Anexo(s)
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
