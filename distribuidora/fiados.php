<?php

declare(strict_types=1);


@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ========= INCLUDES (ajuste se precisar) ========= */
$helpers = __DIR__ . '/assets/dados/_helpers.php';
if (is_file($helpers)) require_once $helpers;

$con = __DIR__ . '/assets/conexao.php';
if (is_file($con)) require_once $con;

/* ========= FALLBACKS ========= */
if (!function_exists('e')) {
    function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        return (string)$_SESSION['_csrf'];
    }
}
if (!function_exists('db')) {
    http_response_code(500);
    echo "ERRO: função db():PDO não encontrada. Verifique assets/conexao.php";
    exit;
}

/* ========= UTIL ========= */
function json_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function get_str(string $k, string $def = ''): string
{
    return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def;
}
function get_int(string $k, int $def = 0): int
{
    $v = isset($_GET[$k]) ? (int)$_GET[$k] : $def;
    return $v > 0 ? $v : $def;
}
function brl(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function build_where(array &$params): string
{
    $where = " WHERE 1=1 ";

    $di = get_str('di');
    $df = get_str('df');
    $canal = strtoupper(get_str('canal', 'TODOS'));
    $pag = strtoupper(get_str('pag', 'TODOS'));
    $q = get_str('q');

    if ($di !== '') {
        $where .= " AND v.data >= :di ";
        $params['di'] = $di;
    }
    if ($df !== '') {
        $where .= " AND v.data <= :df ";
        $params['df'] = $df;
    }

    if ($canal !== '' && $canal !== 'TODOS') {
        $where .= " AND v.canal = :canal ";
        $params['canal'] = $canal;
    }
    if ($pag !== '' && $pag !== 'TODOS') {
        $where .= " AND v.pagamento = :pag ";
        $params['pag'] = $pag;
    }

    if ($q !== '') {
        if (ctype_digit($q)) {
            $where .= " AND v.id = :vid ";
            $params['vid'] = (int)$q;
        } else {
            $where .= " AND (v.cliente LIKE :q OR v.endereco LIKE :q OR v.obs LIKE :q OR v.pagamento LIKE :q) ";
            $params['q'] = '%' . $q . '%';
        }
    }

    return $where;
}

/**
 * Itens: venda_itens por venda_id
 */
function fetch_items_for_sale_ids(array $saleIds): array
{
    if (!$saleIds) return [];

    $saleIds = array_values(array_unique(array_map('intval', $saleIds)));
    $pdo = db();

    $st = $pdo->query("SHOW TABLES LIKE 'venda_itens'");
    if (!$st || !$st->fetchColumn()) return [];

    $in = implode(',', array_fill(0, count($saleIds), '?'));

    $sql = "
    SELECT
      vi.venda_id,
      vi.codigo,
      vi.nome,
      vi.unidade,
      vi.preco_unit,
      vi.qtd,
      vi.subtotal
    FROM venda_itens vi
    WHERE vi.venda_id IN ($in)
    ORDER BY vi.venda_id DESC, vi.id ASC
  ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($saleIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $out = [];
    foreach ($rows as $r) {
        $sid = (int)($r['venda_id'] ?? 0);
        if ($sid <= 0) continue;

        $preco = (float)($r['preco_unit'] ?? 0);
        $qtd   = (float)($r['qtd'] ?? 0);
        $sub   = (float)($r['subtotal'] ?? 0);
        $lineTotal = $sub > 0 ? $sub : ($preco * $qtd);

        $out[$sid] ??= [];
        $out[$sid][] = [
            'codigo' => (string)($r['codigo'] ?? ''),
            'nome'   => (string)($r['nome'] ?? 'Item'),
            'qtd'    => $qtd,
            'un'     => (string)($r['unidade'] ?? ''),
            'preco'  => $preco,
            'total'  => $lineTotal,
        ];
    }
    return $out;
}

function fetch_one_sale(int $id): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM vendas WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$v) return null;

    $itemsMap = fetch_items_for_sale_ids([$id]);
    $itens = $itemsMap[$id] ?? [];

    $itSubtotal = 0.0;
    $itQtd = 0.0;
    foreach ($itens as $it) {
        $itSubtotal += (float)$it['total'];
        $itQtd += (float)$it['qtd'];
    }

    return [
        'venda' => $v,
        'itens' => $itens,
        'itens_total' => $itSubtotal,
        'itens_qtd' => $itQtd
    ];
}

