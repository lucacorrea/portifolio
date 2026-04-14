<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
suporte_check();

$page_title = "Gerenciamento de Usuários";

// Lógica de Cadastro/Edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $nome = trim($_POST['nome']);
        $usuario = trim($_POST['usuario']);
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $nivel = $_POST['nivel'];

        if (!empty($nome) && !empty($usuario) && !empty($_POST['senha'])) {
            try {
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, usuario, senha, nivel) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nome, $usuario, $senha, $nivel]);
                log_action($pdo, "CADASTRO_USUARIO", "Usuário $usuario ($nivel) cadastrado");
                flash_message('success', "Usuário cadastrado com sucesso!");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash_message('danger', "Erro: Este nome de usuário já existe.");
                } else {
                    flash_message('danger', "Erro ao cadastrar: " . $e->getMessage());
                }
            }
        } else {
            flash_message('danger', "Preencha todos os campos obrigatórios.");
        }
    }

    if (isset($_POST['del_user'])) {
        $id = (int)$_POST['user_id'];
        if ($id != $_SESSION['user_id']) {
            $stmt_find = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = ?");
            $stmt_find->execute([$id]);
            $u_name = $stmt_find->fetchColumn();

            try {
                $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
                log_action($pdo, "EXCLUSAO_USUARIO", "Usuário $u_name removido");
                flash_message('success', "Usuário removido com sucesso!");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash_message('danger', "Erro: Não é possível excluir o usuário <strong>$u_name</strong> pois ele possui histórico.");
                } else {
                    flash_message('danger', "Erro interno: " . $e->getMessage());
                }
            }
        } else {
            flash_message('danger', "Você não pode excluir seu próprio usuário!");
        }
    }
}

$usuarios = $pdo->query("SELECT id, nome, usuario, nivel, criado_em FROM usuarios ORDER BY nome")->fetchAll();

include 'views/layout/header.php';
?>

<div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    
    <!-- Novo Usuário -->
    <div class="card">
        <div class="card-body">
            <h3 class="card-title" style="margin-bottom: 1.5rem; font-weight: 700; font-size: 1rem;">
                <i class="fas fa-user-plus" style="margin-right: 8px; color: var(--primary);"></i> Cadastrar Novo Usuário
            </h3>

            <form action="" method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" class="form-control" placeholder="Ex: João da Silva" required>
                </div>

                <div class="form-group">
                    <label>Login</label>
                    <input type="text" name="usuario" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Nível</label>
                    <select name="nivel" class="form-control" required>
                        <option value="SECRETARIO">SECRETARIO</option>
                        <option value="CASA_CIVIL">CASA CIVIL</option>
                        <option value="SEFAZ">SEFAZ</option>
                        <option value="FUNCIONARIO">FUNCIONARIO</option>
                        <option value="ADMIN">ADMIN</option>
                        <option value="SUPORTE">SUPORTE</option>
                    </select>
                </div>

                <div style="grid-column: span 2;">
                    <label>Senha</label>
                    <input type="password" name="senha" class="form-control" required>
                </div>

                <div style="grid-column: span 2; text-align: right;">
                    <button type="submit" name="add_user" class="btn btn-primary">
                        Criar Usuário
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin-bottom: 1.5rem;">Usuários</h3>

            <?php display_flash(); ?>

            <div class="table-responsive" style="max-height: 450px;">
                <table class="table-vcenter" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Nome / Login</th>
                            <th>Nível</th>
                            <th style="width: 80px; text-align:center;">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach($usuarios as $u): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($u['nome']); ?></strong><br>
                                <small>@<?php echo htmlspecialchars($u['usuario']); ?></small>
                            </td>

                            <td><?php echo $u['nivel']; ?></td>

                            <!-- 🔥 AQUI FOI AJUSTADO -->
                            <td style="text-align:center;">
                                <div style="display:flex; justify-content:center; align-items:center;">
                                    
                                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" onsubmit="return confirm('Tem certeza?')">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            
                                            <button type="submit" name="del_user"
                                                style="
                                                    display:flex;
                                                    align-items:center;
                                                    justify-content:center;
                                                    width:32px;
                                                    height:32px;
                                                    border-radius:6px;
                                                    border:1px solid #dc354533;
                                                    color:#dc3545;
                                                    background:transparent;
                                                    cursor:pointer;
                                                ">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size: 0.75rem;">Você</span>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>