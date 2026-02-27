<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  csrf_check($_POST['csrf_token'] ?? null);

  $id        = (int)($_POST['id'] ?? 0);
  $nome      = trim((string)($_POST['nome'] ?? ''));
  $descricao = trim((string)($_POST['descricao'] ?? ''));
  $cor       = norm_hex((string)($_POST['cor'] ?? ''));
  $obs       = trim((string)($_POST['obs'] ?? ''));
  $status    = only_status((string)($_POST['status'] ?? 'ATIVO'));

  if ($nome === '') {
    flash_set('danger', 'Informe o nome da categoria.');
    redirect_categorias();
  }

  $pdo = pdo();

  if ($id > 0) {
    $st = $pdo->prepare("UPDATE categorias
                         SET nome=?, descricao=?, cor=?, obs=?, status=?
                         WHERE id=?");
    $st->execute([$nome, $descricao, $cor, $obs, $status, $id]);

    flash_set('success', 'Categoria atualizada com sucesso!');
    redirect_categorias();
  }

  $st = $pdo->prepare("INSERT INTO categorias (nome, descricao, cor, obs, status)
                       VALUES (?,?,?,?,?)");
  $st->execute([$nome, $descricao, $cor, $obs, $status]);

  flash_set('success', 'Categoria cadastrada com sucesso!');
  redirect_categorias();

} catch (Throwable $e) {
  fail_page($e->getMessage());
}

?>