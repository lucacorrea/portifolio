<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('APP_DEBUG')) define('APP_DEBUG', false);

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_once __DIR__ . '/../../../lib/util.php';

guard_empresa_user(['super_admin','dono','administrativo','caixa']);

// a página deve definir $pdo
if (!isset($pdo) || !($pdo instanceof PDO)) {
  die('Conexão indisponível.');
}

// perfil
$perfil = strtolower((string)($_SESSION['user_perfil'] ?? $_SESSION['perfil'] ?? ''));

// cpf do usuário logado (use o que existir no seu sistema)
$userCpfRaw = $_SESSION['user_cpf'] ?? $_SESSION['usuario_cpf'] ?? $_SESSION['cpf'] ?? '';
$userCpf = preg_replace('/\D+/', '', (string)$userCpfRaw);

// cnpj vindo da sessão (se existir)
$empresaCnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? $_SESSION['empresa_cnpj'] ?? ''));

// resolve cnpj final
$empresaCnpj = $empresaCnpjSess;

// se não for super_admin, garante empresa_cnpj (preferindo buscar no banco via CPF)
if ($perfil !== 'super_admin') {
  if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
    if (!preg_match('/^\d{11}$/', $userCpf)) {
      die('Usuário sem CPF válido na sessão para resolver empresa.');
    }

    $st = $pdo->prepare("SELECT empresa_cnpj FROM usuarios_peca WHERE cpf = :cpf LIMIT 1");
    $st->execute([':cpf' => $userCpf]);
    $empresaCnpj = preg_replace('/\D+/', '', (string)($st->fetchColumn() ?: ''));

    if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
      die('Empresa não vinculada ao usuário.');
    }

    // salva na sessão para as próximas páginas
    $_SESSION['user_empresa_cnpj'] = $empresaCnpj;
  }
}

// filtro ativo
$ativo = isset($_GET['ativo']) ? (string)$_GET['ativo'] : '';

// csrf
if (empty($_SESSION['csrf_lavadores'])) {
  $_SESSION['csrf_lavadores'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_lavadores'];

// nome da empresa
$empresaNome = empresa_nome_logada($pdo) ?: 'Sua empresa';

// query lavadores
$sql = "
  SELECT
    id, nome, cpf, telefone, email, ativo,
    DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i') AS criado
  FROM lavadores_peca
  WHERE 1=1
";
$params = [];

if ($perfil !== 'super_admin') {
  $sql .= " AND empresa_cnpj = :c";
  $params[':c'] = $empresaCnpj;
}

if ($ativo === '1' || $ativo === '0') {
  $sql .= " AND ativo = :a";
  $params[':a'] = (int)$ativo;
}

$sql .= " ORDER BY nome";

$rows = [];
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  if (APP_DEBUG) die($e->getMessage());
}

// viewmodel
$vm = [
  'rows'        => $rows,
  'ativo'       => $ativo,
  'csrf'        => $csrf,
  'empresaNome' => $empresaNome,
  'empresaCnpj' => $empresaCnpj, // útil se quiser mostrar/depurar
];
