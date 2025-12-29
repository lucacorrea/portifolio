<?php
declare(strict_types=1);

/* =========================================================
   redefinirSenha.php  (TUDO AQUI DENTRO)
   - Form + Processamento (POST) + envio de e-mail
   - Log em: php_error.log (na mesma pasta deste arquivo)
   - Usa sua conexão padrão: require ./assets/php/conexao.php -> db():PDO
   - Tabela: redefinir_senha_tokens (email, codigo, token_hash, expira_em, usado_em, criado_em, usuario_id nullable)
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

function app_url(): string {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host;
}

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

    /* Entrada */
    $login = trim((string)($_POST['login'] ?? ''));
    if ($login === '' || str_len($login) < 3) {
      $_SESSION['flash_erro'] = "Informe um e-mail ou nome válido.";
      header("Location: ./redefinirSenha.php");
      exit;
    }

    /* Conexão (tenta caminhos comuns) */
    $candidatos = [
      __DIR__ . '/assets/php/conexao.php',             // quando está na raiz
      dirname(__DIR__) . '/assets/php/conexao.php',    // quando está 1 pasta abaixo
      dirname(__DIR__, 2) . '/assets/php/conexao.php', // quando está 2 pastas abaixo
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
    $BASE_URL   = app_url();

    /* mensagem padrão (não revela se existe) */
    $respostaOk = "Se existir uma conta ativa, enviaremos as instruções para o e-mail cadastrado.";

    /* Procura usuário (email exato OU nome parecido) */
    $st = $pdo->prepare("
      SELECT id, nome, email, ativo
      FROM usuarios
      WHERE ativo = 1
        AND (
          LOWER(email) = LOWER(:login)
          OR LOWER(nome) LIKE LOWER(:nomeLike)
        )
      ORDER BY (LOWER(email)=LOWER(:login)) DESC, id DESC
      LIMIT 1
    ");
    $st->execute([
      ':login'    => $login,
      ':nomeLike' => '%'.$login.'%',
    ]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    /* Se não encontrou, retorna OK do mesmo jeito */
    if (!$user) {
      $_SESSION['flash_ok'] = $respostaOk;
      header("Location: ./redefinirSenhaConfirmar.php");
      exit;
    }

    $uid       = isset($user['id']) ? (int)$user['id'] : null; // sua tabela aceita NULL
    $emailUser = (string)($user['email'] ?? '');
    $nomeUser  = (string)($user['nome'] ?? 'Usuário');

    if ($emailUser === '' || str_len($emailUser) < 5) {
      // não revela detalhe
      $_SESSION['flash_ok'] = $respostaOk;
      header("Location: ./redefinirSenhaConfirmar.php");
      exit;
    }

    /* Gera token forte + código */
    $rawToken  = bin2hex(random_bytes(32));               // 64 chars
    $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
    $codigo    = (string)random_int(100000, 999999);      // 6 dígitos (cabe no varchar(10))
    $expiraEm  = date('Y-m-d H:i:s', time() + (60 * 30));  // 30 min

    /* Invalida tokens anteriores não usados (por email) */
    $upd = $pdo->prepare("
      UPDATE redefinir_senha_tokens
      SET usado_em = NOW()
      WHERE email = :email AND usado_em IS NULL
    ");
    $upd->execute([':email' => $emailUser]);

    /* Salva o novo token (usuario_id pode ser NULL) */
    $ins = $pdo->prepare("
      INSERT INTO redefinir_senha_tokens (usuario_id, email, token_hash, codigo, expira_em)
      VALUES (:uid, :email, :hash, :codigo, :expira)
    ");
    if ($uid === null) {
      $ins->bindValue(':uid', null, PDO::PARAM_NULL);
    } else {
      $ins->bindValue(':uid', $uid, PDO::PARAM_INT);
    }
    $ins->bindValue(':email',  $emailUser, PDO::PARAM_STR);
    $ins->bindValue(':hash',   $tokenHash, PDO::PARAM_STR);
    $ins->bindValue(':codigo', $codigo, PDO::PARAM_STR);
    $ins->bindValue(':expira', $expiraEm, PDO::PARAM_STR);
    $ins->execute();

    /* Link (página que recebe token e define nova senha) */
    $link = $BASE_URL . "/redefinirSenhaNova.php?token=" . urlencode($rawToken);

    /* E-mail (texto simples) */
    $assunto = "Redefinição de senha - SIGRelatórios";
    $mensagem =
      "Olá, {$nomeUser}!\n\n" .
      "Recebemos um pedido para redefinir sua senha.\n\n" .
      "Código: {$codigo}\n" .
      "Link: {$link}\n\n" .
      "Esse código/link expira em 30 minutos.\n" .
      "Se você não solicitou, ignore este e-mail.\n\n" .
      "SIGRelatórios\n";

    $headers =
      "From: {$FROM_NAME} <{$FROM_EMAIL}>\r\n" .
      "Reply-To: {$FROM_EMAIL}\r\n" .
      "Content-Type: text/plain; charset=UTF-8\r\n";

    // tenta enviar (se falhar, não quebra o fluxo)
    $sent = @mail($emailUser, $assunto, $mensagem, $headers);
    if (!$sent) {
      error_log("AVISO redefinirSenha: mail() falhou para {$emailUser} | IP=" . client_ip());
    }

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
              Informe seu <b>e-mail</b> (ou <b>nome</b>) para receber um link/código de redefinição.
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
                <label class="font-weight-semibold">E-mail ou nome</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="ti-email"></i></span>
                  </div>
                  <input
                    type="text"
                    name="login"
                    class="form-control"
                    placeholder="Digite seu e-mail ou nome"
                    required
                  >
                </div>
                <div class="hint">Se o usuário existir e estiver ativo, enviaremos as instruções.</div>
              </div>

              <div class="mt-4">
                <button class="btn btn-primary btn-block" type="submit">
                  <i class="ti-arrow-right mr-1"></i> Enviar instruções
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
<script src="./js/hoverable-collapse.js"></script>
<script src="./js/template.js"></script>
<script src="./js/settings.js"></script>
<script src="./js/todolist.js"></script>
</body>
</html>