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

$fornecedores = $pdo->query("SELECT id, nome, cnpj FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$fornecedores_by_id = [];
foreach ($fornecedores as $fornecedor) {
    $fornecedores_by_id[(int)$fornecedor['id']] = $fornecedor;
}

if (empty($_SESSION['csrf_analise_oficio'])) {
    $_SESSION['csrf_analise_oficio'] = bin2hex(random_bytes(32));
}
$csrf_analise_oficio = (string)$_SESSION['csrf_analise_oficio'];
$error = null;
$fornecedor_id = 0;
$justificativa_admin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = is_scalar($_POST['status'] ?? null) ? (string)$_POST['status'] : '';
    $justificativa_admin = is_scalar($_POST['justificativa_admin'] ?? null)
        ? trim((string)$_POST['justificativa_admin'])
        : '';
    $csrf_token = is_scalar($_POST['csrf_token'] ?? null) ? (string)$_POST['csrf_token'] : '';
    $fornecedor_post = $_POST['fornecedor_id'] ?? null;
    $fornecedor_id = is_scalar($fornecedor_post) && ctype_digit((string)$fornecedor_post)
        ? (int)$fornecedor_post
        : 0;

    if ($csrf_token === '' || !hash_equals($csrf_analise_oficio, $csrf_token)) {
        $error = 'A sessão de análise expirou. Atualize a página e tente novamente.';
    } elseif (!in_array($status, ['APROVADO', 'REPROVADO'], true)) {
        $error = 'A decisão informada é inválida.';
    } elseif ((string)$oficio['status'] !== 'ENVIADO') {
        $error = 'Este ofício não está mais disponível para análise.';
    } elseif ($status === 'APROVADO' && !isset($fornecedores_by_id[$fornecedor_id])) {
        $error = 'Selecione um fornecedor válido para aprovar a solicitação.';
    }

    if ($error === null) {
        try {
            $pdo->beginTransaction();

            if ($status === 'APROVADO') {
                $stmt = $pdo->prepare("
                    UPDATE oficios
                    SET status = 'APROVADO', fornecedor_indicado_id = ?
                    WHERE id = ? AND status = 'ENVIADO'
                ");
                $stmt->execute([$fornecedor_id, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE oficios SET status = 'REPROVADO' WHERE id = ? AND status = 'ENVIADO'");
                $stmt->execute([$id]);
            }

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('O ofício foi alterado por outro usuário.');
            }

            $detalhes = "Solicitação {$oficio['numero']} alterada para {$status}";
            if ($status === 'APROVADO') {
                $detalhes .= ' com fornecedor indicado ' . $fornecedores_by_id[$fornecedor_id]['nome'];
            }
            log_action($pdo, 'ANALISAR_SOLICITACAO', $detalhes);

            $pdo->commit();
            $_SESSION['csrf_analise_oficio'] = bin2hex(random_bytes(32));

            if ($status === 'APROVADO') {
                flash_message('success', "Solicitação {$oficio['numero']} APROVADA! O fornecedor foi indicado para a aquisição.");
                header("Location: gerar_aquisicao.php?id=$id", true, 303);
            } else {
                flash_message('danger', "Solicitação {$oficio['numero']} foi REPROVADA e ARQUIVADA.");
                header('Location: oficios_lista.php', true, 303);
            }
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Não foi possível concluir a análise. Atualize a página e tente novamente.';
        }
    }
}

$page_title = "Analisar Solicitação: " . $oficio['numero'];
include 'views/layout/header.php';
?>

<style>
    .analise-wrapper {
        width: 100%;
    }

    .analise-card {
        border-radius: 14px;
        overflow: hidden;
    }

    .analise-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.25rem;
        flex-wrap: wrap;
    }

    .analise-header .btn {
        white-space: nowrap;
    }

    .resumo-oficio {
        background: #f8f9fa;
        padding: 1.25rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border-left: 5px solid var(--primary) !important;
    }

    .resumo-oficio p {
        margin: 0 0 .6rem 0;
        line-height: 1.5;
        color: var(--text-dark);
        word-break: break-word;
    }

    .resumo-oficio p:last-child {
        margin-bottom: 0;
    }

    .resumo-label {
        font-weight: 700;
    }

    .analise-form .form-label {
        font-weight: 700;
        margin-bottom: .6rem;
    }

    .analise-form textarea.form-control {
        min-height: 130px;
        resize: vertical;
    }

    .analise-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        justify-content: flex-end;
        flex-wrap: wrap;
        border-top: 1px solid var(--border-color);
        padding-top: 1.25rem;
    }

    .btn-reprovar {
        background: #dc3545 !important;
        border-color: #dc3545 !important;
        color: #fff !important;
        padding: 12px 30px;
    }

    .btn-reprovar:hover {
        background: #bb2d3b !important;
        border-color: #bb2d3b !important;
        color: #fff !important;
    }

    .btn-aprovar {
        background: #198754 !important;
        border-color: #198754 !important;
        color: #fff !important;
        padding: 12px 30px;
    }

    .btn-aprovar:hover {
        background: #157347 !important;
        border-color: #157347 !important;
        color: #fff !important;
    }

    .btn-reprovar i,
    .btn-aprovar i {
        margin-right: 8px;
    }

    @media (max-width: 768px) {
        .analise-header {
            flex-direction: column;
            align-items: stretch;
        }

        .analise-header .btn {
            width: 100%;
            text-align: center;
            justify-content: center;
        }

        .resumo-oficio {
            padding: 1rem;
        }

        .analise-actions {
            flex-direction: column-reverse;
            gap: 12px;
        }

        .analise-actions .btn {
            width: 100%;
            justify-content: center;
            text-align: center;
        }

        .analise-form textarea.form-control {
            min-height: 120px;
        }
    }
