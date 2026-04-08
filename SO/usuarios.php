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

            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
            log_action($pdo, "EXCLUSAO_USUARIO", "Usuário $u_name removido");
            flash_message('success', "Usuário removido!");
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
                    <label class="form-label">Nome Completo</label>
                    <input type="text" name="nome" class="form-control" placeholder="Ex: João da Silva" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Login (Usuário)</label>
                    <input type="text" name="usuario" class="form-control" placeholder="Ex: joao.silva" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nível de Acesso</label>
                    <select name="nivel" class="form-control" required>
                        <option value="SECRETARIO">SECRETARIO (Apenas Consulta)</option>
                        <option value="CASA_CIVIL">CASA CIVIL (Cadastro de Solicitação)</option>
                        <option value="SEFAZ">SEFAZ (Atribuição de Itens)</option>
                        <option value="FUNCIONARIO">FUNCIONARIO</option>
                        <option value="ADMIN">ADMIN</option>
                        <option value="SUPORTE">SUPORTE TÉCNICO</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Senha Inicial</label>
                    <input type="password" name="senha" class="form-control" placeholder="Digite a senha" required>
                </div>
                <div style="grid-column: span 2; text-align: right;">
                    <button type="submit" name="add_user" class="btn btn-primary"><i class="fas fa-check"></i> Criar Usuário</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Usuários -->
    <div class="card">
        <div class="card-body">
            <h3 class="card-title" style="margin-bottom: 1.5rem; font-weight: 700; font-size: 1rem;">
                <i class="fas fa-users" style="margin-right: 8px; color: var(--primary);"></i> Usuários Ativos
            </h3>
            
            <?php display_flash(); ?>

            <div class="table-responsive" style="max-height: 450px;">
                <table class="table-vcenter">
                    <thead><tr><th>Nome / Login</th><th>Nível</th><th style="text-align: right;">Ações</th></tr></thead>
                    <tbody>
                        <?php foreach($usuarios as $u): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($u['nome']); ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">@<?php echo htmlspecialchars($u['usuario']); ?></div>
                                </td>
                                <td>
                                    <?php 
                                        $badge_class = 'badge-outline';
                                        if($u['nivel'] == 'SUPORTE') $badge_class = 'badge-primary';
                                        if($u['nivel'] == 'ADMIN') $badge_class = 'badge-secondary';
                                        if($u['nivel'] == 'CASA_CIVIL') $badge_class = 'badge-pending';
                                        if($u['nivel'] == 'SEFAZ') $badge_class = 'badge-approved';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>" style="font-size: 0.7rem;">
                                        <?php echo $u['nivel']; ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                                        <form action="" method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?')">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" name="del_user" class="btn btn-outline btn-sm" style="color: #dc3545; border-color: #dc354533; width: 32px; height: 32px; padding: 0;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge" style="background: #f1f5f9; color: #64748b; padding: 0.4rem 0.8rem;">Você</span>
                                    <?php endif; ?>
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
