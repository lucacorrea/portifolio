<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  csrf_check($_POST['csrf_token'] ?? null);

  if (empty($_FILES['arquivo']['tmp_name'])) {
    flash_set('danger', 'Nenhum arquivo enviado.');
    redirect_categorias();
  }

  $raw = file_get_contents($_FILES['arquivo']['tmp_name']);
  $arr = json_decode($raw ?: '[]', true);

  if (!is_array($arr)) {
    flash_set('danger', 'JSON inválido (esperado um array).');
    redirect_categorias();
  }

  $pdo = pdo();
  $pdo->beginTransaction();

  $chk = $pdo->prepare("SELECT id FROM categorias WHERE id=?");

  $ins = $pdo->prepare("INSERT INTO categorias (nome, descricao, cor, obs, status)
                        VALUES (?,?,?,?,?)");

  $upd = $pdo->prepare("UPDATE categorias
                        SET nome=?, descricao=?, cor=?, obs=?, status=?
                        WHERE id=?");

  $ok = 0;

  foreach ($arr as $x) {
    if (!is_array($x)) continue;

    $id = (int)($x['id'] ?? 0);
    $nome = trim((string)($x['nome'] ?? ''));
    if ($nome === '') continue;

    $descricao = trim((string)($x['descricao'] ?? $x['desc'] ?? ''));
    $cor = norm_hex((string)($x['cor'] ?? '#60a5fa'));
    $obs = trim((string)($x['obs'] ?? ''));
    $status = only_status((string)($x['status'] ?? 'ATIVO'));

    if ($id > 0) {
      $chk->execute([$id]);
      $exists = (bool)$chk->fetchColumn();
      if ($exists) {
        $upd->execute([$nome, $descricao, $cor, $obs, $status, $id]);
        $ok++;
        continue;
      }
    }

    $ins->execute([$nome, $descricao, $cor, $obs, $status]);
    $ok++;
  }

  $pdo->commit();
  flash_set('success', "Importação concluída: {$ok} registro(s) processado(s).");
  redirect_categorias();

} catch (Throwable $e) {
  try { if (isset($pdo) && $pdo instanceof PDO) $pdo->rollBack(); } catch (Throwable $e2) {}
  fail_page($e->getMessage());
}

?>