<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  $body = read_json_body();
  if (!csrf_ok($body['csrf'] ?? null)) json_out(['ok'=>false,'msg'=>'CSRF inválido. Recarregue a página.'], 403);

  $r = norm_row($body);
  if ($r['nome'] === '') json_out(['ok'=>false,'msg'=>'Informe o nome / razão social.'], 400);

  $pdo = pdo();
  $st = $pdo->prepare("INSERT INTO fornecedores (nome,status,doc,tel,email,endereco,cidade,uf,contato,obs)
                       VALUES (?,?,?,?,?,?,?,?,?,?)");
  $st->execute([$r['nome'],$r['status'],$r['doc'],$r['tel'],$r['email'],$r['endereco'],$r['cidade'],$r['uf'],$r['contato'],$r['obs']]);

  json_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
  json_out(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
}

?>