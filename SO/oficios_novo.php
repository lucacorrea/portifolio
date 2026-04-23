<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$nivel_user = strtoupper(trim($_SESSION['nivel'] ?? ''));
$page_title = "Cadastrar Nova Solicitação";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secretaria_id    = $_POST['secretaria_id'] ?? '';
    $local            = trim($_POST['local'] ?? '');
    $justificativa    = trim($_POST['justificativa'] ?? '');
    $valor_orcamento  = !empty($_POST['valor_orcamento'])
        ? str_replace(['.', ','], ['', '.'], $_POST['valor_orcamento'])
        : null;
    $numero_manual    = isset($_POST['numero_oficio']) ? mb_strtoupper(trim($_POST['numero_oficio']), 'UTF-8') : null;
    $criado_em_device = trim($_POST['criado_em_device'] ?? '');

    if (empty($justificativa)) {
        $error = "O campo Justificativa é obrigatório.";
    } elseif (empty($secretaria_id)) {
        $error = "Selecione a secretaria solicitante.";
    } elseif (empty($local)) {
        $error = "Informe o local da solicitação.";
    } elseif (empty($criado_em_device)) {
        $error = "Não foi possível capturar a data e hora do dispositivo. Atualize a página e tente novamente.";
    } else {
        try {
            $pdo->beginTransaction();

            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $criado_em_device);
            if (!$dt || $dt->format('Y-m-d H:i:s') !== $criado_em_device) {
                throw new Exception("Data/hora do dispositivo inválida.");
            }

            function handleMultipleUploads($filesKey, $targetDir, $prefix, $tipo, $oficio_id, $pdo) {
                if (!isset($_FILES[$filesKey]) || empty($_FILES[$filesKey]['name'][0])) {
                    return null;
                }

                $first_file_path = null;
                $files = $_FILES[$filesKey];

                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file_tmp  = $files['tmp_name'][$i];
                        $file_name = $files['name'][$i];
                        $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $new_name  = $prefix . "_" . date("Ymd_His") . "_" . uniqid() . "." . $ext;

                        if (move_uploaded_file($file_tmp, $targetDir . $new_name)) {
                            $caminho = $targetDir . $new_name;

                            if ($i === 0) {
                                $first_file_path = $caminho;
                            }

                            $stmt_anexo = $pdo->prepare("
                                INSERT INTO oficio_anexos (oficio_id, caminho, tipo, nome_original)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt_anexo->execute([$oficio_id, $caminho, $tipo, $file_name]);
                        }
                    }
                }

                return $first_file_path;
            }

            $stmt_check = $pdo->prepare("SELECT id FROM oficios WHERE numero = ?");
            $stmt_check->execute([$numero_manual]);
            if ($stmt_check->fetch()) {
                throw new Exception("O número de ofício '{$numero_manual}' já está cadastrado.");
            }

            $arquivo_orcamento = null;
            $arquivo_oficio    = null;
            $status = 'PENDENTE_ITENS';

            $stmt = $pdo->prepare("
                INSERT INTO oficios
                    (numero, secretaria_id, local, justificativa, usuario_id, valor_orcamento, arquivo_orcamento, status, criado_em)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $numero_manual,
                $secretaria_id,
                $local,
                $justificativa,
                $_SESSION['user_id'],
                $valor_orcamento,
                $arquivo_orcamento,
                $status,
                $criado_em_device
            ]);

            $oficio_id = $pdo->lastInsertId();

            $arquivo_orcamento = handleMultipleUploads(
                'orcamento',
                'assets/uploads/orcamentos/',
                'ORC',
                'ORCAMENTO',
                $oficio_id,
                $pdo
            );

            $arquivo_oficio = handleMultipleUploads(
                'arquivo_oficio',
                'assets/uploads/oficios/',
                'OFI',
                'OFICIO',
                $oficio_id,
                $pdo
            );

            $stmt_upd = $pdo->prepare("
                UPDATE oficios
                SET arquivo_orcamento = ?, arquivo_oficio = ?, valor_orcamento = ?
                WHERE id = ?
            ");
            $stmt_upd->execute([
                $arquivo_orcamento,
                $arquivo_oficio,
                $valor_orcamento,
                $oficio_id
            ]);

            log_action($pdo, "CRIAR_OFICIO", "Ofício {$numero_manual} cadastrado com sucesso.");
            $pdo->commit();

            flash_message('success', "Solicitação {$numero_manual} cadastrada com sucesso.");
            header("Location: oficios_visualizar.php?id={$oficio_id}");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Erro ao cadastrar: " . $e->getMessage();
        }
    }
}

$secretarias = $pdo->query("SELECT * FROM secretarias ORDER BY nome")->fetchAll();

include 'views/layout/header.php';
?>

<style>
    .solicitacao-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .solicitacao-top-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .solicitacao-actions {
        margin-top: 2rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1.5rem;
        text-align: right;
    }

    .btn-salvar-solicitacao {
        padding: 0.75rem 2rem;
    }

    .device-time-box {
        margin-bottom: 1.5rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 0.95rem;
        color: #334155;
    }

    .device-time-box strong {
        color: #0f172a;
    }

    @media (max-width: 768px) {
        .solicitacao-top-grid {
            grid-template-columns: 1fr;
        }

        .solicitacao-actions {
            text-align: center;
        }

        .btn-salvar-solicitacao {
            width: 100%;
        }
    }
