<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$nivel_user = strtoupper(trim($_SESSION['nivel'] ?? ''));

$page_title = "Cadastrar Nova Solicitação";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secretaria_id    = $_POST['secretaria_id'] ?? '';
    $justificativa    = trim($_POST['justificativa'] ?? '');
    $valor_orcamento  = !empty($_POST['valor_orcamento']) ? str_replace(',', '.', $_POST['valor_orcamento']) : null;
    $numero_manual    = isset($_POST['numero_oficio']) ? mb_strtoupper(trim($_POST['numero_oficio']), 'UTF-8') : null;
    $criado_em_device = trim($_POST['criado_em_device'] ?? '');

    // Validação de Campos Obrigatórios
    if (empty($justificativa)) {
        $error = "O campo Justificativa é obrigatório.";
    } elseif (empty($numero_manual)) {
        $error = "O número do ofício é obrigatório.";
    } elseif (empty($secretaria_id)) {
        $error = "Selecione a secretaria solicitante.";
    } elseif (empty($criado_em_device)) {
        $error = "Não foi possível capturar a data e hora do dispositivo. Atualize a página e tente novamente.";
    } else {
        try {
            $pdo->beginTransaction();

            // Tratamento do horário vindo do dispositivo
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $criado_em_device);
            if (!$dt || $dt->format('Y-m-d H:i:s') !== $criado_em_device) {
                throw new Exception("Data/hora do dispositivo inválida.");
            }

            // Tratamento de Upload de Orçamento (Opcional)
            $arquivo_orcamento = null;
            if (isset($_FILES['orcamento']) && $_FILES['orcamento']['error'] === UPLOAD_ERR_OK) {
                $file_tmp  = $_FILES['orcamento']['tmp_name'];
                $file_name = $_FILES['orcamento']['name'];
                $ext       = pathinfo($file_name, PATHINFO_EXTENSION);

                // Gerar nome único para o arquivo
                $new_name   = "ORC_" . date("Ymd_His") . "_" . uniqid() . "." . $ext;
                $upload_dir = "assets/uploads/orcamentos/";

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                    $arquivo_orcamento = $upload_dir . $new_name;
                }
            }

            // Verificar se o número do ofício já existe
            $stmt_check = $pdo->prepare("SELECT id FROM oficios WHERE numero = ?");
            $stmt_check->execute([$numero_manual]);
            if ($stmt_check->fetch()) {
                throw new Exception("O número de ofício '$numero_manual' já está cadastrado.");
            }

            // Toda nova solicitação agora entra como PENDENTE_ITENS
            $status = 'PENDENTE_ITENS';

            $stmt = $pdo->prepare("
                INSERT INTO oficios 
                    (numero, secretaria_id, justificativa, usuario_id, arquivo_orcamento, valor_orcamento, status, criado_em) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $numero_manual,
                $secretaria_id,
                $justificativa,
                $_SESSION['user_id'],
                $arquivo_orcamento,
                $valor_orcamento,
                $status,
                $criado_em_device
            ]);

            $oficio_id = $pdo->lastInsertId();

            log_action($pdo, "CRIAR_OFICIO", "Ofício $numero_manual cadastrado aguardando itens.");
            $pdo->commit();

            flash_message('success', "Solicitação $numero_manual cadastrada com sucesso! Agora a SEMFAZ deverá atribuir os itens.");
            header("Location: oficios_visualizar.php?id=$oficio_id");
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
                        required
                        placeholder="Ex: OF-2026-01"
                        oninput="this.value = this.value.toUpperCase()"
                        value="<?php echo htmlspecialchars($_POST['numero_oficio'] ?? ''); ?>"
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
                    <input type="file" name="orcamento" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
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
                    <strong>Nota:</strong> Este formulário é destinado ao registro inicial. Os itens específicos de produtos serão atribuídos pela <strong>SEMFAZ</strong> após a criação deste registro.
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