<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../_helpers.php';

$pdo = db();

if (!is_post()) {
  redirect('../../../vendas.php');
}

csrf_validate_or_die('csrf');

$id         = to_int(post('id', 0), 0);
$data       = to_str(post('data', ''), '');
$cliente_id = to_int(post('cliente_id', 0), 0);
$produto_id = to_int(post('produto_id', 0), 0);
$canal      = strtoupper(to_str(post('canal', 'PRESENCIAL'), 'PRESENCIAL'));
$pagamento  = strtoupper(to_str(post('pagamento', 'DINHEIRO'), 'DINHEIRO'));
$obs        = to_str(post('obs', ''), '');

$qtdRaw     = to_str(post('quantidade', '0'), '0');
$precoRaw   = to_str(post('preco_unit', '0'), '0');

$qtd = (float)str_replace(',', '.', $qtdRaw);
if ($qtd < 0) $qtd = 0;

$preco = brl_to_float($precoRaw);
if ($preco < 0) $preco = 0;

if ($data === '' || $cliente_id <= 0 || $produto_id <= 0) {
  flash_set('danger', 'Preencha os campos obrigatórios.');
  redirect('../../../vendas.php');
}

if (!in_array($canal, ['PRESENCIAL', 'DELIVERY'], true)) $canal = 'PRESENCIAL';
if ($pagamento === '') $pagamento = 'DINHEIRO';

$total = $qtd * $preco;

try {
  $pdo->beginTransaction();

  // produto atual (novo)
  $stProd = $pdo->prepare("SELECT id, estoque, nome FROM produtos WHERE id = ?");
  $stProd->execute([$produto_id]);
  $prod = $stProd->fetch(PDO::FETCH_ASSOC);

  if (!$prod) {
    throw new RuntimeException('Produto não encontrado.');
  }

  $estoqueAtual = (float)$prod['estoque'];

  if ($id <= 0) {
    // INSERT
    if ($qtd > $estoqueAtual) {
      throw new RuntimeException("Qtd maior que estoque. Estoque atual: {$estoqueAtual}");
    }

    $ins = $pdo->prepare("
      INSERT INTO vendas (data, cliente_id, produto_id, canal, pagamento, quantidade, preco_unit, total, obs)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([$data, $cliente_id, $produto_id, $canal, $pagamento, $qtd, $preco, $total, $obs]);

    // baixa estoque
    $upd = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
    $upd->execute([$qtd, $produto_id]);

    $pdo->commit();
    flash_set('success', 'Venda registrada com sucesso!');
    redirect('../../../vendas.php');
  }

  // UPDATE
  $stOld = $pdo->prepare("SELECT id, produto_id, quantidade FROM vendas WHERE id = ?");
  $stOld->execute([$id]);
  $old = $stOld->fetch(PDO::FETCH_ASSOC);

  if (!$old) {
    throw new RuntimeException('Venda não encontrada para editar.');
  }

  $oldProduto = (int)$old['produto_id'];
  $oldQtd     = (float)$old['quantidade'];

  if ($oldProduto !== $produto_id) {
    // devolve estoque do produto antigo
    $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?")->execute([$oldQtd, $oldProduto]);

    // valida estoque do novo produto
    $stProd->execute([$produto_id]);
    $prod2 = $stProd->fetch(PDO::FETCH_ASSOC);
    if (!$prod2) throw new RuntimeException('Produto novo não encontrado.');

    $estoqueNovo = (float)$prod2['estoque'];
    if ($qtd > $estoqueNovo) {
      throw new RuntimeException("Qtd maior que estoque do novo produto. Estoque atual: {$estoqueNovo}");
    }

    // baixa estoque do novo produto
    $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?")->execute([$qtd, $produto_id]);
  } else {
    // mesmo produto: ajusta diferença
    $delta = $qtd - $oldQtd; // se >0 baixa mais, se <0 devolve
    if ($delta > 0) {
      // precisa ter estoque pra baixar
      $stProd->execute([$produto_id]);
      $pcur = $stProd->fetch(PDO::FETCH_ASSOC);
      $est = (float)$pcur['estoque'];
      if ($delta > $est) throw new RuntimeException("Qtd maior que estoque. Estoque atual: {$est}");
      $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?")->execute([$delta, $produto_id]);
    } elseif ($delta < 0) {
      $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?")->execute([abs($delta), $produto_id]);
    }
  }

  $updVenda = $pdo->prepare("
    UPDATE vendas
    SET data = ?, cliente_id = ?, produto_id = ?, canal = ?, pagamento = ?,
        quantidade = ?, preco_unit = ?, total = ?, obs = ?
    WHERE id = ?
  ");
  $updVenda->execute([$data, $cliente_id, $produto_id, $canal, $pagamento, $qtd, $preco, $total, $obs, $id]);

  $pdo->commit();
  flash_set('success', 'Venda atualizada com sucesso!');
  redirect('../../../vendas.php');

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  flash_set('danger', 'Erro ao salvar venda: ' . $e->getMessage());
  redirect('../../../vendas.php');
}

?>