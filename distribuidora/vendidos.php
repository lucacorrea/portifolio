<?php

declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);

/* ========= INCLUDES ========= */
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

/* ========= HELPERS ========= */
function clean_buffers(): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
}

function json_out(array $payload, int $code = 200): void
{
    clean_buffers();
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_str(string $k, string $def = ''): string
{
    return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def;
}

function get_int(string $k, int $def = 0): int
{
    return isset($_GET[$k]) ? (int)$_GET[$k] : $def;
}

function brl(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function table_exists(PDO $pdo, string $table): bool
{
    $sql = "SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :table";
    $st = $pdo->prepare($sql);
    $st->execute([':table' => $table]);
    return (int)$st->fetchColumn() > 0;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $sql = "SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :table
              AND column_name = :column";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':table'  => $table,
        ':column' => $column
    ]);
    return (int)$st->fetchColumn() > 0;
}

function build_where(array &$params): string
{
    $where = " WHERE 1=1 ";

    $di    = get_str('di');
    $df    = get_str('df');
    $canal = strtoupper(get_str('canal', 'TODOS'));
    $pag   = strtoupper(get_str('pag', 'TODOS'));
    $q     = get_str('q');

    if ($di !== '') {
        $where .= " AND DATE(v.data) >= :di ";
        $params[':di'] = $di;
    }

    if ($df !== '') {
        $where .= " AND DATE(v.data) <= :df ";
        $params[':df'] = $df;
    }

    if ($canal !== '' && $canal !== 'TODOS') {
        $where .= " AND UPPER(COALESCE(v.canal,'')) = :canal ";
        $params[':canal'] = $canal;
    }

    if ($pag !== '' && $pag !== 'TODOS') {
        $where .= " AND UPPER(COALESCE(v.pagamento,'')) = :pag ";
        $params[':pag'] = $pag;
    }

    if ($q !== '') {
        $params[':q_id']      = '%' . $q . '%';
        $params[':q_data']    = '%' . $q . '%';
        $params[':q_cliente'] = '%' . $q . '%';
        $params[':q_end']     = '%' . $q . '%';
        $params[':q_obs']     = '%' . $q . '%';
        $params[':q_pag']     = '%' . $q . '%';
        $params[':q_canal']   = '%' . $q . '%';
        $params[':q_sub']     = '%' . $q . '%';
        $params[':q_desc']    = '%' . $q . '%';
        $params[':q_taxa']    = '%' . $q . '%';
        $params[':q_total']   = '%' . $q . '%';
        $params[':q_item1']   = '%' . $q . '%';
        $params[':q_item2']   = '%' . $q . '%';
        $params[':q_item3']   = '%' . $q . '%';

        $where .= "
            AND (
                CAST(v.id AS CHAR) LIKE :q_id
                OR CAST(v.data AS CHAR) LIKE :q_data
                OR COALESCE(v.cliente,'') LIKE :q_cliente
                OR COALESCE(v.endereco,'') LIKE :q_end
                OR COALESCE(v.obs,'') LIKE :q_obs
                OR COALESCE(v.pagamento,'') LIKE :q_pag
                OR COALESCE(v.canal,'') LIKE :q_canal
                OR CAST(COALESCE(v.subtotal,0) AS CHAR) LIKE :q_sub
                OR CAST(COALESCE(v.desconto_valor,0) AS CHAR) LIKE :q_desc
                OR CAST(COALESCE(v.taxa_entrega,0) AS CHAR) LIKE :q_taxa
                OR CAST(COALESCE(v.total,0) AS CHAR) LIKE :q_total
                OR EXISTS (
                    SELECT 1
                    FROM venda_itens vi
                    WHERE vi.venda_id = v.id
                      AND (
                          COALESCE(vi.nome,'') LIKE :q_item1
                          OR COALESCE(vi.codigo,'') LIKE :q_item2
                          OR COALESCE(vi.unidade,'') LIKE :q_item3
                      )
                )
            )
        ";
    }

    return $where;
}

function fetch_items_for_sale_ids(array $saleIds): array
{
    if (!$saleIds) return [];

    $pdo = db();
    if (!table_exists($pdo, 'venda_itens')) return [];

    $saleIds = array_values(array_unique(array_map('intval', $saleIds)));
    if (!$saleIds) return [];

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

    $st = $pdo->prepare($sql);
    $st->execute($saleIds);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $out = [];
    foreach ($rows as $r) {
        $vendaId = (int)($r['venda_id'] ?? 0);
        if ($vendaId <= 0) continue;

        $preco = (float)($r['preco_unit'] ?? 0);
        $qtd   = (float)($r['qtd'] ?? 0);
        $sub   = (float)($r['subtotal'] ?? 0);
        $total = $sub > 0 ? $sub : ($preco * $qtd);

        if (!isset($out[$vendaId])) $out[$vendaId] = [];
        $out[$vendaId][] = [
            'codigo' => (string)($r['codigo'] ?? ''),
            'nome'   => (string)($r['nome'] ?? 'Item'),
            'qtd'    => $qtd,
            'un'     => (string)($r['unidade'] ?? ''),
            'preco'  => $preco,
            'total'  => $total,
        ];
    }

    return $out;
}

function get_recebido_for_sale(PDO $pdo, int $saleId, string $pagamento, float $totalVenda): float
{
    $pagamento = strtoupper(trim($pagamento));

    if ($pagamento !== 'FIADO') {
        return $totalVenda;
    }

    if (!table_exists($pdo, 'fiados')) {
        return 0.0;
    }

    if (!column_exists($pdo, 'fiados', 'venda_id') || !column_exists($pdo, 'fiados', 'valor_pago')) {
        return 0.0;
    }

    $st = $pdo->prepare("SELECT COALESCE(valor_pago,0) FROM fiados WHERE venda_id = ? LIMIT 1");
    $st->execute([$saleId]);
    return (float)($st->fetchColumn() ?: 0);
}

