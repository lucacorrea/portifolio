<?php
declare(strict_types=1);

/**
 * vendas.php (PDV) - funcional com MySQL (tabelas: produtos/saidas/fornecedores/categorias/entradas/inventario_itens)
 * Requer:
 *   - ./assets/conexao.php  (função db():PDO)
 *   - ./assets/dados/entradas/_helpers.php (helpers: e(), flash, csrf, redirect, etc.)
 */

date_default_timezone_set('America/Manaus');

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/vendas/_helpers.php';

$pdo  = db();
$csrf = csrf_token();
$flash = flash_pop();

/** ===== Helpers locais ===== */
function brDate(string $ymd): string {
    $ymd = trim($ymd);
    if (!$ymd) return '';
    $p = explode('-', $ymd);
    if (count($p) !== 3) return $ymd;
    return $p[2] . '/' . $p[1] . '/' . $p[0];
}
function fmtMoney($v): string {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
/**
 * Banco (produtos.imagem): images/arquivo.png
 * Exibir em páginas na raiz: assets/dados/produtos/images/arquivo.png
 */
function img_url_from_db(string $dbValue): string {
    $v = trim($dbValue);
    if ($v === '') return '';
    if (preg_match('~^(https?://|/|assets/)~i', $v)) return $v;
    $v = ltrim($v, '/');
    return 'assets/dados/produtos/' . $v;
}
function json_out(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function read_json_body(): array {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw ?: '', true);
    return is_array($j) ? $j : [];
}
function csrf_ok_from_payload(array $payload): bool {
    $posted = (string)($payload['csrf_token'] ?? $_POST['csrf_token'] ?? '');
    $sess   = (string)($_SESSION['csrf_token'] ?? '');
    return ($posted !== '' && $sess !== '' && hash_equals($sess, $posted));
}
function money_to_cents($val): int {
    // aceita "0,00", "0.00", "R$ 0,00"
    $s = trim((string)$val);
    if ($s === '') return 0;
    $s = preg_replace('/[^\d,.\-]/', '', $s);
    $s = str_replace('.', '', $s);      // remove milhar
    $s = str_replace(',', '.', $s);     // decimal
    $n = (float)$s;
    return (int) round($n * 100);
}
function cents_to_money(int $cents): float {
    return $cents / 100;
}

/** ====== ENDPOINTS (AJAX) ====== */
$action = (string)($_GET['action'] ?? '');

/**
 * GET vendas.php?action=search&q=...
 * Retorna produtos para o dropdown de sugestões.
 */
if ($action === 'search') {
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '') json_out(['ok' => true, 'items' => []]);

    $like = '%' . $q . '%';

    $st = $pdo->prepare("
        SELECT
            p.id, p.codigo, p.nome, p.unidade, p.preco, p.estoque, p.imagem,
            c.nome AS categoria_nome,
            f.nome AS fornecedor_nome
        FROM produtos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
        WHERE p.status = 'ATIVO'
          AND (p.nome LIKE :q OR p.codigo LIKE :q)
        ORDER BY
            CASE WHEN p.codigo = :qExact THEN 0 ELSE 1 END,
            p.nome ASC
        LIMIT 30
    ");
    $st->execute([
        ':q' => $like,
        ':qExact' => $q,
    ]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $items = array_map(function ($r) {
        $img = img_url_from_db((string)($r['imagem'] ?? ''));
        return [
            'id' => (int)$r['id'],
            'code' => (string)$r['codigo'],
            'name' => (string)$r['nome'],
            'unidade' => (string)($r['unidade'] ?? ''),
            'price' => (float)($r['preco'] ?? 0),
            'stock' => (int)($r['estoque'] ?? 0),
            'img' => $img,
            'categoria' => (string)($r['categoria_nome'] ?? ''),
            'fornecedor' => (string)($r['fornecedor_nome'] ?? ''),
        ];
    }, $rows);

    json_out(['ok' => true, 'items' => $items]);
}

/**
 * GET vendas.php?action=last
 * Retorna últimos pedidos (agrupados por saidas.pedido).
 */
if ($action === 'last') {
    $rows = $pdo->query("
        SELECT
            pedido,
            MAX(data) AS data,
            MIN(cliente) AS cliente,
            MIN(canal) AS canal,
            MIN(pagamento) AS pagamento,
            SUM(total) AS total,
            MAX(id) AS last_id
        FROM saidas
        GROUP BY pedido
        ORDER BY last_id DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    $items = array_map(fn($r) => [
        'pedido' => (string)$r['pedido'],
        'data' => brDate((string)$r['data']),
        'cliente' => (string)$r['cliente'],
        'canal' => (string)$r['canal'],
        'pagamento' => (string)$r['pagamento'],
        'total' => (float)$r['total'],
    ], $rows);

    json_out(['ok' => true, 'items' => $items]);
}

/**
 * GET vendas.php?action=get_pedido&pedido=...
 * Retorna detalhes do pedido para reimpressão.
 */
if ($action === 'get_pedido') {
    $pedido = trim((string)($_GET['pedido'] ?? ''));
    if ($pedido === '') json_out(['ok' => false, 'msg' => 'Pedido inválido.'], 400);

    $st = $pdo->prepare("
        SELECT
            s.id, s.data, s.pedido, s.cliente, s.canal, s.pagamento,
            s.produto_id, s.unidade, s.qtd, s.preco, s.total,
            p.codigo AS produto_codigo,
            p.nome AS produto_nome
        FROM saidas s
        LEFT JOIN produtos p ON p.id = s.produto_id
        WHERE s.pedido = :pedido
        ORDER BY s.id ASC
    ");
    $st->execute([':pedido' => $pedido]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) json_out(['ok' => false, 'msg' => 'Pedido não encontrado.'], 404);

    $sum = 0.0;
    $items = [];
    foreach ($rows as $r) {
        $sum += (float)$r['total'];
        $items[] = [
            'code' => (string)($r['produto_codigo'] ?? ''),
            'name' => (string)($r['produto_nome'] ?? ''),
            'qty' => (int)($r['qtd'] ?? 0),
            'price' => (float)($r['preco'] ?? 0),
            'total' => (float)($r['total'] ?? 0),
            'unidade' => (string)($r['unidade'] ?? ''),
        ];
    }

    $head = $rows[0];
    json_out([
        'ok' => true,
        'sale' => [
            'pedido' => (string)$head['pedido'],
            'date' => brDate((string)$head['data']),
            'customer' => (string)$head['cliente'],
            'canal' => (string)$head['canal'],
            'pagamento' => (string)$head['pagamento'],
            'items' => $items,
            'total' => $sum,
        ]
    ]);
}

/**
 * POST vendas.php?action=confirm
 * Body JSON:
 *  {
 *    csrf_token,
 *    customer,
 *    delivery:{mode,address,fee,obs},
 *    discount:{type,value},
 *    pay:{mode,method,paid,parts:[{method,value}]},
 *    items:[{id,qty}]
 *  }
 */
if ($action === 'confirm') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_out(['ok' => false, 'msg' => 'Método inválido.'], 405);
    }

    $payload = read_json_body();

    if (!csrf_ok_from_payload($payload)) {
        json_out(['ok' => false, 'msg' => 'CSRF inválido. Recarregue a página.'], 403);
    }

    $itemsIn = $payload['items'] ?? [];
    if (!is_array($itemsIn) || count($itemsIn) === 0) {
        json_out(['ok' => false, 'msg' => 'Adicione pelo menos 1 item.'], 400);
    }

    // normaliza cliente / canal
    $customer = trim((string)($payload['customer'] ?? ''));
    if ($customer === '') $customer = 'CONSUMIDOR FINAL';

    $delivery = is_array($payload['delivery'] ?? null) ? $payload['delivery'] : [];
    $deliveryMode = strtoupper(trim((string)($delivery['mode'] ?? 'PRESENCIAL')));
    if (!in_array($deliveryMode, ['PRESENCIAL', 'DELIVERY'], true)) $deliveryMode = 'PRESENCIAL';

    $deliveryAddress = trim((string)($delivery['address'] ?? ''));
    $deliveryFeeCents = ($deliveryMode === 'DELIVERY') ? max(0, money_to_cents($delivery['fee'] ?? '0')) : 0;

    if ($deliveryMode === 'DELIVERY' && $deliveryAddress === '') {
        json_out(['ok' => false, 'msg' => 'Informe o endereço do Delivery.'], 400);
    }

    // desconto
    $discount = is_array($payload['discount'] ?? null) ? $payload['discount'] : [];
    $dType = strtoupper(trim((string)($discount['type'] ?? 'PERC')));
    if (!in_array($dType, ['PERC', 'VALOR'], true)) $dType = 'PERC';
    $dValue = (string)($discount['value'] ?? '0');

    // pagamento
    $pay = is_array($payload['pay'] ?? null) ? $payload['pay'] : [];
    $payMode = strtoupper(trim((string)($pay['mode'] ?? 'UNICO')));
    if (!in_array($payMode, ['UNICO', 'MULTI'], true)) $payMode = 'UNICO';

    $payMethod = strtoupper(trim((string)($pay['method'] ?? 'DINHEIRO')));
    if (!in_array($payMethod, ['DINHEIRO', 'PIX', 'CARTAO', 'BOLETO'], true)) $payMethod = 'DINHEIRO';

    // itens: pegar ids únicos
    $idQty = [];
    foreach ($itemsIn as $it) {
        if (!is_array($it)) continue;
        $id = (int)($it['id'] ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        if ($id <= 0 || $qty <= 0) continue;
        $idQty[$id] = ($idQty[$id] ?? 0) + $qty; // soma caso repetido
    }
    if (!$idQty) json_out(['ok' => false, 'msg' => 'Itens inválidos.'], 400);

    $ids = array_keys($idQty);
    $place = implode(',', array_fill(0, count($ids), '?'));

    $today = date('Y-m-d');

    // gerar pedido
    $pedido = 'PDV-' . date('Ymd-His') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);

    // pagamento (campo VARCHAR(30) na tabela)
    $pagamentoDb = ($payMode === 'MULTI') ? 'MULTI' : $payMethod;
    $canalDb = $deliveryMode;

    $pdo->beginTransaction();
    try {
        // trava produtos (estoque)
        $stP = $pdo->prepare("
            SELECT id, codigo, nome, unidade, preco, estoque, status
            FROM produtos
            WHERE id IN ($place)
              AND status = 'ATIVO'
            FOR UPDATE
        ");
        $stP->execute($ids);
        $prods = $stP->fetchAll(PDO::FETCH_ASSOC);

        if (count($prods) !== count($ids)) {
            throw new RuntimeException('Alguns produtos não foram encontrados ou estão inativos.');
        }

        // monta mapa
        $map = [];
        foreach ($prods as $p) $map[(int)$p['id']] = $p;

        // valida estoque e calcula base em cents
        $lines = [];
        $baseTotalCents = 0;
        foreach ($idQty as $pid => $qty) {
            $p = $map[$pid] ?? null;
            if (!$p) throw new RuntimeException('Produto inválido.');

            $stock = (int)($p['estoque'] ?? 0);
            if ($qty > $stock) {
                throw new RuntimeException("Estoque insuficiente para: {$p['nome']} (disp.: {$stock}).");
            }

            $priceCents = (int) round(((float)$p['preco']) * 100);
            $lineCents = (int) round($qty * $priceCents);
            $baseTotalCents += $lineCents;

            $lines[] = [
                'produto_id' => $pid,
                'codigo' => (string)$p['codigo'],
                'nome' => (string)$p['nome'],
                'unidade' => (string)($p['unidade'] ?? 'UN'),
                'qty' => $qty,
                'price' => (float)$p['preco'],
                'base_cents' => $lineCents,
                'total_cents' => 0,
            ];
        }

        if ($baseTotalCents <= 0) throw new RuntimeException('Total inválido.');

        // desconto cents
        $discountCents = 0;
        if ($dType === 'PERC') {
            $perc = (float)str_replace(',', '.', preg_replace('/[^\d,.\-]/', '', $dValue));
            if ($perc > 0) {
                $perc = min(100.0, $perc);
                $discountCents = (int) round($baseTotalCents * ($perc / 100.0));
            }
        } else {
            $discountCents = max(0, min($baseTotalCents, money_to_cents($dValue)));
        }

        $finalTotalCents = max(0, ($baseTotalCents - $discountCents) + $deliveryFeeCents);
        if ($finalTotalCents <= 0) throw new RuntimeException('Total inválido.');

        // distribui total final proporcionalmente às linhas (para bater com soma no DB)
        $sumAlloc = 0;
        for ($i = 0; $i < count($lines); $i++) {
            $share = $lines[$i]['base_cents'] / $baseTotalCents;
            $lines[$i]['total_cents'] = (int) round($finalTotalCents * $share);
            $sumAlloc += $lines[$i]['total_cents'];
        }
        // ajusta diferença por arredondamento
        $diff = $finalTotalCents - $sumAlloc;
        $lines[count($lines) - 1]['total_cents'] += $diff;

        // valida pagamento do PDV (servidor)
        if ($payMode === 'UNICO') {
            $paidCents = money_to_cents($pay['paid'] ?? '0');
            if ($payMethod === 'DINHEIRO') {
                if ($paidCents < $finalTotalCents) throw new RuntimeException('No dinheiro, o valor pago deve ser >= total.');
            } else {
                if (abs($paidCents - $finalTotalCents) > 1) throw new RuntimeException('Para Pix/Cartão/Boleto, o valor pago deve ser igual ao total.');
            }
        } else {
            $parts = is_array($pay['parts'] ?? null) ? $pay['parts'] : [];
            $sum = 0;
            $hasCash = false;
            foreach ($parts as $pt) {
                if (!is_array($pt)) continue;
                $m = strtoupper(trim((string)($pt['method'] ?? '')));
                $v = money_to_cents($pt['value'] ?? '0');
                if ($v <= 0) continue;
                $sum += $v;
                if ($m === 'DINHEIRO') $hasCash = true;
            }
            if ($sum < $finalTotalCents) throw new RuntimeException('Pagamento múltiplo insuficiente.');
            if ($sum > $finalTotalCents && !$hasCash) throw new RuntimeException('Se passar do total, precisa ter Dinheiro (troco).');
        }

        // INSERT saidas (1 linha por item)
        $stIns = $pdo->prepare("
            INSERT INTO saidas
              (data, pedido, cliente, canal, pagamento, produto_id, unidade, qtd, preco, total)
            VALUES
              (:data, :pedido, :cliente, :canal, :pagamento, :produto_id, :unidade, :qtd, :preco, :total)
        ");

        // UPDATE estoque
        $stUp = $pdo->prepare("UPDATE produtos SET estoque = estoque - :qtd WHERE id = :id");

        foreach ($lines as $ln) {
            $stIns->execute([
                ':data' => $today,
                ':pedido' => $pedido,
                ':cliente' => $customer,
                ':canal' => $canalDb,
                ':pagamento' => $pagamentoDb,
                ':produto_id' => $ln['produto_id'],
                ':unidade' => $ln['unidade'] ?: 'UN',
                ':qtd' => $ln['qty'], // DECIMAL(10,3) ok (aqui inteiro)
                ':preco' => $ln['price'],
                ':total' => cents_to_money((int)$ln['total_cents']),
            ]);
            $stUp->execute([':qtd' => $ln['qty'], ':id' => $ln['produto_id']]);
        }

        $pdo->commit();

        // resposta para UI + cupom
        $saleOut = [
            'pedido' => $pedido,
            'date' => brDate($today),
            'customer' => $customer,
            'canal' => $canalDb,
            'pagamento' => $pagamentoDb,
            'delivery' => [
                'mode' => $deliveryMode,
                'address' => $deliveryAddress,
                'fee' => cents_to_money($deliveryFeeCents),
            ],
            'totals' => [
                'base' => cents_to_money($baseTotalCents),
                'discount' => cents_to_money($discountCents),
                'fee' => cents_to_money($deliveryFeeCents),
            ],
            'total' => cents_to_money($finalTotalCents),
            'items' => array_map(fn($ln) => [
                'code' => $ln['codigo'],
                'name' => $ln['nome'],
                'qty' => $ln['qty'],
                'price' => $ln['price'],
                'total' => cents_to_money((int)$ln['total_cents']),
                'unidade' => $ln['unidade'],
            ], $lines),
        ];

        json_out(['ok' => true, 'sale' => $saleOut]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_out(['ok' => false, 'msg' => $e->getMessage()], 400);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Vendas (PDV)</title>

    <!-- ========== CSS ========= -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <style>
        /* dropdown do profile: largura acompanha conteúdo */
        .profile-box .dropdown-menu { width: max-content; min-width: 260px; max-width: calc(100vw - 24px); }
        .profile-box .dropdown-menu .author-info { width: max-content; max-width: 100%; display: flex !important; align-items: center; gap: 10px; }
        .profile-box .dropdown-menu .author-info .content { min-width: 0; max-width: 100%; }
        .profile-box .dropdown-menu .author-info .content a { display: inline-block; white-space: nowrap; max-width: 100%; }

        /* Botões compactos */
        .main-btn.btn-compact { height: 38px !important; padding: 8px 14px !important; font-size: 13px !important; line-height: 1 !important; }
        .main-btn.btn-compact i { font-size: 14px; vertical-align: -1px; }
        .icon-btn { height: 34px !important; width: 42px !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }
        .form-control.compact, .form-select.compact { height: 38px; padding: 8px 12px; font-size: 13px; }

        /* PDV layout (altura alinhada) */
        .pdv-row { align-items: stretch; }
        .pdv-left-col, .pdv-right-col { height: 100%; display: flex; flex-direction: column; }

        .pdv-card { border: 1px solid rgba(148, 163, 184, .28); border-radius: 16px; background: #fff; overflow: hidden; }
        .pdv-card .pdv-head { padding: 12px 14px; border-bottom: 1px solid rgba(148, 163, 184, .22); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .pdv-card .pdv-body { padding: 14px; }

        .pdv-card.pdv-search { overflow: visible; position: relative; z-index: 50; }
        .pdv-card.items-card { flex: 1 1 auto; min-height: 520px; display: flex; flex-direction: column; }
        .pdv-card.items-card .pdv-body { flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; }

        .items-scroll { flex: 1 1 auto; min-height: 320px; overflow: auto; -webkit-overflow-scrolling: touch; border-radius: 12px; }
        .pdv-right-col .pdv-card { height: 100%; display: flex; flex-direction: column; }
        .pdv-right-col .checkout-body { flex: 1 1 auto; min-height: 0; overflow: auto; -webkit-overflow-scrolling: touch; }

        /* Busca e sugestões */
        .search-wrap { position: relative; }
        .suggest {
            position: absolute; z-index: 9999; left: 0; right: 0; top: calc(100% + 6px);
            background: #fff; border: 1px solid rgba(148, 163, 184, .25); border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .10);
            max-height: 340px; overflow-y: auto; overflow-x: hidden; display: none;
            -webkit-overflow-scrolling: touch; overscroll-behavior: contain;
        }
        .suggest .it { padding: 10px 12px; display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .suggest .it:hover { background: rgba(241, 245, 249, .9); }
        .suggest .it.disabled { opacity: .55; cursor: not-allowed; }
        .pimg { width: 38px; height: 38px; border-radius: 10px; object-fit: cover; border: 1px solid rgba(148, 163, 184, .30); background: #fff; flex: 0 0 auto; }
        .it .meta { min-width: 0; flex: 1 1 auto; }
        .it .meta .t { font-weight: 900; font-size: 13px; color: #0f172a; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .it .meta .s { font-size: 12px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .it .price { font-weight: 900; font-size: 13px; color: #0f172a; white-space: nowrap; }
        .it .stk { font-weight: 900; font-size: 11px; color: #334155; background: rgba(241,245,249,.9); border: 1px solid rgba(148,163,184,.25); padding: 4px 8px; border-radius: 999px; white-space: nowrap; }

        /* Preview */
        .preview-box { width: 100%; height: 130px; border-radius: 16px; border: 1px dashed rgba(148, 163, 184, .55); background: rgba(248, 250, 252, .7); display: flex; align-items: center; justify-content: center; padding: 10px; text-align: center; }
        .preview-box img { width: 86px; height: 86px; border-radius: 16px; object-fit: cover; border: 1px solid rgba(148, 163, 184, .30); background: #fff; margin-bottom: 6px; }
        .preview-name { font-weight: 900; font-size: 12px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }

        /* Tabela itens */
        .table td, .table th { vertical-align: middle; }
        .table-responsive { -webkit-overflow-scrolling: touch; }
        #tbItens { width: 100%; min-width: 720px; }
        #tbItens th, #tbItens td { white-space: nowrap !important; }

        .qty-ctrl { display: inline-flex; align-items: center; gap: 6px; }
        .qty-btn { height: 34px !important; width: 34px !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 10px !important; }
        .qty-pill { width: 64px !important; height: 34px !important; text-align: center; font-weight: 900; border: 1px solid rgba(148, 163, 184, .30); border-radius: 10px; padding: 4px 6px; background: #fff; font-size: 13px; }
        .qty-pill::-webkit-outer-spin-button, .qty-pill::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .qty-pill { -moz-appearance: textfield; }

        /* Checkout */
        .checkout-head { background: #0b5ed7; color: #fff; padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .checkout-head h6 { margin: 0; font-weight: 900; letter-spacing: .2px; }
        .checkout-body { padding: 14px; }

        .pay-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .pay-btn { border: 1px solid rgba(148, 163, 184, .35); background: #fff; border-radius: 12px; padding: 12px 12px; display: flex; align-items: center; gap: 10px; justify-content: flex-start; font-weight: 900; cursor: pointer; user-select: none; transition: .12s ease; min-height: 44px; }
        .pay-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(15, 23, 42, .08); }
        .pay-btn.active { outline: 2px solid rgba(37, 99, 235, .35); border-color: rgba(37, 99, 235, .55); background: rgba(239, 246, 255, .65); }
        .pay-btn i { font-size: 18px; }

        .totals { border: 1px solid rgba(148, 163, 184, .25); border-radius: 14px; background: #fff; padding: 12px; }
        .tot-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 13px; color: #334155; margin-bottom: 8px; font-weight: 800; }
        .tot-row:last-child { margin-bottom: 0; }
        .tot-hr { height: 1px; background: rgba(148, 163, 184, .22); margin: 10px 0; }
        .grand { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-top: 6px; }
        .grand .lbl { font-weight: 900; color: #0f172a; font-size: 18px; }
        .grand .val { font-weight: 1000; color: #0b5ed7; font-size: 30px; letter-spacing: .2px; }

        .chip-toggle { display: flex; gap: 10px; flex-wrap: wrap; }
        .chip { border: 1px solid rgba(148, 163, 184, .35); border-radius: 999px; padding: 8px 12px; cursor: pointer; font-weight: 900; font-size: 12px; user-select: none; background: #fff; }
        .chip.active { background: rgba(239, 246, 255, .75); border-color: rgba(37, 99, 235, .55); outline: 2px solid rgba(37, 99, 235, .25); }
        .muted { font-size: 12px; color: #64748b; }

        .pay-split-row { border: 1px solid rgba(148, 163, 184, .25); border-radius: 14px; padding: 12px; background: #fff; margin-bottom: 10px; }
        .msg-ok { display: none; color: #16a34a; font-weight: 900; font-size: 12px; }
        .msg-err { display: none; color: #b91c1c; font-weight: 900; font-size: 12px; }

        /* Últimos cupons */
        .last-box { border: 1px solid rgba(148, 163, 184, .25); border-radius: 14px; overflow: hidden; background: #fff; margin-top: 12px; }
        .last-box .head { padding: 10px 12px; border-bottom: 1px solid rgba(148, 163, 184, .18); display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .last-box .head .t { font-weight: 900; font-size: 12px; color: #0f172a; text-transform: uppercase; letter-spacing: .4px; }
        .last-box .list { max-height: 220px; overflow: auto; }
        .cup { padding: 10px 12px; border-bottom: 1px solid rgba(148, 163, 184, .12); display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 12px; cursor: pointer; }
        .cup:last-child { border-bottom: none; }
        .cup:hover { background: rgba(248,250,252,.8); }
        .cup .left .n { font-weight: 900; color: #0f172a; }
        .cup .left .s { color: #64748b; font-size: 12px; }
        .cup .right { text-align: right; white-space: nowrap; }
        .cup .right .v { font-weight: 1000; color: #0b5ed7; }
        .cup .right .st { font-weight: 900; color: #16a34a; font-size: 11px; }

        @media (max-width: 991.98px) {
            .pay-grid { grid-template-columns: 1fr; }
            #tbItens { min-width: 720px; }
            .grand .val { font-size: 26px; }
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- ======== sidebar-nav start =========== -->
    <aside class="sidebar-nav-wrapper">
        <div class="navbar-logo">
            <a href="index.html" class="d-flex align-items-center gap-2">
                <img src="assets/images/logo/logo.svg" alt="logo" />
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item">
                    <a href="index.html">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M8.74999 18.3333C12.2376 18.3333 15.1364 15.8128 15.7244 12.4941C15.8448 11.8143 15.2737 11.25 14.5833 11.25H9.99999C9.30966 11.25 8.74999 10.6903 8.74999 10V5.41666C8.74999 4.7263 8.18563 4.15512 7.50586 4.27556C4.18711 4.86357 1.66666 7.76243 1.66666 11.25C1.66666 15.162 4.83797 18.3333 8.74999 18.3333Z" />
                                <path
                                    d="M17.0833 10C17.7737 10 18.3432 9.43708 18.2408 8.75433C17.7005 5.14918 14.8508 2.29947 11.2457 1.75912C10.5629 1.6568 10 2.2263 10 2.91665V9.16666C10 9.62691 10.3731 10 10.8333 10H17.0833Z" />
                            </svg>
                        </span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes"
                        aria-controls="ddmenu_operacoes" aria-expanded="true">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
                            </svg>
                        </span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse show dropdown-nav">
                        <li><a href="pedidos.html">Pedidos</a></li>
                        <li><a href="vendas.php" class="active">Vendas</a></li>
                        <li><a href="devolucoes.html">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque"
                        aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M2.49999 5.83331C2.03976 5.83331 1.66666 6.2064 1.66666 6.66665V10.8333C1.66666 13.5948 3.90523 15.8333 6.66666 15.8333H9.99999C12.1856 15.8333 14.0436 14.431 14.7235 12.4772C14.8134 12.4922 14.9058 12.5 15 12.5H16.6667C17.5872 12.5 18.3333 11.7538 18.3333 10.8333V8.33331C18.3333 7.41284 17.5872 6.66665 16.6667 6.66665H15C15 6.2064 14.6269 5.83331 14.1667 5.83331H2.49999Z" />
                                <path
                                    d="M2.49999 16.6667C2.03976 16.6667 1.66666 17.0398 1.66666 17.5C1.66666 17.9602 2.03976 18.3334 2.49999 18.3334H14.1667C14.6269 18.3334 15 17.9602 15 17.5C15 17.0398 14.6269 16.6667 14.1667 16.6667H2.49999Z" />
                            </svg>
                        </span>
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse dropdown-nav">
                        <li><a href="produtos.html">Produtos</a></li>
                        <li><a href="inventario.html">Inventário</a></li>
                        <li><a href="entradas.html">Entradas</a></li>
                        <li><a href="saidas.html">Saídas</a></li>
                        <li><a href="estoque-minimo.html">Estoque Mínimo</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros"
                        aria-controls="ddmenu_cadastros" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M1.66666 5.41669C1.66666 3.34562 3.34559 1.66669 5.41666 1.66669C7.48772 1.66669 9.16666 3.34562 9.16666 5.41669C9.16666 7.48775 7.48772 9.16669 5.41666 9.16669C3.34559 9.16669 1.66666 7.48775 1.66666 5.41669Z" />
                                <path
                                    d="M1.66666 14.5834C1.66666 12.5123 3.34559 10.8334 5.41666 10.8334C7.48772 10.8334 9.16666 12.5123 9.16666 14.5834C9.16666 16.6545 7.48772 18.3334 5.41666 18.3334C3.34559 18.3334 1.66666 16.6545 1.66666 14.5834Z" />
                                <path
                                    d="M10.8333 5.41669C10.8333 3.34562 12.5123 1.66669 14.5833 1.66669C16.6544 1.66669 18.3333 3.34562 18.3333 5.41669C18.3333 7.48775 16.6544 9.16669 14.5833 9.16669C12.5123 9.16669 10.8333 7.48775 10.8333 5.41669Z" />
                                <path
                                    d="M10.8333 14.5834C10.8333 12.5123 12.5123 10.8334 14.5833 10.8334C16.6544 10.8334 18.3333 12.5123 18.3333 14.5834C18.3333 16.6545 16.6544 18.3334 14.5833 18.3334C12.5123 18.3334 10.8333 16.6545 10.8333 14.5834Z" />
                            </svg>
                        </span>
                        <span class="text">Cadastros</span>
                    </a>
                    <ul id="ddmenu_cadastros" class="collapse dropdown-nav">
                        <li><a href="clientes.html">Clientes</a></li>
                        <li><a href="fornecedores.html">Fornecedores</a></li>
                        <li><a href="categorias.html">Categorias</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="relatorios.html">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M4.16666 3.33335C4.16666 2.41288 4.91285 1.66669 5.83332 1.66669H14.1667C15.0872 1.66669 15.8333 2.41288 15.8333 3.33335V16.6667C15.8333 17.5872 15.0872 18.3334 14.1667 18.3334H5.83332C4.91285 18.3334 4.16666 17.5872 4.16666 16.6667V3.33335Z" />
                            </svg>
                        </span>
                        <span class="text">Relatórios</span>
                    </a>
                </li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config"
                        aria-controls="ddmenu_config" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M10 1.66669C5.39763 1.66669 1.66666 5.39766 1.66666 10C1.66666 14.6024 5.39763 18.3334 10 18.3334C14.6024 18.3334 18.3333 14.6024 18.3333 10C18.3333 5.39766 14.6024 1.66669 10 1.66669Z" />
                            </svg>
                        </span>
                        <span class="text">Configurações</span>
                    </a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav">
                        <li><a href="usuarios.html">Usuários e Permissões</a></li>
                        <li><a href="parametros.html">Parâmetros do Sistema</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="suporte.html">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M10.8333 2.50008C10.8333 2.03984 10.4602 1.66675 9.99999 1.66675C9.53975 1.66675 9.16666 2.03984 9.16666 2.50008C9.16666 2.96032 9.53975 3.33341 9.99999 3.33341C10.4602 3.33341 10.8333 2.96032 10.8333 2.50008Z" />
                                <path
                                    d="M11.4272 2.69637C10.9734 2.56848 10.4947 2.50006 10 2.50006C7.10054 2.50006 4.75003 4.85057 4.75003 7.75006V9.20873C4.75003 9.72814 4.62082 10.2393 4.37404 10.6963L3.36705 12.5611C2.89938 13.4272 3.26806 14.5081 4.16749 14.9078C7.88074 16.5581 12.1193 16.5581 15.8326 14.9078C16.732 14.5081 17.1007 13.4272 16.633 12.5611L15.626 10.6963C15.43 10.3333 15.3081 9.93606 15.2663 9.52773C15.0441 9.56431 14.8159 9.58339 14.5833 9.58339C12.2822 9.58339 10.4167 7.71791 10.4167 5.41673C10.4167 4.37705 10.7975 3.42631 11.4272 2.69637Z" />
                            </svg>
                        </span>
                        <span class="text">Suporte</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <div class="overlay"></div>

    <main class="main-wrapper">
        <header class="header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-5 col-md-5 col-6">
                        <div class="header-left d-flex align-items-center">
                            <div class="menu-toggle-btn mr-15">
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-compact"
                                    type="button">
                                    <i class="lni lni-chevron-left me-2"></i> Menu
                                </button>
                            </div>
                            <div class="header-search d-none d-md-flex">
                                <form action="#">
                                    <input type="text" placeholder="Atalho: F4 pesquisar..." id="qGlobal" />
                                    <button type="submit" onclick="return false"><i class="lni lni-search-alt"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7 col-md-7 col-6">
                        <div class="header-right">
                            <div class="profile-box ml-15">
                                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="profile-info">
                                        <div class="info">
                                            <div class="image"><img src="assets/images/profile/profile-image.png"
                                                    alt="perfil" /></div>
                                            <div>
                                                <h6 class="fw-500">Administrador</h6>
                                                <p>Distribuidora</p>
                                            </div>
                                        </div>
                                    </div>
                                </button>

                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile">
                                    <li>
                                        <div class="author-info flex items-center !p-1">
                                            <div class="image"><img src="assets/images/profile/profile-image.png"
                                                    alt="image" /></div>
                                            <div class="content">
                                                <h4 class="text-sm">Administrador</h4>
                                                <a class="text-black/40 dark:text-white/40 hover:text-black dark:hover:text-white text-xs"
                                                    href="#">Admin</a>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="divider"></li>
                                    <li><a href="perfil.html"><i class="lni lni-user"></i> Meu Perfil</a></li>
                                    <li><a href="usuarios.html"><i class="lni lni-cog"></i> Usuários</a></li>
                                    <li class="divider"></li>
                                    <li><a href="logout.html"><i class="lni lni-exit"></i> Sair</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </header>

        <section class="section">
            <div class="container-fluid">
                <div class="title-wrapper pt-30">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="title">
                                <h2>Terminal de Vendas (PDV)</h2>
                                <div class="muted">Ponto de Venda & Checkout — <b>F4</b> pesquisar | <b>F2</b> confirmar</div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= e((string)$flash['type']) ?> py-2">
                        <?= e((string)$flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-30 pdv-row">
                    <!-- LEFT -->
                    <div class="col-12 col-lg-8">
                        <div class="pdv-left-col">
                            <!-- Search + Preview -->
                            <div class="pdv-card pdv-search mb-3">
                                <div class="pdv-body">
                                    <div class="row g-3 align-items-stretch">
                                        <div class="col-12 col-md-8">
                                            <label class="form-label">Pesquisar Produto (F4)</label>
                                            <div class="search-wrap">
                                                <input class="form-control compact" id="qProd"
                                                    placeholder="Nome ou código..." autocomplete="off" />
                                                <div class="suggest" id="suggest"></div>
                                            </div>
                                            <div class="muted mt-2">Dica: digite e pressione <b>Enter</b> para adicionar o 1º resultado.</div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Imagem</label>
                                            <div class="preview-box">
                                                <div>
                                                    <img id="previewImg" alt="Prévia" />
                                                    <div class="preview-name" id="previewName">AGUARDANDO...</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <!-- Itens -->
                            <div class="pdv-card items-card">
                                <div class="pdv-head">
                                    <div style="font-weight: 1000; color:#0f172a;">
                                        <i class="lni lni-cart me-1"></i> Itens da Venda
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar" type="button">
                                            <i class="lni lni-trash-can me-1"></i> Limpar
                                        </button>
                                    </div>
                                </div>

                                <div class="pdv-body">
                                    <div class="items-scroll">
                                        <div class="table-responsive">
                                            <table class="table text-nowrap mb-0" id="tbItens">
                                                <thead>
                                                    <tr>
                                                        <th style="min-width:70px;">Item</th>
                                                        <th style="min-width:360px;">Produto</th>
                                                        <th style="min-width:140px;">Qtd</th>
                                                        <th style="min-width:140px;" class="text-end">Unitário</th>
                                                        <th style="min-width:160px;" class="text-end">Subtotal</th>
                                                        <th style="min-width:120px;" class="text-center">Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbodyItens"></tbody>
                                            </table>
                                        </div>

                                        <div class="muted p-3" id="hintEmpty" style="display:none;">Aguardando inclusão de produtos...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: Checkout -->
                    <div class="col-12 col-lg-4">
                        <div class="pdv-right-col">
                            <div class="pdv-card">
                                <div class="checkout-head">
                                    <h6 style="color: #fff;"><i class="lni lni-calculator me-1"></i> Checkout</h6>
                                    <span class="badge bg-light text-dark" id="saleNo">Pedido #—</span>
                                </div>

                                <div class="checkout-body">
                                    <div class="mb-3">
                                        <label class="form-label">Cliente</label>
                                        <input class="form-control compact" id="cCliente" placeholder="CPF ou Nome (Opcional)" />
                                        <div class="muted mt-1">Consumidor final (se vazio).</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Forma de Entrega</label>
                                        <div class="chip-toggle">
                                            <div class="chip active" id="chipPres">Presencial</div>
                                            <div class="chip" id="chipDel">Delivery</div>
                                        </div>
                                    </div>

                                    <div class="mb-3" id="wrapDelivery" style="display:none;">
                                        <label class="form-label">Endereço</label>
                                        <input class="form-control compact mb-2" id="cEndereco" placeholder="Rua, nº, bairro, referência..." />
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label">Taxa entrega</label>
                                                <input class="form-control compact" id="cEntrega" placeholder="0,00" value="0,00" />
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Observação</label>
                                                <input class="form-control compact" id="cObs" placeholder="Opcional" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Desconto</label>
                                        <div class="row g-2">
                                            <div class="col-5">
                                                <select class="form-select compact" id="dTipo">
                                                    <option value="PERC">%</option>
                                                    <option value="VALOR">R$</option>
                                                </select>
                                            </div>
                                            <div class="col-7">
                                                <input class="form-control compact" id="dValor" placeholder="0" value="0" />
                                            </div>
                                        </div>
                                        <div class="muted mt-1">Desconto aplicado no subtotal (antes da taxa).</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Método de Pagamento</label>

                                        <div class="chip-toggle mb-2">
                                            <div class="chip active" id="chipPagUnico">Único</div>
                                            <div class="chip" id="chipPagMulti">Múltiplos</div>
                                        </div>

                                        <!-- único -->
                                        <div id="wrapPagUnico">
                                            <div class="pay-grid mb-2" id="payBtns">
                                                <div class="pay-btn active" data-pay="DINHEIRO"><i class="lni lni-coin"></i> Dinheiro</div>
                                                <div class="pay-btn" data-pay="PIX"><i class="lni lni-telegram-original"></i> Pix</div>
                                                <div class="pay-btn" data-pay="CARTAO"><i class="lni lni-credit-cards"></i> Cartão</div>
                                                <div class="pay-btn" data-pay="BOLETO"><i class="lni lni-ticket-alt"></i> Boleto</div>
                                            </div>

                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="form-label">Valor pago</label>
                                                    <input class="form-control compact" id="pValor" placeholder="0,00" value="0,00" />
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label">Troco</label>
                                                    <input class="form-control compact" id="pTroco" value="0,00" readonly />
                                                </div>
                                            </div>
                                            <div class="muted mt-1" id="hintTroco" style="display:none;">Em dinheiro pode ser maior que o total (troco automático).</div>
                                        </div>

                                        <!-- múltiplos -->
                                        <div id="wrapPagMulti" style="display:none;">
                                            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                                                <div class="muted">Some os pagamentos para fechar o total. Se passar do total, precisa ter Dinheiro (troco).</div>
                                                <button class="main-btn light-btn btn-hover btn-compact" id="btnAddPay" type="button">
                                                    <i class="lni lni-plus me-1"></i> Adicionar
                                                </button>
                                            </div>

                                            <div id="paysWrap"></div>

                                            <div class="totals mt-2">
                                                <div class="tot-row"><span>Somatório</span><span id="mSum">R$ 0,00</span></div>
                                                <div class="tot-row"><span>Diferença (Pag - Total)</span><span id="mDiff">R$ 0,00</span></div>
                                                <div class="tot-row"><span>Troco</span><span id="mTroco">R$ 0,00</span></div>
                                                <div class="msg-ok mt-2" id="mOk">✅ Pagamento OK.</div>
                                                <div class="msg-err mt-2" id="mErr">⚠️ Pagamento inválido. Ajuste os valores.</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="totals mb-3">
                                        <div class="tot-row"><span>Subtotal</span><span id="tSub">R$ 0,00</span></div>
                                        <div class="tot-row"><span>Desconto</span><span id="tDesc">- R$ 0,00</span></div>
                                        <div class="tot-row"><span>Taxa entrega</span><span id="tEnt">R$ 0,00</span></div>
                                        <div class="tot-hr"></div>
                                        <div class="grand">
                                            <span class="lbl">TOTAL</span>
                                            <span class="val" id="tTotal">R$ 0,00</span>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button class="main-btn primary-btn btn-hover btn-compact" id="btnConfirmar" type="button">
                                            <i class="lni lni-checkmark-circle me-1"></i> CONFIRMAR VENDA (F2)
                                        </button>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="chkPrint" checked />
                                            <label class="form-check-label" for="chkPrint">Imprimir cupom após confirmar</label>
                                        </div>
                                    </div>

                                    <!-- Últimos cupons -->
                                    <div class="last-box">
                                        <div class="head">
                                            <div class="t">Últimos cupons</div>
                                            <button class="main-btn light-btn btn-hover btn-compact" id="btnRefreshLast"
                                                type="button" style="height:32px!important;padding:6px 10px!important;">
                                                <i class="lni lni-reload"></i>
                                            </button>
                                        </div>
                                        <div class="list" id="lastList"></div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6 order-last order-md-first">
                        <div class="copyright text-center text-md-start">
                            <p class="text-sm">Painel da Distribuidora • <span class="text-gray">v1.0</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <!-- ========= JS ========= -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        // CSRF (PHP)
        const CSRF_TOKEN = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        // ===== Default Img =====
        const DEFAULT_IMG = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(`
          <svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">
            <rect width="100%" height="100%" fill="#f1f5f9"/>
            <path d="M18 86l22-22 14 14 12-12 26 26" fill="none" stroke="#94a3b8" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="42" cy="42" r="10" fill="#94a3b8"/>
            <text x="50%" y="92%" text-anchor="middle" font-family="Arial" font-size="12" fill="#64748b">Sem imagem</text>
          </svg>
        `);

        function safeText(s) {
            return String(s ?? "")
                .replaceAll("&", "&amp;")
                .replaceAll("<", "&lt;")
                .replaceAll(">", "&gt;")
                .replaceAll('"', "&quot;")
                .replaceAll("'", "&#039;");
        }
        function moneyToNumber(txt) {
            let s = String(txt ?? "").trim();
            if (!s) return 0;
            s = s.replace(/[^\d,.-]/g, "").replace(/\./g, "").replace(",", ".");
            const n = Number(s);
            return isNaN(n) ? 0 : n;
        }
        function numberToMoney(n) {
            const v = Number(n || 0);
            return "R$ " + v.toFixed(2).replace(".", ",");
        }

        // ===== Estado PDV =====
        let CART = []; // {id, code, name, price, img, unidade, stock, qty}
        let PAY_MODE = "UNICO"; // UNICO | MULTI
        let PAY_SELECTED = "DINHEIRO";
        let DELIVERY_MODE = "PRESENCIAL"; // PRESENCIAL | DELIVERY
        let LAST_SUGGEST = [];

        // ===== DOM =====
        const qProd = document.getElementById("qProd");
        const suggest = document.getElementById("suggest");
        const qGlobal = document.getElementById("qGlobal");

        const previewImg = document.getElementById("previewImg");
        const previewName = document.getElementById("previewName");

        const tbodyItens = document.getElementById("tbodyItens");
        const hintEmpty = document.getElementById("hintEmpty");
        const btnLimpar = document.getElementById("btnLimpar");

        const saleNo = document.getElementById("saleNo");

        const cCliente = document.getElementById("cCliente");
        const chipPres = document.getElementById("chipPres");
        const chipDel = document.getElementById("chipDel");
        const wrapDelivery = document.getElementById("wrapDelivery");
        const cEndereco = document.getElementById("cEndereco");
        const cEntrega = document.getElementById("cEntrega");
        const cObs = document.getElementById("cObs");

        const dTipo = document.getElementById("dTipo");
        const dValor = document.getElementById("dValor");

        const chipPagUnico = document.getElementById("chipPagUnico");
        const chipPagMulti = document.getElementById("chipPagMulti");
        const wrapPagUnico = document.getElementById("wrapPagUnico");
        const wrapPagMulti = document.getElementById("wrapPagMulti");

        const payBtns = document.getElementById("payBtns");
        const pValor = document.getElementById("pValor");
        const pTroco = document.getElementById("pTroco");
        const hintTroco = document.getElementById("hintTroco");

        const btnAddPay = document.getElementById("btnAddPay");
        const paysWrap = document.getElementById("paysWrap");
        const mSum = document.getElementById("mSum");
        const mDiff = document.getElementById("mDiff");
        const mTroco = document.getElementById("mTroco");
        const mOk = document.getElementById("mOk");
        const mErr = document.getElementById("mErr");

        const tSub = document.getElementById("tSub");
        const tDesc = document.getElementById("tDesc");
        const tEnt = document.getElementById("tEnt");
        const tTotal = document.getElementById("tTotal");

        const btnConfirmar = document.getElementById("btnConfirmar");
        const chkPrint = document.getElementById("chkPrint");

        const lastList = document.getElementById("lastList");
        const btnRefreshLast = document.getElementById("btnRefreshLast");

        // ===== UI helpers =====
        function setPreview(prod) {
            const img = (prod && prod.img) ? prod.img : DEFAULT_IMG;
            previewImg.src = img;
            previewName.textContent = prod ? prod.name : "AGUARDANDO...";
        }
        function showSuggest(list) {
            LAST_SUGGEST = list || [];
            if (!LAST_SUGGEST.length) { suggest.style.display = "none"; suggest.innerHTML = ""; return; }

            suggest.innerHTML = LAST_SUGGEST.map(p => {
                const disabled = (Number(p.stock || 0) <= 0);
                return `
                    <div class="it ${disabled ? "disabled" : ""}" data-id="${safeText(p.id)}" data-disabled="${disabled ? "1" : "0"}">
                      <img class="pimg" src="${safeText(p.img || DEFAULT_IMG)}" alt="">
                      <div class="meta">
                        <div class="t">${safeText(p.name)}</div>
                        <div class="s">${safeText(p.code)} • ${safeText(p.unidade || "UN")} ${p.categoria ? "• " + safeText(p.categoria) : ""}</div>
                      </div>
                      <span class="stk">Est: ${Number(p.stock || 0)}</span>
                      <div class="price">${numberToMoney(p.price)}</div>
                    </div>
                `;
            }).join("");

            suggest.style.display = "block";
            suggest.scrollTop = 0;
        }
        function hideSuggest() { suggest.style.display = "none"; suggest.innerHTML = ""; LAST_SUGGEST = []; }

        async function apiSearchProducts(q) {
            const qs = new URLSearchParams({ action: "search", q: q });
            const r = await fetch("vendas.php?" + qs.toString(), { headers: { "Accept": "application/json" } });
            const j = await r.json();
            if (!j || !j.ok) return [];
            return j.items || [];
        }

        // ===== Carrinho =====
        function addToCart(prod) {
            if (!prod) return;
            if (Number(prod.stock || 0) <= 0) { alert("Sem estoque para este produto."); return; }

            const idx = CART.findIndex(x => x.id === prod.id);
            if (idx >= 0) {
                const next = CART[idx].qty + 1;
                if (next > CART[idx].stock) { alert("Quantidade acima do estoque."); return; }
                CART[idx].qty = next;
            } else {
                CART.push({
                    id: Number(prod.id),
                    code: String(prod.code || ""),
                    name: String(prod.name || ""),
                    price: Number(prod.price || 0),
                    img: String(prod.img || DEFAULT_IMG),
                    unidade: String(prod.unidade || "UN"),
                    stock: Number(prod.stock || 0),
                    qty: 1
                });
            }
            setPreview(prod);
            renderCart();
            recalcAll();
        }
        function removeFromCart(id) { CART = CART.filter(x => x.id !== id); renderCart(); recalcAll(); }
        function changeQty(id, delta) {
            const it = CART.find(x => x.id === id);
            if (!it) return;
            let next = Math.max(1, Number(it.qty || 1) + delta);
            next = Math.min(next, Number(it.stock || 0));
            if (next <= 0) next = 1;
            it.qty = next;
            renderCart();
            recalcAll();
        }
        function setQty(id, qty) {
            const it = CART.find(x => x.id === id);
            if (!it) return;
            let q = Math.max(1, Number(qty || 1));
            q = Math.min(q, Number(it.stock || 0));
            if (!Number.isFinite(q)) q = 1;
            it.qty = q;
            renderCart();
            recalcAll();
        }

        function calcSubtotal() { return CART.reduce((acc, it) => acc + (Number(it.qty || 0) * Number(it.price || 0)), 0); }
        function calcDiscount(sub) {
            const tipo = dTipo.value;
            const v = moneyToNumber(String(dValor.value ?? "").trim());
            if (!v || v <= 0) return 0;
            if (tipo === "PERC") return (sub * Math.min(100, v)) / 100;
            return Math.min(sub, v);
        }
        function calcDeliveryFee() {
            if (DELIVERY_MODE !== "DELIVERY") return 0;
            return moneyToNumber(cEntrega.value);
        }
        function calcTotal() {
            const sub = calcSubtotal();
            const desc = calcDiscount(sub);
            const ent = calcDeliveryFee();
            return Math.max(0, (sub - desc) + ent);
        }

        // ===== Pagamento =====
        function payRowTpl(method = "PIX", value = "0,00") {
            return `
                <div class="pay-split-row">
                  <div class="row g-2 align-items-end">
                    <div class="col-6">
                      <label class="form-label">Forma</label>
                      <select class="form-select compact mMethod">
                        <option value="DINHEIRO" ${method === "DINHEIRO" ? "selected" : ""}>Dinheiro</option>
                        <option value="PIX" ${method === "PIX" ? "selected" : ""}>Pix</option>
                        <option value="CARTAO" ${method === "CARTAO" ? "selected" : ""}>Cartão</option>
                        <option value="BOLETO" ${method === "BOLETO" ? "selected" : ""}>Boleto</option>
                      </select>
                    </div>
                    <div class="col-4">
                      <label class="form-label">Valor</label>
                      <input class="form-control compact mValue" value="${safeText(value)}" placeholder="0,00" />
                    </div>
                    <div class="col-2 d-grid">
                      <button class="main-btn danger-btn-outline btn-hover btn-compact btnRemPay" type="button" style="height:38px!important;padding:0!important;">
                        <i class="lni lni-trash-can"></i>
                      </button>
                    </div>
                  </div>
                </div>
            `;
        }
        function ensureOnePayRow() {
            if (!paysWrap.querySelector(".pay-split-row")) paysWrap.innerHTML = payRowTpl("PIX", "0,00");
        }

        function computeMultiPay() {
            const total = calcTotal();
            const rows = Array.from(paysWrap.querySelectorAll(".pay-split-row")).map(row => {
                const m = row.querySelector(".mMethod")?.value || "PIX";
                const v = moneyToNumber(row.querySelector(".mValue")?.value || "0");
                return { method: m, value: v };
            }).filter(x => x.value > 0);

            const sum = rows.reduce((a, x) => a + x.value, 0);
            const diff = sum - total;
            const hasCash = rows.some(x => x.method === "DINHEIRO");

            let ok = false;
            let troco = 0;

            if (Math.abs(diff) < 0.009) ok = true;
            else if (diff > 0.009 && hasCash) { ok = true; troco = diff; }

            mSum.textContent = numberToMoney(sum);
            mDiff.textContent = numberToMoney(diff);
            mTroco.textContent = numberToMoney(troco);

            mOk.style.display = ok ? "block" : "none";
            mErr.style.display = ok ? "none" : "block";

            return { ok, rows, sum, diff, troco, total };
        }

        function computeSinglePay() {
            const total = calcTotal();
            const paid = moneyToNumber(pValor.value);
            const method = PAY_SELECTED;

            let ok = false;
            let troco = 0;

            if (method === "DINHEIRO") {
                ok = paid >= total && total > 0;
                troco = ok ? (paid - total) : 0;
                hintTroco.style.display = "block";
            } else {
                hintTroco.style.display = "none";
                ok = (Math.abs(paid - total) < 0.009) && total > 0;
                troco = 0;
            }

            pTroco.value = troco.toFixed(2).replace(".", ",");
            return { ok, method, paid, troco, total };
        }

        // ===== Render =====
        function renderCart() {
            tbodyItens.innerHTML = "";
            hintEmpty.style.display = CART.length ? "none" : "block";

            CART.forEach((it, i) => {
                const sub = Number(it.qty || 0) * Number(it.price || 0);
                tbodyItens.insertAdjacentHTML("beforeend", `
                    <tr data-id="${safeText(it.id)}">
                      <td>${i + 1}</td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <img class="pimg" src="${safeText(it.img || DEFAULT_IMG)}" alt="">
                          <div style="min-width:0;">
                            <div style="font-weight:1000;color:#0f172a;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:380px;">${safeText(it.name)}</div>
                            <div class="muted">${safeText(it.code)} • ${safeText(it.unidade || "UN")} • Est: ${Number(it.stock || 0)}</div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="qty-ctrl">
                          <button class="main-btn light-btn btn-hover btn-compact qty-btn btnMinus" type="button" title="-1"><i class="lni lni-minus"></i></button>
                          <input class="qty-pill iQty" type="number" min="1" step="1" max="${Number(it.stock || 0)}" value="${Number(it.qty || 1)}" />
                          <button class="main-btn light-btn btn-hover btn-compact qty-btn btnPlus" type="button" title="+1"><i class="lni lni-plus"></i></button>
                        </div>
                      </td>
                      <td class="text-end">${numberToMoney(it.price)}</td>
                      <td class="text-end" style="font-weight:1000;">${numberToMoney(sub)}</td>
                      <td class="text-center">
                        <button class="main-btn danger-btn-outline btn-hover btn-compact icon-btn btnRemove" type="button" title="Remover"><i class="lni lni-trash-can"></i></button>
                      </td>
                    </tr>
                `);
            });
        }

        function recalcAll() {
            const sub = calcSubtotal();
            const desc = calcDiscount(sub);
            const ent = calcDeliveryFee();
            const total = Math.max(0, (sub - desc) + ent);

            tSub.textContent = numberToMoney(sub);
            tDesc.textContent = "- " + numberToMoney(desc);
            tEnt.textContent = numberToMoney(ent);
            tTotal.textContent = numberToMoney(total);

            if (PAY_MODE === "UNICO") computeSinglePay();
            else computeMultiPay();
        }

        async function renderLastSales() {
            try {
                const r = await fetch("vendas.php?action=last", { headers: { "Accept": "application/json" } });
                const j = await r.json();
                const all = (j && j.ok) ? (j.items || []) : [];

                if (!all.length) {
                    lastList.innerHTML = `<div class="cup"><div class="left"><div class="n">—</div><div class="s">Sem cupons ainda</div></div><div class="right"><div class="v">R$ 0,00</div></div></div>`;
                    return;
                }

                lastList.innerHTML = all.map(s => `
                    <div class="cup" data-pedido="${safeText(s.pedido)}" title="Clique para reimprimir">
                      <div class="left">
                        <div class="n">${safeText(s.pedido)}</div>
                        <div class="s">${safeText(s.data)} • ${safeText(s.canal)} • ${safeText(s.pagamento)}</div>
                      </div>
                      <div class="right">
                        <div class="v">${numberToMoney(s.total || 0)}</div>
                        <div class="st">CONCLUÍDO</div>
                      </div>
                    </div>
                `).join("");
            } catch (e) {
                lastList.innerHTML = `<div class="cup"><div class="left"><div class="n">—</div><div class="s">Falha ao carregar cupons</div></div><div class="right"><div class="v">—</div></div></div>`;
            }
        }

        // ===== Busca / Sugestões (DB) =====
        let searchTimer = null;
        async function refreshSuggest() {
            const q = String(qProd.value || "").trim();
            if (!q) { hideSuggest(); return; }

            clearTimeout(searchTimer);
            searchTimer = setTimeout(async () => {
                const list = await apiSearchProducts(q);
                showSuggest(list);
            }, 150);
        }

        qProd.addEventListener("input", refreshSuggest);
        qProd.addEventListener("focus", refreshSuggest);

        qProd.addEventListener("keydown", async (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                // garante que tem lista atual
                if (!LAST_SUGGEST.length) {
                    const list = await apiSearchProducts(qProd.value);
                    showSuggest(list);
                }
                const first = LAST_SUGGEST[0];
                if (first) { addToCart(first); qProd.value = ""; hideSuggest(); }
            }
            if (e.key === "Escape") hideSuggest();
        });

        suggest.addEventListener("click", (e) => {
            const it = e.target.closest(".it");
            if (!it) return;
            if (it.getAttribute("data-disabled") === "1") return;

            const id = Number(it.getAttribute("data-id") || 0);
            const prod = LAST_SUGGEST.find(p => Number(p.id) === id);
            addToCart(prod);
            qProd.value = "";
            hideSuggest();
            qProd.focus();
        });

        document.addEventListener("click", (e) => { if (!e.target.closest(".search-wrap")) hideSuggest(); });

        // ===== Entrega toggle =====
        function setDeliveryMode(mode) {
            DELIVERY_MODE = mode;
            const isDel = mode === "DELIVERY";
            chipDel.classList.toggle("active", isDel);
            chipPres.classList.toggle("active", !isDel);
            wrapDelivery.style.display = isDel ? "block" : "none";

            if (!isDel) { cEndereco.value = ""; cEntrega.value = "0,00"; cObs.value = ""; }
            recalcAll();
        }
        chipPres.addEventListener("click", () => setDeliveryMode("PRESENCIAL"));
        chipDel.addEventListener("click", () => setDeliveryMode("DELIVERY"));
        cEntrega.addEventListener("input", recalcAll);

        // Desconto
        dTipo.addEventListener("change", recalcAll);
        dValor.addEventListener("input", recalcAll);

        // ===== Pagamento toggle =====
        function setPayMode(mode) {
            PAY_MODE = mode;
            const isMulti = mode === "MULTI";
            chipPagMulti.classList.toggle("active", isMulti);
            chipPagUnico.classList.toggle("active", !isMulti);
            wrapPagMulti.style.display = isMulti ? "block" : "none";
            wrapPagUnico.style.display = isMulti ? "none" : "block";

            if (isMulti) ensureOnePayRow();
            recalcAll();
        }

        chipPagUnico.addEventListener("click", () => setPayMode("UNICO"));
        chipPagMulti.addEventListener("click", () => setPayMode("MULTI"));

        payBtns.addEventListener("click", (e) => {
            const btn = e.target.closest(".pay-btn");
            if (!btn) return;
            PAY_SELECTED = btn.getAttribute("data-pay") || "DINHEIRO";
            payBtns.querySelectorAll(".pay-btn").forEach(b => b.classList.remove("active"));
            btn.classList.add("active");
            recalcAll();
        });

        pValor.addEventListener("input", recalcAll);

        btnAddPay.addEventListener("click", () => {
            paysWrap.insertAdjacentHTML("beforeend", payRowTpl("PIX", "0,00"));
            recalcAll();
        });

        paysWrap.addEventListener("click", (e) => {
            const btn = e.target.closest(".btnRemPay");
            if (!btn) return;
            const row = btn.closest(".pay-split-row");
            if (row) row.remove();
            ensureOnePayRow();
            recalcAll();
        });

        paysWrap.addEventListener("input", (e) => { if (e.target.closest(".pay-split-row")) recalcAll(); });
        paysWrap.addEventListener("change", (e) => { if (e.target.closest(".pay-split-row")) recalcAll(); });

        // ===== Carrinho events =====
        tbodyItens.addEventListener("click", (e) => {
            const tr = e.target.closest("tr");
            if (!tr) return;
            const id = Number(tr.getAttribute("data-id") || 0);
            if (!id) return;

            if (e.target.closest(".btnRemove")) return removeFromCart(id);
            if (e.target.closest(".btnMinus")) return changeQty(id, -1);
            if (e.target.closest(".btnPlus")) return changeQty(id, +1);
        });

        tbodyItens.addEventListener("input", (e) => {
            const tr = e.target.closest("tr");
            if (!tr) return;
            const id = Number(tr.getAttribute("data-id") || 0);
            if (!id) return;
            if (e.target.classList.contains("iQty")) setQty(id, e.target.value);
        });

        btnLimpar.addEventListener("click", () => {
            if (CART.length && !confirm("Limpar todos os itens da venda?")) return;
            CART = [];
            renderCart();
            recalcAll();
            setPreview(null);
        });

        // ===== Cupom fiscal (80mm) =====
        function buildReceiptHtml(sale) {
            const hr = () => `<div class="hr"></div>`;
            const items = (sale.items || []).map((it, idx) => {
                const sub = Number(it.total || (it.qty * it.price));
                const name = String(it.name || "").toUpperCase();
                const code = String(it.code || "");
                return `
                    <div class="item">
                      <div class="row"><span>${String(idx + 1).padStart(3, "0")} ${safeText(name)}</span></div>
                      <div class="row small">
                        <span>${safeText(code)}</span>
                        <span>${Number(it.qty)} x ${numberToMoney(it.price)} = ${numberToMoney(sub)}</span>
                      </div>
                    </div>
                `;
            }).join("");

            const entregaTxt = (sale.delivery && sale.delivery.mode === "DELIVERY") ? "DELIVERY" : "PRESENCIAL";
            const clienteTxt = sale.customer ? sale.customer : "CONSUMIDOR FINAL";
            const enderecoTxt = (sale.delivery && sale.delivery.mode === "DELIVERY") ? (sale.delivery.address || "-") : "";

            // pagamento exibido (simples)
            const pg = safeText(String(sale.pagamento || sale.pay?.method || "—"));

            return `
                <html>
                  <head>
                    <meta charset="utf-8">
                    <title>Cupom ${safeText(sale.pedido || "")}</title>
                    <style>
                      @page { size: 80mm auto; margin: 6mm; }
                      body { margin: 0; padding: 0; font-family: "Courier New", monospace; color: #000; }
                      .wrap { width: 72mm; margin: 0 auto; font-size: 11px; }
                      .center { text-align: center; }
                      .bold { font-weight: 800; }
                      .small { font-size: 10px; }
                      .hr { border-top: 1px dashed #000; margin: 6px 0; }
                      .row { display: flex; justify-content: space-between; gap: 10px; }
                      .row span:last-child { text-align: right; white-space: nowrap; }
                      .item { margin: 6px 0; }
                      .top { margin-top: 4px; }
                      .mono { letter-spacing: .2px; }
                    </style>
                  </head>
                  <body>
                    <div class="wrap mono">
                      <div class="center bold">PAINEL DA DISTRIBUIDORA</div>
                      <div class="center small">CUPOM (MODELO)</div>
                      ${hr()}
                      <div class="row"><span class="bold">PEDIDO</span><span>${safeText(sale.pedido || "—")}</span></div>
                      <div class="row"><span class="bold">DATA</span><span>${safeText(sale.date || "")}</span></div>
                      <div class="row"><span class="bold">CLIENTE</span><span>${safeText(clienteTxt)}</span></div>
                      <div class="row"><span class="bold">ENTREGA</span><span>${entregaTxt}</span></div>
                      ${(sale.delivery && sale.delivery.mode === "DELIVERY") ? `<div class="top small">END: ${safeText(enderecoTxt)}</div>` : ``}
                      ${hr()}
                      <div class="row bold"><span>ITENS</span><span></span></div>
                      ${items || `<div class="small">—</div>`}
                      ${hr()}
                      <div class="row"><span>TOTAL</span><span>${numberToMoney(sale.total || 0)}</span></div>
                      ${hr()}
                      <div class="row bold"><span>PAGAMENTO</span><span></span></div>
                      <div class="row"><span>${pg}</span><span></span></div>
                      ${hr()}
                      <div class="center small">OBRIGADO PELA PREFERÊNCIA!</div>
                    </div>
                    <script>window.print();<\/script>
                  </body>
                </html>
            `;
        }

        // ===== Confirmar venda (DB) =====
        function validateSaleClient() {
            if (!CART.length) return { ok: false, msg: "Adicione pelo menos 1 item." };
            if (DELIVERY_MODE === "DELIVERY" && !String(cEndereco.value || "").trim()) return { ok: false, msg: "Informe o endereço do Delivery." };

            const total = calcTotal();
            if (total <= 0) return { ok: false, msg: "Total inválido." };

            // estoque client
            for (const it of CART) {
                if (Number(it.qty || 0) > Number(it.stock || 0)) return { ok: false, msg: `Estoque insuficiente: ${it.name}` };
            }

            if (PAY_MODE === "UNICO") {
                const r = computeSinglePay();
                if (!r.ok) {
                    if (r.method === "DINHEIRO") return { ok: false, msg: "No dinheiro, o valor pago deve ser >= total." };
                    return { ok: false, msg: "Para Pix/Cartão/Boleto, o valor pago deve ser igual ao total." };
                }
                return { ok: true };
            }

            const m = computeMultiPay();
            if (!m.ok) return { ok: false, msg: "Pagamento múltiplo inválido. Ajuste os valores." };
            return { ok: true };
        }

        async function confirmSale() {
            const v = validateSaleClient();
            if (!v.ok) { alert(v.msg); return; }

            const payload = {
                csrf_token: CSRF_TOKEN,
                customer: String(cCliente.value || "").trim(),
                delivery: {
                    mode: DELIVERY_MODE,
                    address: DELIVERY_MODE === "DELIVERY" ? String(cEndereco.value || "").trim() : "",
                    fee: DELIVERY_MODE === "DELIVERY" ? String(cEntrega.value || "0,00") : "0,00",
                    obs: DELIVERY_MODE === "DELIVERY" ? String(cObs.value || "").trim() : ""
                },
                discount: {
                    type: String(dTipo.value || "PERC"),
                    value: String(dValor.value || "0")
                },
                pay: (PAY_MODE === "UNICO")
                    ? { mode: "UNICO", method: PAY_SELECTED, paid: String(pValor.value || "0,00") }
                    : { mode: "MULTI", parts: Array.from(paysWrap.querySelectorAll(".pay-split-row")).map(row => ({
                        method: row.querySelector(".mMethod")?.value || "PIX",
                        value: row.querySelector(".mValue")?.value || "0,00"
                    })) },
                items: CART.map(it => ({ id: Number(it.id), qty: Number(it.qty || 0) }))
            };

            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = `<i class="lni lni-spinner-arrow me-1"></i> Processando...`;
            try {
                const r = await fetch("vendas.php?action=confirm", {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "Accept": "application/json" },
                    body: JSON.stringify(payload)
                });
                const j = await r.json();
                if (!j || !j.ok) throw new Error(j?.msg || "Falha ao confirmar.");

                const sale = j.sale;
                saleNo.textContent = `Pedido #${sale.pedido}`;

                if (chkPrint.checked) {
                    const html = buildReceiptHtml(sale);
                    const w = window.open("", "_blank");
                    if (w) { w.document.open(); w.document.write(html); w.document.close(); }
                    else { alert("Pop-up bloqueado. Permita pop-ups para imprimir."); }
                }

                // reset UI
                CART = [];
                renderCart();
                setPreview(null);

                cCliente.value = "";
                setDeliveryMode("PRESENCIAL");

                dTipo.value = "PERC";
                dValor.value = "0";

                setPayMode("UNICO");
                PAY_SELECTED = "DINHEIRO";
                payBtns.querySelectorAll(".pay-btn").forEach(b => b.classList.remove("active"));
                payBtns.querySelector('.pay-btn[data-pay="DINHEIRO"]').classList.add("active");
                pValor.value = "0,00";
                pTroco.value = "0,00";

                paysWrap.innerHTML = "";
                ensureOnePayRow();

                recalcAll();
                await renderLastSales();

                alert(`Venda confirmada! Pedido: ${sale.pedido}`);
                qProd.focus();
            } catch (e) {
                alert(String(e?.message || e));
            } finally {
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = `<i class="lni lni-checkmark-circle me-1"></i> CONFIRMAR VENDA (F2)`;
            }
        }

        btnConfirmar.addEventListener("click", confirmSale);

        // Reimprimir: clique em um cupom (pega detalhes do pedido)
        lastList.addEventListener("click", async (e) => {
            const cup = e.target.closest(".cup");
            if (!cup) return;
            const pedido = cup.getAttribute("data-pedido");
            if (!pedido) return;

            try {
                const qs = new URLSearchParams({ action: "get_pedido", pedido: pedido });
                const r = await fetch("vendas.php?" + qs.toString(), { headers: { "Accept": "application/json" } });
                const j = await r.json();
                if (!j || !j.ok) throw new Error(j?.msg || "Pedido não encontrado.");

                // adapta para cupom
                const sale = j.sale;
                const html = buildReceiptHtml({
                    pedido: sale.pedido,
                    date: sale.date,
                    customer: sale.customer,
                    canal: sale.canal,
                    pagamento: sale.pagamento,
                    items: sale.items.map(it => ({
                        code: it.code, name: it.name, qty: it.qty, price: it.price, total: it.total
                    })),
                    total: sale.total,
                    delivery: { mode: (sale.canal === "DELIVERY" ? "DELIVERY" : "PRESENCIAL"), address: "" }
                });

                const w = window.open("", "_blank");
                if (w) { w.document.open(); w.document.write(html); w.document.close(); }
                else { alert("Pop-up bloqueado. Permita pop-ups para imprimir."); }
            } catch (err) {
                alert(String(err?.message || err));
            }
        });

        // ===== Atalhos teclado =====
        document.addEventListener("keydown", (e) => {
            if (e.key === "F4") { e.preventDefault(); qProd.focus(); return; }
            if (e.key === "F2") { e.preventDefault(); confirmSale(); return; }
            if (e.key === "Escape") hideSuggest();
        });

        // ===== Init =====
        function init() {
            setPreview(null);

            setDeliveryMode("PRESENCIAL");
            setPayMode("UNICO");
            ensureOnePayRow();

            renderCart();
            recalcAll();
            renderLastSales();

            qGlobal.addEventListener("input", () => {
                qProd.value = qGlobal.value;
                refreshSuggest();
            });

            btnRefreshLast.addEventListener("click", renderLastSales);
        }
        init();
    </script>
</body>

</html>