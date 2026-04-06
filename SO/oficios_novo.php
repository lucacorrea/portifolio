<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$page_title = "Cadastrar Nova Solicitação";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secretaria_id = $_POST['secretaria_id'];
    $justificativa = trim($_POST['justificativa']);
    $produtos = $_POST['produtos'] ?? [];

    // Validação de Justificativa (Obrigatória)
    if (empty($justificativa)) {
        $error = "O campo Justificativa é obrigatório.";
    } elseif (!empty($produtos)) {
        try {
            $pdo->beginTransaction();

            // Tratamento de Upload de Orçamento (Opcional)
            $arquivo_orcamento = null;
            if (isset($_FILES['orcamento']) && $_FILES['orcamento']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['orcamento']['tmp_name'];
                $file_name = $_FILES['orcamento']['name'];
                $ext = pathinfo($file_name, PATHINFO_EXTENSION);

                // Gerar nome único para o arquivo
                $new_name = "ORC_" . date("Ymd_His") . "_" . uniqid() . "." . $ext;
                $upload_dir = "assets/uploads/orcamentos/";

                if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                    $arquivo_orcamento = $upload_dir . $new_name;
                }
            }

            $numero = generate_oficio_number($pdo);
            $stmt = $pdo->prepare("INSERT INTO oficios (numero, secretaria_id, justificativa, usuario_id, arquivo_orcamento) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$numero, $secretaria_id, $justificativa, $_SESSION['user_id'], $arquivo_orcamento]);
            $oficio_id = $pdo->lastInsertId();

            $stmt_item = $pdo->prepare("INSERT INTO itens_oficio (oficio_id, produto, quantidade, unidade) VALUES (?, ?, ?, ?)");
            foreach ($produtos as $item) {
                if (!empty($item['nome'])) {
                    $stmt_item->execute([$oficio_id, $item['nome'], $item['qtd'], $item['unidade'] ?: 'UN']);
                }
            }

            log_action($pdo, "CRIAR_OFICIO", "Ofício $numero criado com anexo: " . ($arquivo_orcamento ? 'SIM' : 'NÃO'));
            $pdo->commit();
            flash_message('success', "Ofício $numero cadastrado com sucesso!");
            header("Location: oficios_visualizar.php?id=$oficio_id");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro ao cadastrar: " . $e->getMessage();
        }
    } else {
        $error = "Adicione pelo menos um produto ao ofício.";
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
    }

    .solicitacao-top-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .itens-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .product-item {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 1rem;
        margin-bottom: 1rem;
        align-items: end;
    }

    .remove-product-btn {
        color: var(--status-rejected);
        border-color: var(--status-rejected);
        padding: 0.5rem;
        min-width: 44px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    #add-product{
        padding: 0.5rem 1rem;
    }

    .solicitacao-actions {
        margin-top: 3rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1.5rem;
        text-align: right;
    }

    .btn-salvar-solicitacao {
        padding: 0.75rem 2rem;
    }

    @media (max-width: 768px) {
        .solicitacao-header {
            flex-direction: column;
            align-items: stretch;
        }

        .solicitacao-header h3 {
            margin-bottom: 0;
            font-size: 1.1rem !important;
        }

        .solicitacao-header .btn {
            width: 100%;
            text-align: center;
        }

        .solicitacao-top-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .itens-header {
            flex-direction: column;
            align-items: stretch;
        }

        .itens-header h4 {
            width: 100%;
        }

        .itens-header .btn {
            width: 100%;
            text-align: center;
        }

        .product-item {
            grid-template-columns: 1fr;
            gap: 0.75rem;
            padding: 0.9rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: #fff;
        }

        .product-item .form-group {
            width: 100%;
        }

        .remove-product-wrap {
            display: flex;
            justify-content: flex-end;
        }

        .remove-product-btn {
            width: 100%;
            height: 42px;
        }

        .solicitacao-actions {
            text-align: stretch;
        }

        .btn-salvar-solicitacao {
            width: 100%;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }

        textarea.form-control {
            min-height: 110px;
        }
    }
