<?php
declare(strict_types=1);

/* =========================================================
   enviarRedefinirSenha.php
   - Procura usuário por email EXATO ou nome (LIKE)
   - Gera token + código e salva em redefinir_senha_tokens
   - Envia e-mail via mail() usando noreply@lucascorrea.pro
   - Log: php_error.log (na pasta deste arquivo)
   ========================================================= */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_error.log');

session_start();

$index   = '../../redefinirSenha.php';
$confirm = '../../redefinirSenhaConfirmar.php';

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

/* Conexão */
try {
  require __DIR__ . '/../../assets/php/conexao.php';
  if (!function_exists('db')) throw new RuntimeException("db() não existe");
  $pdo = db();
} catch (Throwable $e) {
  error_log("ERRO enviarRedefinirSenha (db): " . $e->getMessage());
  $_SESSION['flash_erro'] = "Erro interno. Tente novamente.";
  header("Location: {$index}");
  exit;
}

/* Config e-mail */
$FROM_NAME  = "SIGRelatórios";
$FROM_EMAIL = "noreply@lucascorrea.pro";

$APP_URL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
         . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$respostaOk = "Se existir uma conta ativa, enviaremos as instruções para o e-mail cadastrado.";

try {
  /* Busca por e-mail ou nome */
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
    ':nomeLike' => '%' . $login . '%',
  ]);
  $user = $st->fetch();

  /* Não revela se existe */
  if (!$user) {
    $_SESSION['flash_ok'] = $respostaOk;
    header("Location: {$confirm}");
    exit;
  }

  /* Token + código */
  $rawToken  = bin2hex(random_bytes(32));
  $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
  $codigo    = (string)random_int(100000, 999999);
  $expiraEm  = date('Y-m-d H:i:s', time() + 1800); // 30 min

  /* Invalida tokens anteriores (opcional) */
  $pdo->prepare("
    UPDATE redefinir_senha_tokens
    SET usado_em = NOW()
    WHERE email = :email AND usado_em IS NULL
  ")->execute([':email' => (string)$user['email']]);

  /* Insere */
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

  $assunto = "Redefinição de senha - SIGRelatórios";
  $mensagem =
    "Olá, " . (string)$user['nome'] . "!\n\n" .
    "Recebemos um pedido para redefinir sua senha.\n\n" .
    "Código: {$codigo}\n" .
    "Link: {$link}\n\n" .
    "Esse código/link expira em 30 minutos.\n" .
    "Se você não solicitou, ignore este e-mail.\n\n" .
    "SIGRelatórios";

  $headers = "From: {$FROM_NAME} <{$FROM_EMAIL}>\r\n" .
             "Reply-To: {$FROM_EMAIL}\r\n" .
             "Content-Type: text/plain; charset=UTF-8\r\n";

  $sent = @mail((string)$user['email'], $assunto, $mensagem, $headers);
  if (!$sent) {
    error_log("mail() falhou ao enviar para: " . (string)$user['email']);
  }

  $_SESSION['flash_ok'] = $respostaOk;
  header("Location: {$confirm}");
  exit;

} catch (Throwable $e) {
  error_log("ERRO enviarRedefinirSenha (process): " . $e->getMessage());
  $_SESSION['flash_erro'] = "Erro ao processar. Tente novamente.";
  header("Location: {$index}");
  exit;
}
