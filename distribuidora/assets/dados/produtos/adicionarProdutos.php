<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  csrf_check($_POST['csrf_token'] ?? null);

  $id          = (int)($_POST['id'] ?? 0);
  $codigo      = trim((string)($_POST['codigo'] ?? ''));
  $nome        = trim((string)($_POST['nome'] ?? ''));
  $status      = only_status((string)($_POST['status'] ?? 'ATIVO'));
  $categoriaId = (int)($_POST['categoria_id'] ?? 0);
  $fornecedorId= (int)($_POST['fornecedor_id'] ?? 0);
  $unidade     = trim((string)($_POST['unidade'] ?? ''));
  $precoDec    = parse_money_to_decimal_string((string)($_POST['preco'] ?? '0'));
  $estoque     = max(0, (int)($_POST['estoque'] ?? 0));
  $minimo      = max(0, (int)($_POST['minimo'] ?? 0));
  $obs         = trim((string)($_POST['obs'] ?? ''));

  if ($codigo === '' || $nome === '' || $categoriaId <= 0 || $fornecedorId <= 0 || $unidade === '') {
    flash_set('danger', 'Preencha os campos obrigatórios (Código, Produto, Categoria, Fornecedor, Unidade).');
    redirect_produtos();
  }

  $pdo = pdo();

  // valida código único
  if ($id > 0) {
    $chk = $pdo->prepare("SELECT id FROM produtos WHERE codigo=? AND id<>? LIMIT 1");
    $chk->execute([$codigo, $id]);
    if ($chk->fetchColumn()) {
      flash_set('danger', 'Já existe outro produto com esse código.');
      redirect_produtos();
    }
  } else {
    $chk = $pdo->prepare("SELECT id FROM produtos WHERE codigo=? LIMIT 1");
    $chk->execute([$codigo]);
    if ($chk->fetchColumn()) {
      flash_set('danger', 'Já existe um produto com esse código.');
      redirect_produtos();
    }
  }

  if ($id > 0) {
    // UPDATE
    $st = $pdo->prepare("UPDATE produtos
      SET codigo=?, nome=?, status=?, categoria_id=?, fornecedor_id=?, unidade=?, preco=?, estoque=?, minimo=?, obs=?
      WHERE id=?");
    $st->execute([$codigo,$nome,$status,$categoriaId,$fornecedorId,$unidade,$precoDec,$estoque,$minimo,$obs,$id]);

    flash_set('success', 'Produto atualizado com sucesso!');
    redirect_produtos();
  }

  // INSERT
  $st = $pdo->prepare("INSERT INTO produtos
    (codigo,nome,status,categoria_id,fornecedor_id,unidade,preco,estoque,minimo,obs)
    VALUES (?,?,?,?,?,?,?,?,?,?)");
  $st->execute([$codigo,$nome,$status,$categoriaId,$fornecedorId,$unidade,$precoDec,$estoque,$minimo,$obs]);

  flash_set('success', 'Produto cadastrado com sucesso!');
  redirect_produtos();

} catch (Throwable $e) {
  fail_page($e->getMessage());
}
?>