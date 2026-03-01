<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@error_reporting(0);

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/_helpers.php';

$pdo = db();

function fail(string $msg, int $code = 400): void {
  json_response(['ok' => false, 'msg' => $msg], $code);
}

$payload = json_input();
if (!$payload) fail('JSON inválido.');

$csrf = (string)($payload['csrf_token'] ?? '');
if (!csrf_validate_token($csrf)) fail('CSRF inválido. Recarregue a página.', 403);

$items = $payload['items'] ?? null;
if (!is_array($items) || count($items) < 1) fail('Adicione pelo menos 1 item.');

$customer = trim((string)($payload['customer'] ?? ''));
$delivery = $payload['delivery'] ?? [];
$discount = $payload['discount'] ?? [];
$pay      = $payload['pay'] ?? [];

$canal = strtoupper(trim((string)($delivery['mode'] ?? 'PRESENCIAL')));
if (!in_array($canal, ['PRESENCIAL', 'DELIVERY'], true)) $canal = 'PRESENCIAL';

$endereco = $canal === 'DELIVERY' ? trim((string)($delivery['address'] ?? '')) : '';
$taxaEntrega = $canal === 'DELIVERY' ? (float)to_float($delivery['fee'] ?? 0) : 0.0;
$obs = $canal === 'DELIVERY' ? trim((string)($delivery['obs'] ?? '')) : '';

if ($canal === 'DELIVERY' && $endereco === '') fail('Informe o endereço do Delivery.');

$descontoTipo = strtoupper(trim((string)($discount['tipo'] ?? 'PERC')));
if (!in_array($descontoTipo, ['PERC', 'VALOR'], true)) $descontoTipo = 'PERC';
$descontoValorRaw = (float)to_float($discount['valor'] ?? 0);

$pagMode = strtoupper(trim((string)($pay['mode'] ?? 'UNICO')));
if (!in_array($pagMode, ['UNICO', 'MULTI'], true)) $pagMode = 'UNICO';

/**
 * Estoque: por padrão NÃO bloqueia vender com estoque 0/negativo.
 * Se quiser bloquear, mude para true.
 */
$BLOQUEAR_ESTOQUE_NEGATIVO = false;

// normaliza itens (ids/qty)
$norm = [];
foreach ($items as $it) {
  if (!is_array($it)) continue;
  $pid = to_int($it['product_id'] ?? 0);
  $qty = max(1, to_int($it['qty'] ?? 1, 1));
  if ($pid > 0) $norm[$pid] = ($norm[$pid] ?? 0) + $qty;
}
if (!$norm) fail('Itens inválidos.');

// carrega produtos do banco
$ids = array_keys($norm);
$in  = implode(',', array_fill(0, count($ids), '?'));

$sql = "SELECT id, codigo, nome, unidade, preco, estoque, status
        FROM produtos
        WHERE id IN ($in)";
$st = $pdo->prepare($sql);
$st->execute($ids);
$prods = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$map = [];
foreach ($prods as $p) {
  $map[(int)$p['id']] = $p;
}

foreach ($ids as $pid) {
  if (!isset($map[$pid])) fail("Produto ID {$pid} não encontrado.");
  $status = strtoupper(trim((string)($map[$pid]['status'] ?? 'ATIVO')));
  if ($status !== '' && $status !== 'ATIVO') fail("Produto '{$map[$pid]['nome']}' está INATIVO.");
  $est = (int)($map[$pid]['estoque'] ?? 0);
  $q  = (int)$norm[$pid];
  if ($BLOQUEAR_ESTOQUE_NEGATIVO && $est < $q) {
    fail("Estoque insuficiente para '{$map[$pid]['nome']}'. Estoque: {$est}, pedido: {$q}.");
  }
}

// calcula subtotal pelos preços do banco
$subtotal = 0.0;
$itensDetalhe = [];
foreach ($ids as $pid) {
  $p = $map[$pid];
  $q = (int)$norm[$pid];
  $preco = (float)$p['preco'];
  $sub = $preco * $q;
  $subtotal += $sub;

  $itensDetalhe[] = [
    'produto_id' => (int)$pid,
    'codigo'     => (string)$p['codigo'],
    'nome'       => (string)$p['nome'],
    'unidade'    => (string)($p['unidade'] ?? ''),
    'preco_unit' => $preco,
    'qtd'        => $q,
    'subtotal'   => $sub,
  ];
}

// desconto
$desconto = 0.0;
if ($descontoValorRaw > 0) {
  if ($descontoTipo === 'PERC') {
    $perc = min(100.0, max(0.0, $descontoValorRaw));
    $desconto = ($subtotal * $perc) / 100.0;
  } else {
    $desconto = min($subtotal, max(0.0, $descontoValorRaw));
  }
}

$total = max(0.0, ($subtotal - $desconto) + max(0.0, $taxaEntrega));
if ($total <= 0.0) fail('Total inválido.');

// valida pagamento
$pagamento = 'DINHEIRO';
$pagamentoJson = null;