function build_rows_from_sales(array $sales): array
{
    $pdo = db();

    $ids = array_map(fn($r) => (int)$r['id'], $sales);
    $itemsMap = fetch_items_for_sale_ids($ids);

    $rows = [];
    foreach ($sales as $r) {
        $id = (int)$r['id'];
        $itens = $itemsMap[$id] ?? [];

        $itensTotal = 0.0;
        $itensCount = 0;

        foreach ($itens as $it) {
            $itensTotal += (float)$it['total'];
            $itensCount++;
        }

        $recebido = get_recebido_for_sale(
            $pdo,
            $id,
            (string)($r['pagamento'] ?? ''),
            (float)($r['total'] ?? 0)
        );

        $rows[] = [
            'id'          => $id,
            'data'        => (string)($r['data'] ?? ''),
            'created_at'  => (string)($r['created_at'] ?? ''),
            'cliente'     => (string)($r['cliente'] ?? ''),
            'canal'       => (string)($r['canal'] ?? ''),
            'pagamento'   => (string)($r['pagamento'] ?? ''),
            'subtotal'    => (float)($r['subtotal'] ?? 0),
            'desconto'    => (float)($r['desconto_valor'] ?? 0),
            'taxa'        => (float)($r['taxa_entrega'] ?? 0),
            'total'       => (float)($r['total'] ?? 0),
            'recebido'    => $recebido,
            'endereco'    => (string)($r['endereco'] ?? ''),
            'obs'         => (string)($r['obs'] ?? ''),
            'itens'       => $itens,
            'itens_count' => $itensCount,
            'itens_total' => $itensTotal
        ];
    }

    return $rows;
}

