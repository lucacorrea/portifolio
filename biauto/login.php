<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (usuario_logado()) {
    redirect('index.php');
}

$totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE deleted_at IS NULL')->fetchColumn();
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {
        $erro = 'Informe o e-mail e a senha.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (login_bloqueado($pdo, $email)) {
        $erro = 'Muitas tentativas de acesso. Aguarde 15 minutos e tente novamente.';
    } else {
        $stmt = $pdo->prepare('SELECT id, nome, email, senha_hash, nivel, ativo FROM usuarios WHERE email = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && (int) $usuario['ativo'] === 1 && password_verify($senha, $usuario['senha_hash'])) {
            registrar_tentativa_login($pdo, $email, true);

            if (password_needs_rehash($usuario['senha_hash'], PASSWORD_DEFAULT)) {
                $novoHash = password_hash($senha, PASSWORD_DEFAULT);
                $up = $pdo->prepare('UPDATE usuarios SET senha_hash = ?, senha_alterada_em = NOW() WHERE id = ?');
                $up->execute([$novoHash, $usuario['id']]);
            }

            session_regenerate_id(true);
            $_SESSION['usuario_id'] = (int) $usuario['id'];
            $_SESSION['usuario_nome'] = (string) $usuario['nome'];
            $_SESSION['usuario_email'] = (string) $usuario['email'];
            $_SESSION['usuario_nivel'] = (string) $usuario['nivel'];

            $up = $pdo->prepare('UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = ?');
            $up->execute([$usuario['id']]);

            audit($pdo, 'usuarios', (int) $usuario['id'], 'login');
            redirect('index.php');
        }

        registrar_tentativa_login($pdo, $email, false);
        $erro = 'E-mail ou senha incorretos.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login • Bianka Oficina</title>
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
            <h1>Controle da oficina em um só lugar.</h1>
            <p>Acompanhe clientes, veículos, ordens de serviço, orçamentos, peças e pagamentos de forma simples.</p>
        </div>
        <div class="auth-cover-footer">BIAUTO • Sistema acadêmico</div>
    </section>

    <main class="auth-main">
        <div class="auth-box">
            <h2>Entrar no sistema</h2>
            <p class="auth-subtitle">Use sua conta para acessar o painel da oficina.</p>

            <?php if ($erro !== ''): ?>
                <div class="auth-alert danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash'])): ?>
                <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
                <div class="auth-alert <?= htmlspecialchars((string) $flash['tipo'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $flash['mensagem'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input class="input" id="email" name="email" type="email" autocomplete="username" required value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input class="input" id="senha" name="senha" type="password" autocomplete="current-password" required>
                </div>
                <button class="btn btn-primary" type="submit">Entrar</button>
            </form>

            <?php if ($totalUsuarios === 0): ?>
                <div class="auth-links">
                    <span>Primeiro acesso?</span>
                    <a href="cadastro.php">Criar administrador</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
