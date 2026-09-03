<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE deleted_at IS NULL')->fetchColumn();
$primeiroCadastro = $totalUsuarios === 0;

if (!$primeiroCadastro && !usuario_admin()) {
    flash('warning', 'O cadastro de novos usuários é permitido apenas para administrador.');
    redirect(usuario_logado() ? 'index.php' : 'login.php');
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');
    $nivel = $primeiroCadastro ? 'admin' : (string) ($_POST['nivel'] ?? 'atendente');
    $niveis = ['admin', 'gerente', 'atendente', 'mecanico', 'leitor'];

    if ($nome === '') {
        $erro = 'Informe o nome do usuário.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas informadas são diferentes.';
    } elseif (!in_array($nivel, $niveis, true)) {
        $erro = 'Nível de acesso inválido.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $erro = 'Já existe um usuário com este e-mail.';
        } else {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, nivel, ativo, senha_alterada_em) VALUES (?, ?, ?, ?, 1, NOW())');
            $stmt->execute([$nome, $email, $senhaHash, $nivel]);
            $usuarioId = (int) $pdo->lastInsertId();

            if ($primeiroCadastro) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $usuarioId;
                $_SESSION['usuario_nome'] = $nome;
                $_SESSION['usuario_email'] = $email;
                $_SESSION['usuario_nivel'] = 'admin';
                audit($pdo, 'usuarios', $usuarioId, 'cadastro_inicial');
                redirect('index.php');
            }

            audit($pdo, 'usuarios', $usuarioId, 'cadastro');
            $sucesso = 'Usuário cadastrado com sucesso.';
            $_POST = [];
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro de usuário • Bianka Oficina</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css?v=1">
</head>
<body>
<div class="auth-page">
    <section class="auth-cover">
        <div class="auth-brand">
            <div class="auth-brand-mark">B</div>
            <div>
                <strong>Bianka</strong>
                <span>Oficina Mecânica</span>
            </div>
        </div>
        <div class="auth-cover-content">
            <h1><?= $primeiroCadastro ? 'Crie o primeiro acesso.' : 'Cadastre um novo usuário.' ?></h1>
            <p><?= $primeiroCadastro ? 'A primeira conta criada recebe o nível de administrador do sistema.' : 'Cadastre os usuários que terão acesso ao sistema da oficina.' ?></p>
        </div>
        <div class="auth-cover-footer">BIAUTO • Sistema acadêmico</div>
    </section>

    <main class="auth-main">
        <div class="auth-box">
            <h2><?= $primeiroCadastro ? 'Primeiro cadastro' : 'Novo usuário' ?></h2>
            <p class="auth-subtitle"><?= $primeiroCadastro ? 'Preencha os dados para criar a conta administradora.' : 'Informe os dados e escolha o nível de acesso.' ?></p>

            <?php if ($erro !== ''): ?>
                <div class="auth-alert danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($sucesso !== ''): ?>
                <div class="auth-alert success"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="nome">Nome</label>
                    <input class="input" id="nome" name="nome" required maxlength="160" value="<?= htmlspecialchars((string) ($_POST['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input class="input" id="email" name="email" type="email" required maxlength="190" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <?php if (!$primeiroCadastro): ?>
                    <div class="form-group">
                        <label for="nivel">Nível de acesso</label>
                        <select class="select" id="nivel" name="nivel" required>
                            <option value="atendente">Atendente</option>
                            <option value="mecanico">Mecânico</option>
                            <option value="gerente">Gerente</option>
                            <option value="leitor">Leitor</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <input class="input" id="senha" name="senha" type="password" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar senha</label>
                        <input class="input" id="confirmar_senha" name="confirmar_senha" type="password" required minlength="8">
                    </div>
                </div>

                <div class="password-help">Use pelo menos 8 caracteres e evite senhas fáceis de adivinhar.</div>
                <div style="height:16px"></div>
                <button class="btn btn-primary" type="submit">Cadastrar usuário</button>
            </form>

            <div class="auth-links">
                <?php if ($primeiroCadastro): ?>
                    <a href="login.php">Voltar para o login</a>
                <?php else: ?>
                    <a href="index.php">Voltar para o sistema</a>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
