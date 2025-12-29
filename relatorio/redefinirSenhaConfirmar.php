<?php
declare(strict_types=1);

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

function only_digits(string $s): string {
  return preg_replace('/\D+/', '', $s) ?? '';
}

/* ===== Flash ===== */
$erro = (string)($_SESSION['flash_erro'] ?? '');
$ok   = (string)($_SESSION['flash_ok'] ?? '');
unset($_SESSION['flash_erro'], $_SESSION['flash_ok']);

/* Email vindo da etapa anterior (UX) */
$emailSess = (string)($_SESSION['redef_email'] ?? '');

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/* =========================================================
   PROCESSAMENTO (POST): valida o código
   ========================================================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  try {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) throw new RuntimeException("CSRF inválido.");

    $email = trim((string)($_POST['email'] ?? $emailSess));
    $codigo = only_digits((string)($_POST['codigo'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $_SESSION['flash_erro'] = "Informe um e-mail válido.";
      header("Location: ./redefinirSenhaConfirmar.php");
      exit;
    }
    if ($codigo === '' || strlen($codigo) < 4) {
      $_SESSION['flash_erro'] = "Informe o código recebido por e-mail.";
      header("Location: ./redefinirSenhaConfirmar.php");
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

    /* Busca token válido */
    $st = $pdo->prepare("
      SELECT id, usuario_id, email, expira_em
      FROM redefinir_senha_tokens
      WHERE LOWER(email) = LOWER(:email)
        AND codigo = :codigo
        AND usado_em IS NULL
        AND expira_em >= NOW()
      ORDER BY id DESC
      LIMIT 1
    ");
    $st->execute([
      ':email'  => $email,
      ':codigo' => $codigo,
    ]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $_SESSION['flash_erro'] = "Código inválido ou expirado. Solicite um novo código.";
      header("Location: ./redefinirSenhaConfirmar.php");
      exit;
    }

    /* Marca como usado */
    $upd = $pdo->prepare("UPDATE redefinir_senha_tokens SET usado_em = NOW() WHERE id = :id");
    $upd->execute([':id' => (int)$row['id']]);

    /* Libera próxima etapa */
    $_SESSION['redef_ok_email'] = (string)$email;
    $_SESSION['flash_ok'] = "Código confirmado! Agora defina sua nova senha.";
    header("Location: ./redefinirSenhaNova.php");
    exit;

  } catch (Throwable $e) {
    error_log("ERRO redefinirSenhaConfirmar.php: " . $e->getMessage());
    $_SESSION['flash_erro'] = "Erro ao validar o código. Tente novamente.";
    header("Location: ./redefinirSenhaConfirmar.php");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Confirmar código - SIGRelatórios</title>
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
    .hint{ font-size:12px; opacity:.8; }
    .code-input{ font-size:20px; letter-spacing:6px; text-align:center; font-weight:800; }
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
              <img src="./images/3.png" alt="SIGRelatórios">
            </div>

            <h4 class="font-weight-bold text-center mb-1">Digite o código</h4>
            <p class="text-muted text-center mb-4">
              Enviamos um código para o seu e-mail. Digite abaixo para continuar.
            </p>

            <?php if ($erro): ?>
              <div class="alert alert-danger"><?= h($erro) ?></div>
            <?php endif; ?>
            <?php if ($ok): ?>
              <div class="alert alert-success"><?= h($ok) ?></div>
            <?php endif; ?>

            <form method="post" action="./redefinirSenhaConfirmar.php" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

              <div class="form-group">
                <label class="font-weight-semibold">E-mail</label>
                <input
                  type="email"
                  name="email"
                  class="form-control"
                  value="<?= h($emailSess) ?>"
                  placeholder="Digite seu e-mail"
                  required
                >
                <div class="hint mt-1">Use o mesmo e-mail que você informou na etapa anterior.</div>
              </div>

              <div class="form-group">
                <label class="font-weight-semibold">Código</label>
                <input
                  type="text"
                  name="codigo"
                  class="form-control code-input"
                  placeholder="000000"
                  inputmode="numeric"
                  maxlength="10"
                  required
                >
                <div class="hint mt-1">Dica: procure no spam/lixo eletrônico.</div>
              </div>

              <div class="mt-4">
                <button class="btn btn-primary btn-block" type="submit">
                  <i class="ti-check mr-1"></i> Confirmar código
                </button>
              </div>

              <div class="text-center mt-4 small">
                <a href="./redefinirSenha.php" class="text-muted">Não recebi o código • enviar novamente</a>
              </div>

              <div class="text-center mt-3 small">
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
