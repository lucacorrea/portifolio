<?php
declare(strict_types=1);
session_start();

$index   = '../../redefinirSenha.php';
$confirm = '../../redefinirSenhaConfirmar.php';

/* =========================
   CONEXÃO (db(): PDO)
   ========================= */
try {
  require __DIR__ . '/../../assets/php/conexao.php';
  if (!function_exists('db')) throw new RuntimeException("db() não existe");
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

/* =========================
   INPUT (aceita nome OU email)
   ========================= */
$login = trim((string)($_POST['login'] ?? ''));
if ($login === '' || mb_strlen($login) < 3) {
  $_SESSION['flash_erro'] = "Informe um e-mail ou nome válido.";
  header("Location: {$index}");
  exit;
}

/* =========================
   CONFIG E-MAIL / URL
   ========================= */
$FROM_NAME  = "SIGRelatórios";
$FROM_EMAIL = "noreply@lucascorrea.pro";

/* Detecta URL base do app (respeita reverse proxy também) */
$https = (
  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);
$APP_URL = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');

/* =========================
   PROCESSO
   ========================= */
try {
  // Procura por email EXATO ou nome (like) - somente ativos
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
    ':login'    => $login,
    ':nomeLike' => '%' . $login . '%',
  ]);
  $user = $st->fetch(PDO::FETCH_ASSOC) ?: null;

  // Resposta padrão (não revela se existe)
  $respostaOk = "Se existir uma conta ativa, enviaremos as instruções para o e-mail cadastrado.";

  if (!$user) {
    $_SESSION['flash_ok'] = $respostaOk;
    header("Location: {$confirm}");
    exit;
  }

  // Token forte + código curto
  $rawToken  = bin2hex(random_bytes(32));             // 64 chars
  $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);

  $codigo   = (string)random_int(100000, 999999);     // 6 dígitos
  $expiraEm = date('Y-m-d H:i:s', time() + 60 * 30);  // 30 minutos

  // Invalida tokens anteriores não usados (opcional)
  $pdo->prepare("
    UPDATE redefinir_senha_tokens
    SET usado_em = NOW()
    WHERE email = :email AND usado_em IS NULL
  ")->execute([':email' => (string)$user['email']]);

  // Salva token
  $ins = $pdo->prepare("
    INSERT INTO redefinir_senha_tokens (usuario_id, email, token_hash, codigo, expira_em)
    VALUES (:uid, :email, :hash, :codigo, :expira)
  ");
  $ins->execute([
    ':uid'    => (int)$user['id'],
    ':email'  => (string)$user['email'],
    ':hash'   => $tokenHash,
    ':codigo' => $codigo,
    ':expira' => $expiraEm,
  ]);

  $link = $APP_URL . "/redefinirSenhaNova.php?token=" . urlencode($rawToken);

  // Conteúdo do e-mail
  $assunto = "Redefinição de senha - SIGRelatórios";
  $mensagem =
    "Olá, {$user['nome']}!\n\n" .
    "Recebemos um pedido para redefinir sua senha.\n\n" .
    "Código: {$codigo}\n" .
    "Link: {$link}\n\n" .
    "Esse código/link expira em 30 minutos.\n" .
    "Se você não solicitou, ignore este e-mail.\n\n" .
    "SIGRelatórios";

  // Envio (mail)
  $headers =
    "From: {$FROM_NAME} <{$FROM_EMAIL}>\r\n" .
    "Reply-To: {$FROM_EMAIL}\r\n" .
    "MIME-Version: 1.0\r\n" .
    "Content-Type: text/plain; charset=UTF-8\r\n";

  @mail((string)$user['email'], $assunto, $mensagem, $headers);

  // Guarda e-mail (para a página confirmar)
  $_SESSION['redef_email'] = (string)$user['email'];

  // Resposta genérica
  $_SESSION['flash_ok'] = $respostaOk;
  header("Location: {$confirm}");
  exit;

} catch (Throwable $e) {
  error_log("ERRO enviarRedefinirSenha (query): " . $e->getMessage());
  $_SESSION['flash_erro'] = "Erro ao processar. Tente novamente.";
  header("Location: {$index}");
  exit;
}
