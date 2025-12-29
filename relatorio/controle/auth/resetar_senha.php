<?php
declare(strict_types=1);
session_start();

$index = '../../redefinirSenha.php';

try {
  require __DIR__ . '/../../assets/php/conexao.php';
  if (!function_exists('db')) throw new RuntimeException("db() não existe");
  $pdo = db();
} catch (Throwable $e) {
  error_log("ERRO resetar_senha (db): ".$e->getMessage());
  $_SESSION['flash_erro'] = "Erro interno. Tente novamente.";
  header("Location: {$index}");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: {$index}");
  exit;
}

$token = trim((string)($_POST['token'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');
$senha2 = (string)($_POST['senha2'] ?? '');

if ($token === '' || mb_strlen($token) < 20) {
  $_SESSION['flash_erro'] = "Token inválido.";
  header("Location: {$index}");
  exit;
}
if (mb_strlen($senha) < 6) {
  $_SESSION['flash_erro'] = "A senha deve ter no mínimo 6 caracteres.";
  header("Location: ../../redefinirSenhaNova.php?token=".urlencode($token));
  exit;
}
if ($senha !== $senha2) {
  $_SESSION['flash_erro'] = "As senhas não conferem.";
  header("Location: ../../redefinirSenhaNova.php?token=".urlencode($token));
  exit;
}

try {
  // Busca tokens ainda válidos e não usados (últimos primeiro)
  $st = $pdo->prepare("
    SELECT id, usuario_id, token_hash, expira_em, usado_em
    FROM redefinir_senha_tokens
    WHERE usado_em IS NULL AND expira_em >= NOW()
    ORDER BY id DESC
    LIMIT 50
  ");
  $st->execute();
  $tokens = $st->fetchAll();

  $tokenRow = null;
  foreach ($tokens as $t) {
    if (!empty($t['token_hash']) && password_verify($token, (string)$t['token_hash'])) {
      $tokenRow = $t;
      break;
    }
  }

  if (!$tokenRow) {
    $_SESSION['flash_erro'] = "Link inválido ou expirado. Solicite novamente.";
    header("Location: {$index}");
    exit;
  }

  $uid = (int)$tokenRow['usuario_id'];

  $pdo->beginTransaction();

  // Atualiza senha
  $hash = password_hash($senha, PASSWORD_DEFAULT);
  $up = $pdo->prepare("UPDATE usuarios SET senha_hash = :h, atualizado_em = NOW() WHERE id = :id AND ativo = 1");
  $up->execute([':h' => $hash, ':id' => $uid]);

  if ($up->rowCount() <= 0) {
    $pdo->rollBack();
    $_SESSION['flash_erro'] = "Usuário não encontrado ou inativo.";
    header("Location: {$index}");
    exit;
  }

  // Marca token como usado
  $mk = $pdo->prepare("UPDATE redefinir_senha_tokens SET usado_em = NOW() WHERE id = :id");
  $mk->execute([':id' => (int)$tokenRow['id']]);

  $pdo->commit();

  $_SESSION['flash_ok'] = "Senha redefinida com sucesso! Faça login normalmente.";
  header("Location: ../../index.php");
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log("ERRO resetar_senha (query): ".$e->getMessage());
  $_SESSION['flash_erro'] = "Erro ao redefinir. Tente novamente.";
  header("Location: {$index}");
  exit;
}
