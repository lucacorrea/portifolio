<?php

require_once __DIR__ . '/../app/bootstrap.php';

use FluxEmpresa\Core\Auth;
use FluxEmpresa\Core\Audit;
use FluxEmpresa\Core\Csrf;
use FluxEmpresa\Core\Database;

if (Auth::isLogged()) {
    redirect('dashboard.php');
}

$error = null;
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $genericError = 'Usuário ou senha inválidos.';

    if ($usuario === '' || strlen($usuario) > 100 || $senha === '') {
        Audit::record('LOGIN_FALHA', null, [
            'usuario' => substr($usuario, 0, 100),
            'motivo' => 'campos_invalidos',
        ]);

        $error = $genericError;
    } else {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT id, empresa_id, nome, email, usuario, senha, perfil, ativo
                 FROM usuarios
                 WHERE usuario = :usuario
                 LIMIT 1'
            );
            $stmt->execute(['usuario' => $usuario]);

            $user = $stmt->fetch();

            if (!$user) {
                Audit::record('LOGIN_FALHA', null, [
                    'usuario' => $usuario,
                    'motivo' => 'usuario_nao_encontrado',
                ]);

                $error = $genericError;
            } elseif ((int) $user['ativo'] !== 1) {
                Audit::record('LOGIN_FALHA', $user, [
                    'usuario' => $usuario,
                    'motivo' => 'usuario_inativo',
                ]);

                $error = $genericError;
            } elseif (!password_verify($senha, (string) $user['senha'])) {
                Audit::record('LOGIN_FALHA', $user, [
                    'usuario' => $usuario,
                    'motivo' => 'senha_invalida',
                ]);

                $error = $genericError;
            } else {
                $now = now();
                $update = $pdo->prepare(
                    'UPDATE usuarios
                     SET ultimo_login = :ultimo_login, atualizado_em = :atualizado_em
                     WHERE id = :id'
                );
                $update->execute([
                    'ultimo_login' => $now,
                    'atualizado_em' => $now,
                    'id' => (int) $user['id'],
                ]);

                Auth::login($user);

                Audit::record('LOGIN_SUCESSO', $user, [
                    'usuario' => $usuario,
                ]);

                redirect('dashboard.php');
            }
        } catch (Throwable $exception) {
            error_log('FluxEmpresa login failed: ' . $exception->getMessage());
            $error = 'Não foi possível autenticar agora. Tente novamente em instantes.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar | FluxEmpresa</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="brand">
            <div class="brand-mark">FE</div>
            <div>
                <h1>FluxEmpresa</h1>
                <p>Gestão de orçamentos, execução e prestação de contas.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= Csrf::field() ?>
            <label>Usuário</label>
            <input type="text" name="usuario" value="<?= h($usuario) ?>" maxlength="100" autocomplete="username" required autofocus>

            <label>Senha</label>
            <input type="password" name="senha" autocomplete="current-password" required>

            <button type="submit">Entrar no sistema</button>
        </form>
    </main>
</body>
</html>
