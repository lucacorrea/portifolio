<?php
declare(strict_types=1);

/* =========================================================
   redefinirSenhaNova.php
   - Fluxo por CÓDIGO (sem token no link)
   - Só entra aqui se o código foi confirmado (session redef_ok_email)
   - Atualiza usuarios.senha_hash
   - Limpa sessão do reset ao finalizar
   ========================================================= */

/* ===== LOG ===== */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_error.log');

/* ===== SESSION ===== */
if (session_status() !== PHP_SESSION_ACTIVE) {
  if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
      'path' => '/',
      'httponly' => true,
      'samesite' => 'Lax',
      'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
  } else {
    session_set_cookie_params(0, '/');
  }
  session_start();
}

/* ===== Helpers ===== */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function str_len(string $s): int {
  return function_exists('mb_strlen') ? (int)mb_strlen($s, 'UTF-8') : (int)strlen($s);
}

/* ===== Flash ===== */
$erro  = (string)($_SESSION['flash_erro'] ?? '');
$ok    = (string)($_SESSION['flash_ok'] ?? '');
unset($_SESSION['flash_erro'], $_SESSION['flash_ok']);

/* ===== Gate: precisa ter confirmado o código ===== */
$emailOk = (string)($_SESSION['redef_ok_email'] ?? '');
if ($emailOk === '' || !filter_var($emailOk, FILTER_VALIDATE_EMAIL)) {
  $_SESSION['flash_erro'] = "Sessão inválida ou expirada. Solicite um novo código.";
  header("Location: ./redefinirSenha.php");
  exit;
}

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/* =========================================================
   PROCESSAMENTO (POST): salva nova senha
   ========================================================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  try {
    /* CSRF */
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
      throw new RuntimeException("CSRF inválido.");
    }

    $senha  = (string)($_POST['senha'] ?? '');
    $senha2 = (string)($_POST['senha2'] ?? '');

    if (str_len($senha) < 6) {
      $_SESSION['flash_erro'] = "A senha deve ter no mínimo 6 caracteres.";
      header("Location: ./redefinirSenhaNova.php");
      exit;
    }
    if ($senha !== $senha2) {
      $_SESSION['flash_erro'] = "As senhas não conferem.";
      header("Location: ./redefinirSenhaNova.php");
      exit;
    }

    /* Conexão */
    $candidatos = [
      __DIR__ . '/assets/php/conexao.php',
      dirname(__DIR__) . '/assets/php/conexao.php',
      dirname(__DIR__, 2) . '/assets/php/conexao.php',
    ];
    $conPath = '';
    foreach ($candidatos as $p) { if (is_file($p)) { $conPath = $p; break; } }
    if ($conPath === '') throw new RuntimeException("Não encontrei conexao.php.");

    require_once $conPath;
    if (!function_exists('db')) throw new RuntimeException("Função db() não existe no conexao.php");
    $pdo = db();
    if (!($pdo instanceof PDO)) throw new RuntimeException("db() não retornou PDO");

    /* Confere se o usuário ainda existe e está ativo */
    $st = $pdo->prepare("
      SELECT id
      FROM usuarios
      WHERE ativo = 1 AND LOWER(email) = LOWER(:email)
      LIMIT 1
    ");
    $st->execute([':email' => $emailOk]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
      $_SESSION['flash_erro'] = "Conta não encontrada ou inativa.";
      header("Location: ./redefinirSenha.php");
      exit;
    }

    $uid = (int)$u['id'];
    $hash = password_hash($senha, PASSWORD_DEFAULT);

    /* Atualiza senha */
    $upd = $pdo->prepare("
      UPDATE usuarios
      SET senha_hash = :hash, atualizado_em = NOW()
      WHERE id = :id
      LIMIT 1
    ");
    $upd->execute([':hash' => $hash, ':id' => $uid]);

    /* Limpa sessão do reset */
    unset($_SESSION['redef_ok_email'], $_SESSION['redef_email']);

    $_SESSION['flash_ok'] = "Senha alterada com sucesso! Faça login novamente.";
    header("Location: ./index.php");
    exit;

  } catch (Throwable $e) {
    error_log("ERRO redefinirSenhaNova.php: " . $e->getMessage());
    $_SESSION['flash_erro'] = "Erro ao salvar a nova senha. Tente novamente.";
    header("Location: ./redefinirSenhaNova.php");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Nova senha - SIGRelatórios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <link rel="stylesheet" href="./vendors/feather/feather.css">
  <link rel="stylesheet" href="./vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="./vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="./css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="./images/3.png" />

  <style>
    body{ background:#f5f7fb; }
    .auth-card{ border-radius:18px; box-shadow:0 10px 30px rgba(0,0,0,.08); }
    .form-control{ height:46px; border-radius:12px; }
    .btn-primary{ border-radius:12px; height:46px; font-weight:700; }
    .brand-logo img{ max-height:48px; }
    .hint{ font-size:12px; opacity:.75; }
  </style>
</head>
<body>
<div class="container-scroller">
  <div class="container-fluid page-body-wrapper full-page-wrapper">
    <div class="content-wrapper d-flex align-items-center auth px-0">
      <div class="row w-100 mx-0">
        <div class="col-lg-4 mx-auto">

          <div class="auth-form-light text-left py-5 px-4 px-sm-5 auth-card">
            <div class="brand-logo text-center mb-3">
             
            </div>

            <h4 class="font-weight-bold text-center mb-1">Definir nova senha</h4>
            <p class="text-muted text-center mb-4">
              E-mail confirmado: <b><?= h($emailOk) ?></b><br>
              Crie sua nova senha abaixo.
            </p>

            <?php if ($erro): ?>
              <div class="alert alert-danger"><?= h($erro) ?></div>
            <?php endif; ?>
            <?php if ($ok): ?>
              <div class="alert alert-success"><?= h($ok) ?></div>
            <?php endif; ?>

            <form method="post" action="./redefinirSenhaNova.php" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

              <div class="form-group">
                <label class="font-weight-semibold">Nova senha</label>
                <input type="password" name="senha" class="form-control" minlength="6" required>
                <div class="hint">Mínimo 6 caracteres.</div>
              </div>

              <div class="form-group">
                <label class="font-weight-semibold">Confirmar senha</label>
                <input type="password" name="senha2" class="form-control" minlength="6" required>
              </div>

              <button class="btn btn-primary btn-block" type="submit">
                <i class="ti-check mr-1"></i> Salvar nova senha
              </button>

              <div class="text-center mt-4 small">
                <a href="./index.php" class="text-muted">Voltar para o login</a>
              </div>
            </form>

          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<script src="./vendors/js/vendor.bundle.base.js"></script>
<script src="./js/off-canvas.js"></script>
<script src="./js/hoverable-collapse.js"></script>
<script src="./js/template.js"></script>
<script src="./js/settings.js"></script>
<script src="./js/todolist.js"></script>
</body>
</html>
