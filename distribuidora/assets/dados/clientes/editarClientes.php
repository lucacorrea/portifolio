<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/_helpers.php';

require_db_or_die();
$pdo = db();

/**
 * URL padrão de retorno: /distribuidora/clientes.php
 */
$script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$root = preg_replace('#/assets/dados/clientes/[^/]+$#', '', $script);
$clientesUrl = rtrim($root ?: '/', '/') . '/clientes.php';

if (!is_post()) redirect($clientesUrl);

csrf_validate_or_die();
$return = safe_return_to(post_str('return_to', $clientesUrl));

$id   = post_int('id', 0);
$nome = trim(post_str('nome'));
$cpf  = only_digits(post_str('cpf'));       // <<< CPF só números
$tel  = only_digits(post_str('telefone'));  // <<< telefone só números
$end  = trim(post_str('endereco', ''));

if ($id <= 0)                               { flash_set('flash_err', 'ID inválido.'); redirect($return); }
if ($nome === '' || mb_strlen($nome) < 2)   { flash_set('flash_err', 'Informe um nome válido.'); redirect($return); }
if (!cpf_is_valid($cpf))                    { flash_set('flash_err', 'CPF inválido.'); redirect($return); }
if (!tel_min_ok($tel))                      { flash_set('flash_err', 'Telefone inválido.'); redirect($return); }

try {
  // existe?
  $st = $pdo->prepare("SELECT id FROM clientes WHERE id = :id LIMIT 1");
  $st->execute(['id' => $id]);
  if (!$st->fetchColumn()) {
    flash_set('flash_err', 'Cliente não encontrado.');
    redirect($return);
  }

  // CPF único (robusto: compara normalizado e ignora o próprio)
  $st = $pdo->prepare("
    SELECT id
    FROM clientes
    WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
      AND id <> :id
    LIMIT 1
  ");
  $st->execute(['cpf' => $cpf, 'id' => $id]);
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
    'cpf'  => $cpf,
    'tel'  => $tel,
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