function fetch_one_sale(int $id): ?array
{
    $pdo = db();

    $st = $pdo->prepare("SELECT * FROM vendas WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $v = $st->fetch(PDO::FETCH_ASSOC);

    if (!$v) return null;

    $itemsMap = fetch_items_for_sale_ids([$id]);
    $itens = $itemsMap[$id] ?? [];

    $itensTotal = 0.0;
    $itensQtd = 0.0;

    foreach ($itens as $it) {
        $itensTotal += (float)$it['total'];
        $itensQtd += (float)($it['qtd'] ?? 0);
    }

    return [
        'venda'       => $v,
        'itens'       => $itens,
        'itens_total' => $itensTotal,
        'itens_qtd'   => $itensQtd
    ];
}

function sum_recebido_vendas_geral(string $where, array $params): float
{
    $pdo = db();

    $sql = "
        SELECT
            v.id,
            v.pagamento,
            v.total
        FROM vendas v
        $where
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $sum = 0.0;
    foreach ($rows as $r) {
        $sum += get_recebido_for_sale(
            $pdo,
            (int)($r['id'] ?? 0),
            (string)($r['pagamento'] ?? ''),
            (float)($r['total'] ?? 0)
        );
    }

    return $sum;
}

function sum_fiados_pagamentos_filtrados(PDO $pdo): float
{
    $recFiadoExtra = 0.0;

    if (
        table_exists($pdo, 'fiados_pagamentos')
        && column_exists($pdo, 'fiados_pagamentos', 'valor')
        && column_exists($pdo, 'fiados_pagamentos', 'created_at')
    ) {
        $di = get_str('di');
        $df = get_str('df');

        if ($di !== '' && $df !== '') {
            $stPag = $pdo->prepare("
                SELECT COALESCE(SUM(valor),0)
                FROM fiados_pagamentos
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stPag->execute([$di, $df]);
            $recFiadoExtra = (float)($stPag->fetchColumn() ?: 0);
        } elseif ($di !== '') {
            $stPag = $pdo->prepare("
                SELECT COALESCE(SUM(valor),0)
                FROM fiados_pagamentos
                WHERE DATE(created_at) >= ?
            ");
            $stPag->execute([$di]);
            $recFiadoExtra = (float)($stPag->fetchColumn() ?: 0);
        } elseif ($df !== '') {
            $stPag = $pdo->prepare("
                SELECT COALESCE(SUM(valor),0)
                FROM fiados_pagamentos
                WHERE DATE(created_at) <= ?
            ");
            $stPag->execute([$df]);
            $recFiadoExtra = (float)($stPag->fetchColumn() ?: 0);
        } else {
            $stPag = $pdo->query("SELECT COALESCE(SUM(valor),0) FROM fiados_pagamentos");
            $recFiadoExtra = (float)($stPag->fetchColumn() ?: 0);
        }
    }

    return $recFiadoExtra;
}

function fetch_filtered_result(): array
{
    $pdo = db();

    $page = max(1, get_int('page', 1));
    $per  = get_int('per', 10);
    $per  = in_array($per, [10, 20, 30, 40, 50, 100], true) ? $per : 10;
    $off  = ($page - 1) * $per;

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
    $tot = $stTot->fetch(PDO::FETCH_ASSOC) ?: [
        'qtd' => 0,
        'subtotal' => 0,
        'desconto' => 0,
        'taxa' => 0,
        'total' => 0
    ];

    $sql = "
        SELECT
            v.id,
            v.data,
            v.cliente,
            v.canal,
            v.endereco,
            v.obs,
            v.desconto_tipo,
            v.desconto_valor,
            v.taxa_entrega,
            v.subtotal,
            v.total,
            v.pagamento_mode,
            v.pagamento,
            v.pagamento_json,
            v.created_at
        FROM vendas v
        $where
        ORDER BY v.id DESC
        LIMIT $off, $per
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $sales = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $rows = build_rows_from_sales($sales);

    $recFiadoExtra = sum_fiados_pagamentos_filtrados($pdo);
    $recebidoVendasGeral = sum_recebido_vendas_geral($where, $params);

    $totalCount = (int)($tot['qtd'] ?? 0);
    $pages = (int)max(1, ceil($totalCount / $per));

    return [
        'meta' => [
            'page'  => $page,
            'per'   => $per,
            'pages' => $pages,
            'total' => $totalCount,
        ],
        'totais' => [
            'qtd'             => $totalCount,
            'subtotal'        => (float)($tot['subtotal'] ?? 0),
            'desconto'        => (float)($tot['desconto'] ?? 0),
            'taxa'            => (float)($tot['taxa'] ?? 0),
            'total'           => (float)($tot['total'] ?? 0),
            'recebido_vendas' => $recebidoVendasGeral,
            'recebido_fiados' => $recFiadoExtra,
            'caixa_real'      => $recebidoVendasGeral + $recFiadoExtra,
        ],
        'rows' => $rows
    ];
}

function fetch_initial_result(): array
{
    $pdo = db();

    $per = 10;

    $sql = "
        SELECT
            v.id,
            v.data,
            v.cliente,
            v.canal,
            v.endereco,
            v.obs,
            v.desconto_tipo,
            v.desconto_valor,
            v.taxa_entrega,
            v.subtotal,
            v.total,
            v.pagamento_mode,
            v.pagamento,
            v.pagamento_json,
            v.created_at
        FROM vendas v
        ORDER BY v.id DESC
        LIMIT 0, $per
    ";
    $sales = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $rows = build_rows_from_sales($sales);

    $stTot = $pdo->query("
        SELECT
            COUNT(*) AS qtd,
            COALESCE(SUM(subtotal),0) AS subtotal,
            COALESCE(SUM(desconto_valor),0) AS desconto,
            COALESCE(SUM(taxa_entrega),0) AS taxa,
            COALESCE(SUM(total),0) AS total
        FROM vendas
    ");
    $tot = $stTot->fetch(PDO::FETCH_ASSOC) ?: [
        'qtd' => 0,
        'subtotal' => 0,
        'desconto' => 0,
        'taxa' => 0,
        'total' => 0
    ];

    $recebidoVendasGeral = sum_recebido_vendas_geral(" WHERE 1=1 ", []);
    $recFiadoExtra = sum_fiados_pagamentos_filtrados($pdo);

    $qtd = (int)($tot['qtd'] ?? 0);
    $pages = (int)max(1, ceil($qtd / $per));

    return [
        'meta' => [
            'page'  => 1,
            'per'   => $per,
            'pages' => $pages,
            'total' => $qtd,
        ],
        'totais' => [
            'qtd'             => $qtd,
            'subtotal'        => (float)($tot['subtotal'] ?? 0),
            'desconto'        => (float)($tot['desconto'] ?? 0),
            'taxa'            => (float)($tot['taxa'] ?? 0),
            'total'           => (float)($tot['total'] ?? 0),
            'recebido_vendas' => $recebidoVendasGeral,
            'recebido_fiados' => $recFiadoExtra,
            'caixa_real'      => $recebidoVendasGeral + $recFiadoExtra,
        ],
        'rows' => $rows
    ];
}

function render_table_rows(array $rows): string
{
    if (!$rows) {
        return '<tr><td colspan="9" class="muted">Nenhuma venda encontrada.</td></tr>';
    }

    $html = '';
    foreach ($rows as $r) {
        $id        = (int)$r['id'];
        $data      = e((string)$r['data']);
        $createdAt = e((string)$r['created_at']);
        $cliente   = e((string)($r['cliente'] ?? '—'));
        $canal     = strtoupper((string)($r['canal'] ?? ''));
        $pagamento = e((string)($r['pagamento'] ?? '—'));
        $endereco  = e((string)($r['endereco'] ?? ''));
        $total     = brl((float)$r['total']);
        $recebido  = brl((float)$r['recebido']);

        $canalBadge = $canal === 'DELIVERY'
            ? '<span class="badge-soft b-open">DELIVERY</span>'
            : '<span class="badge-soft b-done">PRESENCIAL</span>';

        $itensHtml = '<span class="muted">—</span>';
        if (!empty($r['itens'])) {
            $show = array_slice($r['itens'], 0, 2);
            $extra = count($r['itens']) - count($show);

            $tmp = '<div class="items-preview">';
            foreach ($show as $it) {
                $tmp .= '
                    <div class="item-line">
                        <div class="item-name">' . e((string)($it['nome'] ?? 'Item')) . '</div>
                        <div class="item-meta">
                            <span>' . e((string)($it['qtd'] ?? 0)) . ' ' . e((string)($it['un'] ?? '')) . '</span>
                            <span><b>' . brl((float)($it['total'] ?? 0)) . '</b></span>
                        </div>
                    </div>
                ';
            }
            if ($extra > 0) {
                $tmp .= '<div class="item-more">+ ' . $extra . ' item(ns)</div>';
            }
            $tmp .= '</div>';
            $itensHtml = $tmp;
        }

        $html .= '
            <tr>
                <td class="td-nowrap"><b>#' . $id . '</b></td>
                <td>
                    <div class="mini">' . e((string)$data) . '</div>
                    <div class="muted2">' . e((string)$createdAt) . '</div>
                </td>
                <td>
                    <div class="td-clip mini">' . $cliente . '</div>
                    ' . ($endereco !== '' ? '<div class="td-clip muted2">' . $endereco . '</div>' : '') . '
                </td>
                <td class="td-nowrap">' . $canalBadge . '</td>
                <td class="td-nowrap"><span class="badge-soft b-open">' . $pagamento . '</span></td>
                <td>' . $itensHtml . '</td>
                <td class="td-money">' . $total . '</td>
                <td class="td-money text-success">' . $recebido . '</td>
                <td>
                    <div class="actions-wrap">
                        <button class="main-btn light-btn btn-hover btn-action" onclick="openDetails(' . $id . ')">Detalhes</button>
                        <button class="main-btn primary-btn btn-hover btn-action" onclick="openCupom(' . $id . ')">Cupom</button>
                    </div>
                </td>
            </tr>
        ';
    }

    return $html;
}

/* ========= ACTIONS ========= */
$action = strtolower(get_str('action'));

if ($action === 'fetch') {
    try {
        $result = fetch_filtered_result();
        json_out([
            'ok' => true,
            'meta' => $result['meta'],
            'totais' => $result['totais'],
            'rows' => $result['rows']
        ]);
    } catch (Throwable $e) {
        json_out([
            'ok' => false,
            'msg' => 'Erro ao carregar vendas: ' . $e->getMessage()
        ], 500);
    }
}

if ($action === 'one') {
    try {
        $id = get_int('id', 0);
        if ($id <= 0) {
            json_out(['ok' => false, 'msg' => 'ID inválido'], 400);
        }

        $one = fetch_one_sale($id);
        if (!$one) {
            json_out(['ok' => false, 'msg' => 'Venda não encontrada'], 404);
        }

        json_out(['ok' => true, 'data' => $one]);
    } catch (Throwable $e) {
        json_out([
            'ok' => false,
            'msg' => 'Erro ao abrir detalhes: ' . $e->getMessage()
        ], 500);
    }
}

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

if ($action === 'excel') {
    $result = fetch_filtered_result();
    $rows = $result['rows'];

    $agora = date('d/m/Y H:i');
    $di = get_str('di') ?: '—';
    $df = get_str('df') ?: '—';
    $canal = get_str('canal', 'TODOS');
    $pag = get_str('pag', 'TODOS');
    $q = get_str('q') ?: '—';

    $fname = 'vendidos_' . date('Y-m-d_His') . '.xls';

    clean_buffers();
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office"
        xmlns:x="urn:schemas-microsoft-com:office:excel"
        xmlns="http://www.w3.org/TR/REC-html40">

    <head>
        <meta charset="UTF-8">
        <style>
            @page {
                size: landscape;
                margin: 1cm;
            }

            body {
                font-family: Arial, Helvetica, sans-serif;
                font-size: 11pt;
                margin: 0;
                padding: 0;
            }

            table {
                border-collapse: collapse;
                width: 100%;
                table-layout: fixed;
            }

            .tbl-meta td {
                border: 1px solid #000;
                padding: 6px;
                font-size: 11pt;
            }

            .tbl-main th,
            .tbl-main td {
                border: 1px solid #000;
                padding: 6px;
                font-size: 11pt;
                vertical-align: middle;
            }

            .title {
                font-size: 16pt;
                font-weight: 700;
                text-align: center;
                background: #dbeafe;
            }

            .head {
                background: #dbeafe;
                font-weight: 700;
                text-align: center;
            }

            .center {
                text-align: center;
            }

            .right {
                text-align: center;
            }

            .foot {
                font-weight: 700;
                background: #eef2ff;
            }

            .w-id {
                width: 8%;
            }

            .w-data {
                width: 12%;
            }

            .w-cli {
                width: 26%;
            }

            .w-canal {
                width: 12%;
            }

            .w-pag {
                width: 12%;
            }

            .w-num {
                width: 10%;
            }

            .print-wide {
                width: 100%;
            }
        </style>
    </head>

    <body>
        <table class="tbl-meta print-wide" style="border: 0.8px solid #000;">
            <tr>
                <td colspan="9" class="title">PAINEL DA DISTRIBUIDORA - VENDIDOS</td>
            </tr>
            <tr>
                <td colspan="9">Gerado em: <?= e($agora) ?></td>
            </tr>
            <tr>
                <td colspan="9">
                    Período: <?= e($di) ?> até <?= e($df) ?> |
                    Canal: <?= e($canal) ?> |
                    Pagamento: <?= e($pag) ?> |
                    Busca: <?= e($q) ?>
                </td>
            </tr>
        </table>

        <table class="tbl-main print-wide" style="margin-top:6px;">
            <thead>
                <tr>
                    <th class="head w-id">ID</th>
                    <th class="head w-data">Data</th>
                    <th class="head w-cli">Cliente</th>
                    <th class="head w-canal">Canal</th>
                    <th class="head w-pag">Pagamento</th>
                    <th class="head w-num">Subtotal</th>
                    <th class="head w-num">Desconto</th>
                    <th class="head w-num">Entrega</th>
                    <th class="head w-num">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sumSub = 0.0;
                $sumDesc = 0.0;
                $sumTax = 0.0;
                $sumTot = 0.0;
                foreach ($rows as $r):
                    $sumSub += (float)$r['subtotal'];
                    $sumDesc += (float)$r['desconto'];
                    $sumTax += (float)$r['taxa'];
                    $sumTot += (float)$r['total'];
                ?>
                    <tr>
                        <td class="center"><?= (int)$r['id'] ?></td>
                        <td class="center"><?= e((string)$r['data']) ?></td>
                        <td><?= e((string)($r['cliente'] ?? '')) ?></td>
                        <td class="center"><?= e((string)($r['canal'] ?? '')) ?></td>
                        <td class="center"><?= e((string)($r['pagamento'] ?? '')) ?></td>
                        <td class="center"><?= e(number_format((float)$r['subtotal'], 2, ',', '.')) ?></td>
                        <td class="center"><?= e(number_format((float)$r['desconto'], 2, ',', '.')) ?></td>
                        <td class="center"><?= e(number_format((float)$r['taxa'], 2, ',', '.')) ?></td>
                        <td class="center"><?= e(number_format((float)$r['total'], 2, ',', '.')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="foot">
                    <td colspan="5" class="center">Totais</td>
                    <td class="center"><?= e(number_format($sumSub, 2, ',', '.')) ?></td>
                    <td class="center"><?= e(number_format($sumDesc, 2, ',', '.')) ?></td>
                    <td class="center"><?= e(number_format($sumTax, 2, ',', '.')) ?></td>
                    <td class="center"><?= e(number_format($sumTot, 2, ',', '.')) ?></td>
                </tr>
            </tfoot>
        </table>
    </body>

    </html>
<?php
    exit;
}

/* ========= INITIAL PHP RENDER ========= */
$initial = fetch_initial_result();
$csrf = csrf_token();

$initialRows   = $initial['rows'];
$initialMeta   = $initial['meta'];
$initialTotais = $initial['totais'];

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

        .mini {
            font-size: 12px;
            color: #475569;
            font-weight: 800;
        }

        .muted2 {
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

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 14px;
        }

        .summary-card {
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, .22);
            padding: 14px 16px;
            box-shadow: 0 6px 24px rgba(15, 23, 42, .04);
            min-height: 94px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .summary-card .lbl {
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: .3px;
        }

        .summary-card .val {
            font-size: 28px;
            line-height: 1.1;
            font-weight: 1000;
        }

        .summary-card.s1 {
            background: #f8fafc;
            border-left: 4px solid #0f172a;
        }

        .summary-card.s2 {
            background: #f8fafc;
            border-left: 4px solid #166534 !important;
        }

        .summary-card.s3 {
            background: #f8fafc !important;
            border-left: 4px solid #0369a1;
        }

        .summary-card.s4 {
            background: #f8fafc !important;
            border-left: 4px solid #4338ca;
        }

        .summary-card.s1 .val {
            color: #0f172a;
        }

        .summary-card.s2 .val {
            color: #166534;
        }

        .summary-card.s3 .val {
            color: #0369a1;
        }

        .summary-card.s4 .val {
            color: #4338ca;
        }

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
            text-align: center;
        }

        #tbDev tbody td {
            border-top: 1px solid rgba(148, 163, 184, .18);
            padding: 10px 10px;
            font-size: 13px;
            vertical-align: top;
            color: #0f172a;
            background: #fff;
        }

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

        .td-money {
            text-align: center;
            font-weight: 900;
            white-space: nowrap;
        }

        .td-nowrap {
            white-space: nowrap;
            text-align: center;
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
            justify-content: center;
        }

        .btn-action {
            height: 34px !important;
            padding: 8px 10px !important;
            font-size: 12px !important;
            border-radius: 10px !important;
            white-space: nowrap;
        }

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

        @media(max-width:1199.98px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media(max-width:991.98px) {
            #tbDev {
                min-width: 980px;
            }

            .grand .val {
                font-size: 22px;
            }
        }

        @media(max-width:575.98px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

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
                        <span class="icon"><i class="lni lni-dashboard"></i></span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="vendas.php">
                        <span class="icon"><i class="lni lni-cart"></i></span>
                        <span class="text">Vendas</span>
                    </a>
                </li>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
                        <span class="icon"><i class="lni lni-layers"></i></span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse dropdown-nav show">
                        <li><a href="vendidos.php" class="active">Vendidos</a></li>
                        <li><a href="fiados.php">À Prazo</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon"><i class="lni lni-package"></i></span>
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
                        <span class="icon"><i class="lni lni-users"></i></span>
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
                        <span class="icon"><i class="lni lni-clipboard"></i></span>
                        <span class="text">Relatórios</span>
                    </a>
                </li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config" aria-controls="ddmenu_config" aria-expanded="false">
                        <span class="icon"><i class="lni lni-cog"></i></span>
                        <span class="text">Configurações</span>
                    </a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav">
                        <li><a href="usuarios.php">Usuários e Permissões</a></li>
                        <li><a href="parametros.php">Parâmetros do Sistema</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="suporte.php">
                        <span class="icon"><i class="lni lni-whatsapp"></i></span>
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
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-compact" type="button">
                                    <i class="lni lni-chevron-left me-2"></i> Menu
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7 col-md-7 col-6">
                        <div class="header-right">
                            <div class="profile-box ml-15">
                                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="profile-info">
                                        <div class="info">
                                            <div>
                                                <h6 class="fw-500">Sair</h6>
                                            </div>
                                        </div>
                                    </div>
                                </button>
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
                                <h2>Gestão de Vendas</h2>
                                <p class="text-muted">Listagem e filtros de vendas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cardx mb-3">
                    <div class="head">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="pill ok" id="pillCountTop"><?= (int)$initialTotais['qtd'] ?> vendas</span>
                                <span class="muted" id="lblRange">Período: — até —</span>
                            </div>
                            <div class="muted mt-1">Lista de vendas registradas no PDV (tabela <b>vendas</b>)</div>
                        </div>
                        <div class="toolbar">
                            <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel">
                                <i class="lni lni-download me-1"></i> Excel
                            </button>
                            <select id="per" class="form-select compact" style="min-width:190px;">
                                <option value="10" selected>10 por página</option>
                                <option value="20">20 por página</option>
                                <option value="30">30 por página</option>
                                <option value="40">40 por página</option>
                                <option value="50">50 por página</option>
                                <option value="100">100 por página</option>
                            </select>
                        </div>
                    </div>

                    <div class="body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label mini">Data inicial</label>
                                <input type="date" class="form-control compact" id="di">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mini">Data final</label>
                                <input type="date" class="form-control compact" id="df">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mini">Canal</label>
                                <select class="form-select compact" id="canal">
                                    <option value="TODOS" selected>Todos</option>
                                    <option value="PRESENCIAL">Presencial</option>
                                    <option value="DELIVERY">Delivery</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mini">Pagamento</label>
                                <select class="form-select compact" id="pag">
                                    <option value="TODOS" selected>Todos</option>
                                    <option value="DINHEIRO">Dinheiro</option>
                                    <option value="PIX">PIX</option>
                                    <option value="CARTAO">Cartão</option>
                                    <option value="BOLETO">Boleto</option>
                                    <option value="MULTI">Multi</option>
                                    <option value="FIADO">Fiado</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mini">Pesquisar em tudo da tabela</label>
                                <input type="text" class="form-control compact" id="q" placeholder="ID, cliente, canal, pagamento, itens, total..." autocomplete="off">
                            </div>

                            <div class="col-12 d-flex gap-2 flex-wrap mt-2">
                                <button class="main-btn primary-btn btn-hover btn-compact" id="btnFiltrar">
                                    <i class="lni lni-funnel me-1"></i> Filtrar
                                </button>
                                <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar">
                                    <i class="lni lni-close me-1"></i> Limpar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="summary-grid">
                    <div class="summary-card s1">
                        <div class="lbl">Total Vendido (Bruto)</div>
                        <div class="val" id="txtTotalBruto"><?= e(brl((float)$initialTotais['total'])) ?></div>
                    </div>
                    <div class="summary-card s2">
                        <div class="lbl">Recebido em Vendas</div>
                        <div class="val" id="txtRecVendas"><?= e(brl((float)$initialTotais['recebido_vendas'])) ?></div>
                    </div>
                    <div class="summary-card s3">
                        <div class="lbl">Receb. À Prazo (Dívidas)</div>
                        <div class="val" id="txtRecFiado"><?= e(brl((float)$initialTotais['recebido_fiados'])) ?></div>
                    </div>
                    <div class="summary-card s4">
                        <div class="lbl">Caixa Real (Total)</div>
                        <div class="val" id="txtCaixaReal"><?= e(brl((float)$initialTotais['caixa_real'])) ?></div>
                    </div>
                </div>

                <div class="row g-3 equal-h">
                    <div class="col-lg-8">
                        <div class="cardx card-table">
                            <div class="head">
                                <div class="muted"><b>Vendidos</b> • pesquisa AJAX automática em toda a tabela</div>
                                <div class="toolbar">
                                    <div class="pill ok" id="pillCountTable"><?= (int)$initialTotais['qtd'] ?> vendas</div>
                                    <div class="pill" id="pillLoading" style="display:none;">
                                        <i class="lni lni-spinner-arrow lni-spin"></i> Carregando...
                                    </div>
                                </div>
                            </div>

                            <div class="body">
                                <div class="table-wrap">
                                    <table class="table table-hover mb-0" id="tbDev">
                                        <thead>
                                            <tr>
                                                <th class="col-id">ID</th>
                                                <th class="col-data">Data</th>
                                                <th class="col-cliente">Cliente</th>
                                                <th class="col-canal">Canal</th>
                                                <th class="col-pag">Pagamento</th>
                                                <th class="col-itens">Itens</th>
                                                <th class="col-num">Total</th>
                                                <th class="col-num">Recebido</th>
                                                <th class="col-acoes">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody">
                                            <?= render_table_rows($initialRows) ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="page-nav">
                                    <button class="page-btn" id="btnPrev">←</button>
                                    <span class="page-info" id="pageInfo">Página <?= (int)$initialMeta['page'] ?> / <?= (int)$initialMeta['pages'] ?></span>
                                    <button class="page-btn" id="btnNext">→</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="cardx card-tot">
                            <div class="head">
                                <div class="fw-1000">Totais do Filtro</div>
                                <div class="muted">Totais gerais do filtro inteiro, não só da página</div>
                            </div>
                            <div class="body">
                                <div class="box-tot">
                                    <div class="tot-row"><span>Quantidade</span><span id="tQtd"><?= (int)$initialTotais['qtd'] ?></span></div>
                                    <div class="tot-row"><span>Subtotal</span><span id="tSub"><?= e(brl((float)$initialTotais['subtotal'])) ?></span></div>
                                    <div class="tot-row"><span>Desconto</span><span id="tDesc"><?= e(brl((float)$initialTotais['desconto'])) ?></span></div>
                                    <div class="tot-row"><span>Entrega</span><span id="tTaxa"><?= e(brl((float)$initialTotais['taxa'])) ?></span></div>
                                    <div class="tot-hr"></div>
                                    <div class="grand">
                                        <div class="lbl">TOTAL</div>
                                        <div class="val" id="tTotal"><?= e(brl((float)$initialTotais['total'])) ?></div>
                                    </div>
                                </div>

                                <div class="muted mt-3">
                                    <b>Obs.:</b> a pesquisa também procura em <b>venda_itens</b>.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <footer class="footer">...</footer>
    </main>

    <div class="modal fade" id="mdDetalhes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" style="border-radius:16px;">
                <div class="modal-header">
                    <h5 class="modal-title fw-1000">Detalhes da Venda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="cardx">
                                <div class="head"><b>Dados</b></div>
                                <div class="body">
                                    <div class="tot-row"><span>ID</span><span id="dId">—</span></div>
                                    <div class="tot-row"><span>Data/Hora</span><span id="dDt">—</span></div>
                                    <div class="tot-row"><span>Cliente</span><span id="dCli">—</span></div>
                                    <div class="tot-row"><span>Canal</span><span id="dCanal">—</span></div>
                                    <div class="tot-row"><span>Pagamento</span><span id="dPag">—</span></div>
                                    <div class="tot-row"><span>Endereço</span><span id="dEnd">—</span></div>
                                    <div class="tot-row"><span>Obs</span><span id="dObs">—</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="cardx">
                                <div class="head"><b>Totais</b></div>
                                <div class="body">
                                    <div class="tot-row"><span>Subtotal</span><span id="dSub">R$ 0,00</span></div>
                                    <div class="tot-row"><span>Desconto</span><span id="dDesc">R$ 0,00</span></div>
                                    <div class="tot-row"><span>Entrega</span><span id="dTaxa">R$ 0,00</span></div>
                                    <div class="tot-hr"></div>
                                    <div class="grand">
                                        <div class="lbl">TOTAL</div>
                                        <div class="val" id="dTotal">R$ 0,00</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="cardx">
                                <div class="head d-flex justify-content-between align-items-center">
                                    <b>Itens</b>
                                    <button class="main-btn light-btn btn-hover btn-compact" id="btnCupomModal">
                                        <i class="lni lni-printer me-1"></i> Cupom
                                    </button>
                                </div>
                                <div class="body">
                                    <div class="sale-box" id="dItens">—</div>
                                    <div class="sale-mini">
                                        <span id="dItensQtd">0 itens</span>
                                        <span class="td-money" id="dItensTot">R$ 0,00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const el = (id) => document.getElementById(id);
        const CUPOM_URL = './assets/dados/vendas/cupom.php';

        const state = {
            page: <?= (int)$initialMeta['page'] ?>,
            pages: <?= (int)$initialMeta['pages'] ?>,
            per: 10,
            lastCupomId: null,
            searchTimer: null
        };

        function brl(v) {
            try {
                return new Intl.NumberFormat('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                }).format(Number(v || 0));
            } catch (e) {
                return 'R$ ' + (Number(v || 0).toFixed(2)).replace('.', ',');
            }
        }

        function fmtDate(iso) {
            if (!iso) return '—';
            const s = String(iso).slice(0, 10);
            const p = s.split('-');
            if (p.length === 3) return `${p[2]}/${p[1]}/${p[0]}`;
            return iso;
        }

        function fmtDateTime(dt) {
            if (!dt) return '—';
            return String(dt);
        }

        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, (m) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [m]));
        }

        function numQ(v) {
            const n = Number(v || 0);
            if (Number.isInteger(n)) return String(n);
            return n.toLocaleString('pt-BR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 3
            });
        }

        function buildParams() {
            const p = new URLSearchParams();
            p.set('action', 'fetch');
            p.set('page', String(state.page));
            p.set('per', String(state.per));

            const di = el('di').value.trim();
            const df = el('df').value.trim();
            const canal = el('canal').value.trim();
            const pag = el('pag').value.trim();
            const q = el('q').value.trim();

            if (di) p.set('di', di);
            if (df) p.set('df', df);
            if (canal) p.set('canal', canal);
            if (pag) p.set('pag', pag);
            if (q) p.set('q', q);

            return p;
        }

        function buildExportUrl(action) {
            const p = new URLSearchParams();
            p.set('action', action);

            const di = el('di').value.trim();
            const df = el('df').value.trim();
            const canal = el('canal').value.trim();
            const pag = el('pag').value.trim();
            const q = el('q').value.trim();

            if (di) p.set('di', di);
            if (df) p.set('df', df);
            if (canal) p.set('canal', canal);
            if (pag) p.set('pag', pag);
            if (q) p.set('q', q);

            return `vendidos.php?${p.toString()}`;
        }

        function setLoading(on) {
            el('pillLoading').style.display = on ? '' : 'none';
        }

        function renderRows(rows) {
            if (!rows || !rows.length) {
                el('tbody').innerHTML = `<tr><td colspan="9" class="muted">Nenhuma venda encontrada com este filtro.</td></tr>`;
                return;
            }

            el('tbody').innerHTML = rows.map(r => {
                const canalBadge = String(r.canal || '').toUpperCase() === 'DELIVERY' ?
                    `<span class="badge-soft b-open">DELIVERY</span>` :
                    `<span class="badge-soft b-done">PRESENCIAL</span>`;

                const pagBadge = `<span class="badge-soft b-open">${escapeHtml(r.pagamento || '—')}</span>`;

                let itensHtml = `<span class="muted">—</span>`;
                if (r.itens && r.itens.length) {
                    const show = r.itens.slice(0, 2);
                    const extra = r.itens.length - show.length;

                    itensHtml = `
                        <div class="items-preview">
                            ${show.map(it => `
                                <div class="item-line">
                                    <div class="item-name">${escapeHtml(it.nome || 'Item')}</div>
                                    <div class="item-meta">
                                        <span>${numQ(it.qtd)} ${escapeHtml(it.un || '')}</span>
                                        <span><b>${brl(it.total)}</b></span>
                                    </div>
                                </div>
                            `).join('')}
                            ${extra > 0 ? `<div class="item-more">+ ${extra} item(ns)</div>` : ``}
                        </div>
                    `;
                }

                return `
                    <tr>
                        <td class="td-nowrap"><b>#${r.id}</b></td>
                        <td class="td-nowrap">
                            <div class="mini">${fmtDate(r.data)}</div>
                            <div class="muted2">${fmtDateTime(r.created_at)}</div>
                        </td>
                        <td>
                            <div class="td-clip mini">${escapeHtml(r.cliente || '—')}</div>
                            ${r.endereco ? `<div class="td-clip muted2">${escapeHtml(r.endereco)}</div>` : ``}
                        </td>
                        <td class="td-nowrap">${canalBadge}</td>
                        <td class="td-nowrap">${pagBadge}</td>
                        <td>${itensHtml}</td>
                        <td class="td-money">${brl(r.total)}</td>
                        <td class="td-money text-success">${brl(r.recebido)}</td>
                        <td>
                            <div class="actions-wrap">
                                <button class="main-btn light-btn btn-hover btn-action" onclick="openDetails(${r.id})">Detalhes</button>
                                <button class="main-btn primary-btn btn-hover btn-action" onclick="openCupom(${r.id})">Cupom</button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function searchRows() {
            setLoading(true);

            const p = buildParams();
            const url = `vendidos.php?${p.toString()}`;

            try {
                const res = await fetch(url, {
                    headers: {
                        'X-CSRF': csrf,
                        'Accept': 'application/json'
                    }
                });

                const text = await res.text();
                let js;
                try {
                    js = JSON.parse(text);
                } catch (e) {
                    throw new Error('Resposta inválida do servidor.');
                }

                if (!res.ok || !js.ok) {
                    throw new Error(js?.msg || 'Falha ao pesquisar');
                }

                state.page = Number(js.meta.page || 1);
                state.pages = Number(js.meta.pages || 1);

                el('tQtd').textContent = js.totais.qtd;
                el('tSub').textContent = brl(js.totais.subtotal);
                el('tDesc').textContent = brl(js.totais.desconto);
                el('tTaxa').textContent = brl(js.totais.taxa);
                el('tTotal').textContent = brl(js.totais.total);

                el('txtTotalBruto').textContent = brl(js.totais.total);
                el('txtRecVendas').textContent = brl(js.totais.recebido_vendas);
                el('txtRecFiado').textContent = brl(js.totais.recebido_fiados);
                el('txtCaixaReal').textContent = brl(js.totais.caixa_real);

                el('pillCountTop').textContent = `${js.totais.qtd} vendas`;
                el('pillCountTable').textContent = `${js.totais.qtd} vendas`;
                el('pageInfo').textContent = `Página ${state.page} / ${state.pages}`;

                el('btnPrev').disabled = state.page <= 1;
                el('btnNext').disabled = state.page >= state.pages;

                const di = el('di').value ? fmtDate(el('di').value) : '—';
                const df = el('df').value ? fmtDate(el('df').value) : '—';
                el('lblRange').textContent = `Período: ${di} até ${df}`;

                renderRows(js.rows || []);

            } catch (err) {
                el('tbody').innerHTML = `<tr><td colspan="9" class="text-danger">Erro: ${escapeHtml(err.message || String(err))}</td></tr>`;
            } finally {
                setLoading(false);
            }
        }

        function triggerAutoSearch() {
            clearTimeout(state.searchTimer);
            state.searchTimer = setTimeout(() => {
                state.page = 1;
                state.per = Number(el('per').value || 10);
                searchRows();
            }, 350);
        }

        async function openDetails(id) {
            try {
                const res = await fetch(`vendidos.php?action=one&id=${id}`, {
                    headers: {
                        'X-CSRF': csrf,
                        'Accept': 'application/json'
                    }
                });

                const text = await res.text();
                let js;
                try {
                    js = JSON.parse(text);
                } catch (e) {
                    throw new Error('Detalhes da venda não vieram em JSON válido.');
                }

                if (!res.ok || !js.ok) {
                    throw new Error(js?.msg || 'Falha ao abrir detalhes');
                }

                const v = js.data.venda || {};
                const itens = js.data.itens || [];
                state.lastCupomId = Number(v.id || 0);

                el('dId').textContent = `#${v.id || '—'}`;
                el('dDt').textContent = `${v.data || '—'} • ${v.created_at || '—'}`;
                el('dCli').textContent = v.cliente || '—';
                el('dCanal').textContent = v.canal || '—';
                el('dPag').textContent = v.pagamento || '—';
                el('dEnd').textContent = v.endereco || '—';
                el('dObs').textContent = v.obs || '—';

                el('dSub').textContent = brl(v.subtotal || 0);
                el('dDesc').textContent = brl(v.desconto_valor || 0);
                el('dTaxa').textContent = brl(v.taxa_entrega || 0);
                el('dTotal').textContent = brl(v.total || 0);

                if (!itens.length) {
                    el('dItens').innerHTML = `<span class="muted">Sem itens cadastrados para esta venda.</span>`;
                    el('dItensQtd').textContent = `0 itens`;
                    el('dItensTot').textContent = brl(0);
                } else {
                    el('dItens').innerHTML = itens.map(it => `
                        <div class="sale-row">
                            <div class="left">
                                <div class="nm">${escapeHtml(it.nome || 'Item')}</div>
                                ${it.codigo ? `<div class="cd">${escapeHtml(it.codigo)}</div>` : ``}
                                <div class="cd">${escapeHtml(it.un || '')} • ${brl(it.preco || 0)}</div>
                            </div>
                            <div class="right">
                                <div><b>${numQ(it.qtd)}</b></div>
                                <div class="muted2">${brl(it.total || 0)}</div>
                            </div>
                        </div>
                    `).join('');

                    el('dItensQtd').textContent = `${itens.length} item(ns)`;
                    el('dItensTot').textContent = brl(js.data.itens_total || 0);
                }

                const modal = new bootstrap.Modal(el('mdDetalhes'));
                modal.show();

            } catch (err) {
                alert('Erro: ' + (err.message || String(err)));
            }
        }

        function openCupom(id) {
            window.open(`${CUPOM_URL}?id=${encodeURIComponent(id)}&auto=1`, '_blank');
        }

        el('btnCupomModal').addEventListener('click', () => {
            if (!state.lastCupomId) return;
            openCupom(state.lastCupomId);
        });

        el('btnFiltrar').addEventListener('click', () => {
            state.page = 1;
            state.per = Number(el('per').value || 10);
            searchRows();
        });

        el('btnLimpar').addEventListener('click', () => {
            el('di').value = '';
            el('df').value = '';
            el('canal').value = 'TODOS';
            el('pag').value = 'TODOS';
            el('q').value = '';
            state.page = 1;
            state.per = Number(el('per').value || 10);
            searchRows();
        });

        el('btnPrev').addEventListener('click', () => {
            if (state.page <= 1) return;
            state.page -= 1;
            searchRows();
        });

        el('btnNext').addEventListener('click', () => {
            if (state.page >= state.pages) return;
            state.page += 1;
            searchRows();
        });

        el('per').addEventListener('change', () => {
            state.per = Number(el('per').value || 10);
            state.page = 1;
            searchRows();
        });

        el('btnExcel').addEventListener('click', () => {
            window.location.href = buildExportUrl('excel');
        });

        el('q').addEventListener('input', () => {
            triggerAutoSearch();
        });

        el('di').addEventListener('change', () => {
            triggerAutoSearch();
        });

        el('df').addEventListener('change', () => {
            triggerAutoSearch();
        });

        el('canal').addEventListener('change', () => {
            triggerAutoSearch();
        });

        el('pag').addEventListener('change', () => {
            triggerAutoSearch();
        });

        el('q').addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                clearTimeout(state.searchTimer);
                state.page = 1;
                searchRows();
            }
        });

        el('btnPrev').disabled = state.page <= 1;
        el('btnNext').disabled = state.page >= state.pages;

        window.openDetails = openDetails;
        window.openCupom = openCupom;
    </script>
</body>

</html>