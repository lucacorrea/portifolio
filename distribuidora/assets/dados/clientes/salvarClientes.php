<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/_helpers.php';

require_db_or_die();
$pdo = db();

if (!is_post()) redirect(url_here('../../../clientes.php'));

csrf_validate_or_die();
$return = safe_return_to(post_str('return_to', url_here('../../../clientes.php')));

$nome = trim(post_str('nome'));
$cpf  = only_digits(post_str('cpf'));
$tel  = only_digits(post_str('telefone'));
$end  = trim(post_str('endereco', ''));

if ($nome === '' || mb_strlen($nome) < 2) { flash_set('flash_err', 'Informe um nome válido.'); redirect($return); }
if (!cpf_is_valid($cpf)) { flash_set('flash_err', 'CPF inválido.'); redirect($return); }
if (!tel_min_ok($tel)) { flash_set('flash_err', 'Telefone inválido.'); redirect($return); }

try {
  // CPF único
  $st = $pdo->prepare("SELECT id FROM clientes WHERE cpf = :cpf LIMIT 1");
  $st->execute(['cpf' => $cpf]);
  if ($st->fetchColumn()) {
    flash_set('flash_err', 'CPF já cadastrado.');
    redirect($return);
  }

  $sql = "INSERT INTO clientes (nome, cpf, telefone, endereco, created_at, updated_at)
          VALUES (:nome, :cpf, :tel, :end, NOW(), NOW())";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'nome' => $nome,
    'cpf' => $cpf,
    'tel' => $tel,
    'end' => ($end !== '' ? $end : null),
  ]);

  flash_set('flash_ok', 'Cliente cadastrado com sucesso.');
  redirect($return);
} catch (Throwable $e) {
  flash_set('flash_err', 'Erro ao salvar cliente: ' . $e->getMessage());
  redirect($return);
}

?>