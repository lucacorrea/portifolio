<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!usuario_admin()) {
    flash('warning', 'Somente o administrador pode gerenciar usuários.');
    redirect('index.php');
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$usuario = null;
$erro = '';

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT id, nome, email, nivel, ativo FROM usuarios WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        flash('danger', 'Usuário não encontrado.');
        redirect('usuarios.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');
    $nivel = (string) ($_POST['nivel'] ?? 'atendente');
    $niveis = ['admin', 'gerente', 'atendente', 'mecanico', 'leitor'];

    if ($nome === '') {
        $erro = 'Informe o nome do usuário.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (!in_array($nivel, $niveis, true)) {
        $erro = 'Nível de acesso inválido.';
    } elseif ($id === 0 && strlen($senha) < 8) {
        $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    } elseif ($senha !== '' && strlen($senha) < 8) {
        $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas informadas são diferentes.';
    } elseif ($id === (int) ($_SESSION['usuario_id'] ?? 0) && $nivel !== 'admin') {
        $erro = 'Você não pode remover seu próprio nível de administrador.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND deleted_at IS NULL AND id <> ? LIMIT 1');
        $stmt->execute([$email, $id]);

        if ($stmt->fetch()) {
            $erro = 'Já existe um usuário com este e-mail.';
        } elseif ($id > 0) {
            $antes = $usuario;

            if ($senha !== '') {
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, nivel = ?, senha_hash = ?, senha_alterada_em = NOW() WHERE id = ?');
                $stmt->execute([$nome, $email, $nivel, $senhaHash, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, nivel = ? WHERE id = ?');
                $stmt->execute([$nome, $email, $nivel, $id]);
            }

            if ($id === (int) ($_SESSION['usuario_id'] ?? 0)) {
                $_SESSION['usuario_nome'] = $nome;
                $_SESSION['usuario_email'] = $email;
                $_SESSION['usuario_nivel'] = $nivel;
            }

            audit($pdo, 'usuarios', $id, 'editar', $antes, ['nome' => $nome, 'email' => $email, 'nivel' => $nivel]);
            flash('success', 'Usuário atualizado com sucesso.');
            redirect('usuarios.php');
        } else {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, nivel, ativo, senha_alterada_em) VALUES (?, ?, ?, ?, 1, NOW())');
            $stmt->execute([$nome, $email, $senhaHash, $nivel]);
            $usuarioId = (int) $pdo->lastInsertId();
            audit($pdo, 'usuarios', $usuarioId, 'cadastro');
            flash('success', 'Usuário cadastrado com sucesso.');
            redirect('usuarios.php');
        }
    }
}

$pageTitle = $id > 0 ? 'Editar Usuário' : 'Novo Usuário';
$currentPage = 'usuarios';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header($id > 0 ? 'Editar usuário' : 'Novo usuário', $id > 0 ? 'Atualize os dados e o nível de acesso.' : 'Cadastre uma nova pessoa para acessar o sistema.', [
    ['label' => 'Voltar', 'href' => 'usuarios.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<?php if ($erro !== ''): ?><div class="card section-card"><span class="badge danger"><?= h($erro) ?></span></div><?php endif; ?>

<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="card section-card">
        <div class="section-title"><div><h2>Dados de acesso</h2><p>Defina o usuário, senha e nível de permissão.</p></div></div>
        <div class="form-row">
            <div class="form-group"><label>Nome</label><input class="input" name="nome" required maxlength="160" value="<?= h((string) ($_POST['nome'] ?? $usuario['nome'] ?? '')) ?>"></div>
            <div class="form-group"><label>E-mail</label><input class="input" name="email" type="email" required maxlength="190" value="<?= h((string) ($_POST['email'] ?? $usuario['email'] ?? '')) ?>"></div>
            <div class="form-group">
                <label>Nível de acesso</label>
                <?php $nivelSelecionado = (string) ($_POST['nivel'] ?? $usuario['nivel'] ?? 'atendente'); ?>
                <select class="select" name="nivel" required>
                    <option value="atendente" <?= $nivelSelecionado === 'atendente' ? 'selected' : '' ?>>Atendente</option>
                    <option value="mecanico" <?= $nivelSelecionado === 'mecanico' ? 'selected' : '' ?>>Mecânico</option>
                    <option value="gerente" <?= $nivelSelecionado === 'gerente' ? 'selected' : '' ?>>Gerente</option>
                    <option value="leitor" <?= $nivelSelecionado === 'leitor' ? 'selected' : '' ?>>Leitor</option>
                    <option value="admin" <?= $nivelSelecionado === 'admin' ? 'selected' : '' ?>>Administrador</option>
                </select>
            </div>
            <div class="form-group"><label>Senha <?= $id > 0 ? '(deixe vazia para manter)' : '' ?></label><input class="input" name="senha" type="password" <?= $id > 0 ? '' : 'required' ?> minlength="8"></div>
            <div class="form-group"><label>Confirmar senha</label><input class="input" name="confirmar_senha" type="password" <?= $id > 0 ? '' : 'required' ?> minlength="8"></div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= $id > 0 ? 'Salvar alterações' : 'Cadastrar usuário' ?></button>
        <a class="btn" href="usuarios.php">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
