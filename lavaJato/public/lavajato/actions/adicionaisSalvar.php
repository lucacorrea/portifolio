<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo']);

/* ===== CONEXÃO ===== */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;

if (!$pdo instanceof PDO) {
  header("Location: ../pages/adicionaisNovo.php?err=1&msg=" . urlencode("Conexão indisponível."));
  exit;
}

/* ===== CSRF ===== */
$csrfSess = $_SESSION['csrf_adicional_novo'] ?? '';
$csrfPost = $_POST['csrf'] ?? '';
if (!$csrfSess || !$csrfPost || !hash_equals($csrfSess, $csrfPost)) {
  header("Location: ../pages/adicionaisNovo.php?err=1&msg=" . urlencode("Falha de segurança (CSRF)."));
  exit;
}

/* ===== CNPJ DA EMPRESA LOGADA ===== */
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  header("Location: ../pages/adicionaisNovo.php?err=1&msg=" . urlencode("Empresa não vinculada ao usuário."));
  exit;
}

/* ===== DADOS DO FORM ===== */
$nome  = trim((string)($_POST['nome'] ?? ''));
$valor = (float)($_POST['valor'] ?? 0);
$ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;

/* ===== VALIDAÇÃO ===== */
if ($nome === '' || $valor <= 0) {
  header("Location: ../pages/adicionaisNovo.php?err=1&msg=" . urlencode("Preencha corretamente todos os campos."));
  exit;
}

/* ===== INSERT CORRETO ===== */
try {
  $sql = "
    INSERT INTO adicionais_peca
      (empresa_cnpj, nome, valor, ativo, criado_em)
    VALUES
      (:empresa_cnpj, :nome, :valor, :ativo, NOW())
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':empresa_cnpj' => $empresaCnpj,
    ':nome'         => $nome,
    ':valor'        => $valor,
    ':ativo'        => $ativo,
  ]);

  header("Location: ../pages/adicionaisNovo.php?ok=1&msg=" . urlencode("Adicional cadastrado com sucesso."));
  exit;

} catch (Throwable $e) {
  error_log('Erro ao salvar adicional: ' . $e->getMessage());

  $msg = 'Erro ao salvar adicional.';
  if (defined('APP_DEBUG') && APP_DEBUG) {
    $msg .= ' ' . $e->getMessage();
  }

  header("Location: ../pages/adicionaisNovo.php?err=1&msg=" . urlencode($msg));
  exit;
}
