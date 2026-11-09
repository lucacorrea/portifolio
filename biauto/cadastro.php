<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE deleted_at IS NULL')->fetchColumn();

if ($totalUsuarios > 0) {
    redirect(usuario_logado() && usuario_admin() ? 'usuarios.php' : 'login.php');
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

    if ($nome === '') {
        $erro = 'Informe o nome do usuário.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas informadas são diferentes.';
    } else {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, nivel, ativo, senha_alterada_em) VALUES (?, ?, ?, ?, 1, NOW())');
        $stmt->execute([$nome, $email, $senhaHash, 'admin']);
        $usuarioId = (int) $pdo->lastInsertId();

        iniciar_sessao_usuario([
            'id' => $usuarioId,
            'nome' => $nome,
            'email' => $email,
            'nivel' => 'admin',
        ]);

        audit($pdo, 'usuarios', $usuarioId, 'cadastro_inicial');
        redirect('index.php');
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Primeiro cadastro • Bianka Oficina</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css?v=2">
</head>
<body>
<div class="auth-page">
    <section class="auth-cover">
        <div class="auth-brand">
            <div class="auth-brand-mark">B</div>
            <div><strong>Bianka</strong><span>Oficina Mecânica</span></div>
        </div>
        <div class="auth-cover-content">
            <h1>Crie o primeiro acesso.</h1>
            <p>A primeira conta será a administradora do sistema.</p>
        </div>
        <div class="auth-cover-footer">BIAUTO • Sistema acadêmico</div>
    </section>

    <main class="auth-main">
        <div class="auth-box">
            <h2>Primeiro cadastro</h2>
            <p class="auth-subtitle">Preencha os dados para criar a conta administradora.</p>

            <?php if ($erro !== ''): ?><div class="auth-alert danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-group"><label for="nome">Nome</label><input class="input" id="nome" name="nome" required maxlength="160" value="<?= htmlspecialchars((string) ($_POST['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="form-group"><label for="email">E-mail</label><input class="input" id="email" name="email" type="email" required maxlength="190" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="form-row">
                    <div class="form-group"><label for="senha">Senha</label><input class="input" id="senha" name="senha" type="password" required minlength="8"></div>
                    <div class="form-group"><label for="confirmar_senha">Confirmar senha</label><input class="input" id="confirmar_senha" name="confirmar_senha" type="password" required minlength="8"></div>
                </div>
                <div class="password-help">A senha deve ter pelo menos 8 caracteres.</div>
                <div style="height:16px"></div>
                <button class="btn btn-primary" type="submit">Criar administrador</button>
            </form>

            <div class="auth-links"><a href="login.php">Voltar para o login</a></div>
        </div>
    </main>
</div>
</body>
</html>
