<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/_helpers.php';

require_db_or_die();
$pdo = db();

/**
 * URL padrão /distribuidora/clientes.php
 */
$script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$root = preg_replace('#/assets/dados/clientes/[^/]+$#', '', $script);
$clientesUrl = rtrim($root ?: '/', '/') . '/clientes.php';

if (!is_post()) redirect($clientesUrl);

csrf_validate_or_die();
$return = safe_return_to(post_str('return_to', $clientesUrl));

/* ========= DADOS ========= */
$id   = post_int('id', 0);
$nome = trim(post_str('nome'));

// CPF: salva só números
$cpfDigits = only_digits(post_str('cpf'));

// Telefone: salva como digitado (formatado pode)
$telefoneInput = trim(post_str('telefone'));
$telefoneDigits = only_digits($telefoneInput);

$end  = trim(post_str('endereco', ''));

/* ========= VALIDAÇÕES ========= */
if ($id <= 0) {
  flash_set('flash_err', 'ID inválido.');
  redirect($return);
}

if ($nome === '' || mb_strlen($nome) < 2) {
  flash_set('flash_err', 'Informe um nome válido.');
  redirect($return);
}

if (strlen($cpfDigits) !== 11 || preg_match('/^(\d)\1{10}$/', $cpfDigits)) {
  flash_set('flash_err', 'CPF deve ter 11 dígitos (somente números).');
  redirect($return);
}

if (!tel_min_ok($telefoneDigits)) {
  flash_set('flash_err', 'Telefone inválido.');
  redirect($return);
}

try {
  // existe?
  $st = $pdo->prepare("SELECT id FROM clientes WHERE id = :id LIMIT 1");
  $st->execute(['id' => $id]);
  if (!$st->fetchColumn()) {
    flash_set('flash_err', 'Cliente não encontrado.');
    redirect($return);
  }

  // CPF único (normalizado) exceto o próprio
  $st = $pdo->prepare("
    SELECT id
    FROM clientes
    WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
      AND id <> :id
    LIMIT 1
  ");
  $st->execute(['cpf' => $cpfDigits, 'id' => $id]);
  if ($st->fetchColumn()) {
    flash_set('flash_err', 'CPF já cadastrado em outro cliente.');
    redirect($return);
  }

  $sql = "UPDATE clientes
          SET nome = :nome,
              cpf = :cpf,
              telefone = :tel,
              endereco = :end,
              updated_at = NOW()
          WHERE id = :id
          LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'nome' => $nome,
    'cpf'  => $cpfDigits,         // <<< CPF SEM . e -
    'tel'  => $telefoneInput,     // <<< TELEFONE pode ir formatado
    'end'  => ($end !== '' ? $end : null),
    'id'   => $id,
  ]);

  flash_set('flash_ok', 'Cliente atualizado com sucesso.');
  redirect($return);
} catch (Throwable $e) {
  flash_set('flash_err', 'Erro ao editar cliente: ' . $e->getMessage());
  redirect($return);
}

?>