<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/**
 * A CONEXÃO você disse que quer depois.
 * Aqui eu deixo só a referência.
 * Esperado: assets/php/conexao.php com função db(): PDO
 */
require_once __DIR__ . '/../conexao.php';

function pdo(): PDO {
  $pdo = db(); // <- você coloca a conexão depois nesse arquivo externo
  if (!$pdo instanceof PDO) {
    throw new RuntimeException("db() não retornou PDO.");
  }
  return $pdo;
}

function json_out(array $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function read_json_body(): array {
  $raw = file_get_contents('php://input');
  $j = json_decode($raw ?: '[]', true);
  return is_array($j) ? $j : [];
}

function csrf_ok(?string $t): bool {
  return is_string($t) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $t);
}

function only_status(string $s): string {
  $v = strtoupper(trim($s));
  return ($v === 'ATIVO' || $v === 'INATIVO') ? $v : 'ATIVO';
}

function norm_row(array $x): array {
  return [
    'nome'     => trim((string)($x['nome'] ?? '')),
    'status'   => only_status((string)($x['status'] ?? 'ATIVO')),
    'doc'      => trim((string)($x['doc'] ?? '')),
    'tel'      => trim((string)($x['tel'] ?? '')),
    'email'    => trim((string)($x['email'] ?? '')),
    'endereco' => trim((string)($x['endereco'] ?? '')),
    'cidade'   => trim((string)($x['cidade'] ?? '')),
    'uf'       => strtoupper(substr(trim((string)($x['uf'] ?? '')), 0, 2)),
    'contato'  => trim((string)($x['contato'] ?? '')),
    'obs'      => trim((string)($x['obs'] ?? '')),
  ];
}

?>