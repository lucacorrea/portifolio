<?php
// autoErp/public/lavajato/controllers/lavagemRapidaController.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('APP_DEBUG')) define('APP_DEBUG', false);

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_once __DIR__ . '/../../../lib/util.php';

// =========================
// PERMISSÕES
// =========================
guard_empresa_user(['super_admin', 'dono', 'administrativo', 'caixa']);

// =========================
// CONEXÃO
// =========================
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!$pdo instanceof PDO) die('Conexão indisponível.');

// =========================
// PERFIL / EMPRESA
// =========================
$perfil = strtolower((string)($_SESSION['user_perfil'] ?? ''));
// se no seu sistema o perfil fica em outro lugar, mantém assim também:
if ($perfil === '') $perfil = strtolower((string)($_SESSION['perfil'] ?? ''));

$empresaCnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));

if ($perfil !== 'super_admin' && !preg_match('/^\d{14}$/', $empresaCnpjSess)) {
  die('Empresa não vinculada ao usuário.');
}

// =========================
// CSRF
// =========================
if (empty($_SESSION['csrf_lavagem_rapida'])) {
  $_SESSION['csrf_lavagem_rapida'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_lavagem_rapida'];

// =========================
// FLASH
// =========================
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');

// =========================
// NOME DA EMPRESA
// =========================
$empresaNome = empresa_nome_logada($pdo) ?: 'Sua Empresa';

// =========================
// SERVIÇOS (SEM valor_padrao)
// =========================
$servicos = [];
try {
  $sql = "
    SELECT id, nome, descricao
    FROM categorias_lavagem_peca
    WHERE ativo = 1
  ";

  if ($perfil !== 'super_admin') {
    $sql .= " AND empresa_cnpj = :c";
  }

  $sql .= " ORDER BY nome";

  $st = $pdo->prepare($sql);

  if ($perfil !== 'super_admin') {
    $st->bindValue(':c', $empresaCnpjSess);
  }

  $st->execute();
  $servicos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $servicos = [];
  if (APP_DEBUG) {
    $msg = 'Erro ao carregar serviços: ' . $e->getMessage();
    $err = 1;
  }
}

// =========================
// LAVADORES
// =========================
$lavadores = [];
try {
  $sql = "
    SELECT id, nome, cpf
    FROM lavadores_peca
    WHERE ativo = 1
  ";

  if ($perfil !== 'super_admin') {
    $sql .= " AND empresa_cnpj = :c";
  }

  $sql .= " ORDER BY nome";

  $st = $pdo->prepare($sql);

  if ($perfil !== 'super_admin') {
    $st->bindValue(':c', $empresaCnpjSess);
  }

  $st->execute();
  $lavadores = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $lavadores = [];
  if (APP_DEBUG) {
    $msg = 'Erro ao carregar lavadores: ' . $e->getMessage();
    $err = 1;
  }
}

// =========================
// ADICIONAIS
// ✅ sua tabela adicionais_peca NÃO tem empresa_cnpj
// =========================
$adicionais = [];
try {
  $sql = "
    SELECT id, nome, valor
    FROM adicionais_peca
    WHERE ativo = 1
    ORDER BY nome
  ";

  $st = $pdo->prepare($sql);
  $st->execute();
  $adicionais = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $adicionais = [];
  if (APP_DEBUG) {
    $msg = 'Erro ao carregar adicionais: ' . $e->getMessage();
    $err = 1;
  }
}

// =========================
// VIEWMODEL
// =========================
$vm = [
  'empresaNome' => $empresaNome,
  'csrf'        => $csrf,
  'ok'          => $ok,
  'err'         => $err,
  'msg'         => $msg,
  'servicos'    => $servicos,
  'lavadores'   => $lavadores,
  'adicionais'  => $adicionais,
];
