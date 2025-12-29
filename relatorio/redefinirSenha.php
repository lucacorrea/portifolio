<?php
declare(strict_types=1);
session_start();

/* =========================
   ERROR LOG (arquivo local)
   ========================= */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_error.log');

/* Flash */
$erro  = (string)($_SESSION['flash_erro'] ?? '');
$ok    = (string)($_SESSION['flash_ok'] ?? '');
unset($_SESSION['flash_erro'], $_SESSION['flash_ok']);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* =========================
   PROCESSAR POST AQUI
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $login = trim((string)($_POST['login'] ?? ''));

  if ($login === '' || mb_strlen($login) < 3) {
    $_SESSION['flash_erro'] = "Informe um e-mail ou nome válido.";
    header("Location: ./redefinirSenha.php");
    exit;
  }

  // Resposta padrão (não revela se existe)
  $respostaOk = "Se existir uma conta ativa, enviaremos as instruções para o e-mail cadastrado.";

  // App URL base
  $APP_URL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
           . ($_SERVER['HTTP_HOST'] ?? 'localhost');

  // Config e-mail (EXATAMENTE como você pediu)
  $FROM_NAME  = "SIGRelatórios";
  $FROM_EMAIL = "noreply@lucascorrea.pro";

  try {
    require __DIR__ . '/assets/php/conexao.php';
    if (!function_exists('db')) {
      throw new RuntimeException("Função db() não encontrada em conexao.php");
    }
    $pdo = db();

    // Procura por email EXATO ou nome LIKE (somente ativos)
    $st = $pdo->prepare("
      SELECT id, nome, email, ativo
      FROM usuarios
      WHERE ativo = 1 AND (
        LOWER(email) = LOWER(:login)
        OR LOWER(nome) LIKE LOWER(:nomeLike)
      )
      ORDER BY (LOWER(email)=LOWER(:login)) DESC, id DESC
      LIMIT 1
    ");
    $st->execute([
      ':login' => $login,
      ':nomeLike' => '%'.$login.'%',
    ]);
    $user = $st->fetch();

    // Se não achar, responde igual
    if (!$user) {
      $_SESSION['flash_ok'] = $respostaOk;
      header("Location: ./redefinirSenhaConfirmar.php");
      exit;
    }

    // Token forte + código curto
    $rawToken  = bin2hex(random_bytes(32));      // 64 chars
    $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);

    $codigo   = (string)random_int(100000, 999999);
    $expiraEm = date('Y-m-d H:i:s', time() + (60 * 30)); // 30 minutos

    // Invalida tokens anteriores não usados
    $pdo->prepare("
      UPDATE redefinir_senha_tokens
      SET usado_em = NOW()
      WHERE email = :email AND usado_em IS NULL
    ")->execute([':email' => (string)$user['email']]);

    // Insere token
    $ins = $pdo->prepare("
      INSERT INTO redefinir_senha_tokens (usuario_id, email, token_hash, codigo, expira_em, criado_em)
      VALUES (:uid, :email, :hash, :codigo, :expira, NOW())
    ");
    $ins->execute([
      ':uid'    => (int)$user['id'],
      ':email'  => (string)$user['email'],
      ':hash'   => $tokenHash,
      ':codigo' => $codigo,
      ':expira' => $expiraEm,
    ]);

    $link = $APP_URL . "/redefinirSenhaNova.php?token=" . urlencode($rawToken);

    // Mensagem
    $assunto  = "Redefinição de senha - SIGRelatórios";
    $mensagem = "Olá, {$user['nome']}!\n\n"
              . "Recebemos um pedido para redefinir sua senha.\n\n"
              . "Código: {$codigo}\n"
              . "Link: {$link}\n\n"
              . "Esse código/link expira em 30 minutos.\n"
              . "Se você não solicitou, ignore este e-mail.\n\n"
              . "SIGRelatórios";

    $headers = "From: {$FROM_NAME} <{$FROM_EMAIL}>\r\n"
             . "Reply-To: {$FROM_EMAIL}\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    // Tenta enviar
    @mail((string)$user['email'], $assunto, $mensagem, $headers);

    // Guarda e-mail para a próxima página (opcional)
    $_SESSION['redef_email'] = (string)$user['email'];

    $_SESSION['flash_ok'] = $respostaOk;
    header("Location: ./redefinirSenhaConfirmar.php");
    exit;

  } catch (Throwable $e) {
    error_log("ERRO redefinirSenha.php (processo): " . $e->getMessage());
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

            <?php if (!empty($erro)): ?>
              <div class="alert alert-danger"><?= h($erro) ?></div>
            <?php endif; ?>
            <?php if (!empty($ok)): ?>
              <div class="alert alert-success"><?= h($ok) ?></div>
            <?php endif; ?>

            <form method="post" action="./redefinirSenha.php" autocomplete="off">
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
                <button class="btn btn-primary btn-block">
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
<script src="./js/template.js"></script>
<script src="./js/settings.js"></script>
<script src="./js/todolist.js"></script>
</body>
</html>
