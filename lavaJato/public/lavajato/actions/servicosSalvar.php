<?php
// autoErp/public/lavajato/actions/servicosSalvar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo']); // só dono e administrativo podem salvar serviço

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  header("Location: ../pages/servicosNovo.php?err=1&msg=" . urlencode("Conexão indisponível."));
  exit;
}

// Verifica CSRF
$csrfSess = $_SESSION['csrf_servico_novo'] ?? '';
$csrfPost = $_POST['csrf'] ?? '';
if (!$csrfSess || !$csrfPost || !hash_equals($csrfSess, $csrfPost)) {
  header("Location: ../pages/servicosNovo.php?err=1&msg=" . urlencode("Falha de segurança (CSRF)."));
  exit;
}

// Empresa CNPJ da sessão
$empresaCnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpjSess)) {
  header("Location: ../pages/servicosNovo.php?err=1&msg=" . urlencode("Empresa não vinculada ao usuário."));
  exit;
}

// Coleta dados do POST
$nome      = trim((string)($_POST['nome'] ?? ''));
$descricao = trim((string)($_POST['descricao'] ?? ''));
$ativo     = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;

// Validação
if ($nome === '') {
  header("Location: ../pages/servicosNovo.php?err=1&msg=" . urlencode("Preencha o nome do serviço."));
  exit;
}
if ($ativo !== 0 && $ativo !== 1) $ativo = 1;

try {
  $sql = "INSERT INTO categorias_lavagem_peca
            (empresa_cnpj, nome, descricao, ativo)
          VALUES
            (:c, :n, :d, :a)";
  $st = $pdo->prepare($sql);
  $st->execute([
    ':c' => $empresaCnpjSess,
    ':n' => $nome,
    ':d' => ($descricao !== '' ? $descricao : null),
    ':a' => $ativo
  ]);

  header("Location: ../pages/servicosNovo.php?ok=1&msg=" . urlencode("Serviço cadastrado com sucesso."));
  exit;
} catch (Throwable $e) {
  $msg = "Erro ao salvar serviço.";
  if (defined('APP_DEBUG') && APP_DEBUG) {
    $msg .= " Detalhes: " . $e->getMessage();
  }
  header("Location: ../pages/servicosNovo.php?err=1&msg=" . urlencode($msg));
  exit;
}
