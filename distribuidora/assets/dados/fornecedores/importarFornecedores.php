<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  csrf_check($_POST['csrf_token'] ?? null);

  if (empty($_FILES['arquivo']['tmp_name'])) {
    flash_set('danger', 'Nenhum arquivo enviado.');
    redirect_fornecedores();
  }

  $raw = file_get_contents($_FILES['arquivo']['tmp_name']);
  $arr = json_decode($raw ?: '[]', true);

  if (!is_array($arr)) {
    flash_set('danger', 'JSON inválido (esperado um array).');
    redirect_fornecedores();
  }

  $pdo = pdo();
  $pdo->beginTransaction();

  $chk = $pdo->prepare("SELECT id FROM fornecedores WHERE id=?");

  $ins = $pdo->prepare("INSERT INTO fornecedores (nome,status,doc,tel,email,endereco,cidade,uf,contato,obs)
                        VALUES (?,?,?,?,?,?,?,?,?,?)");

  $upd = $pdo->prepare("UPDATE fornecedores
                        SET nome=?, status=?, doc=?, tel=?, email=?, endereco=?, cidade=?, uf=?, contato=?, obs=?
                        WHERE id=?");

  $ok = 0;

  foreach ($arr as $x) {
    if (!is_array($x)) continue;

    $id = (int)($x['id'] ?? 0);
    $nome = trim((string)($x['nome'] ?? ''));
    if ($nome === '') continue;

    $status   = only_status((string)($x['status'] ?? 'ATIVO'));
    $doc      = trim((string)($x['doc'] ?? ''));
    $tel      = trim((string)($x['tel'] ?? ''));
    $email    = trim((string)($x['email'] ?? ''));
    $endereco = trim((string)($x['endereco'] ?? ''));
    $cidade   = trim((string)($x['cidade'] ?? ''));
    $uf       = strtoupper(substr(trim((string)($x['uf'] ?? '')), 0, 2));
    $contato  = trim((string)($x['contato'] ?? ''));
    $obs      = trim((string)($x['obs'] ?? ''));

    if ($id > 0) {
      $chk->execute([$id]);
      $exists = (bool)$chk->fetchColumn();
      if ($exists) {
        $upd->execute([$nome,$status,$doc,$tel,$email,$endereco,$cidade,$uf,$contato,$obs,$id]);
        $ok++;
        continue;
      }
    }

    $ins->execute([$nome,$status,$doc,$tel,$email,$endereco,$cidade,$uf,$contato,$obs]);
    $ok++;
  }

  $pdo->commit();
  flash_set('success', "Importação concluída: {$ok} registro(s) processado(s).");
  redirect_fornecedores();

} catch (Throwable $e) {
  try { if (isset($pdo) && $pdo instanceof PDO) $pdo->rollBack(); } catch (Throwable $e2) {}
  fail($e->getMessage());
}

?>