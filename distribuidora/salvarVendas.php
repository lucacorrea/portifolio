<?php
declare(strict_types=1);

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok' => false, 'msg' => 'Método inválido.'], 405);
}

$pdo = db();
$in = json_input();

$csrf = (string)($in['csrf_token'] ?? '');
if (!csrf_validate_token($csrf)) {
  json_response(['ok' => false, 'msg' => 'CSRF inválido. Recarregue a página.'], 403);
}

$customer = trim((string)($in['customer'] ?? ''));
$customer = $customer !== '' ? $customer : 'CONSUMIDOR FINAL';

$delivery = $in['delivery'] ?? [];
$deliveryMode = strtoupper(trim((string)($delivery['mode'] ?? 'PRESENCIAL')));
$deliveryMode = $deliveryMode === 'DELIVERY' ? 'DELIVERY' : 'PRESENCIAL';

$address = trim((string)($delivery['address'] ?? ''));
$obs = trim((string)($delivery['obs'] ?? ''));
$fee = ($deliveryMode === 'DELIVERY') ? to_float($delivery['fee'] ?? 0) : 0.0;

if ($deliveryMode === 'DELIVERY' && $address === '') {
  json_response(['ok' => false, 'msg' => 'Informe o endereço do Delivery.'], 422);
}

$discount = $in['discount'] ?? [];
$dTipo = strtoupper(trim((string)($discount['tipo'] ?? 'PERC')));
$dTipo = in_array($dTipo, ['PERC', 'VALOR'], true) ? $dTipo : 'PERC';
$dValor = to_float($discount['valor'] ?? 0);

$items = $in['items'] ?? [];
if (!is_array($items) || count($items) < 1) {
  json_response(['ok' => false, 'msg' => 'Adicione pelo menos 1 item.'], 422);
}

$pay = $in['pay'] ?? [];
$payMode = strtoupper(trim((string)($pay['mode'] ?? 'UNICO')));
$payMode = $payMode === 'MULTI' ? 'MULTI' : 'UNICO';

$today = date('Y-m-d');
$nowBR = date('d/m/Y H:i');

function calc_discount(float $sub, string $tipo, float $valor): float {
  if ($valor <= 0) return 0.0;
  if ($tipo === 'PERC') {
    $p = min(100.0, max(0.0, $valor));
    return ($sub * $p) / 100.0;
  }
  return min($sub, max(0.0, $valor));
}