if ($pagMode === 'UNICO') {
  $method = strtoupper(trim((string)($pay['method'] ?? 'DINHEIRO')));
  if (!in_array($method, ['DINHEIRO', 'PIX', 'CARTAO', 'BOLETO'], true)) $method = 'DINHEIRO';
  $paid = (float)to_float($pay['paid'] ?? 0);

  $ok = false;
  $troco = 0.0;

  if ($method === 'DINHEIRO') {
    $ok = ($paid + 0.009) >= $total;
    $troco = $ok ? max(0.0, $paid - $total) : 0.0;
  } else {
    $ok = abs($paid - $total) < 0.01;
    $troco = 0.0;
  }

  if (!$ok) {
    if ($method === 'DINHEIRO') fail('No dinheiro, o valor pago deve ser maior/igual ao total.');
    fail('Para Pix/Cartão/Boleto, o valor pago deve ser igual ao total.');
  }

  $pagamento = $method;
  $pagamentoJson = json_encode([
    'mode' => 'UNICO',
    'method' => $method,
    'paid' => $paid,
    'total' => $total,
    'troco' => $troco,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} else { // MULTI
  $parts = $pay['parts'] ?? [];
  if (!is_array($parts) || count($parts) < 1) fail('Pagamento múltiplo inválido.');

  $rows = [];
  foreach ($parts as $pt) {
    if (!is_array($pt)) continue;
    $m = strtoupper(trim((string)($pt['method'] ?? 'PIX')));
    if (!in_array($m, ['DINHEIRO', 'PIX', 'CARTAO', 'BOLETO'], true)) $m = 'PIX';
    $v = (float)to_float($pt['value'] ?? 0);
    if ($v > 0) $rows[] = ['method' => $m, 'value' => $v];
  }

  if (!$rows) fail('Informe os valores do pagamento múltiplo.');

  $sum = 0.0;
  $hasCash = false;
  foreach ($rows as $r) {
    $sum += (float)$r['value'];
    if ($r['method'] === 'DINHEIRO') $hasCash = true;
  }

  $diff = $sum - $total;
  $ok = false;
  $troco = 0.0;

  if (abs($diff) < 0.01) $ok = true;
  else if ($diff > 0.01 && $hasCash) { $ok = true; $troco = $diff; }

  if (!$ok) fail('Pagamento múltiplo inválido. Ajuste os valores.');

  $pagamento = 'MULTI';
  $pagamentoJson = json_encode([
    'mode' => 'MULTI',
    'parts' => $rows,
    'sum' => $sum,
    'total' => $total,
    'diff' => $diff,
    'troco' => $troco
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// salva no banco
try {
  $pdo->beginTransaction();

  $stVenda = $pdo->prepare("
    INSERT INTO vendas
      (data, cliente, canal, endereco, obs,
       desconto_tipo, desconto_valor, taxa_entrega,
       subtotal, total,
       pagamento_mode, pagamento, pagamento_json)
    VALUES
      (CURDATE(), :cliente, :canal, :endereco, :obs,
       :dtipo, :dvalor, :taxa,
       :sub, :total,
       :pmode, :pag, :pjson)
  ");

  $stVenda->execute([
    ':cliente' => ($customer !== '' ? $customer : null),
    ':canal'   => $canal,
    ':endereco'=> ($endereco !== '' ? $endereco : null),
    ':obs'     => ($obs !== '' ? $obs : null),
    ':dtipo'   => $descontoTipo,
    ':dvalor'  => $desconto,
    ':taxa'    => $taxaEntrega,
    ':sub'     => $subtotal,
    ':total'   => $total,
    ':pmode'   => $pagMode,
    ':pag'     => $pagamento,
    ':pjson'   => $pagamentoJson,
  ]);

  $saleId = (int)$pdo->lastInsertId();

  $stItem = $pdo->prepare("
    INSERT INTO venda_itens
      (venda_id, produto_id, codigo, nome, unidade, preco_unit, qtd, subtotal)
    VALUES
      (:venda_id, :produto_id, :codigo, :nome, :unidade, :preco_unit, :qtd, :subtotal)
  ");

  $stEstoque = $pdo->prepare("UPDATE produtos SET estoque = estoque - :q WHERE id = :id");

  foreach ($itensDetalhe as $it) {
    $stItem->execute([
      ':venda_id'  => $saleId,
      ':produto_id'=> (int)$it['produto_id'],
      ':codigo'    => (string)$it['codigo'],
      ':nome'      => (string)$it['nome'],
      ':unidade'   => ($it['unidade'] !== '' ? (string)$it['unidade'] : null),
      ':preco_unit'=> (float)$it['preco_unit'],
      ':qtd'       => (int)$it['qtd'],
      ':subtotal'  => (float)$it['subtotal'],
    ]);

    // baixa estoque
    $stEstoque->execute([
      ':q'  => (int)$it['qtd'],
      ':id' => (int)$it['produto_id'],
    ]);
  }

  $pdo->commit();

  // próximo número
  $next = (int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM vendas")->fetchColumn();

  json_response([
    'ok' => true,
    'msg' => 'Venda confirmada com sucesso!',
    'sale' => [
      'id' => $saleId,
      'no' => $saleId,
      'print_url' => "assets/dados/vendas/cupom.php?id={$saleId}&auto=1"
    ],
    'next' => $next
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fail('Erro ao salvar venda: ' . $e->getMessage(), 500);
}

?>