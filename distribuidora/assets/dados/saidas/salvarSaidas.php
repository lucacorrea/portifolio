<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

$back = '../../../saidas.php';

require_post_or_redirect($back);
csrf_validate_or_redirect($back);

$pdo = db();

function brl_to_float(string $v): float {
  $s = trim($v);
  $s = str_replace(['R$', ' '], '', $s);
  $s = str_replace('.', '', $s);
  $s = str_replace(',', '.', $s);
  $n = (float)$s;
  return $n;
}

$id        = (int)($_POST['id'] ?? 0);
$data      = trim((string)($_POST['data'] ?? ''));
$pedido    = trim((string)($_POST['pedido'] ?? ''));
$cliente   = trim((string)($_POST['cliente'] ?? ''));
$canal     = strtoupper(trim((string)($_POST['canal'] ?? '')));
$pagamento = strtoupper(trim((string)($_POST['pagamento'] ?? '')));
$produtoId = (int)($_POST['produto_id'] ?? 0);
$unidade   = trim((string)($_POST['unidade'] ?? ''));
$qtd       = (float)($_POST['qtd'] ?? 0);
$preco     = brl_to_float((string)($_POST['preco'] ?? '0'));

if ($data === '' || $pedido === '' || $cliente === '' || $canal === '' || $pagamento === '' || $produtoId <= 0 || $unidade === '') {
  flash_set('danger', 'Preencha todos os campos obrigatórios.');
  redirect_to($back);
}

if ($qtd <= 0) {
  flash_set('danger', 'A quantidade deve ser maior que zero.');
  redirect_to($back);
}

if ($preco < 0) {
  flash_set('danger', 'Preço inválido.');
  redirect_to($back);
}

$total = $qtd * $preco;

try {
  $pdo->beginTransaction();

  // trava produto para calcular estoque com segurança
  $st = $pdo->prepare("SELECT id, estoque FROM produtos WHERE id = ? FOR UPDATE");
  $st->execute([$produtoId]);
  $prod = $st->fetch(PDO::FETCH_ASSOC);
  if (!$prod) {
    throw new RuntimeException('Produto não encontrado.');
  }

  if ($id > 0) {
    // busca registro antigo (para ajustar estoque ao editar)
    $oldSt = $pdo->prepare("SELECT id, produto_id, qtd FROM saidas WHERE id = ? FOR UPDATE");
    $oldSt->execute([$id]);
    $old = $oldSt->fetch(PDO::FETCH_ASSOC);
    if (!$old) throw new RuntimeException('Saída não encontrada.');

    $oldPid = (int)$old['produto_id'];
    $oldQtd = (float)$old['qtd'];

    if ($oldPid === $produtoId) {
      $delta = $qtd - $oldQtd; // >0 => sai mais (baixa mais), <0 => devolve
      $upd = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
      $upd->execute([$delta, $produtoId]);
    } else {
      // devolve no antigo
      $upd1 = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?");
      $upd1->execute([$oldQtd, $oldPid]);
      // baixa no novo
      $upd2 = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
      $upd2->execute([$qtd, $produtoId]);
    }

    // valida estoque não negativo (opcional: você pode permitir negativo se quiser)
    $chk = $pdo->prepare("SELECT estoque FROM produtos WHERE id = ?");
    $chk->execute([$produtoId]);
    $stk = (float)$chk->fetchColumn();
    if ($stk < 0) {
      throw new RuntimeException('Estoque insuficiente para esta saída.');
    }

    $up = $pdo->prepare("
      UPDATE saidas
      SET data=?, pedido=?, cliente=?, canal=?, pagamento=?, produto_id=?, unidade=?, qtd=?, preco=?, total=?, updated_at=NOW()
      WHERE id=?
    ");
    $up->execute([$data, $pedido, $cliente, $canal, $pagamento, $produtoId, $unidade, $qtd, $preco, $total, $id]);

    $pdo->commit();
    flash_set('success', 'Saída atualizada com sucesso!');
    redirect_to($back);
  }

  // INSERT (nova saída) -> baixa estoque
  $upd = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
  $upd->execute([$qtd, $produtoId]);

  // valida estoque não negativo
  $chk = $pdo->prepare("SELECT estoque FROM produtos WHERE id = ?");
  $chk->execute([$produtoId]);
  $stk = (float)$chk->fetchColumn();
  if ($stk < 0) {
    throw new RuntimeException('Estoque insuficiente para esta saída.');
  }

  $ins = $pdo->prepare("
    INSERT INTO saidas (data, pedido, cliente, canal, pagamento, produto_id, unidade, qtd, preco, total)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $ins->execute([$data, $pedido, $cliente, $canal, $pagamento, $produtoId, $unidade, $qtd, $preco, $total]);

  $pdo->commit();
  flash_set('success', 'Saída cadastrada com sucesso!');
  redirect_to($back);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  flash_set('danger', 'Erro ao salvar: ' . $e->getMessage());
  redirect_to($back);
}

?>