try {
  $pdo->beginTransaction();

  // cria cabeçalho da venda (valores serão atualizados ao final)
  $pagamentoStr = 'DINHEIRO';
  if ($payMode === 'UNICO') {
    $pagamentoStr = strtoupper(trim((string)($pay['method'] ?? 'DINHEIRO')));
  } else {
    $pagamentoStr = 'MULTI';
  }

  $stmtV = $pdo->prepare("
    INSERT INTO vendas (data, cliente, canal, endereco, obs, desconto_tipo, desconto_valor, taxa_entrega,
                        subtotal, total, pagamento_mode, pagamento, pagamento_json)
    VALUES (:data, :cliente, :canal, :endereco, :obs, :dtipo, :dvalor, :taxa,
            0, 0, :pmode, :pag, :pjson)
  ");
  $stmtV->execute([
    ':data'    => $today,
    ':cliente' => $customer,
    ':canal'   => $deliveryMode,
    ':endereco'=> ($deliveryMode === 'DELIVERY') ? $address : null,
    ':obs'     => ($deliveryMode === 'DELIVERY' && $obs !== '') ? $obs : null,
    ':dtipo'   => $dTipo,
    ':dvalor'  => $dValor,
    ':taxa'    => $fee,
    ':pmode'   => $payMode,
    ':pag'     => $pagamentoStr,
    ':pjson'   => null,
  ]);

  $vendaId = (int)$pdo->lastInsertId();
  if ($vendaId <= 0) throw new RuntimeException('Falha ao criar venda.');

  $subtotal = 0.0;
  $outItems = [];

  $stmtP = $pdo->prepare("SELECT id, codigo, nome, unidade, preco, estoque, status FROM produtos WHERE id = :id FOR UPDATE");
  $stmtS = $pdo->prepare("
    INSERT INTO saidas (data, pedido, cliente, canal, pagamento, produto_id, unidade, qtd, preco, total)
    VALUES (:data, :pedido, :cliente, :canal, :pagamento, :produto_id, :unidade, :qtd, :preco, :total)
  ");
  $stmtUpd = $pdo->prepare("UPDATE produtos SET estoque = estoque - :qtd WHERE id = :id");

  foreach ($items as $it) {
    if (!is_array($it)) continue;

    $pid = to_int($it['product_id'] ?? 0);
    $qty = to_int($it['qty'] ?? 0);

    if ($pid <= 0 || $qty <= 0) {
      throw new RuntimeException('Item inválido no carrinho.');
    }

    $stmtP->execute([':id' => $pid]);
    $p = $stmtP->fetch();

    if (!$p) throw new RuntimeException("Produto ID {$pid} não encontrado.");
    if (($p['status'] ?? '') !== 'ATIVO') throw new RuntimeException("Produto {$p['nome']} está inativo.");

    $estoque = (int)($p['estoque'] ?? 0);
    if ($qty > $estoque) {
      throw new RuntimeException("Estoque insuficiente: {$p['nome']} (disp. {$estoque}).");
    }

    $preco = (float)($p['preco'] ?? 0);
    $linhaTotal = $preco * $qty;
    $subtotal += $linhaTotal;

    $stmtS->execute([
      ':data'      => $today,
      ':pedido'    => (string)$vendaId,     // pedido = ID da venda
      ':cliente'   => $customer,
      ':canal'     => $deliveryMode,
      ':pagamento' => $pagamentoStr,
      ':produto_id'=> $pid,
      ':unidade'   => (string)($p['unidade'] ?? ''),
      ':qtd'       => $qty,
      ':preco'     => $preco,
      ':total'     => $linhaTotal,
    ]);

    $stmtUpd->execute([':qtd' => $qty, ':id' => $pid]);

    $outItems[] = [
      'product_id' => $pid,
      'code' => (string)$p['codigo'],
      'name' => (string)$p['nome'],
      'qty' => $qty,
      'price' => $preco,
      'total' => $linhaTotal,
    ];
  }

  $desc = calc_discount($subtotal, $dTipo, $dValor);
  $total = max(0.0, ($subtotal - $desc) + $fee);

  if ($total <= 0.0) {
    throw new RuntimeException('Total inválido.');
  }

  // valida pagamento no servidor
  $troco = 0.0;

  if ($payMode === 'UNICO') {
    $method = strtoupper(trim((string)($pay['method'] ?? 'DINHEIRO')));
    $paid = to_float($pay['paid'] ?? 0);

    if ($method === 'DINHEIRO') {
      if ($paid + 0.0001 < $total) throw new RuntimeException('No dinheiro, valor pago deve ser >= total.');
      $troco = max(0.0, $paid - $total);
    } else {
      if (abs($paid - $total) > 0.01) throw new RuntimeException('Para Pix/Cartão/Boleto, valor pago deve ser igual ao total.');
    }

    $payJson = [
      'mode' => 'UNICO',
      'method' => $method,
      'paid' => $paid,
      'troco' => $troco,
    ];
    $pagamentoStr = $method;

  } else {
    $parts = $pay['parts'] ?? [];
    if (!is_array($parts) || count($parts) < 1) throw new RuntimeException('Pagamento múltiplo inválido.');

    $sum = 0.0;
    $hasCash = false;
    $normParts = [];

    foreach ($parts as $p) {
      if (!is_array($p)) continue;
      $m = strtoupper(trim((string)($p['method'] ?? 'PIX')));
      $v = to_float($p['value'] ?? 0);
      if ($v <= 0) continue;

      if ($m === 'DINHEIRO') $hasCash = true;
      $sum += $v;
      $normParts[] = ['method' => $m, 'value' => $v];
    }

    if ($sum <= 0) throw new RuntimeException('Informe valores de pagamento.');

    $diff = $sum - $total; // pag - total
    $ok = false;

    if (abs($diff) <= 0.01) {
      $ok = true;
      $troco = 0.0;
    } elseif ($diff > 0.01 && $hasCash) {
      $ok = true;
      $troco = $diff;
    }

    if (!$ok) throw new RuntimeException('Pagamento múltiplo inválido. Ajuste os valores.');

    $payJson = [
      'mode' => 'MULTI',
      'parts' => $normParts,
      'sum' => $sum,
      'troco' => $troco,
    ];
    $pagamentoStr = 'MULTI';
  }

  // atualiza cabeçalho com valores finais + json pagamento
  $stmtUpV = $pdo->prepare("
    UPDATE vendas
    SET subtotal = :sub,
        total = :total,
        desconto_tipo = :dtipo,
        desconto_valor = :desc,
        taxa_entrega = :fee,
        pagamento_mode = :pmode,
        pagamento = :pag,
        pagamento_json = :pjson
    WHERE id = :id
  ");
  $stmtUpV->execute([
    ':sub' => $subtotal,
    ':total' => $total,
    ':dtipo' => $dTipo,
    ':desc' => $desc,
    ':fee' => $fee,
    ':pmode' => $payMode,
    ':pag' => $pagamentoStr,
    ':pjson' => json_encode($payJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ':id' => $vendaId,
  ]);

  $pdo->commit();

  json_response([
    'ok' => true,
    'msg' => "Venda #{$vendaId} confirmada!",
    'sale' => [
      'no' => $vendaId,
      'date' => $nowBR,
      'customer' => $customer,
      'delivery' => [
        'mode' => $deliveryMode,
        'address' => ($deliveryMode === 'DELIVERY') ? $address : '',
        'fee' => $fee,
        'obs' => ($deliveryMode === 'DELIVERY') ? $obs : '',
      ],
      'discount' => ['tipo' => $dTipo, 'valor' => $dValor, 'aplicado' => $desc],
      'totals' => ['sub' => $subtotal, 'desc' => $desc, 'fee' => $fee],
      'total' => $total,
      'pay' => $payJson,
      'items' => $outItems,
      'print_url' => "cupom.php?id={$vendaId}",
    ],
    'next' => $vendaId + 1,
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_response(['ok' => false, 'msg' => $e->getMessage()], 422);
}

?>