<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  $csrf = (string)($_POST['csrf'] ?? '');
  if (!csrf_ok($csrf)) json_out(['ok'=>false,'msg'=>'CSRF inválido. Recarregue a página.'], 403);

  if (empty($_FILES['arquivo']['tmp_name'])) json_out(['ok'=>false,'msg'=>'Nenhum arquivo enviado.'], 400);

  $raw = file_get_contents($_FILES['arquivo']['tmp_name']);
  $arr = json_decode($raw ?: '[]', true);
  if (!is_array($arr)) json_out(['ok'=>false,'msg'=>'JSON inválido (esperado um array).'], 400);

  $pdo = pdo();
  $pdo->beginTransaction();

  $ins = $pdo->prepare("INSERT INTO fornecedores (nome,status,doc,tel,email,endereco,cidade,uf,contato,obs)
                        VALUES (?,?,?,?,?,?,?,?,?,?)");
  $upd = $pdo->prepare("UPDATE fornecedores
                        SET nome=?, status=?, doc=?, tel=?, email=?, endereco=?, cidade=?, uf=?, contato=?, obs=?
                        WHERE id=?");
  $chk = $pdo->prepare("SELECT id FROM fornecedores WHERE id=?");

  $ok = 0;

  foreach ($arr as $x) {
    if (!is_array($x)) continue;

    $id = (int)($x['id'] ?? 0);
    $r = norm_row($x);

    if ($r['nome'] === '') continue;

    if ($id > 0) {
      $chk->execute([$id]);
      $exists = (bool)$chk->fetchColumn();
      if ($exists) {
        $upd->execute([$r['nome'],$r['status'],$r['doc'],$r['tel'],$r['email'],$r['endereco'],$r['cidade'],$r['uf'],$r['contato'],$r['obs'],$id]);
        $ok++;
        continue;
      }
    }

    $ins->execute([$r['nome'],$r['status'],$r['doc'],$r['tel'],$r['email'],$r['endereco'],$r['cidade'],$r['uf'],$r['contato'],$r['obs']]);
    $ok++;
  }

  $pdo->commit();
  json_out(['ok'=>true,'msg'=>"Importado: {$ok} registro(s)."]);
} catch (Throwable $e) {
  try { if (isset($pdo) && $pdo instanceof PDO) $pdo->rollBack(); } catch (Throwable $e2) {}
  json_out(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
}

?>