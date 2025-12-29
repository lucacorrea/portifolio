<?php
declare(strict_types=1);

/* =========================================================
   resetarSenha.php
   - Valida token + código
   - Atualiza senha do usuário
   - Marca token como usado
   - Log: php_error.log (na pasta deste arquivo)
   ========================================================= */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_error.log');

session_start();

$nova = '../../redefinirSenhaNova.php';
$index = '../../index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: {$index}");
  exit;
}

$token = trim((string)($_POST['token'] ?? ''));
$codigo = trim((string)($_POST['codigo'] ?? ''));
$senha  = (string)($_POST['senha'] ?? '');
$senha2 = (string)($_POST['senha2'] ?? '');

if ($token === '' || strlen($token) < 20) {
  $_SESSION['flash_erro'] = "Link inválido.";
  header("Location: {$nova}");
  exit;
}
if ($codigo === '' || !preg_match('/^\d{6}$/', $codigo)) {
  $_SESSION['flash_erro'] = "Informe o código de 6 dígitos.";
  header("Location: {$nova}?token=" . urlencode($token));
  exit;
}
if (mb_strlen($senha) < 6) {
  $_SESSION['flash_erro'] = "A senha deve ter no mínimo 6 caracteres.";
  header("Location: {$nova}?token=" . urlencode($token));
  exit;
}
if ($senha !== $senha2) {
  $_SESSION['flash_erro'] = "As senhas não conferem.";
  header("Location: {$nova}?token=" . urlencode($token));
  exit;
}

/* Conexão */
try {
  require __DIR__ . '/../../assets/php/conexao.php';
  if (!function_exists('db')) throw new RuntimeException("db() não existe");
  $pdo = db();
} catch (Throwable $e) {
  error_log("ERRO resetarSenha (db): " . $e->getMessage());
  $_SESSION['flash_erro'] = "Erro interno. Tente novamente.";
  header("Location: {$nova}?token=" . urlencode($token));
  exit;
}

try {
  /* Busca tokens válidos pelo código (não usados e não expirados) */
  $st = $pdo->prepare("
    SELECT id, usuario_id, token_hash, expira_em, usado_em
    FROM redefinir_senha_tokens
    WHERE codigo = :codigo
      AND usado_em IS NULL
      AND expira_em >= NOW()
    ORDER BY id DESC
    LIMIT 10
  ");
  $st->execute([':codigo' => $codigo]);
  $rows = $st->fetchAll();

  if (!$rows) {
    $_SESSION['flash_erro'] = "Código inválido ou expirado.";
    header("Location: {$nova}?token=" . urlencode($token));
    exit;
  }

  /* Confere token com password_verify */
  $tokenRow = null;
  foreach ($rows as $r) {
    if (!empty($r['token_hash']) && password_verify($token, (string)$r['token_hash'])) {
      $tokenRow = $r;
      break;
    }
  }

  if (!$tokenRow) {
    $_SESSION['flash_erro'] = "Link/token não confere com o código informado.";
    header("Location: {$nova}?token=" . urlencode($token));
    exit;
  }

  $uid = (int)$tokenRow['usuario_id'];

  /* Atualiza senha */
  $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

  $upd = $pdo->prepare("
    UPDATE usuarios
    SET senha_hash = :h, atualizado_em = NOW()
    WHERE id = :id AND ativo = 1
  ");
  $upd->execute([':h' => $senhaHash, ':id' => $uid]);

  if ($upd->rowCount() <= 0) {
    $_SESSION['flash_erro'] = "Não foi possível atualizar a senha (usuário inativo ou inexistente).";
    header("Location: {$nova}?token=" . urlencode($token));
    exit;
  }

  /* Marca token como usado */
  $pdo->prepare("UPDATE redefinir_senha_tokens SET usado_em = NOW() WHERE id = :id")
      ->execute([':id' => (int)$tokenRow['id']]);

  $_SESSION['flash_ok'] = "Senha atualizada com sucesso! Você já pode entrar no sistema.";
  header("Location: {$index}");
  exit;

} catch (Throwable $e) {
  error_log("ERRO resetarSenha (process): " . $e->getMessage());
  $_SESSION['flash_erro'] = "Erro ao redefinir. Tente novamente.";
  header("Location: {$nova}?token=" . urlencode($token));
  exit;
}
