<?php
declare(strict_types=1);

/* =========================
   LOG de ERROS (php_error.log na pasta deste arquivo)
   ========================= */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_error.log');

session_start();

$index   = '../../redefinirSenha.php';
$confirm = '../../redefinirSenhaConfirmar.php';

/* =========================
   CONEXÃO (db(): PDO)
   ========================= */
try {
  require __DIR__ . '/../../assets/php/conexao.php';
  if (!function_exists('db')) throw new RuntimeException("db() não existe em conexao.php");
  $pdo = db();
  if (!($pdo instanceof PDO)) throw new RuntimeException("db() não retornou PDO");
} catch (Throwable $e) {
  error_log("ERRO enviarRedefinirSenha (db): " . $e->getMessage());
  $_SESSION['flash_erro'] = "Erro interno. Tente novamente.";
  header("Location: {$index}");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: {$index}");
  exit;
}

$login = trim((string)($_POST['login'] ?? ''));
if ($login === '' || mb_strlen($login) < 3) {
  $_SESSION['flash_erro'] = "Informe um e-mail ou nome válido.";
  header("Location: {$index}");
  exit;
}

/* =========================
   Config e-mail
   ========================= */
$FROM_NAME  = "SIGRelatórios";
$FROM_EMAIL = "noreply@lucascorrea.pro"; // como você pediu

$APP_URL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
         . ($_SERVER['HTTP_HOST'] ?? 'localhost');

/* Resposta padrão (não revela se existe usuário) */
$respostaOk = "Se existir uma conta ativa, enviaremos as instruções para o e-mail cadastrado.";

try {
  /* =========================
     Busca usuário (email exato OU nome parecido) somente ativos
     ========================= */
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
    ':nomeLike' => '%' . $login . '%',
  ]);
  $user = $st->fetch(PDO::FETCH_ASSOC);

  /* Sempre responde ok */
  if (!$user) {
    $_SESSION['flash_ok'] = $respostaOk;
    header("Location: {$confirm}");
    exit;
  }

  /* =========================
     Cria token forte
     - rawToken vai no link
     - token_hash vai no banco
     ========================= */
  $rawToken  = bin2hex(random_bytes(32)); // 64 chars
  $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
  $expiraEm  = date('Y-m-d H:i:s', time() + (60 * 30)); // 30 minutos

  $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
  $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
  if (mb_strlen($ua) > 255) $ua = mb_substr($ua, 0, 255, 'UTF-8');

  /* =========================
     Invalida tokens anteriores NÃO usados (opcional)
     ========================= */
  $pdo->prepare("
    UPDATE password_resets
    SET usado_em = NOW()
    WHERE usuario_id = :uid AND usado_em IS NULL
  ")->execute([':uid' => (int)$user['id']]);

  /* =========================
     Salva em password_resets (sem FK)
     ========================= */
  $ins = $pdo->prepare("
    INSERT INTO password_resets
      (usuario_id, token_hash, expira_em, usado_em, ip_solicitacao, user_agent)
    VALUES
      (:uid, :hash, :expira, NULL, :ip, :ua)
  ");
  $ins->execute([
    ':uid'    => (int)$user['id'],
    ':hash'   => $tokenHash,
    ':expira' => $expiraEm,
    ':ip'     => $ip !== '' ? $ip : null,
    ':ua'     => $ua !== '' ? $ua : null,
  ]);

  /* Link */
  $link = $APP_URL . "/redefinirSenhaNova.php?token=" . urlencode($rawToken);

  /* =========================
     E-mail (texto simples)
     ========================= */
  $assunto = "Redefinição de senha - SIGRelatórios";

  $mensagem =
    "Olá, " . ($user['nome'] ?? 'usuário') . "!\n\n" .
    "Recebemos um pedido para redefinir sua senha.\n\n" .
    "Para criar uma nova senha, acesse o link abaixo:\n" .
    $link . "\n\n" .
    "Esse link expira em 30 minutos.\n" .
    "Se você não solicitou, ignore este e-mail.\n\n" .
    "SIGRelatórios\n";

  $headers =
    "From: {$FROM_NAME} <{$FROM_EMAIL}>\r\n" .
    "Reply-To: {$FROM_EMAIL}\r\n" .
    "Content-Type: text/plain; charset=UTF-8\r\n";

  /* Envia e loga se falhar */
  $sent = @mail((string)$user['email'], $assunto, $mensagem, $headers);
  if (!$sent) {
    error_log("MAIL FALHOU para: " . (string)$user['email'] . " | token_link: " . $link);
  }

  $_SESSION['redef_email'] = (string)$user['email'];
  $_SESSION['flash_ok']    = $respostaOk;

  header("Location: {$confirm}");
  exit;

} catch (Throwable $e) {
  error_log("ERRO enviarRedefinirSenha (query): " . $e->getMessage());
  $_SESSION['flash_erro'] = "Erro ao processar. Tente novamente.";
  header("Location: {$index}");
  exit;
}