/* ========= AÇÕES ========= */
$action = strtolower(get_str('action'));

/** compat: se alguém usar action=cupom, redireciona para o cupom real */
if ($action === 'cupom') {
    $id = get_int('id', 0);
    if ($id <= 0) {
        http_response_code(400);
        echo "ID inválido";
        exit;
    }
    $auto = isset($_GET['auto']) ? (int)$_GET['auto'] : 1;
    $qs = http_build_query(['id' => $id, 'auto' => $auto]);
    header('Location: ./assets/dados/vendas/cupom.php?' . $qs);
    exit;
}

if ($action === 'suggest') {
    $q = get_str('q');
    if (mb_strlen($q) < 2) json_out(['ok' => true, 'items' => []]);

    $pdo = db();
    $stmt = $pdo->prepare("
    SELECT DISTINCT cliente
    FROM vendas
    WHERE cliente IS NOT NULL AND cliente <> ''
      AND cliente LIKE :q
    ORDER BY cliente
    LIMIT 10
  ");
    $stmt->execute(['q' => $q . '%']);

    $items = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[] = (string)$r['cliente'];
    }
    json_out(['ok' => true, 'items' => $items]);
}

if ($action === 'fetch') {
    $pdo = db();

    $page = max(1, get_int('page', 1));
    $per = get_int('per', 25);
    $per = in_array($per, [10, 25, 50, 100], true) ? $per : 25;
    $off = ($page - 1) * $per;

    $params = [];
    $where = build_where($params);

    $sqlTot = "
    SELECT
      COUNT(*) AS qtd,
      COALESCE(SUM(v.subtotal),0) AS subtotal,
      COALESCE(SUM(v.desconto_valor),0) AS desconto,
      COALESCE(SUM(v.taxa_entrega),0) AS taxa,
      COALESCE(SUM(v.total),0) AS total
    FROM vendas v
    $where
  ";
    $stTot = $pdo->prepare($sqlTot);
    $stTot->execute($params);
    $tot = $stTot->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'subtotal' => 0, 'desconto' => 0, 'taxa' => 0, 'total' => 0];

    $sql = "
    SELECT
      v.id, v.data, v.cliente, v.canal, v.endereco, v.obs,
      v.desconto_tipo, v.desconto_valor, v.taxa_entrega,
      v.subtotal, v.total, v.pagamento_mode, v.pagamento, v.pagamento_json,
      v.created_at
    FROM vendas v
    $where
    ORDER BY v.id DESC
    LIMIT $per OFFSET $off
  ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $itemsMap = fetch_items_for_sale_ids($ids);

    $outRows = [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $itens = $itemsMap[$id] ?? [];

        $itTotal = 0.0;
        $itCount = 0;
        foreach ($itens as $it) {
            $itTotal += (float)$it['total'];
            $itCount++;
        }

        $outRows[] = [
            'id' => $id,
            'data' => (string)$r['data'],
            'created_at' => (string)($r['created_at'] ?? ''),
            'cliente' => (string)($r['cliente'] ?? ''),
            'canal' => (string)($r['canal'] ?? ''),
            'pagamento' => (string)($r['pagamento'] ?? ''),
            'subtotal' => (float)($r['subtotal'] ?? 0),
            'desconto' => (float)($r['desconto_valor'] ?? 0),
            'taxa' => (float)($r['taxa_entrega'] ?? 0),
            'total' => (float)($r['total'] ?? 0),
            'endereco' => (string)($r['endereco'] ?? ''),
            'obs' => (string)($r['obs'] ?? ''),
            'itens' => $itens,
            'itens_count' => $itCount,
            'itens_total' => $itTotal
        ];
    }

    $totalCount = (int)($tot['qtd'] ?? 0);
    $pages = (int)max(1, ceil($totalCount / $per));

    json_out([
        'ok' => true,
        'meta' => [
            'page' => $page,
            'per' => $per,
            'pages' => $pages,
            'total' => $totalCount,
        ],
        'totais' => [
            'qtd' => (int)($tot['qtd'] ?? 0),
            'subtotal' => (float)($tot['subtotal'] ?? 0),
            'desconto' => (float)($tot['desconto'] ?? 0),
            'taxa' => (float)($tot['taxa'] ?? 0),
            'total' => (float)($tot['total'] ?? 0),
        ],
        'rows' => $outRows,
    ]);
}

