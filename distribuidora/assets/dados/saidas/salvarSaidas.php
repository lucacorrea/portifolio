<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

$back = '../../../saidas.php';

require_post_or_redirect($back);
csrf_validate_or_redirect($back);

$pdo = db();

/** qtd pode vir com vírgula em alguns navegadores */
function to_float_qty($v): float {
  $s = trim((string)$v);
  if ($s === '') return 0.0;
  $s = str_replace(',', '.', $s);
  return (float)$s;
}

$id        = (int)($_POST['id'] ?? 0);
$data      = trim((string)($_POST['data'] ?? ''));
$tipo      = strtoupper(trim((string)($_POST['tipo'] ?? 'PERDA')));
$motivo    = trim((string)($_POST['motivo'] ?? ''));
$produtoId = (int)($_POST['produto_id'] ?? 0);
$unidade   = trim((string)($_POST['unidade'] ?? ''));
$qtd       = to_float_qty($_POST['qtd'] ?? 0);
$valorUnitTxt = (string)($_POST['valor_unit'] ?? '0');
$valorUnit = brl_to_float($valorUnitTxt);
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

  // trava produto
  $st = $pdo->prepare("SELECT id, estoque FROM produtos WHERE id = ? FOR UPDATE");
  $st->execute([$produtoId]);
  $prod = $st->fetch(PDO::FETCH_ASSOC);
  if (!$prod) throw new RuntimeException('Produto não encontrado.');

  if ($id > 0) {
    // trava saída antiga
    $oldSt = $pdo->prepare("SELECT id, produto_id, qtd FROM saidas WHERE id = ? FOR UPDATE");
    $oldSt->execute([$id]);
    $old = $oldSt->fetch(PDO::FETCH_ASSOC);
    if (!$old) throw new RuntimeException('Saída não encontrada.');

    $oldPid = (int)$old['produto_id'];
    $oldQtd = (float)$old['qtd'];

    if ($oldPid === $produtoId) {
      // ajusta apenas diferença
      $delta = $qtd - $oldQtd; // >0: baixar mais | <0: devolver
      $upd = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
      $upd->execute([$delta, $produtoId]);
    } else {
      // devolve no produto antigo
      $upd1 = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?");
      $upd1->execute([$oldQtd, $oldPid]);

      // baixa no novo produto
      $upd2 = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
      $upd2->execute([$qtd, $produtoId]);
    }

    // valida estoque >= 0
    $chk = $pdo->prepare("SELECT estoque FROM produtos WHERE id = ?");
    $chk->execute([$produtoId]);
    $stk = (float)$chk->fetchColumn();
    if ($stk < 0) throw new RuntimeException('Estoque insuficiente para registrar esta saída.');

    $up = $pdo->prepare("
      UPDATE saidas
      SET data=?, tipo=?, motivo=?, produto_id=?, unidade=?, qtd=?, valor_unit=?, valor_total=?, obs=?, updated_at=NOW()
      WHERE id=?
    ");
    $up->execute([$data, $tipo, $motivo, $produtoId, $unidade, $qtd, $valorUnit, $valorTotal, $obs, $id]);

    $pdo->commit();
    flash_set('success', 'Saída (perda/avaria) atualizada com sucesso!');
    redirect_to($back);
  }

  // NOVA saída -> baixa estoque
  $upd = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
  $upd->execute([$qtd, $produtoId]);

  $chk = $pdo->prepare("SELECT estoque FROM produtos WHERE id = ?");
  $chk->execute([$produtoId]);
  $stk = (float)$chk->fetchColumn();
  if ($stk < 0) throw new RuntimeException('Estoque insuficiente para registrar esta saída.');

  $ins = $pdo->prepare("
    INSERT INTO saidas (data, tipo, motivo, produto_id, unidade, qtd, valor_unit, valor_total, obs)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $ins->execute([$data, $tipo, $motivo, $produtoId, $unidade, $qtd, $valorUnit, $valorTotal, $obs]);

  $pdo->commit();
  flash_set('success', 'Saída (perda/avaria) registrada com sucesso!');
  redirect_to($back);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  flash_set('danger', 'Erro ao salvar: ' . $e->getMessage());
  redirect_to($back);
}

?>