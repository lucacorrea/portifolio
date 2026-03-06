<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

$back = '../../../saidas.php';

require_post_or_redirect($back);
csrf_validate_or_redirect($back);

$pdo = db();

function to_int_safe($v): int {
  $s = trim((string)$v);
  if ($s === '') return 0;
  if (!preg_match('/^-?\d+$/', $s)) {
    $s = preg_replace('/[^\d-]/', '', $s);
  }
  return (int)$s;
}

$id        = (int)($_POST['id'] ?? 0);
$data      = trim((string)($_POST['data'] ?? ''));
$tipo      = strtoupper(trim((string)($_POST['tipo'] ?? 'PERDA')));
$motivo    = trim((string)($_POST['motivo'] ?? ''));
$produtoId = (int)($_POST['produto_id'] ?? 0);
$unidade   = trim((string)($_POST['unidade'] ?? ''));
$qtd       = to_int_safe($_POST['qtd'] ?? 0);
$valorUnit = brl_to_float((string)($_POST['valor_unit'] ?? '0'));
$obs       = trim((string)($_POST['obs'] ?? ''));

$tiposOk = ['PERDA','AVARIA','VENCIDO','CONSUMO','AJUSTE','OUTROS'];
if (!in_array($tipo, $tiposOk, true)) $tipo = 'PERDA';

if ($data === '' || $motivo === '' || $produtoId <= 0 || $unidade === '') {
  flash_set('danger', 'Preencha Data, Motivo, Produto e Unidade.');
  redirect_to($back);
}
if ($qtd <= 0) {
  flash_set('danger', 'A quantidade deve ser maior que zero.');
  redirect_to($back);
}
if ($valorUnit < 0) {
  flash_set('danger', 'Valor unitário inválido.');
  redirect_to($back);
}

$valorTotal = $qtd * $valorUnit;

try {
  $pdo->beginTransaction();

  if ($id > 0) {
    // trava saída antiga
    $oldSt = $pdo->prepare("SELECT id, produto_id, qtd FROM saidas WHERE id = ? FOR UPDATE");
    $oldSt->execute([$id]);
    $old = $oldSt->fetch(PDO::FETCH_ASSOC);
    if (!$old) throw new RuntimeException('Saída não encontrada.');

    $oldPid = (int)$old['produto_id'];
    $oldQtd = (int)$old['qtd'];

    if ($oldPid === $produtoId) {
      // trava produto
      $pSt = $pdo->prepare("SELECT id, estoque FROM produtos WHERE id = ? FOR UPDATE");
      $pSt->execute([$produtoId]);
      $p = $pSt->fetch(PDO::FETCH_ASSOC);
      if (!$p) throw new RuntimeException('Produto não encontrado.');

      $estoqueAtual = (int)$p['estoque'];
      $delta = $qtd - $oldQtd; // >0 baixa mais | <0 devolve

      $novoEstoque = $estoqueAtual - $delta;
      if ($novoEstoque < 0) throw new RuntimeException('Estoque insuficiente para esta saída.');

      $upd = $pdo->prepare("UPDATE produtos SET estoque = ? WHERE id = ?");
      $upd->execute([$novoEstoque, $produtoId]);

    } else {
      // produto mudou: trava os 2 produtos
      $pOldSt = $pdo->prepare("SELECT id, estoque FROM produtos WHERE id = ? FOR UPDATE");
      $pOldSt->execute([$oldPid]);
      $pOld = $pOldSt->fetch(PDO::FETCH_ASSOC);
      if (!$pOld) throw new RuntimeException('Produto antigo não encontrado.');

      $pNewSt = $pdo->prepare("SELECT id, estoque FROM produtos WHERE id = ? FOR UPDATE");
      $pNewSt->execute([$produtoId]);
      $pNew = $pNewSt->fetch(PDO::FETCH_ASSOC);
      if (!$pNew) throw new RuntimeException('Produto novo não encontrado.');

      $oldEst = (int)$pOld['estoque'];
      $newEst = (int)$pNew['estoque'];

      // devolve no antigo
      $oldEst2 = $oldEst + $oldQtd;

      // baixa no novo
      $newEst2 = $newEst - $qtd;
      if ($newEst2 < 0) throw new RuntimeException('Estoque insuficiente para esta saída.');

      $upd1 = $pdo->prepare("UPDATE produtos SET estoque = ? WHERE id = ?");
      $upd1->execute([$oldEst2, $oldPid]);

      $upd2 = $pdo->prepare("UPDATE produtos SET estoque = ? WHERE id = ?");
      $upd2->execute([$newEst2, $produtoId]);
    }

    // atualiza a saída
    $up = $pdo->prepare("
      UPDATE saidas
      SET data=?, tipo=?, motivo=?, produto_id=?, unidade=?, qtd=?, valor_unit=?, valor_total=?, obs=?, updated_at=NOW()
      WHERE id=?
    ");
    $up->execute([$data, $tipo, $motivo, $produtoId, $unidade, $qtd, $valorUnit, $valorTotal, $obs, $id]);

    $pdo->commit();
    flash_set('success', 'Saída atualizada com sucesso!');
    redirect_to($back);
  }

  // NOVA: trava produto
  $pSt = $pdo->prepare("SELECT id, estoque FROM produtos WHERE id = ? FOR UPDATE");
  $pSt->execute([$produtoId]);
  $p = $pSt->fetch(PDO::FETCH_ASSOC);
  if (!$p) throw new RuntimeException('Produto não encontrado.');

  $estoqueAtual = (int)$p['estoque'];
  $novoEstoque = $estoqueAtual - $qtd;
  if ($novoEstoque < 0) throw new RuntimeException('Estoque insuficiente para esta saída.');

  $upd = $pdo->prepare("UPDATE produtos SET estoque = ? WHERE id = ?");
  $upd->execute([$novoEstoque, $produtoId]);

  $ins = $pdo->prepare("
    INSERT INTO saidas (data, tipo, motivo, produto_id, unidade, qtd, valor_unit, valor_total, obs)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $ins->execute([$data, $tipo, $motivo, $produtoId, $unidade, $qtd, $valorUnit, $valorTotal, $obs]);

  $pdo->commit();
  flash_set('success', 'Saída registrada com sucesso!');
  redirect_to($back);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  flash_set('danger', 'Erro ao salvar: ' . $e->getMessage());
  redirect_to($back);
}

?>