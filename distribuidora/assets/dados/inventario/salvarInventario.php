<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

$back = '../../../inventario.php';

require_post_or_redirect($back);
csrf_validate_or_redirect($back);

$pdo = db();

$produtoId = (int)($_POST['produto_id'] ?? 0);
$contagemRaw = trim((string)($_POST['contagem'] ?? ''));

if ($produtoId <= 0) {
  flash_set('danger', 'Produto inválido.');
  redirect_to($back);
}

$hasCount = ($contagemRaw !== '');
$contagem = null;

if ($hasCount) {
  if (!preg_match('/^\d+$/', $contagemRaw)) {
    flash_set('danger', 'Contagem inválida.');
    redirect_to($back);
  }
  $contagem = (int)$contagemRaw;
  if ($contagem < 0) $contagem = 0;
}

try {
  $pdo->beginTransaction();

  // estoque do produto
  $p = $pdo->prepare("SELECT id, estoque FROM produtos WHERE id = ? LIMIT 1");
  $p->execute([$produtoId]);
  $prod = $p->fetch(PDO::FETCH_ASSOC);
  if (!$prod) throw new RuntimeException('Produto não encontrado.');

  $estoque = (int)$prod['estoque'];

  // vendas e saídas
  $calc = $pdo->prepare("
    SELECT
      COALESCE((SELECT SUM(qtd) FROM venda_itens WHERE produto_id = :pid), 0) AS vendas,
      COALESCE((SELECT SUM(qtd) FROM saidas WHERE produto_id = :pid), 0) AS saidas
  ");
  $calc->execute([':pid' => $produtoId]);
  $r = $calc->fetch(PDO::FETCH_ASSOC) ?: ['vendas'=>0,'saidas'=>0];

  $vendas = (int)$r['vendas'];
  $saidas = (int)$r['saidas'];

  $soma = $estoque + $vendas + $saidas;

  if (!$hasCount) {
    // NAO_CONFERIDO
    $up = $pdo->prepare("
      INSERT INTO inventario_itens (produto_id, contagem, diferenca, situacao)
      VALUES (?, NULL, NULL, 'NAO_CONFERIDO')
      ON DUPLICATE KEY UPDATE
        contagem = NULL,
        diferenca = NULL,
        situacao = 'NAO_CONFERIDO',
        updated_at = NOW()
    ");
    $up->execute([$produtoId]);

    $pdo->commit();
    flash_set('success', 'Item marcado como NÃO CONFERIDO.');
    redirect_to($back);
  }

  $diferenca = (int)$contagem - (int)$soma;
  $situacao = ($diferenca === 0) ? 'OK' : 'DIVERGENTE';

  $st = $pdo->prepare("
    INSERT INTO inventario_itens (produto_id, contagem, diferenca, situacao)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      contagem = VALUES(contagem),
      diferenca = VALUES(diferenca),
      situacao = VALUES(situacao),
      updated_at = NOW()
  ");
  $st->execute([$produtoId, $contagem, $diferenca, $situacao]);

  $pdo->commit();
  flash_set('success', "Inventário salvo! Situação: {$situacao}.");
  redirect_to($back);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  flash_set('danger', 'Erro ao salvar inventário: ' . $e->getMessage());
  redirect_to($back);
}

?>