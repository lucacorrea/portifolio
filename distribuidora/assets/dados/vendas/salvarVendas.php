<?php
declare(strict_types=1);

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/_helpers.php';

$pdo = db();

// Auto-patch: Create tables if not exists (Outside transaction to avoid implicit commit)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nome VARCHAR(255) NOT NULL,
      cpf VARCHAR(20),
      telefone VARCHAR(20),
      endereco TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS fiados (
      id INT AUTO_INCREMENT PRIMARY KEY,
      venda_id INT NOT NULL,
      cliente_id INT NOT NULL,
      valor_total DECIMAL(10,2) NOT NULL,
      valor_pago DECIMAL(10,2) DEFAULT 0.00,
      valor_restante DECIMAL(10,2) NOT NULL,
      status VARCHAR(20) DEFAULT 'ABERTO',
      data_vencimento DATE,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Throwable $e) {
}

function fail(string $msg, int $code = 400): void
{
    json_response(['ok' => false, 'msg' => $msg], $code);
}

/**
 * Resolve a data/hora do dispositivo enviada pelo front.
 *
 * Aceita preferencialmente:
 *   device_datetime => "YYYY-MM-DD HH:MM:SS"
 *
 * Também tenta aceitar:
 *   "YYYY-MM-DDTHH:MM:SS"
 *   ISO 8601
 *
 * Se não vier nada válido, cai no horário do servidor.
 */
function resolve_device_datetime(array $payload): array
{
    $raw = trim((string)($payload['device_datetime'] ?? ''));

    if ($raw !== '') {
        $raw = str_replace('T', ' ', $raw);

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
            $raw .= ':00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) {
            return [
                'datetime' => $raw,
                'date'     => substr($raw, 0, 10),
            ];
        }

        try {
            $dt = new DateTimeImmutable($raw);
            return [
                'datetime' => $dt->format('Y-m-d H:i:s'),
                'date'     => $dt->format('Y-m-d'),
            ];
        } catch (Throwable $e) {
        }
    }

    $now = new DateTimeImmutable('now');
    return [
        'datetime' => $now->format('Y-m-d H:i:s'),
        'date'     => $now->format('Y-m-d'),
    ];
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

$deviceDt = resolve_device_datetime($payload);
$dataVenda = $deviceDt['date'];
$createdAt = $deviceDt['datetime'];

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
$temFiado = false;