</style>

<div class="analise-wrapper">
    <div class="card analise-card">
        <div class="card-body">

            <div class="analise-header">
                <h3 style="margin: 0; color: var(--text-dark); font-weight: 700; font-size: 1.2rem;">
                    <i class="fas fa-search" style="margin-right: 10px; color: var(--primary);"></i>
                    Analisar Solicitação
                </h3>

                <a href="oficios_visualizar.php?id=<?php echo (int)$id; ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-eye"></i> Ver Detalhes
                </a>
            </div>

            <div class="resumo-oficio">
                <p>
                    <span class="resumo-label">Número do Ofício:</span>
                    <?php echo htmlspecialchars($oficio['numero']); ?>
                </p>
                <p>
                    <span class="resumo-label">Secretaria:</span>
                    <?php echo htmlspecialchars($oficio['secretaria']); ?>
                </p>
                <p>
                    <span class="resumo-label">Justificativa do Solicitante:</span>
                    <?php echo nl2br(htmlspecialchars($oficio['justificativa'])); ?>
                </p>
                <p>
                    <span class="resumo-label">Data de Envio:</span>
                    <?php echo format_date($oficio['criado_em']); ?>
                </p>
            </div>

            <form action="" method="POST" class="analise-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_analise_oficio, ENT_QUOTES, 'UTF-8'); ?>">

                <?php if ($error !== null): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Parecer / Observação da Administração</label>
                    <textarea
                        name="justificativa_admin"
                        class="form-control"
                        placeholder="Descreva o motivo da aprovação ou reprovação..."><?php echo htmlspecialchars($justificativa_admin, ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="fornecedor-aprovacao" class="form-label">Fornecedor para aprovação</label>
                    <select name="fornecedor_id" id="fornecedor-aprovacao" class="form-control" required>
                        <option value="">Selecione o fornecedor...</option>
                        <?php foreach ($fornecedores as $fornecedor): ?>
                            <option
                                value="<?php echo (int)$fornecedor['id']; ?>"
                                <?php echo $fornecedor_id === (int)$fornecedor['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($fornecedor['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($fornecedor['cnpj'])): ?>
                                    (<?php echo htmlspecialchars($fornecedor['cnpj'], ENT_QUOTES, 'UTF-8'); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Obrigatório somente para aprovar. O fornecedor ficará pré-selecionado na geração da aquisição.</small>
                </div>

                <?php if (empty($fornecedores)): ?>
                    <div class="alert alert-warning">Cadastre um fornecedor antes de aprovar esta solicitação.</div>
                <?php endif; ?>

                <div class="analise-actions">
                    <button
                        type="submit"
                        name="status"
                        value="REPROVADO"
                        class="btn btn-reprovar"
                        formnovalidate
                        onclick="return confirm('Tem certeza que deseja REPROVAR esta solicitação?')">
                        <i class="fas fa-times"></i> REPROVAR E ARQUIVAR
                    </button>

                    <button
                        type="submit"
                        name="status"
                        value="APROVADO"
                        <?php echo empty($fornecedores) ? 'disabled' : ''; ?>
                        class="btn btn-aprovar">
                        <i class="fas fa-check"></i> APROVAR SOLICITAÇÃO
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
