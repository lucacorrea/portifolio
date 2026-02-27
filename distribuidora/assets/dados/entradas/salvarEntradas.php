<?php
declare(strict_types=1);

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

function backPage(): void { redirect_to('../../../entradas.php'); }

function parse_money_br(string $s): float {
  $s = trim($s);
  if ($s === '') return 0.0;
  $s = str_replace(['R$', ' ', '.'], ['', '', ''], $s);
  $s = str_replace(',', '.', $s);
  $n = (float)$s;
  return $n < 0 ? 0.0 : $n;
}

try {
  require_post_or_redirect('../../../entradas.php');
  csrf_validate_or_redirect('../../../entradas.php');

  $id = (int)($_POST['id'] ?? 0);

  $data = trim((string)($_POST['data'] ?? ''));
  $nf = trim((string)($_POST['nf'] ?? ''));
  $fornecedorId = (int)($_POST['fornecedor_id'] ?? 0);
  $produtoId = (int)($_POST['produto_id'] ?? 0);
  $unidade = trim((string)($_POST['unidade'] ?? ''));
  $qtd = (int)($_POST['qtd'] ?? 0);
  $custo = parse_money_br((string)($_POST['custo'] ?? '0'));

  if ($data === '' || $nf === '' || $fornecedorId <= 0 || $produtoId <= 0 || $unidade === '') {
    flash_set('danger', 'Preencha os campos obrigatórios.');
    backPage();
  }

  if ($qtd < 0) $qtd = 0;
  $total = (float)$qtd * (float)$custo;

  $pdo = db();
  $pdo->beginTransaction();

  // valida produto
  $stP = $pdo->prepare("SELECT id, nome FROM produtos WHERE id = :id LIMIT 1");
  $stP->execute([':id' => $produtoId]);
  $prod = $stP->fetch(PDO::FETCH_ASSOC);
  if (!$prod) {
    $pdo->rollBack();
    flash_set('danger', 'Produto não encontrado.');
    backPage();
  }

  // valida fornecedor
  $stF = $pdo->prepare("SELECT id, nome FROM fornecedores WHERE id = :id LIMIT 1");
  $stF->execute([':id' => $fornecedorId]);
  $forn = $stF->fetch(PDO::FETCH_ASSOC);
  if (!$forn) {
    $pdo->rollBack();
    flash_set('danger', 'Fornecedor não encontrado.');
    backPage();
  }

  if ($id > 0) {
    // edição: pega antigo
    $oldSt = $pdo->prepare("SELECT produto_id, qtd FROM entradas WHERE id = :id LIMIT 1");
    $oldSt->execute([':id' => $id]);
    $old = $oldSt->fetch(PDO::FETCH_ASSOC);

    if (!$old) {
      $pdo->rollBack();
      flash_set('danger', 'Entrada não encontrada.');
      backPage();
    }

    $oldProdutoId = (int)$old['produto_id'];
    $oldQtd = (int)$old['qtd'];

    // atualiza entrada
    $up = $pdo->prepare("
      UPDATE entradas
      SET data = :data, nf = :nf, fornecedor_id = :fornecedor_id, produto_id = :produto_id,
          unidade = :unidade, qtd = :qtd, custo = :custo, total = :total
      WHERE id = :id
    ");
    $up->execute([
      ':data' => $data,
      ':nf' => $nf,
      ':fornecedor_id' => $fornecedorId,
      ':produto_id' => $produtoId,
      ':unidade' => $unidade,
      ':qtd' => $qtd,
      ':custo' => $custo,
      ':total' => $total,
      ':id' => $id
    ]);

    // ajusta estoque
    if ($oldProdutoId === $produtoId) {
      $delta = $qtd - $oldQtd; // soma ou subtrai diferença
      if ($delta !== 0) {
        $stk = $pdo->prepare("UPDATE produtos SET estoque = estoque + :delta WHERE id = :id");
        $stk->execute([':delta' => $delta, ':id' => $produtoId]);
      }
    } else {
      // devolve antigo
      $stk1 = $pdo->prepare("UPDATE produtos SET estoque = estoque - :qtd WHERE id = :id");
      $stk1->execute([':qtd' => $oldQtd, ':id' => $oldProdutoId]);

      // aplica novo
      $stk2 = $pdo->prepare("UPDATE produtos SET estoque = estoque + :qtd WHERE id = :id");
      $stk2->execute([':qtd' => $qtd, ':id' => $produtoId]);
    }

    $pdo->commit();
    flash_set('success', 'Entrada atualizada com sucesso!');
    backPage();
  }

  // novo
  $ins = $pdo->prepare("
    INSERT INTO entradas (data, nf, fornecedor_id, produto_id, unidade, qtd, custo, total)
    VALUES (:data, :nf, :fornecedor_id, :produto_id, :unidade, :qtd, :custo, :total)
  ");
  $ins->execute([
    ':data' => $data,
    ':nf' => $nf,
    ':fornecedor_id' => $fornecedorId,
    ':produto_id' => $produtoId,
    ':unidade' => $unidade,
    ':qtd' => $qtd,
    ':custo' => $custo,
    ':total' => $total
  ]);

  // soma estoque
  $stk = $pdo->prepare("UPDATE produtos SET estoque = estoque + :qtd WHERE id = :id");
  $stk->execute([':qtd' => $qtd, ':id' => $produtoId]);

  $pdo->commit();
  flash_set('success', 'Entrada lançada com sucesso!');
  backPage();

} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
  flash_set('danger', 'Erro ao salvar entrada.');
  backPage();
}

?>