if ($action === 'one') {
    $id = get_int('id', 0);
    if ($id <= 0) json_out(['ok' => false, 'msg' => 'ID inválido'], 400);

    $one = fetch_one_sale($id);
    if (!$one) json_out(['ok' => false, 'msg' => 'Venda não encontrada'], 404);

    json_out(['ok' => true, 'data' => $one]);
}

if ($action === 'excel') {
    $pdo = db();
    $params = [];
    $where = build_where($params);

    $sql = "
    SELECT
      v.id, v.data, v.cliente, v.canal, v.pagamento,
      v.subtotal, v.desconto_valor, v.taxa_entrega, v.total
    FROM vendas v
    $where
    ORDER BY v.id DESC
    LIMIT 5000
  ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $agora = date('d/m/Y H:i');
    $di = get_str('di') ?: '—';
    $df = get_str('df') ?: '—';
    $canal = get_str('canal', 'TODOS');
    $pag = get_str('pag', 'TODOS');
    $q = get_str('q') ?: '—';

    $fname = 'vendidos_' . date('Y-m-d_His') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
?>
    <table border="0" cellpadding="4" cellspacing="0" style="width:100%;">
        <tr>
            <td colspan="9" align="center" style="font-size:16px;font-weight:900;">
                PAINEL DA DISTRIBUIDORA - VENDIDOS
            </td>
        </tr>
        <tr>
            <td colspan="9" style="font-size:12px;">Gerado em: <?= e($agora) ?></td>
        </tr>
        <tr>
            <td colspan="9" style="font-size:12px;">
                Período: <?= e($di) ?> até <?= e($df) ?> &nbsp;|&nbsp;
                Canal: <?= e($canal) ?> &nbsp;|&nbsp;
                Pagamento: <?= e($pag) ?> &nbsp;|&nbsp;
                Busca: <?= e($q) ?>
            </td>
        </tr>
        <tr>
            <td colspan="9"></td>
        </tr>
    </table>

    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
        <thead>
            <tr style="background:#eef2ff;font-weight:900;">
                <th>ID</th>
                <th>Data</th>
                <th>Cliente</th>
                <th>Canal</th>
                <th>Pagamento</th>
                <th>Subtotal</th>
                <th>Desconto</th>
                <th>Entrega</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sumSub = 0;
            $sumDesc = 0;
            $sumTax = 0;
            $sumTot = 0;
            foreach ($rows as $r):
                $sumSub += (float)$r['subtotal'];
                $sumDesc += (float)$r['desconto_valor'];
                $sumTax += (float)$r['taxa_entrega'];
                $sumTot += (float)$r['total'];
            ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= e((string)$r['data']) ?></td>
                    <td><?= e((string)($r['cliente'] ?? '')) ?></td>
                    <td><?= e((string)($r['canal'] ?? '')) ?></td>
                    <td><?= e((string)($r['pagamento'] ?? '')) ?></td>
                    <td><?= e(number_format((float)$r['subtotal'], 2, ',', '.')) ?></td>
                    <td><?= e(number_format((float)$r['desconto_valor'], 2, ',', '.')) ?></td>
                    <td><?= e(number_format((float)$r['taxa_entrega'], 2, ',', '.')) ?></td>
                    <td><?= e(number_format((float)$r['total'], 2, ',', '.')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:900;background:#f8fafc;">
                <td colspan="5" align="right">Totais</td>
                <td><?= e(number_format($sumSub, 2, ',', '.')) ?></td>
                <td><?= e(number_format($sumDesc, 2, ',', '.')) ?></td>
                <td><?= e(number_format($sumTax, 2, ',', '.')) ?></td>
                <td><?= e(number_format($sumTot, 2, ',', '.')) ?></td>
            </tr>
        </tfoot>
    </table>
<?php
    exit;
}

if ($action === 'print') {
    // (mantive seu print como estava)
    $pdo = db();
    $params = [];
    $where = build_where($params);

    $sql = "
    SELECT
      v.id, v.data, v.cliente, v.canal, v.pagamento,
      v.subtotal, v.desconto_valor, v.taxa_entrega, v.total
    FROM vendas v
    $where
    ORDER BY v.id DESC
    LIMIT 5000
  ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $agora = date('d/m/Y H:i:s');
    $di = get_str('di') ?: '—';
    $df = get_str('df') ?: '—';
    $canal = get_str('canal', 'TODOS');
    $pag = get_str('pag', 'TODOS');
    $q = get_str('q') ?: '—';

    $sumSub = 0;
    $sumDesc = 0;
    $sumTax = 0;
    $sumTot = 0;
    foreach ($rows as $r) {
        $sumSub += (float)$r['subtotal'];
        $sumDesc += (float)$r['desconto_valor'];
        $sumTax += (float)$r['taxa_entrega'];
        $sumTot += (float)$r['total'];
    }
?>
    <!doctype html>
    <html lang="pt-BR">

    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>PAINEL DA DISTRIBUIDORA - VENDIDOS</title>
        <style>
            @page {
                size: A4;
                margin: 16mm;
            }

            body {
                font-family: Arial, Helvetica, sans-serif;
                color: #0f172a;
            }

            h1 {
                font-size: 18px;
                margin: 0 0 8px;
            }

            .meta {
                font-size: 12px;
                margin: 2px 0;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 12px;
            }

            th,
            td {
                border: 1px solid #e5e7eb;
                padding: 8px;
                font-size: 12px;
            }

            th {
                background: #f1f5f9;
                text-align: left;
            }

            tfoot td {
                font-weight: 900;
                background: #f8fafc;
            }

            .right {
                text-align: right;
            }

            .muted {
                color: #475569;
            }

            .topbar {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                align-items: flex-start;
            }

            .btn {
                display: none;
            }

            @media screen {
                body {
                    background: #0b1220;
                    padding: 24px;
                }

                .sheet {
                    max-width: 920px;
                    margin: 0 auto;
                    background: #fff;
                    border-radius: 14px;
                    padding: 18px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
                }

                .btn {
                    display: inline-block;
                    margin-top: 10px;
                    padding: 10px 14px;
                    border-radius: 10px;
                    border: 1px solid #cbd5e1;
                    cursor: pointer;
                    font-weight: 900;
                    background: #fff;
                }
            }
        </style>
    </head>

    <body>
        <div class="sheet">
            <div class="topbar">
                <div>
                    <h1>PAINEL DA DISTRIBUIDORA - VENDIDOS</h1>
                    <div class="meta">Gerado em: <span class="muted"><?= e($agora) ?></span></div>
                    <div class="meta">Período: <span class="muted"><?= e($di) ?></span> até <span class="muted"><?= e($df) ?></span></div>
                    <div class="meta">Canal: <span class="muted"><?= e($canal) ?></span> &nbsp;|&nbsp; Pagamento: <span class="muted"><?= e($pag) ?></span> &nbsp;|&nbsp; Busca: <span class="muted"><?= e($q) ?></span></div>
                </div>
                <div>
                    <button class="btn" onclick="window.print()">Imprimir / Salvar PDF</button>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th style="width:90px;">Data</th>
                        <th>Cliente</th>
                        <th style="width:110px;">Canal</th>
                        <th style="width:130px;">Pagamento</th>
                        <th class="right" style="width:110px;">Subtotal</th>
                        <th class="right" style="width:110px;">Desconto</th>
                        <th class="right" style="width:110px;">Entrega</th>
                        <th class="right" style="width:110px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= (int)$r['id'] ?></td>
                            <td><?= e((string)$r['data']) ?></td>
                            <td><?= e((string)($r['cliente'] ?? '')) ?></td>
                            <td><?= e((string)($r['canal'] ?? '')) ?></td>
                            <td><?= e((string)($r['pagamento'] ?? '')) ?></td>
                            <td class="right"><?= e(brl((float)$r['subtotal'])) ?></td>
                            <td class="right"><?= e(brl((float)$r['desconto_valor'])) ?></td>
                            <td class="right"><?= e(brl((float)$r['taxa_entrega'])) ?></td>
                            <td class="right"><?= e(brl((float)$r['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="right">Totais</td>
                        <td class="right"><?= e(brl($sumSub)) ?></td>
                        <td class="right"><?= e(brl($sumDesc)) ?></td>
                        <td class="right"><?= e(brl($sumTax)) ?></td>
                        <td class="right"><?= e(brl($sumTot)) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <script>
            window.addEventListener('load', () => {
                setTimeout(() => window.print(), 300);
            });
        </script>
    </body>

    </html>
<?php
    exit;
}

/* ========= HTML ========= */
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= e($csrf) ?>">

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Vendidos</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <style>
        .main-btn.btn-compact {
            height: 36px !important;
            padding: 8px 12px !important;
            font-size: 13px !important;
            line-height: 1 !important;
        }

        .form-control.compact,
        .form-select.compact {
            height: 38px;
            padding: 8px 12px;
            font-size: 13px;
        }

        .cardx {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 16px;
            background: #fff;
            overflow: hidden;
        }

        .cardx .head {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .cardx .body {
            padding: 14px;
        }

        .muted {
            font-size: 12px;
            color: #64748b;
        }

        .pill {
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, .22);
            font-weight: 900;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(248, 250, 252, .7);
        }

        .pill.ok {
            border-color: rgba(34, 197, 94, .25);
            background: rgba(240, 253, 244, .9);
            color: #166534;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* ====== FIX PRINCIPAL: igualar altura SEM cortar paginação ====== */
        .equal-h>.col-lg-8,
        .equal-h>.col-lg-4 {
            display: flex;
        }

        .cardx.card-table,
        .cardx.card-tot {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
        }

        .cardx.card-table .body,
        .cardx.card-tot .body {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        /* área scroll da tabela (isso impede cortar a paginação) */
        .table-wrap {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
            border-radius: 14px;
        }

        #tbDev {
            width: 100%;
            min-width: 1140px;
            table-layout: fixed;
        }

        #tbDev thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fafc;
            border-bottom: 1px solid rgba(148, 163, 184, .25);
            font-size: 12px;
            color: #0f172a;
            padding: 10px 10px;
            white-space: nowrap;
        }

        #tbDev tbody td {
            border-top: 1px solid rgba(148, 163, 184, .18);
            padding: 10px 10px;
            font-size: 13px;
            vertical-align: top;
            color: #0f172a;
            background: #fff;
        }

        /* paginação sempre visível */
        .page-nav {
            flex: 0 0 auto;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            margin-top: 10px;
            padding-top: 6px;
        }

        .page-btn {
            border: 1px solid rgba(148, 163, 184, .35);
            background: #fff;
            border-radius: 10px;
            padding: 8px 10px;
            font-weight: 900;
            font-size: 12px;
            cursor: pointer;
        }

        .page-btn[disabled] {
            opacity: .55;
            cursor: not-allowed;
        }

        .page-info {
            font-size: 12px;
            color: #64748b;
            font-weight: 900;
        }

        /* Totais: não cortar conteúdo */
        .box-tot {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 14px;
            background: #fff;
            padding: 12px;
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .tot-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #334155;
            margin-bottom: 8px;
            font-weight: 900;
        }

        .tot-hr {
            height: 1px;
            background: rgba(148, 163, 184, .22);
            margin: 10px 0;
        }

        .grand {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            margin-top: auto;
            padding-top: 8px;
        }

        .grand .lbl {
            font-weight: 1000;
            color: #0f172a;
            font-size: 16px;
        }

        .grand .val {
            font-weight: 1000;
            color: #0b5ed7;
            font-size: 26px;
            letter-spacing: .2px;
        }

        /* col widths */
        .col-id {
            width: 70px;
        }

        .col-data {
            width: 120px;
        }

        .col-cliente {
            width: 210px;
        }

        .col-canal {
            width: 110px;
        }

        .col-pag {
            width: 120px;
        }

        .col-itens {
            width: 270px;
        }

        .col-num {
            width: 110px;
        }

        .col-acoes {
            width: 170px;
        }

        .mini {
            font-size: 12px;
            color: #475569;
            font-weight: 800;
        }

        .muted2 {
            font-size: 12px;
            color: #64748b;
        }

        .td-money {
            text-align: right;
            font-weight: 900;
            white-space: nowrap;
        }

        .td-right {
            text-align: right;
            white-space: nowrap;
        }

        .td-nowrap {
            white-space: nowrap;
        }

        .td-clip {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
            max-width: 100%;
        }

        .badge-soft {
            font-weight: 1000;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .b-open {
            background: rgba(255, 251, 235, .95);
            color: #92400e;
            border: 1px solid rgba(245, 158, 11, .25);
        }

        .b-done {
            background: rgba(240, 253, 244, .95);
            color: #166534;
            border: 1px solid rgba(34, 197, 94, .25);
        }

        .items-preview {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 12px;
            padding: 8px 10px;
            background: rgba(248, 250, 252, .7);
        }

        .item-line {
            margin-bottom: 8px;
        }

        .item-line:last-child {
            margin-bottom: 0;
        }

        .item-name {
            font-weight: 900;
            font-size: 12px;
            line-height: 1.2;
        }

        .item-meta {
            font-size: 12px;
            color: #64748b;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            white-space: nowrap;
        }

        .item-more {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
            font-weight: 900;
        }

        .actions-wrap {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .btn-action {
            height: 34px !important;
            padding: 8px 10px !important;
            font-size: 12px !important;
            border-radius: 10px !important;
            white-space: nowrap;
        }

        /* MODAL: equal height sem cortar */
        #mdDetalhes .modal-body .row.g-3>.col-md-6 {
            display: flex;
        }

        #mdDetalhes .modal-body .row.g-3>.col-md-6>.cardx {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        #mdDetalhes .modal-body .row.g-3>.col-md-6>.cardx .body {
            flex: 1;
            min-height: 0;
        }

        .sale-box {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 14px;
            background: rgba(248, 250, 252, .7);
            padding: 10px 12px;
            max-height: 260px;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
        }

        .sale-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed rgba(148, 163, 184, .35);
            font-size: 12px;
        }

        .sale-row:last-child {
            border-bottom: none;
        }

        .sale-row .left {
            min-width: 0;
        }

        .sale-row .left .nm {
            font-weight: 900;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 380px;
        }

        .sale-row .left .cd {
            color: #64748b;
            font-size: 12px;
        }

        .sale-row .right {
            white-space: nowrap;
            text-align: right;
        }

        .sale-mini {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        @media(max-width:991.98px) {
            #tbDev {
                min-width: 980px;
            }

            .grand .val {
                font-size: 22px;
            }
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
            <a href="dashboard.php" class="d-flex align-items-center gap-2">
                <img src="assets/images/logo/logo.svg" alt="logo" />
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item">
                    <a href="dashboard.php">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8.74999 18.3333C12.2376 18.3333 15.1364 15.8128 15.7244 12.4941C15.8448 11.8143 15.2737 11.25 14.5833 11.25H9.99999C9.30966 11.25 8.74999 10.6903 8.74999 10V5.41666C8.74999 4.7263 8.18563 4.15512 7.50586 4.27556C4.18711 4.86357 1.66666 7.76243 1.66666 11.25C1.66666 15.162 4.83797 18.3333 8.74999 18.3333Z" />
                                <path d="M17.0833 10C17.7737 10 18.3432 9.43708 18.2408 8.75433C17.7005 5.14918 14.8508 2.29947 11.2457 1.75912C10.5629 1.6568 10 2.2263 10 2.91665V9.16666C10 9.62691 10.3731 10 10.8333 10H17.0833Z" />
                            </svg>
                        </span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="vendas.php">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1.66666 5C1.66666 3.89543 2.5621 3 3.66666 3H16.3333C17.4379 3 18.3333 3.89543 18.3333 5V15C18.3333 16.1046 17.4379 17 16.3333 17H3.66666C2.5621 17 1.66666 16.1046 1.66666 15V5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M1.66666 5L10 10.8333L18.3333 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span class="text">Vendas</span>
                    </a>
                </li>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="true">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
                            </svg>
                        </span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse show dropdown-nav">
                        <li><a href="vendidos.php" class="active">Vendidos</a></li>
                        <li><a href="fiados.php">Fiados</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.49999 5.83331C2.03976 5.83331 1.66666 6.2064 1.66666 6.66665V10.8333C1.66666 13.5948 3.90523 15.8333 6.66666 15.8333H9.99999C12.1856 15.8333 14.0436 14.431 14.7235 12.4772C14.8134 12.4922 14.9058 12.5 15 12.5H16.6667C17.5872 12.5 18.3333 11.7538 18.3333 10.8333V8.33331C18.3333 7.41284 17.5872 6.66665 16.6667 6.66665H15C15 6.2064 14.6269 5.83331 14.1667 5.83331H2.49999Z" />
                                <path d="M2.49999 16.6667C2.03976 16.6667 1.66666 17.0398 1.66666 17.5C1.66666 17.9602 2.03976 18.3334 2.49999 18.3334H14.1667C14.6269 18.3334 15 17.9602 15 17.5C15 17.0398 14.6269 16.6667 14.1667 16.6667H2.49999Z" />
                            </svg>
                        </span>
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse dropdown-nav">
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php">Inventário</a></li>
                        <li><a href="entradas.php">Entradas</a></li>
                        <li><a href="saidas.php">Saídas</a></li>
                        <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1.66666 5.41669C1.66666 3.34562 3.34559 1.66669 5.41666 1.66669C7.48772 1.66669 9.16666 3.34562 9.16666 5.41669C9.16666 7.48775 7.48772 9.16669 5.41666 9.16669C3.34559 9.16669 1.66666 7.48775 1.66666 5.41669Z" />
                                <path d="M1.66666 14.5834C1.66666 12.5123 3.34559 10.8334 5.41666 10.8334C7.48772 10.8334 9.16666 12.5123 9.16666 14.5834C9.16666 16.6545 7.48772 18.3334 5.41666 18.3334C3.34559 18.3334 1.66666 16.6545 1.66666 14.5834Z" />
                                <path d="M10.8333 5.41669C10.8333 3.34562 12.5123 1.66669 14.5833 1.66669C16.6544 1.66669 18.3333 3.34562 18.3333 5.41669C18.3333 7.48775 16.6544 9.16669 14.5833 9.16669C12.5123 9.16669 10.8333 7.48775 10.8333 5.41669Z" />
                                <path d="M10.8333 14.5834C10.8333 12.5123 12.5123 10.8334 14.5833 10.8334C16.6544 10.8334 18.3333 12.5123 18.3333 14.5834C18.3333 16.6545 16.6544 18.3334 14.5833 18.3334C12.5123 18.3334 10.8333 16.6545 10.8333 14.5834Z" />
                            </svg>
                        </span>
                        <span class="text">Cadastros</span>
                    </a>
                    <ul id="ddmenu_cadastros" class="collapse dropdown-nav">
                        <li><a href="clientes.php">Clientes</a></li>
                        <li><a href="fornecedores.php">Fornecedores</a></li>
                        <li><a href="categorias.php">Categorias</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="relatorios.php">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4.16666 3.33335C4.16666 2.41288 4.91285 1.66669 5.83332 1.66669H14.1667C15.0872 1.66669 15.8333 2.41288 15.8333 3.33335V16.6667C15.8333 17.5872 15.0872 18.3334 14.1667 18.3334H5.83332C4.91285 18.3334 4.16666 17.5872 4.16666 16.6667V3.33335Z" />
                            </svg>
                        </span>
                        <span class="text">Relatórios</span>
                    </a>
                </li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config" aria-controls="ddmenu_config" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 1.66669C5.39763 1.66669 1.66666 5.39766 1.66666 10C1.66666 14.6024 5.39763 18.3334 10 18.3334C14.6024 18.3334 18.3333 14.6024 18.3333 10C18.3333 5.39766 14.6024 1.66669 10 1.66669Z" />
                            </svg>
                        </span>
                        <span class="text">Configurações</span>
                    </a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav">
                        <li><a href="usuarios.php">Usuários e Permissões</a></li>
                        <li><a href="parametros.php">Parâmetros do Sistema</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="suporte.php">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10.8333 2.50008C10.8333 2.03984 10.4602 1.66675 9.99999 1.66675C9.53975 1.66675 9.16666 2.03984 9.16666 2.50008C9.16666 2.96032 9.53975 3.33341 9.99999 3.33341C10.4602 3.33341 10.8333 2.96032 10.8333 2.50008Z" />
                                <path d="M11.4272 2.69637C10.9734 2.56848 10.4947 2.50006 10 2.50006C7.10054 2.50006 4.75003 4.85057 4.75003 7.75006V9.20873C4.75003 9.72814 4.62082 10.2393 4.37404 10.6963L3.36705 12.5611C2.89938 13.4272 3.26806 14.5081 4.16749 14.9078C7.88074 16.5581 12.1193 16.5581 15.8326 14.9078C16.732 14.5081 17.1007 13.4272 16.633 12.5611L15.626 10.6963C15.43 10.3333 15.3081 9.93606 15.2663 9.52773C15.0441 9.56431 14.8159 9.58339 14.5833 9.58339C12.2822 9.58339 10.4167 7.71791 10.4167 5.41673C10.4167 4.37705 10.7975 3.42631 11.4272 2.69637Z" />
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
        <header class="header"> ... </header>


        <footer class="footer"> ... </footer>
    </main>



    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

</body>

</html>