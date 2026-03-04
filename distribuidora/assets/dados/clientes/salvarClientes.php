<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

require_db_or_die();
$pdo = db();

$data = $_POST ?: read_json_body();

$csrf = (string)($data['_csrf'] ?? '');
csrf_validate_or_die($csrf);

$nome = trim((string)($data['nome'] ?? ''));
$cpf  = only_digits((string)($data['cpf'] ?? ''));
$tel  = only_digits((string)($data['telefone'] ?? ''));
$status = strtoupper(trim((string)($data['status'] ?? 'ATIVO')));
$obs = trim((string)($data['obs'] ?? ''));

if ($nome === '' || mb_strlen($nome) < 2) json_out(['ok' => false, 'msg' => 'Informe um nome válido.'], 422);
if (!cpf_is_valid($cpf)) json_out(['ok' => false, 'msg' => 'CPF inválido.'], 422);
if (!tel_min_ok($tel)) json_out(['ok' => false, 'msg' => 'Telefone inválido.'], 422);
if (!in_array($status, ['ATIVO','INATIVO'], true)) $status = 'ATIVO';

try {
  // CPF único
  $st = $pdo->prepare("SELECT id FROM clientes WHERE cpf = :cpf LIMIT 1");
  $st->execute(['cpf' => $cpf]);
  if ($st->fetchColumn()) {
    json_out(['ok' => false, 'msg' => 'CPF já cadastrado.'], 409);
  }

  $sql = "INSERT INTO clientes (nome, cpf, telefone, status, obs, created_at, updated_at)
          VALUES (:nome, :cpf, :telefone, :status, :obs, :ca, :ua)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'nome' => $nome,
    'cpf' => $cpf,
    'telefone' => $tel,
    'status' => $status,
    'obs' => $obs !== '' ? $obs : null,
    'ca' => now_sql(),
    'ua' => now_sql(),
  ]);

  $id = (int)$pdo->lastInsertId();

  json_out([
    'ok' => true,
    'msg' => 'Cliente salvo com sucesso.',
    'id' => $id
  ]);
} catch (Throwable $e) {
  json_out(['ok' => false, 'msg' => 'Erro ao salvar cliente.', 'detail' => $e->getMessage()], 500);
}

?>