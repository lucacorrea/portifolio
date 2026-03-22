<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Login') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/auth.css')) ?>">
</head>
<body class="auth-body">
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-brand">
                <h1>Contábil ERP</h1>
                <p>Entre para acessar o dashboard do escritório.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="auth-alert auth-alert-error"><?= htmlspecialchars((string)$error) ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="auth-alert auth-alert-success"><?= htmlspecialchars((string)$success) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars(url('login')) ?>" class="auth-form">
                <div class="auth-field">
                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email" value="admin@saas.com" required>
                </div>

                <div class="auth-field">
                    <label for="senha">Senha</label>
                    <input id="senha" name="senha" type="password" value="123456" required>
                </div>

                <button class="auth-btn" type="submit">Entrar no dashboard</button>
            </form>

            <div class="auth-demo">
                <strong>Acesso de teste</strong>
                <span>E-mail: admin@saas.com</span>
                <span>Senha: 123456</span>
            </div>
        </div>
    </div>
</body>
</html>
