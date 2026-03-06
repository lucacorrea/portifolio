<?php
declare(strict_types=1);

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

function backInv(): void { redirect_to('../../../inventario.php'); }

try {
  require_post_or_redirect('../../../inventario.php');
  csrf_validate_or_redirect('../../../inventario.php');

  $produtoId = (int)($_POST['produto_id'] ?? 0);
  if ($produtoId <= 0) {
    flash_set('danger', 'Produto inválido.');
    backInv();
  }

  $raw = $_POST['contagem'] ?? '';
  $raw = is_string($raw) ? trim($raw) : '';

  $contagem = null;
  if ($raw !== '') {
    if (!ctype_digit($raw)) {
      flash_set('danger', 'Contagem inválida.');
      backInv();
    }
    $contagem = (int)$raw;
    if ($contagem < 0) $contagem = 0;
  }

  $pdo = db();

  $st = $pdo->prepare("SELECT estoque, nome FROM produtos WHERE id = :id LIMIT 1");
  $st->execute([':id' => $produtoId]);
  $p = $st->fetch(PDO::FETCH_ASSOC);

  if (!$p) {
    flash_set('danger', 'Produto não encontrado.');
    backInv();
  }

  $sistema = (int)($p['estoque'] ?? 0);

  if ($contagem === null) {
    $diferenca = null;
    $situacao = 'NAO_CONFERIDO';
  } else {
    $diferenca = $contagem - $sistema;
    $situacao = ($diferenca === 0) ? 'OK' : 'DIVERGENTE';
  }

  $sql = "
    INSERT INTO inventario_itens (produto_id, contagem, diferenca, situacao, created_at, updated_at)
    VALUES (:produto_id, :contagem, :diferenca, :situacao, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
      contagem = VALUES(contagem),
      diferenca = VALUES(diferenca),
      situacao = VALUES(situacao),
      updated_at = NOW()
  ";
  $q = $pdo->prepare($sql);
  $q->bindValue(':produto_id', $produtoId, PDO::PARAM_INT);

  if ($contagem === null) $q->bindValue(':contagem', null, PDO::PARAM_NULL);
  else $q->bindValue(':contagem', $contagem, PDO::PARAM_INT);

  if ($diferenca === null) $q->bindValue(':diferenca', null, PDO::PARAM_NULL);
  else $q->bindValue(':diferenca', (int)$diferenca, PDO::PARAM_INT);

  $q->bindValue(':situacao', $situacao, PDO::PARAM_STR);
  $q->execute();

  flash_set('success', 'Inventário salvo: ' . (string)$p['nome']);
  backInv();
} catch (Throwable $e) {
  flash_set('danger', 'Erro ao salvar inventário.');
  backInv();
}

?>