</style>

<div class="card">
    <div class="card-body">
        <div class="solicitacao-header">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1.25rem; margin: 0;">
                <i class="fas fa-edit" style="margin-right: 10px; color: var(--primary);"></i> Formulário de Solicitação
            </h3>
            <a href="oficios_lista.php" class="btn btn-outline btn-sm">Voltar</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="" method="POST" id="oficio-form" enctype="multipart/form-data">
            <div class="solicitacao-top-grid">
                <div class="form-group">
                    <label class="form-label">Secretaria Solicitante</label>
                    <select name="secretaria_id" class="form-control" required>
                        <option value="">Selecione a Secretaria...</option>
                        <?php foreach ($secretarias as $sec): ?>
                            <option value="<?php echo $sec['id']; ?>"><?php echo $sec['nome']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Arquivo do Orçamento (Opcional)</label>
                    <input type="file" name="orcamento" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    <small class="text-muted">Formatos aceitos: PDF, JPG, PNG</small>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label class="form-label">Justificativa / Finalidade <span style="color:red">*</span></label>
                <textarea name="justificativa" class="form-control" placeholder="Descreva detalhadamente a necessidade da solicitação..." rows="3" required></textarea>
            </div>

            <div style="border-top: 1px solid var(--border-color); padding-top: 2rem; margin-bottom: 1.5rem;">
                <div class="itens-header">
                    <h4 style="font-size: 1rem; font-weight: 700; color: var(--text-dark); margin: 0;">
                        <i class="fas fa-box" style="margin-right: 8px; color: var(--primary);"></i> Itens da Solicitação
                    </h4>
                    <button type="button" class="btn btn-outline btn-sm" id="add-product">
                        <i class="fas fa-plus"></i> Adicionar Produto
                    </button>
                </div>

                <div id="products-container">
                    <div class="product-item">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Produto/Serviço</label>
                            <input type="text" name="produtos[0][nome]" class="form-control" required placeholder="Ex: Resma de Papel A4">
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Qtd</label>
                            <input type="number" step="0.01" name="produtos[0][qtd]" class="form-control" required placeholder="0.00">
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Unidade</label>
                            <input type="text" name="produtos[0][unidade]" class="form-control" placeholder="UN" value="UN">
                        </div>

                        <div class="form-group remove-product-wrap" style="margin: 0;">
                            <button type="button" class="btn btn-outline btn-sm remove-product remove-product-btn">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
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
    document.getElementById('add-product').addEventListener('click', function() {
        const container = document.getElementById('products-container');
        const index = container.getElementsByClassName('product-item').length;
        const item = document.createElement('div');
        item.className = 'product-item';
        item.innerHTML = `
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Produto/Serviço</label>
            <input type="text" name="produtos[${index}][nome]" class="form-control" required placeholder="Ex: Item extra">
        </div>

        <div class="form-group" style="margin: 0;">
            <label class="form-label">Qtd</label>
            <input type="number" step="0.01" name="produtos[${index}][qtd]" class="form-control" required placeholder="0.00">
        </div>

        <div class="form-group" style="margin: 0;">
            <label class="form-label">Unidade</label>
            <input type="text" name="produtos[${index}][unidade]" class="form-control" placeholder="UN" value="UN">
        </div>

        <div class="form-group remove-product-wrap" style="margin: 0;">
            <button type="button" class="btn btn-outline btn-sm remove-product remove-product-btn">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
        container.appendChild(item);

        item.querySelector('.remove-product').addEventListener('click', function() {
            if (document.querySelectorAll('.product-item').length > 1) {
                item.remove();
            }
        });
    });

    document.querySelectorAll('.remove-product').forEach(btn => {
        btn.addEventListener('click', function() {
            if (document.querySelectorAll('.product-item').length > 1) {
                this.closest('.product-item').remove();
            }
        });
    });
</script>

<?php include 'views/layout/footer.php'; ?>