</style>

<div class="card">
    <div class="card-body">
        <div class="solicitacao-header">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1.25rem; margin: 0;">
                <i class="fas fa-edit" style="margin-right: 10px; color: var(--primary);"></i>
                Formulário de Solicitação (Casa Civil)
            </h3>
            <a href="oficios_lista.php" class="btn btn-outline btn-sm">Voltar</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="device-time-box">
            <strong>Data/hora do dispositivo:</strong>
            <span id="preview-datahora-dispositivo">Carregando...</span>
        </div>

        <form action="" method="POST" id="oficio-form" enctype="multipart/form-data">
            <input type="hidden" name="criado_em_device" id="criado_em_device" value="">

            <div class="solicitacao-top-grid">

                <div class="form-group">
                    <label class="form-label">Número do Ofício <span style="color:red">*</span></label>
                    <input
                        type="text"
                        name="numero_oficio"
                        class="form-control"
                        placeholder="Ex: OF-2026-01"
                        oninput="this.value = this.value.toUpperCase()"
                        value="<?php echo htmlspecialchars($_POST['numero_oficio'] ?? ''); ?>"
                        required
                    >
                    <small class="text-muted">Informe o número do processo físico ou ofício.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Secretaria Solicitante <span style="color:red">*</span></label>
                    <select name="secretaria_id" class="form-control" required>
                        <option value="">Selecione a Secretaria...</option>
                        <?php foreach ($secretarias as $sec): ?>
                            <option
                                value="<?php echo $sec['id']; ?>"
                                <?php echo (isset($_POST['secretaria_id']) && $_POST['secretaria_id'] == $sec['id']) ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($sec['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Local <span style="color:red">*</span></label>
                    <input
                        type="text"
                        name="local"
                        class="form-control"
                        placeholder="Ex: Almoxarifado Central, Zona Rural, Unidade de Saúde..."
                        value="<?php echo htmlspecialchars($_POST['local'] ?? ''); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Valor do Orçamento (Opcional)</label>
                    <input
                        type="text"
                        name="valor_orcamento"
                        class="form-control"
                        placeholder="0,00"
                        onkeyup="this.value = this.value.replace(/[^\d,]/g, '')"
                        value="<?php echo htmlspecialchars($_POST['valor_orcamento'] ?? ''); ?>"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Arquivo do Orçamento (Opcional)</label>
                    <input type="file" name="orcamento[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
                    <small class="text-muted">Você pode selecionar múltiplos arquivos.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Ofício de Solicitação (Opcional)</label>
                    <input type="file" name="arquivo_oficio[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
                    <small class="text-muted">Você pode selecionar múltiplos arquivos.</small>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label class="form-label">Justificativa / Finalidade <span style="color:red">*</span></label>
                <textarea
                    name="justificativa"
                    class="form-control"
                    placeholder="Descreva detalhadamente a necessidade da solicitação..."
                    rows="4"
                    required
                ><?php echo htmlspecialchars($_POST['justificativa'] ?? ''); ?></textarea>
            </div>

            <div class="alert alert-info" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <i class="fas fa-info-circle" style="font-size: 1.5rem;"></i>
                <div>
                    <strong>Nota:</strong> Este formulário é destinado ao registro inicial. Os itens específicos de produtos serão atribuídos posteriormente.
                </div>
            </div>

            <div class="solicitacao-actions">
                <button type="submit" class="btn btn-primary btn-salvar-solicitacao">
                    <i class="fas fa-save"></i> Salvar Solicitação
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function getDeviceDateTimeForMysql() {
        const now = new Date();
        return now.getFullYear() + '-' +
            pad(now.getMonth() + 1) + '-' +
            pad(now.getDate()) + ' ' +
            pad(now.getHours()) + ':' +
            pad(now.getMinutes()) + ':' +
            pad(now.getSeconds());
    }

    function getDeviceDateTimeForPreview() {
        const now = new Date();
        return pad(now.getDate()) + '/' +
            pad(now.getMonth() + 1) + '/' +
            now.getFullYear() + ' ' +
            pad(now.getHours()) + ':' +
            pad(now.getMinutes()) + ':' +
            pad(now.getSeconds());
    }

    function atualizarDataHoraDispositivo() {
        const mysqlDatetime = getDeviceDateTimeForMysql();
        const previewDatetime = getDeviceDateTimeForPreview();

        const inputHidden = document.getElementById('criado_em_device');
        const preview = document.getElementById('preview-datahora-dispositivo');

        if (inputHidden) inputHidden.value = mysqlDatetime;
        if (preview) preview.textContent = previewDatetime;
    }

    atualizarDataHoraDispositivo();
    setInterval(atualizarDataHoraDispositivo, 1000);

    document.getElementById('oficio-form').addEventListener('submit', function () {
        atualizarDataHoraDispositivo();
    });
</script>

<?php include 'views/layout/footer.php'; ?>