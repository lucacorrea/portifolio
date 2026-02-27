<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  csrf_check($_POST['csrf_token'] ?? null);

  $redirect = (string)($_POST['redirect_to'] ?? '../../../fornecedores.php');

  $id       = (int)($_POST['id'] ?? 0);
  $nome     = trim((string)($_POST['nome'] ?? ''));
  $status   = only_status((string)($_POST['status'] ?? 'ATIVO'));
  $doc      = trim((string)($_POST['doc'] ?? ''));
  $tel      = trim((string)($_POST['tel'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));
  $endereco = trim((string)($_POST['endereco'] ?? ''));
  $cidade   = trim((string)($_POST['cidade'] ?? ''));
  $uf       = strtoupper(substr(trim((string)($_POST['uf'] ?? '')), 0, 2));
  $contato  = trim((string)($_POST['contato'] ?? ''));
  $obs      = trim((string)($_POST['obs'] ?? ''));

  if ($nome === '') {
    flash_set('danger', 'Informe o nome / razão social.');
    redirect_to($redirect);
  }

  $pdo = pdo();

  if ($id > 0) {
    $st = $pdo->prepare("UPDATE fornecedores
                         SET nome=?, status=?, doc=?, tel=?, email=?, endereco=?, cidade=?, uf=?, contato=?, obs=?
                         WHERE id=?");
    $st->execute([$nome,$status,$doc,$tel,$email,$endereco,$cidade,$uf,$contato,$obs,$id]);

    flash_set('success', 'Fornecedor atualizado com sucesso!');
    redirect_to($redirect);
  }

  $st = $pdo->prepare("INSERT INTO fornecedores (nome,status,doc,tel,email,endereco,cidade,uf,contato,obs)
                       VALUES (?,?,?,?,?,?,?,?,?,?)");
  $st->execute([$nome,$status,$doc,$tel,$email,$endereco,$cidade,$uf,$contato,$obs]);

  flash_set('success', 'Fornecedor cadastrado com sucesso!');
  redirect_to($redirect);

} catch (Throwable $e) {
  fail_page($e->getMessage());
}

?>