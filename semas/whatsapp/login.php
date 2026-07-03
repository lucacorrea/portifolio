<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/auth/permissoes.php';
require_once __DIR__ . '/config/database.php';

if (!empty($_SESSION['semas_whatsapp_user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $login = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $key = 'whatsapp_login_' . sha1((string)($_SERVER['REMOTE_ADDR'] ?? 'local'));
    $attempts = (array)($_SESSION[$key] ?? ['count' => 0, 'until' => 0]);

    if ((int)($attempts['until'] ?? 0) > time()) {
        $erro = 'Muitas tentativas. Aguarde alguns minutos.';
    } elseif (!whatsapp_csrf_validate($token)) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } elseif ($login === '' || $password === '') {
        $erro = 'Informe login e senha.';
    } else {
        $email = strtolower($login);
        $cpf = preg_replace('/\D+/', '', $login) ?: '';

        try {
            $stmt = $pdo->prepare("SELECT * FROM contas_acesso_privado WHERE email = :email OR cpf = :cpf LIMIT 1");
            $stmt->execute([':email' => $email, ':cpf' => $cpf]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $valid = false;
            if ($user) {
                $calc = hash('sha256', (string)$user['senha_salt'] . $password, false);
                $valid = hash_equals((string)$user['senha_hash'], $calc);
            }

            $role = $user ? (string)$user['role'] : '';
            $autorizado = $user ? (string)$user['autorizado'] : 'nao';
            $canEnter = $valid && ($role === 'suporte' || $role === 'secretario' || $role === 'prefeito' || ($role === 'admin' && $autorizado === 'sim'));

            if (!$canEnter) {
                $attempts['count'] = (int)($attempts['count'] ?? 0) + 1;
                if ($attempts['count'] >= 5) {
                    $attempts['until'] = time() + 300;
                }
                $_SESSION[$key] = $attempts;
                $erro = 'Login ou senha inválidos.';
            } else {
                unset($_SESSION[$key]);
                session_regenerate_id(true);
                $_SESSION['semas_whatsapp_user_id'] = (int)$user['id'];
                $_SESSION['semas_whatsapp_user_nome'] = (string)$user['nome'];
                $_SESSION['semas_whatsapp_user_email'] = (string)$user['email'];
                $_SESSION['semas_whatsapp_role'] = whatsapp_role_from_semas($role);
                $_SESSION['semas_whatsapp_origin_role'] = $role;
                $_SESSION['semas_whatsapp_last_activity'] = time();
                header('Location: dashboard.php');
                exit;
            }
        } catch (Throwable $e) {
            $erro = 'Erro ao autenticar. Tente novamente.';
        }
    }
}

$csrf = whatsapp_csrf_token();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Central SEMAS - Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { min-height: 100vh; display: grid; place-items: center; background: #eef4ff; font-family: Inter, system-ui, sans-serif; }
    .login-card { width: min(440px, calc(100vw - 32px)); border: 0; border-radius: 18px; box-shadow: 0 18px 40px rgba(37,57,111,.16); }
    .brand { color: #25396f; font-weight: 800; }
  </style>
</head>
<body>
  <main class="card login-card">
    <div class="card-body p-4 p-md-5">
      <div class="mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white mb-3" style="width:48px;height:48px"><i class="bi bi-whatsapp fs-4"></i></div>
        <h1 class="h4 brand mb-1">Central de Comunicação e Atualização Cadastral</h1>
        <p class="text-muted mb-0">SEMAS Coari</p>
      </div>
      <?php if ($erro !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3">
          <label class="form-label" for="login">E-mail ou CPF</label>
          <input class="form-control" id="login" name="login" autocomplete="username" required>
        </div>
        <div class="mb-4">
          <label class="form-label" for="password">Senha</label>
          <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Entrar</button>
      </form>
    </div>
  </main>
</body>
</html>