if ($pagMode === 'UNICO') {
    $method = strtoupper(trim((string)($pay['method'] ?? 'DINHEIRO')));
    if (!in_array($method, ['DINHEIRO', 'PIX', 'CARTAO', 'FIADO'], true)) $method = 'DINHEIRO';
    $paid = (float)to_float($pay['paid'] ?? 0);

    $ok = false;
    $troco = 0.0;

    if ($method === 'DINHEIRO') {
        $ok = ($paid + 0.009) >= $total;
        $troco = $ok ? max(0.0, $paid - $total) : 0.0;
    } elseif ($method === 'FIADO') {
        $ok = true;
        $troco = 0.0;
        $temFiado = true;
    } else {
        $ok = abs($paid - $total) < 0.01;
        $troco = 0.0;
    }

    if (!$ok) {
        if ($method === 'DINHEIRO') fail('No dinheiro, o valor pago deve ser maior/igual ao total.');
        fail('Para Pix/Cartão, o valor pago deve ser igual ao total.');
    }

    $pagamento = $method;
    $pagamentoJson = json_encode([
        'mode'   => 'UNICO',
        'method' => $method,
        'paid'   => $paid,
        'total'  => $total,
        'troco'  => $troco,
        'fiado'  => $payload['pay']['fiado'] ?? null
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    $parts = $pay['parts'] ?? [];
    if (!is_array($parts) || count($parts) < 1) fail('Pagamento múltiplo inválido.');

    $rows = [];
    foreach ($parts as $pt) {
        if (!is_array($pt)) continue;
        $m = strtoupper(trim((string)($pt['method'] ?? 'PIX')));
        if (!in_array($m, ['DINHEIRO', 'PIX', 'CARTAO', 'FIADO'], true)) $m = 'PIX';
        $v = (float)to_float($pt['value'] ?? 0);
        if ($v > 0) {
            $rows[] = ['method' => $m, 'value' => $v];
            if ($m === 'FIADO') $temFiado = true;
        }
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

    if (abs($diff) < 0.01) {
        $ok = true;
    } elseif ($diff > 0.01 && $hasCash) {
        $ok = true;
        $troco = $diff;
    }

    if (!$ok) fail('Pagamento múltiplo inválido. Ajuste os valores.');

    $pagamento = 'MULTI';
    $pagamentoJson = json_encode([
        'mode'  => 'MULTI',
        'parts' => $rows,
        'sum'   => $sum,
        'total' => $total,
        'diff'  => $diff,
        'troco' => $troco
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Validation for Fiado client
if ($temFiado) {
    if (!isset($payload['client_id']) || (int)$payload['client_id'] <= 0) {
        fail('Venda à prazo exige um cliente cadastrado.');
    }
}

// salva no banco
try {
    $pdo->beginTransaction();

    $stVenda = $pdo->prepare("
        INSERT INTO vendas
          (data, cliente, canal, endereco, obs,
           desconto_tipo, desconto_valor, taxa_entrega,
           subtotal, total,
           pagamento_mode, pagamento, pagamento_json, created_at)
        VALUES
          (:data_venda, :cliente, :canal, :endereco, :obs,
           :dtipo, :dvalor, :taxa,
           :sub, :total,
           :pmode, :pag, :pjson, :created_at)
    ");

    $stVenda->execute([
        ':data_venda' => $dataVenda,
        ':cliente'    => ($customer !== '' ? $customer : null),
        ':canal'      => $canal,
        ':endereco'   => ($endereco !== '' ? $endereco : null),
        ':obs'        => ($obs !== '' ? $obs : null),
        ':dtipo'      => $descontoTipo,
        ':dvalor'     => $desconto,
        ':taxa'       => $taxaEntrega,
        ':sub'        => $subtotal,
        ':total'      => $total,
        ':pmode'      => $pagMode,
        ':pag'        => $pagamento,
        ':pjson'      => $pagamentoJson,
        ':created_at' => $createdAt,
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
            ':venda_id'   => $saleId,
            ':produto_id' => (int)$it['produto_id'],
            ':codigo'     => (string)$it['codigo'],
            ':nome'       => (string)$it['nome'],
            ':unidade'    => ($it['unidade'] !== '' ? (string)$it['unidade'] : null),
            ':preco_unit' => (float)$it['preco_unit'],
            ':qtd'        => (int)$it['qtd'],
            ':subtotal'   => (float)$it['subtotal'],
        ]);

        $stEstoque->execute([
            ':q'  => (int)$it['qtd'],
            ':id' => (int)$it['produto_id'],
        ]);
    }

    // --- REGISTRO DE FIADO ---
    if ($temFiado) {
        $clientId = (int)($payload['client_id'] ?? 0);
        $debtValue = $total;
        $paidValue = 0.0;

        if ($pagMode === 'UNICO' && isset($payload['pay']['fiado']) && is_array($payload['pay']['fiado'])) {
            $debtValue = (float)($payload['pay']['fiado']['debt_value'] ?? $total);
            $paidValue = (float)($payload['pay']['fiado']['entry_value'] ?? 0);
        } elseif ($pagMode === 'MULTI') {
            $pts = json_decode((string)$pagamentoJson, true)['parts'] ?? [];
            $debtValue = 0.0;
            $paidValue = 0.0;

            foreach ($pts as $p) {
                $m = strtoupper((string)($p['method'] ?? ''));
                $v = (float)($p['value'] ?? 0);

                if ($m === 'FIADO') $debtValue += $v;
                else $paidValue += $v;
            }
        }

        $stFiado = $pdo->prepare("
            INSERT INTO fiados
              (venda_id, cliente_id, valor_total, valor_pago, valor_restante, status, created_at)
            VALUES
              (?, ?, ?, ?, ?, 'ABERTO', ?)
        ");

        $stFiado->execute([
            $saleId,
            $clientId,
            $debtValue + $paidValue,
            $paidValue,
            $debtValue,
            $createdAt
        ]);
    }
    // -------------------------

    if ($pdo->inTransaction()) $pdo->commit();

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