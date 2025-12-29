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

function str_len(string $s): int {
  return function_exists('mb_strlen') ? (int)mb_strlen($s, 'UTF-8') : (int)strlen($s);
}

function client_ip(): string {
  $keys = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
  foreach ($keys as $k) {
    if (!empty($_SERVER[$k])) {
      $v = (string)$_SERVER[$k];
      if ($k === 'HTTP_X_FORWARDED_FOR') {
        $parts = explode(',', $v);
        $v = trim($parts[0] ?? $v);
      }
      return substr($v, 0, 64);
    }
  }
  return '0.0.0.0';
}

/* ===== Flash ===== */
$erro = (string)($_SESSION['flash_erro'] ?? '');
$ok   = (string)($_SESSION['flash_ok'] ?? '');
unset($_SESSION['flash_erro'], $_SESSION['flash_ok']);

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/* =========================================================
   PROCESSAMENTO (POST)
   ========================================================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  try {
    /* CSRF */
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
      throw new RuntimeException("CSRF inválido.");
    }

    /* Entrada: SOMENTE EMAIL */
    $email = trim((string)($_POST['login'] ?? ''));

    if ($email === '' || str_len($email) < 6 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $_SESSION['flash_erro'] = "Informe um e-mail válido.";
      header("Location: ./redefinirSenha.php");
      exit;
    }

    /* Conexão (tenta caminhos comuns) */
    $candidatos = [
      __DIR__ . '/assets/php/conexao.php',
      dirname(__DIR__) . '/assets/php/conexao.php',
      dirname(__DIR__, 2) . '/assets/php/conexao.php',
    ];

    $conPath = '';
    foreach ($candidatos as $p) {
      if (is_file($p)) { $conPath = $p; break; }
    }
    if ($conPath === '') {
      throw new RuntimeException("Não encontrei conexao.php. Testei: " . implode(' | ', $candidatos));
    }

    require_once $conPath;
    if (!function_exists('db')) {
      throw new RuntimeException("Função db() não existe no conexao.php");
    }

    $pdo = db();
    if (!($pdo instanceof PDO)) {
      throw new RuntimeException("db() não retornou PDO");
    }

    /* Config e-mail */
    $FROM_NAME  = "SIGRelatórios";
    $FROM_EMAIL = "noreply@lucascorrea.pro";

    /* mensagem padrão (não revela se existe) */
    $respostaOk = "Se existir uma conta ativa, enviaremos o código para o e-mail cadastrado.";

    /* Procura usuário: SOMENTE EMAIL (case-insensitive) */
    $st = $pdo->prepare("
      SELECT id, nome, email
      FROM usuarios
      WHERE ativo = 1
        AND LOWER(email) = LOWER(:email)
      LIMIT 1
    ");
    $st->execute([':email' => $email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    /* Se não encontrou, retorna OK do mesmo jeito */
    if (!$user) {
      $_SESSION['flash_ok'] = $respostaOk;
      header("Location: ./redefinirSenhaConfirmar.php");
      exit;
    }

    $uid       = isset($user['id']) ? (int)$user['id'] : null;
    $emailUser = (string)($user['email'] ?? '');
    $nomeUser  = (string)($user['nome'] ?? 'Usuário');

    if ($emailUser === '' || !filter_var($emailUser, FILTER_VALIDATE_EMAIL)) {
      $_SESSION['flash_ok'] = $respostaOk;
      header("Location: ./redefinirSenhaConfirmar.php");
      exit;
    }

    /* Gera token forte + código */
    $rawToken  = bin2hex(random_bytes(32));                 // guardado (token_hash), mas NÃO enviado por e-mail
    $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
    $codigo    = (string)random_int(100000, 999999);        // 6 dígitos
    $expiraEm  = date('Y-m-d H:i:s', time() + (60 * 30));    // 30 min
    $expiraMin = 30;

    /* Invalida tokens anteriores não usados (por email) */
    $upd = $pdo->prepare("
      UPDATE redefinir_senha_tokens
      SET usado_em = NOW()
      WHERE email = :email AND usado_em IS NULL
    ");
    $upd->execute([':email' => $emailUser]);

    /* Salva o novo token */
    $ins = $pdo->prepare("
      INSERT INTO redefinir_senha_tokens (usuario_id, email, token_hash, codigo, expira_em)
      VALUES (:uid, :email, :hash, :codigo, :expira)
    ");
    if ($uid === null) $ins->bindValue(':uid', null, PDO::PARAM_NULL);
    else              $ins->bindValue(':uid', $uid, PDO::PARAM_INT);

    $ins->bindValue(':email',  $emailUser, PDO::PARAM_STR);
    $ins->bindValue(':hash',   $tokenHash, PDO::PARAM_STR);
    $ins->bindValue(':codigo', $codigo, PDO::PARAM_STR);
    $ins->bindValue(':expira', $expiraEm, PDO::PARAM_STR);
    $ins->execute();

    /* ===== E-mail HTML “estilo Hostinger” (azul) — SOMENTE CÓDIGO ===== */
    $assunto = "Seu código de redefinição - SIGRelatórios";

    $ipInfo = client_ip();

    // Importante: em e-mail não use classes externas; usar estilo inline
    $html = '
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Código de redefinição</title>
</head>
<body style="margin:0;padding:0;background:#f2f6ff;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f6ff;padding:26px 0;">
    <tr>
      <td align="center">

        <!-- header -->
        <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:92vw;">
          <tr>
            <td style="padding:0 10px 14px 10px;text-align:left;">
              <div style="display:inline-block;padding:10px 14px;border-radius:14px;background:linear-gradient(135deg,#0b5fff,#2b8cff);color:#ffffff;font-weight:800;font-size:14px;letter-spacing:.2px;">
                SIGRelatórios
              </div>
            </td>
          </tr>
        </table>

        <!-- card -->
        <table role="presentation" width="640" cellpadding="0" cellspacing="0"
          style="width:640px;max-width:92vw;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 14px 36px rgba(11,95,255,.14);">
          <tr>
            <td style="padding:26px 26px 0 26px;">
              <div style="font-size:12px;color:#2563eb;font-weight:800;letter-spacing:.35px;text-transform:uppercase;">
                Redefinição de senha
              </div>
              <div style="margin-top:8px;font-size:20px;font-weight:900;color:#0f172a;line-height:1.25;">
                Use este código para confirmar
              </div>
              <div style="margin-top:10px;font-size:14px;color:#334155;line-height:1.6;">
                Olá, <b>'.h($nomeUser).'</b>!<br>
                Digite o código abaixo na tela de confirmação para continuar a redefinição da senha.
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:18px 26px 0 26px;">
              <div style="background:#f6f9ff;border:1px solid #dbeafe;border-radius:16px;padding:16px;text-align:center;">
                <div style="font-size:12px;color:#1e40af;font-weight:800;letter-spacing:.3px;text-transform:uppercase;">
                  Seu código
                </div>
                <div style="margin-top:10px;font-size:30px;font-weight:900;color:#0b5fff;letter-spacing:6px;">
                  '.h($codigo).'
                </div>
                <div style="margin-top:10px;font-size:12px;color:#64748b;line-height:1.5;">
                  Este código expira em <b>'.$expiraMin.' minutos</b>.
                </div>
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:18px 26px 0 26px;">
              <div style="font-size:12px;color:#64748b;line-height:1.6;">
                Se você não solicitou esta alteração, ignore este e-mail.
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:18px 26px 22px 26px;">
              <div style="border-top:1px solid #eef2ff;padding-top:14px;font-size:12px;color:#94a3b8;line-height:1.6;">
                IP da solicitação: '.h($ipInfo).'<br>
                © '.date('Y').' SIGRelatórios • Mensagem automática, não responda.
              </div>
            </td>
          </tr>
        </table>

        <!-- footer -->
        <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:92vw;">
          <tr>
            <td style="padding:14px 10px 0 10px;text-align:center;color:#94a3b8;font-size:12px;">
              Se precisar de ajuda, entre em contato com o suporte do SIGRelatórios.
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>
</body>
</html>
';

    $headers =
      "From: {$FROM_NAME} <{$FROM_EMAIL}>\r\n" .
      "Reply-To: {$FROM_EMAIL}\r\n" .
      "MIME-Version: 1.0\r\n" .
      "Content-Type: text/html; charset=UTF-8\r\n";

    /* tenta enviar (se falhar, não quebra o fluxo) */
    $sent = @mail($emailUser, $assunto, $html, $headers);
    if (!$sent) {
      error_log("AVISO redefinirSenha: mail() HTML falhou para {$emailUser} | IP=" . client_ip());

      // fallback texto (só código)
      $texto =
        "Olá, {$nomeUser}!\n\n".
        "Seu código de redefinição é: {$codigo}\n".
        "Ele expira em {$expiraMin} minutos.\n\n".
        "SIGRelatórios\n";

      $headersTxt =
        "From: {$FROM_NAME} <{$FROM_EMAIL}>\r\n" .
        "Reply-To: {$FROM_EMAIL}\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n";

      $sent2 = @mail($emailUser, $assunto, $texto, $headersTxt);
      if (!$sent2) error_log("AVISO redefinirSenha: mail() TEXTO falhou para {$emailUser}");
    }

    /* já passa para outra página */
    $_SESSION['redef_email'] = $emailUser;
    $_SESSION['flash_ok'] = $respostaOk;
    header("Location: ./redefinirSenhaConfirmar.php");
    exit;

  } catch (Throwable $e) {
    error_log("ERRO redefinirSenha.php: " . $e->getMessage());
    $_SESSION['flash_erro'] = "Erro ao processar. Tente novamente.";
    header("Location: ./redefinirSenha.php");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Redefinir senha - SIGRelatórios</title>
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
              <img src="./images/3.png" alt="SIGRelatórios">
            </div>

            <h4 class="font-weight-bold text-center mb-1">Esqueceu sua senha?</h4>
            <p class="text-muted text-center mb-4">
              Informe seu <b>e-mail</b> para receber o código de redefinição.
            </p>

            <?php if ($erro): ?>
              <div class="alert alert-danger"><?= h($erro) ?></div>
            <?php endif; ?>
            <?php if ($ok): ?>
              <div class="alert alert-success"><?= h($ok) ?></div>
            <?php endif; ?>

            <form method="post" action="./redefinirSenha.php" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

              <div class="form-group">
                <label class="font-weight-semibold">E-mail</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="ti-email"></i></span>
                  </div>
                  <input
                    type="email"
                    name="login"
                    class="form-control"
                    placeholder="Digite seu e-mail"
                    required
                  >
                </div>
                <div class="hint">Se o usuário existir e estiver ativo, enviaremos o código.</div>
              </div>

              <div class="mt-4">
                <button class="btn btn-primary btn-block" type="submit">
                  <i class="ti-arrow-right mr-1"></i> Enviar código
                </button>
              </div>

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
