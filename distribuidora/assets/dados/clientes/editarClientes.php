<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

require_db_or_die();
$pdo = db();

$data = $_POST ?: read_json_body();

$csrf = (string)($data['_csrf'] ?? '');
csrf_validate_or_die($csrf);

$id = (int)($data['id'] ?? 0);
$nome = trim((string)($data['nome'] ?? ''));
$cpf  = only_digits((string)($data['cpf'] ?? ''));
$tel  = only_digits((string)($data['telefone'] ?? ''));
$status = strtoupper(trim((string)($data['status'] ?? 'ATIVO')));
$obs = trim((string)($data['obs'] ?? ''));

if ($id <= 0) json_out(['ok' => false, 'msg' => 'ID inválido.'], 422);
if ($nome === '' || mb_strlen($nome) < 2) json_out(['ok' => false, 'msg' => 'Informe um nome válido.'], 422);
if (!cpf_is_valid($cpf)) json_out(['ok' => false, 'msg' => 'CPF inválido.'], 422);
if (!tel_min_ok($tel)) json_out(['ok' => false, 'msg' => 'Telefone inválido.'], 422);
if (!in_array($status, ['ATIVO','INATIVO'], true)) $status = 'ATIVO';

try {
  // existe?
  $st = $pdo->prepare("SELECT id FROM clientes WHERE id = :id LIMIT 1");
  $st->execute(['id' => $id]);
  if (!$st->fetchColumn()) json_out(['ok' => false, 'msg' => 'Cliente não encontrado.'], 404);

  // CPF único (exceto o próprio)
  $st = $pdo->prepare("SELECT id FROM clientes WHERE cpf = :cpf AND id <> :id LIMIT 1");
  $st->execute(['cpf' => $cpf, 'id' => $id]);
  if ($st->fetchColumn()) json_out(['ok' => false, 'msg' => 'CPF já cadastrado em outro cliente.'], 409);

  $sql = "UPDATE clientes
          SET nome = :nome,
              cpf = :cpf,
              telefone = :telefone,
              status = :status,
              obs = :obs,
              updated_at = :ua
          WHERE id = :id
          LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'nome' => $nome,
    'cpf' => $cpf,
    'telefone' => $tel,
    'status' => $status,
    'obs' => $obs !== '' ? $obs : null,
    'ua' => now_sql(),
    'id' => $id
  ]);

  json_out(['ok' => true, 'msg' => 'Cliente atualizado com sucesso.']);
} catch (Throwable $e) {
  json_out(['ok' => false, 'msg' => 'Erro ao editar cliente.', 'detail' => $e->getMessage()], 500);
}

?>