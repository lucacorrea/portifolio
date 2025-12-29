<?php
declare(strict_types=1);
session_start();

$index   = '../../redefinirSenha.php';
$confirm = '../../redefinirSenhaConfirmar.php';

/* LOG (ajuda muito em hospedagem) */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_error.log');

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

/* ===== INPUT ===== */
$login = trim((string)($_POST['login'] ?? ''));

$len = function_exists('mb_strlen') ? mb_strlen($login) : strlen($login);
if ($login === '' || $len < 3) {
  $_SESSION['flash_erro'] = "Informe um e-mail ou nome válido.";
  header("Location: {$index}");
  exit;
}

/* ===== CONFIG E-MAIL / URL ===== */
$FROM_NAME  = "SIGRelatórios";
$FROM_EMAIL = "noreply@lucascorrea.pro";

$https = (
  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);
$APP_URL = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');

try {
  /* 1) Confere se tabela existe (se não existir, já sabemos o motivo) */
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'redefinir_senha_tokens'
  ");
  $st->execute();
  if ((int)$st->fetchColumn() <= 0) {
    throw new RuntimeException("Tabela redefinir_senha_tokens não existe. Crie a tabela no banco.");
  }

  /* 2) Procura usuário ativo por email exato OU nome LIKE */
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

  /* Resposta padrão (não revela se existe) */
  $respostaOk = "Se existir uma conta ativa, enviaremos as instruções para o e-mail cadastrado.";

  if (!$user) {
    $_SESSION['flash_ok'] = $respostaOk;
    header("Location: {$confirm}");
    exit;
  }

  /* 3) Gera token e código */
  $rawToken  = bin2hex(random_bytes(32)); // 64 chars
  $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);

  $codigo   = (string)random_int(100000, 999999);
  $expiraEm = date('Y-m-d H:i:s', time() + 60 * 30); // 30 min

  /* 4) Invalida tokens anteriores não usados */
  $pdo->prepare("
    UPDATE redefinir_senha_tokens
    SET usado_em = NOW()
    WHERE email = :email AND usado_em IS NULL
  ")->execute([':email' => (string)$user['email']]);

  /* 5) Insere token */
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

  /* 6) Monta e-mail */
  $assunto = "Redefinição de senha - SIGRelatórios";
  $mensagem =
    "Olá, {$user['nome']}!\n\n" .
    "Recebemos um pedido para redefinir sua senha.\n\n" .
    "Código: {$codigo}\n" .
    "Link: {$link}\n\n" .
    "Esse código/link expira em 30 minutos.\n" .
    "Se você não solicitou, ignore este e-mail.\n\n" .
    "SIGRelatórios";

  $headers =
    "From: {$FROM_NAME} <{$FROM_EMAIL}>\r\n" .
    "Reply-To: {$FROM_EMAIL}\r\n" .
    "MIME-Version: 1.0\r\n" .
    "Content-Type: text/plain; charset=UTF-8\r\n";

  /* 7) Envia (se falhar, não quebra fluxo) */
  $sent = @mail((string)$user['email'], $assunto, $mensagem, $headers);
  if (!$sent) {
    error_log("AVISO enviarRedefinirSenha: mail() retornou false (servidor pode não estar com envio configurado). Email destino: {$user['email']}");
  }

  $_SESSION['redef_email'] = (string)$user['email'];
  $_SESSION['flash_ok'] = $respostaOk;
  header("Location: {$confirm}");
  exit;

} catch (Throwable $e) {
  error_log("ERRO enviarRedefinirSenha (query): " . $e->getMessage());
  $_SESSION['flash_erro'] = "Erro ao processar. Tente novamente.";
  header("Location: {$index}");
  exit;
}
