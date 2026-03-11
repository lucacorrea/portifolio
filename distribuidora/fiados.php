<?php

declare(strict_types=1);

require_once __DIR__ . '/assets/auth/auth.php';
auth_require('index.php');

@date_default_timezone_set('America/Manaus');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/assets/conexao.php';

if (!function_exists('db')) {
    http_response_code(500);
    echo 'Erro crítico: função db() não encontrada. Verifique assets/conexao.php.';
    exit;
}

$pdo = db();

/* =========================================================
   HELPERS
========================================================= */
function fi_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fi_csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function fi_clean_buffers(): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
}

function fi_json_out(array $payload, int $code = 200): void
{
    fi_clean_buffers();
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fi_brl(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function fi_get_str(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

function fi_get_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
}

function fi_read_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function fi_flash_take(string $key): ?string
{
    if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key])) {
        return null;
    }
    $msg = $_SESSION[$key];
    unset($_SESSION[$key]);
    return $msg;
}

function fi_fmt_datetime(?string $d): string
{
    $d = trim((string)$d);
    if ($d === '') {
        return '—';
    }
    $ts = strtotime($d);
    if ($ts === false) {
        return '—';
    }
    return date('d/m/Y H:i:s', $ts);
}

function fi_fmt_date(?string $d): string
{
    $d = trim((string)$d);
    if ($d === '') {
        return '—';
    }
    $ts = strtotime($d);
    if ($ts === false) {
        return '—';
    }
    return date('d/m/Y', $ts);
}

function fi_sql_parts(): array
{
    $from = "
        FROM fiados f
        LEFT JOIN vendas v
            ON v.id = f.venda_id
        LEFT JOIN clientes c
            ON c.id = f.cliente_id
        LEFT JOIN (
            SELECT
                fp.fiado_id,
                COALESCE(SUM(fp.valor), 0) AS soma_pago
            FROM fiados_pagamentos fp
            GROUP BY fp.fiado_id
        ) pg
            ON pg.fiado_id = f.id
    ";

    $exprPago = "
        CASE
            WHEN COALESCE(pg.soma_pago,0) > COALESCE(f.valor_pago,0)
                THEN COALESCE(pg.soma_pago,0)
            ELSE COALESCE(f.valor_pago,0)
        END
    ";

    $exprRestante = "
        CASE
            WHEN (COALESCE(f.valor_total,0) - ({$exprPago})) > 0
                THEN (COALESCE(f.valor_total,0) - ({$exprPago}))
            ELSE 0
        END
    ";

    $statusNumExpr = "
        CASE
            WHEN ({$exprRestante}) <= 0.00001 THEN 2
            ELSE 1
        END
    ";

    $statusTextExpr = "
        CASE
            WHEN ({$statusNumExpr}) = 2 THEN 'PAGO'
            ELSE 'ABERTO'
        END
    ";

    $canalNumExpr = "
        CASE
            WHEN HEX(UPPER(COALESCE(v.canal,''))) = '44454C4956455259' THEN 2
            ELSE 1
        END
    ";

    $canalTextExpr = "
        CASE
            WHEN ({$canalNumExpr}) = 2 THEN 'DELIVERY'
            ELSE 'PRESENCIAL'
        END
    ";

    $exprDataRef = "COALESCE(v.created_at, f.created_at)";

    return [
        'from' => $from,
        'expr_pago' => $exprPago,
        'expr_restante' => $exprRestante,
        'status_num_expr' => $statusNumExpr,
        'status_text_expr' => $statusTextExpr,
        'canal_num_expr' => $canalNumExpr,
        'canal_text_expr' => $canalTextExpr,
        'expr_data_ref' => $exprDataRef,
    ];
}

function fi_build_where(array &$params, array $parts): string
{
    $where = " WHERE 1=1 ";

    $di = fi_get_str('di');
    $df = fi_get_str('df');
    $canal = strtoupper(fi_get_str('canal', 'TODOS'));
    $status = strtoupper(fi_get_str('status', 'TODOS'));

    if ($di !== '') {
        $where .= " AND DATE({$parts['expr_data_ref']}) >= :di ";
        $params[':di'] = $di;
    }

    if ($df !== '') {
        $where .= " AND DATE({$parts['expr_data_ref']}) <= :df ";
        $params[':df'] = $df;
    }

    if ($canal === 'PRESENCIAL') {
        $where .= " AND ({$parts['canal_num_expr']}) = 1 ";
    } elseif ($canal === 'DELIVERY') {
        $where .= " AND ({$parts['canal_num_expr']}) = 2 ";
    }

    if ($status === 'ABERTO') {
        $where .= " AND ({$parts['status_num_expr']}) = 1 ";
    } elseif ($status === 'PAGO') {
        $where .= " AND ({$parts['status_num_expr']}) = 2 ";
    }

    return $where;
}

function fi_build_search_blob(array $row, string $itensTxt): string
{
    $parts = [
        (string)($row['id'] ?? ''),
        (string)($row['venda_id'] ?? ''),
        (string)($row['cliente_nome'] ?? ''),
        (string)($row['canal'] ?? ''),
        (string)($row['status'] ?? ''),
        (string)($row['pagamento'] ?? ''),
        (string)($row['created_at'] ?? ''),
        (string)($row['created_at_fmt'] ?? ''),
        (string)($row['data_vencimento'] ?? ''),
        (string)($row['vencimento_fmt'] ?? ''),
        (string)($row['endereco'] ?? ''),
        (string)($row['obs'] ?? ''),
        fi_brl((float)($row['valor_total'] ?? 0)),
        fi_brl((float)($row['valor_pago'] ?? 0)),
        fi_brl((float)($row['valor_restante'] ?? 0)),
        number_format((float)($row['valor_total'] ?? 0), 2, '.', ''),
        number_format((float)($row['valor_pago'] ?? 0), 2, '.', ''),
        number_format((float)($row['valor_restante'] ?? 0), 2, '.', ''),
        $itensTxt,
    ];

    return implode(' | ', $parts);
}

function fi_attach_items_text(PDO $pdo, array &$rows): void
{
    if (!$rows) {
        return;
    }

    $vendaIds = [];
    foreach ($rows as $r) {
        $id = (int)($r['venda_id'] ?? 0);
        if ($id > 0) {
            $vendaIds[] = $id;
        }
    }

    $vendaIds = array_values(array_unique($vendaIds));
    if (!$vendaIds) {
        foreach ($rows as &$r) {
            $r['itens_txt'] = '';
            $r['search_blob'] = fi_build_search_blob($r, '');
        }
        unset($r);
        return;
    }

    $in = implode(',', array_fill(0, count($vendaIds), '?'));

    $sql = "
        SELECT
            venda_id,
            GROUP_CONCAT(CONCAT_WS(' ', COALESCE(codigo,''), COALESCE(nome,''), COALESCE(unidade,'')) SEPARATOR ' | ') AS itens_txt
        FROM venda_itens
        WHERE venda_id IN ($in)
        GROUP BY venda_id
    ";
    $st = $pdo->prepare($sql);
    $st->execute($vendaIds);
    $tmp = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $map = [];
    foreach ($tmp as $r) {
        $map[(int)$r['venda_id']] = (string)($r['itens_txt'] ?? '');
    }

    foreach ($rows as &$r) {
        $itensTxt = $map[(int)($r['venda_id'] ?? 0)] ?? '';
        $r['itens_txt'] = $itensTxt;
        $r['search_blob'] = fi_build_search_blob($r, $itensTxt);
    }
    unset($r);
}

function fi_filter_rows_local_search(array $rows, string $q): array
{
    $q = trim($q);
    if ($q === '') {
        return $rows;
    }

    $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $out = [];

    foreach ($rows as $r) {
        $hay = (string)($r['search_blob'] ?? '');
        $hay = function_exists('mb_strtolower') ? mb_strtolower($hay, 'UTF-8') : strtolower($hay);
        if (strpos($hay, $needle) !== false) {
            $out[] = $r;
        }
    }

    return $out;
}

function fi_compute_totals_from_rows(array $rows): array
{
    $sumTotal = 0.0;
    $sumPago = 0.0;
    $sumRestante = 0.0;

    foreach ($rows as $r) {
        $sumTotal += (float)($r['valor_total'] ?? 0);
        $sumPago += (float)($r['valor_pago'] ?? 0);
        $sumRestante += (float)($r['valor_restante'] ?? 0);
    }

    return [
        'qtd' => count($rows),
        'total_venda' => $sumTotal,
        'total_pago' => $sumPago,
        'total_restante' => $sumRestante,
    ];
}

function fi_fetch_result(PDO $pdo): array
{
    $parts = fi_sql_parts();
    $params = [];
    $where = fi_build_where($params, $parts);

    $sql = "
        SELECT
            f.id,
            f.venda_id,
            f.cliente_id,
            f.valor_total,
            {$parts['expr_pago']} AS valor_pago_real,
            {$parts['expr_restante']} AS valor_restante_real,
            {$parts['status_text_expr']} AS status_real,
            f.data_vencimento,
            f.created_at AS fiado_created_at,

            v.created_at AS venda_created_at,
            {$parts['canal_text_expr']} AS canal_normalizado,
            v.pagamento,
            v.endereco,
            v.obs,
            v.cliente AS venda_cliente,

            c.nome AS cliente_nome
        {$parts['from']}
        {$where}
        ORDER BY {$parts['expr_data_ref']} DESC, f.id DESC
    ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rowsRaw = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $rows = [];
    foreach ($rowsRaw as $r) {
        $cliente = trim((string)($r['cliente_nome'] ?: $r['venda_cliente'] ?: '—'));
        $createdAt = (string)($r['venda_created_at'] ?? $r['fiado_created_at'] ?? '');

        $rows[] = [
            'id'              => (int)($r['id'] ?? 0),
            'venda_id'        => (int)($r['venda_id'] ?? 0),
            'cliente_nome'    => $cliente,
            'canal'           => (string)($r['canal_normalizado'] ?? 'PRESENCIAL'),
            'pagamento'       => (string)($r['pagamento'] ?? ''),
            'valor_total'     => (float)($r['valor_total'] ?? 0),
            'valor_pago'      => (float)($r['valor_pago_real'] ?? 0),
            'valor_restante'  => (float)($r['valor_restante_real'] ?? 0),
            'status'          => (string)($r['status_real'] ?? 'ABERTO'),
            'data_vencimento' => (string)($r['data_vencimento'] ?? ''),
            'created_at'      => $createdAt,
            'created_at_fmt'  => fi_fmt_datetime($createdAt),
            'vencimento_fmt'  => fi_fmt_date((string)($r['data_vencimento'] ?? '')),
            'endereco'        => (string)($r['endereco'] ?? ''),
            'obs'             => (string)($r['obs'] ?? ''),
        ];
    }

    fi_attach_items_text($pdo, $rows);

    return [
        'rows' => $rows,
        'totais' => fi_compute_totals_from_rows($rows),
    ];
}

function fi_fetch_details(PDO $pdo, int $fiadoId): ?array
{
    $parts = fi_sql_parts();

    $sql = "
        SELECT
            f.id,
            f.venda_id,
            f.cliente_id,
            f.valor_total,
            {$parts['expr_pago']} AS valor_pago_real,
            {$parts['expr_restante']} AS valor_restante_real,
            {$parts['status_text_expr']} AS status_real,
            f.data_vencimento,
            f.created_at AS fiado_created_at,

            v.created_at AS venda_created_at,
            {$parts['canal_text_expr']} AS canal_normalizado,
            v.pagamento,
            v.endereco,
            v.obs,
            v.cliente AS venda_cliente,

            c.nome AS cliente_nome
        {$parts['from']}
        WHERE f.id = :id
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':id' => $fiadoId]);
    $fiado = $st->fetch(PDO::FETCH_ASSOC);

    if (!$fiado) {
        return null;
    }

    $stItens = $pdo->prepare("
        SELECT nome, qtd, unidade, preco_unit, subtotal
        FROM venda_itens
        WHERE venda_id = :venda_id
        ORDER BY id ASC
    ");
    $stItens->execute([':venda_id' => (int)$fiado['venda_id']]);
    $items = $stItens->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stPay = $pdo->prepare("
        SELECT valor, metodo, created_at
        FROM fiados_pagamentos
        WHERE fiado_id = :fiado_id
        ORDER BY created_at DESC, id DESC
    ");
    $stPay->execute([':fiado_id' => $fiadoId]);
    $payments = $stPay->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'fiado' => [
            'id'              => (int)($fiado['id'] ?? 0),
            'venda_id'        => (int)($fiado['venda_id'] ?? 0),
            'cliente_nome'    => trim((string)($fiado['cliente_nome'] ?: $fiado['venda_cliente'] ?: '—')),
            'canal'           => (string)($fiado['canal_normalizado'] ?? ''),
            'pagamento'       => (string)($fiado['pagamento'] ?? ''),
            'valor_total'     => (float)($fiado['valor_total'] ?? 0),
            'valor_pago'      => (float)($fiado['valor_pago_real'] ?? 0),
            'valor_restante'  => (float)($fiado['valor_restante_real'] ?? 0),
            'status'          => (string)($fiado['status_real'] ?? 'ABERTO'),
            'data_vencimento' => (string)($fiado['data_vencimento'] ?? ''),
            'created_at'      => (string)($fiado['venda_created_at'] ?? $fiado['fiado_created_at'] ?? ''),
            'endereco'        => (string)($fiado['endereco'] ?? ''),
            'obs'             => (string)($fiado['obs'] ?? ''),
        ],
        'items' => $items,
        'payments' => $payments,
    ];
}

/* =========================================================
   ACTIONS
========================================================= */
$action = strtolower(fi_get_str('action'));

if ($action === 'fetch') {
    try {
        $result = fi_fetch_result($pdo);
        fi_json_out([
            'ok' => true,
            'rows' => $result['rows'],
            'totais' => $result['totais'],
        ]);
    } catch (Throwable $e) {
        fi_json_out([
            'ok' => false,
            'msg' => 'Erro ao carregar fiados: ' . $e->getMessage()
        ], 500);
    }
}

if ($action === 'get_details') {
    try {
        $id = fi_get_int('id', 0);
        if ($id <= 0) {
            fi_json_out(['ok' => false, 'msg' => 'ID inválido.'], 400);
        }

        $details = fi_fetch_details($pdo, $id);
        if (!$details) {
            fi_json_out(['ok' => false, 'msg' => 'Dívida não encontrada.'], 404);
        }

        fi_json_out([
            'ok' => true,
            'fiado' => $details['fiado'],
            'items' => $details['items'],
            'payments' => $details['payments'],
        ]);
    } catch (Throwable $e) {
        fi_json_out([
            'ok' => false,
            'msg' => 'Erro ao buscar detalhes: ' . $e->getMessage()
        ], 500);
    }
}

if ($action === 'pay' && strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $payload = fi_read_json_input();

        $csrfHeader = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $csrfBody   = (string)($payload['csrf'] ?? '');
        $csrfSent   = $csrfHeader !== '' ? $csrfHeader : $csrfBody;
        $csrfSess   = (string)($_SESSION['csrf_token'] ?? '');

        if ($csrfSent === '' || $csrfSess === '' || !hash_equals($csrfSess, $csrfSent)) {
            fi_json_out(['ok' => false, 'msg' => 'CSRF inválido.'], 419);
        }

        $id = (int)($payload['id'] ?? 0);
        $valor = (float)($payload['valor'] ?? 0);
        $metodo = strtoupper(trim((string)($payload['metodo'] ?? 'DINHEIRO')));

        if ($id <= 0) {
            fi_json_out(['ok' => false, 'msg' => 'Fiado inválido.'], 400);
        }

        if ($valor <= 0) {
            fi_json_out(['ok' => false, 'msg' => 'Informe um valor válido.'], 400);
        }

        if (!in_array($metodo, ['DINHEIRO', 'PIX', 'CARTAO'], true)) {
            $metodo = 'DINHEIRO';
        }

        $pdo->beginTransaction();

        $details = fi_fetch_details($pdo, $id);
        if (!$details) {
            throw new RuntimeException('Fiado não encontrado.');
        }

        $fiado = $details['fiado'];
        $restante = (float)($fiado['valor_restante'] ?? 0);
        $total = (float)($fiado['valor_total'] ?? 0);
        $pago = (float)($fiado['valor_pago'] ?? 0);

        if ($restante <= 0) {
            throw new RuntimeException('Esta dívida já está quitada.');
        }

        if ($valor > $restante) {
            throw new RuntimeException('O valor informado é maior que o restante em aberto.');
        }

        $stPay = $pdo->prepare("
            INSERT INTO fiados_pagamentos (fiado_id, valor, metodo, created_at)
            VALUES (:fiado_id, :valor, :metodo, NOW())
        ");
        $stPay->execute([
            ':fiado_id' => $id,
            ':valor'    => $valor,
            ':metodo'   => $metodo,
        ]);

        $novoPago = $pago + $valor;
        if ($novoPago > $total) {
            $novoPago = $total;
        }

        $novoRestante = $total - $novoPago;
        if ($novoRestante < 0) {
            $novoRestante = 0;
        }

        $novoStatus = $novoRestante <= 0.00001 ? 'PAGO' : 'ABERTO';

        $stUp = $pdo->prepare("
            UPDATE fiados
            SET valor_pago = :valor_pago,
                valor_restante = :valor_restante,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $stUp->execute([
            ':valor_pago'     => $novoPago,
            ':valor_restante' => $novoRestante,
            ':status'         => $novoStatus,
            ':id'             => $id,
        ]);

        $pdo->commit();

        fi_json_out([
            'ok' => true,
            'msg' => 'Pagamento registrado com sucesso.'
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fi_json_out([
            'ok' => false,
            'msg' => 'Erro ao registrar pagamento: ' . $e->getMessage()
        ], 500);
    }
}

if ($action === 'excel') {
    $result = fi_fetch_result($pdo);
    $rows = $result['rows'];

    $q = fi_get_str('q');
    if ($q !== '') {
        $rows = fi_filter_rows_local_search($rows, $q);
    }

    $totais = fi_compute_totals_from_rows($rows);

    $agora  = date('d/m/Y H:i:s');
    $di     = fi_get_str('di') ?: '—';
    $df     = fi_get_str('df') ?: '—';
    $canal  = fi_get_str('canal', 'TODOS');
    $status = fi_get_str('status', 'TODOS');
    $busca  = $q ?: '—';

    $fname = 'relatorio_fiados_' . date('Ymd_His') . '.xls';

    fi_clean_buffers();
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
                size: A4 landscape;
                margin: 0.5cm;
            }

            html,
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 11pt;
                background: #fff;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
            }

            th,
            td {
                border: 1px solid #000;
                padding: 6px;
                font-size: 11pt;
                text-align: center;
            }

            .title {
                background: #dbeafe;
                font-size: 16pt;
                font-weight: 700;
                text-align: center;
            }

            .head {
                background: #dbeafe;
                font-weight: 700;
            }

            .left {
                text-align: left;
            }

            .foot {
                background: #eef2ff;
                font-weight: 700;
            }
        </style>
    </head>

    <body>
        <table>
            <tr>
                <td colspan="8" class="title">PAINEL DA DISTRIBUIDORA - FIADOS (RESUMO)</td>
            </tr>
            <tr>
                <td colspan="8">Gerado em: <?= fi_e($agora) ?></td>
            </tr>
            <tr>
                <td colspan="8">Período: <?= fi_e($di) ?> até <?= fi_e($df) ?> | Canal: <?= fi_e($canal) ?> | Status: <?= fi_e($status) ?> | Busca: <?= fi_e($busca) ?></td>
            </tr>
            <tr>
                <td colspan="8">
                    Total em fiados: <?= fi_e(fi_brl((float)$totais['total_venda'])) ?> |
                    Total pago: <?= fi_e(fi_brl((float)$totais['total_pago'])) ?> |
                    Total em aberto: <?= fi_e(fi_brl((float)$totais['total_restante'])) ?> |
                    Registros: <?= (int)$totais['qtd'] ?>
                </td>
            </tr>
        </table>

        <table style="margin-top:6px;">
            <thead>
                <tr>
                    <th class="head">Venda #</th>
                    <th class="head">Data/Hora</th>
                    <th class="head">Cliente</th>
                    <th class="head">Canal</th>
                    <th class="head">Total Venda</th>
                    <th class="head">Total Pago</th>
                    <th class="head">Restante</th>
                    <th class="head">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>#<?= (int)$r['venda_id'] ?></td>
                        <td><?= fi_e(fi_fmt_datetime((string)$r['created_at'])) ?></td>
                        <td class="left"><?= fi_e((string)$r['cliente_nome']) ?></td>
                        <td><?= fi_e((string)$r['canal']) ?></td>
                        <td><?= fi_e(fi_brl((float)$r['valor_total'])) ?></td>
                        <td><?= fi_e(fi_brl((float)$r['valor_pago'])) ?></td>
                        <td><?= fi_e(fi_brl((float)$r['valor_restante'])) ?></td>
                        <td><?= fi_e((string)$r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="foot">
                    <td colspan="4">Totais</td>
                    <td><?= fi_e(fi_brl((float)$totais['total_venda'])) ?></td>
                    <td><?= fi_e(fi_brl((float)$totais['total_pago'])) ?></td>
                    <td><?= fi_e(fi_brl((float)$totais['total_restante'])) ?></td>
                    <td><?= (int)$totais['qtd'] ?> registro(s)</td>
                </tr>
            </tfoot>
        </table>
    </body>

    </html>
<?php
    exit;
}

/* =========================================================
   PRIMEIRA CARGA
========================================================= */
$initial = fi_fetch_result($pdo);
$initialRowsAll = $initial['rows'];
$initialPer = 10;
$initialTotal = count($initialRowsAll);
$initialPages = max(1, (int)ceil($initialTotal / $initialPer));
$initialRowsPage = array_slice($initialRowsAll, 0, $initialPer);

$csrf = fi_csrf_token();
$flashOk  = fi_flash_take('flash_ok');
$flashErr = fi_flash_take('flash_err');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= fi_e($csrf) ?>">
    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | À Prazo</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <style>
        .card-fiado {
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.15);
            background: #fff;
            margin-bottom: 20px;
        }

        .card-fiado .body {
            padding: 20px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .status-aberto {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #ffedd5;
        }

        .status-pago {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #dcfce7;
        }

        .val-total {
            font-weight: 800;
            color: #0f172a;
            white-space: nowrap;
        }

        .val-restante {
            font-weight: 800;
            color: #ef4444;
            white-space: nowrap;
        }

        .btn-pay {
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .pager-box {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 16px;
            border-top: 1px solid rgba(148, 163, 184, 0.15);
        }

        .pager-box .page-text {
            font-size: 12px;
            color: #64748b;
            font-weight: 800;
            white-space: nowrap;
        }

        .pager-box .btn-disabled {
            opacity: .45;
            pointer-events: none;
        }

        .pager-left {
            margin-right: auto;
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
            white-space: nowrap;
        }

        .logout-btn {
            padding: 8px 14px !important;
            min-width: 88px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none !important;
            white-space: nowrap;
        }

        .logout-btn i {
            font-size: 16px;
        }

        .header-right {
            height: 100%;
        }

        .brand-vertical {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            text-align: center;
        }

        .brand-name {
            display: block;
            font-size: 18px;
            line-height: 1.2;
            font-weight: 600;
            color: #1e2a78;
            white-space: normal;
            word-break: break-word;
        }

        .table-custom thead th {
            padding: 16px 18px !important;
            vertical-align: middle;
            background: #f8fafc;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            font-size: 13px;
            letter-spacing: .1px;
            text-align: center;
            white-space: nowrap;
        }

        .table-custom tbody td {
            padding: 16px 18px !important;
            vertical-align: middle;
            border-top: 1px solid rgba(148, 163, 184, 0.12);
            font-size: 14px;
            text-align: center;
            white-space: nowrap;
        }

        .table-custom tbody td.td-left,
        .table-custom thead th.th-left {
            text-align: left;
        }

        .table-custom tbody td.td-right,
        .table-custom thead th.th-right {
            text-align: right;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: center;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .summary-card {
            background: #fff;
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 16px;
            padding: 18px 18px 16px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .03);
        }

        .summary-card .lbl {
            font-size: 12px;
            font-weight: 800;
            color: #64748b;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .summary-card .val {
            font-size: 28px;
            font-weight: 900;
            line-height: 1.1;
            white-space: nowrap;
        }

        .summary-card.total .val {
            color: #0f172a;
        }

        .summary-card.pago .val {
            color: #15803d;
        }

        .summary-card.restante .val {
            color: #ef4444;
        }

        .flash-auto-hide {
            transition: opacity .35s ease, transform .35s ease;
        }

        .flash-auto-hide.hide-now {
            opacity: 0;
            transform: translateY(-8px);
        }

        .mini-muted {
            color: #64748b;
            font-size: 12px;
            line-height: 1.35;
            white-space: nowrap;
        }

        .text-nowrap-force {
            white-space: nowrap !important;
        }

        @media (max-width: 991.98px) {
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
            <a href="dashboard.php" class="brand-vertical">
                <span class="brand-name">DISTRIBUIDORA<br>PLHB</span>
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
                        <li><a href="vendidos.php">Vendidos</a></li>
                        <li><a href="fiados.php" class="active">À Prazo</a></li>
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
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover" type="button">
                                    <i class="lni lni-chevron-left me-2"></i> Menu
                                </button>
                            </div>
                            <div class="header-search d-none d-md-flex"></div>
                        </div>
                    </div>

                    <div class="col-lg-7 col-md-7 col-6">
                        <div class="header-right d-flex justify-content-end align-items-center">
                            <a href="assets/auth/logout.php" class="main-btn primary-btn btn-hover logout-btn">
                                <i class="lni lni-exit me-1"></i> Sair
                            </a>
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
                                <h2>Gestão de Vendas À Prazo</h2>
                                <p class="text-muted">Listagem, filtros automáticos, pesquisa no tbody e recebimentos (AVS)</p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="breadcrumb-wrapper">
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="#">Operações</a></li>
                                        <li class="breadcrumb-item active" aria-current="page">À Prazo</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>

                    </div>
                </div>

                <?php if ($flashOk): ?>
                    <div class="alert alert-success flash-auto-hide mt-3" style="border-radius:14px;"><?= fi_e($flashOk) ?></div>
                <?php endif; ?>

                <?php if ($flashErr): ?>
                    <div class="alert alert-danger flash-auto-hide mt-3" style="border-radius:14px;"><?= fi_e($flashErr) ?></div>
                <?php endif; ?>

                <div class="summary-grid">
                    <div class="summary-card total">
                        <div class="lbl">Total em vendas à prazo</div>
                        <div class="val" id="sumTotal"><?= fi_e(fi_brl((float)$initial['totais']['total_venda'])) ?></div>
                    </div>
                    <div class="summary-card pago">
                        <div class="lbl">Total pago</div>
                        <div class="val" id="sumPago"><?= fi_e(fi_brl((float)$initial['totais']['total_pago'])) ?></div>
                    </div>
                    <div class="summary-card restante">
                        <div class="lbl">Total restante</div>
                        <div class="val" id="sumRestante"><?= fi_e(fi_brl((float)$initial['totais']['total_restante'])) ?></div>
                    </div>
                </div>

                <div class="card-fiado mb-30">
                    <div class="body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">Data Inicial</label>
                                <input type="date" class="form-control" id="fDi">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Data Final</label>
                                <input type="date" class="form-control" id="fDf">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Canal</label>
                                <select class="form-select" id="fCanal">
                                    <option value="TODOS">Todos</option>
                                    <option value="PRESENCIAL">Presencial</option>
                                    <option value="DELIVERY">Delivery</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="fStatus">
                                    <option value="TODOS">Todos</option>
                                    <option value="ABERTO">Aberto</option>
                                    <option value="PAGO">Pago</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Cliente / Venda / Itens</label>
                                <input type="text" class="form-control" id="fSearch" placeholder="Nome do cliente, ID da venda, item, valor...">
                            </div>

                            <div class="col-12">
                                <div class="toolbar-actions">
                                    <button type="button" class="btn btn-outline-secondary" id="btnClear">
                                        <i class="lni lni-close me-1"></i> Limpar
                                    </button>
                                    <button type="button" class="btn btn-outline-success" id="btnExcel">
                                        <i class="lni lni-download me-1"></i> Exportar Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mini-muted mt-3">
                            Data, canal e status fazem busca AJAX. O campo de pesquisa filtra no tbody sem quebrar os textos.
                        </div>
                    </div>
                </div>

                <div class="card-fiado">
                    <div class="body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th class="th-left ps-4 text-nowrap-force">Venda #</th>
                                        <th class="text-nowrap-force">Data/Hora</th>
                                        <th class="th-left text-nowrap-force">Cliente</th>
                                        <th class="text-nowrap-force">Total Venda</th>
                                        <th class="text-nowrap-force">Total Pago</th>
                                        <th class="text-nowrap-force">Restante</th>
                                        <th class="text-nowrap-force">Status</th>
                                        <th class="th-right pe-4 text-nowrap-force">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="fiadosTableBody">
                                    <?php if (!$initialRowsPage): ?>
                                        <tr>
                                            <td colspan="8" class="text-center p-5">Nenhuma venda à prazo encontrada.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($initialRowsPage as $f): ?>
                                            <tr>
                                                <td class="ps-4 td-left text-nowrap-force"><b>#<?= (int)$f['venda_id'] ?></b></td>
                                                <td class="text-nowrap-force"><?= fi_e((string)$f['created_at_fmt']) ?></td>
                                                <td class="td-left text-nowrap-force"><?= fi_e((string)$f['cliente_nome']) ?></td>
                                                <td class="text-nowrap-force"><span class="val-total"><?= fi_e(fi_brl((float)$f['valor_total'])) ?></span></td>
                                                <td class="text-nowrap-force"><span class="text-success"><?= fi_e(fi_brl((float)$f['valor_pago'])) ?></span></td>
                                                <td class="text-nowrap-force"><span class="val-restante"><?= fi_e(fi_brl((float)$f['valor_restante'])) ?></span></td>
                                                <td class="text-nowrap-force">
                                                    <span class="status-badge <?= strtoupper((string)$f['status']) === 'PAGO' ? 'status-pago' : 'status-aberto' ?>">
                                                        <?= fi_e((string)$f['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="td-right pe-4 text-nowrap-force">
                                                    <button
                                                        type="button"
                                                        class="btn btn-light btn-pay js-detail"
                                                        data-id="<?= (int)$f['id'] ?>">
                                                        <i class="lni lni-eye"></i> Detalhes
                                                    </button>

                                                    <?php if (strtoupper((string)$f['status']) === 'ABERTO'): ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-success btn-pay text-white ms-1 js-pay"
                                                            data-id="<?= (int)$f['id'] ?>"
                                                            data-cliente="<?= fi_e((string)$f['cliente_nome']) ?>"
                                                            data-restante="<?= (float)$f['valor_restante'] ?>">
                                                            <i class="lni lni-reply"></i> Pagar
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="pager-box">
                            <div class="pager-left" id="pgSummary">
                                Mostrando <?= $initialTotal > 0 ? 1 : 0 ?>-<?= min($initialPer, $initialTotal) ?> de <?= $initialTotal ?>
                            </div>

                            <a href="#0" id="pgPrev" class="main-btn light-btn btn-hover btn-sm <?= ($initialPages <= 1) ? 'btn-disabled' : '' ?>" title="Anterior">
                                <i class="lni lni-chevron-left"></i>
                            </a>

                            <span class="page-text" id="pgInfo">Página 1/<?= $initialPages ?></span>

                            <a href="#0" id="pgNext" class="main-btn light-btn btn-hover btn-sm <?= ($initialPages <= 1) ? 'btn-disabled' : '' ?>" title="Próxima">
                                <i class="lni lni-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes da Dívida - Venda #<span id="detVendaId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Informações do Cliente</h6>
                            <p class="mb-1"><b>Nome:</b> <span id="detCliente"></span></p>
                            <p class="mb-1"><b>Canal:</b> <span id="detCanal"></span></p>
                            <p class="mb-1"><b>Pagamento:</b> <span id="detPagamento"></span></p>
                            <p class="mb-1"><b>Vencimento:</b> <span id="detVencimento"></span></p>
                            <p class="mb-1"><b>Endereço:</b> <span id="detEndereco"></span></p>
                            <p class="mb-0"><b>Observação:</b> <span id="detObs"></span></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <h6>Resumo Financeiro</h6>
                            <p class="mb-0">Total: <b id="detTotal"></b></p>
                            <p class="mb-0">Pago: <b class="text-success" id="detPago"></b></p>
                            <p class="mb-0">Restante: <b class="text-danger" id="detRestante"></b></p>
                            <p class="mb-0">Status: <b id="detStatus"></b></p>
                            <p class="mb-0">Criado em: <b id="detCreated"></b></p>
                        </div>
                    </div>

                    <h6>Produtos da Venda</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm border p-2">
                            <thead class="bg-light">
                                <tr>
                                    <th>Produto</th>
                                    <th>Qtd</th>
                                    <th class="text-end">Preço</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="detItemsBody"></tbody>
                        </table>
                    </div>

                    <h6>Histórico de Pagamentos (AVS)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm border p-2">
                            <thead class="bg-light">
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Método</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody id="detPaysBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPagamento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Receber Pagamento (AVS)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        Cliente: <b id="payCliente"></b><br>
                        Saldo Devedor: <b id="paySaldo"></b>
                    </div>

                    <form id="payForm">
                        <input type="hidden" id="payFiadoId">

                        <div class="mb-3">
                            <label class="form-label">Valor do Pagamento (R$)</label>
                            <input type="text" class="form-control form-control-lg" id="payValor" placeholder="0,00" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Método de Recebimento</label>
                            <select class="form-select" id="payMetodo">
                                <option value="DINHEIRO">Dinheiro</option>
                                <option value="PIX">Pix</option>
                                <option value="CARTAO">Cartão</option>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">Confirmar Recebimento</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        const API = 'fiados.php';
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const modalDetalhes = new bootstrap.Modal(document.getElementById('modalDetalhes'));
        const modalPagamento = new bootstrap.Modal(document.getElementById('modalPagamento'));

        const STATE = {
            per: 10,
            page: 1,
            totalPages: <?= $initialPages ?>,
            totalRows: <?= $initialTotal ?>,
            timerRemote: null,
            timerLocal: null,
            rowsAll: <?= json_encode($initialRowsAll, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
        };

        const $body = document.getElementById('fiadosTableBody');
        const $pgPrev = document.getElementById('pgPrev');
        const $pgNext = document.getElementById('pgNext');
        const $pgInfo = document.getElementById('pgInfo');
        const $pgSummary = document.getElementById('pgSummary');

        function brlJs(v) {
            return parseFloat(v || 0).toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });
        }

        function esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, function(m) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                })[m];
            });
        }

        function buildRemoteQs(extra) {
            const p = new URLSearchParams({
                action: (extra && extra.action) ? extra.action : 'fetch',
                di: document.getElementById('fDi').value,
                df: document.getElementById('fDf').value,
                canal: document.getElementById('fCanal').value,
                status: document.getElementById('fStatus').value,
                q: document.getElementById('fSearch').value
            });
            return p;
        }

        function currentSearchNeedle() {
            return String(document.getElementById('fSearch').value || '').trim().toLowerCase();
        }

        function getFilteredLocalRows() {
            const q = currentSearchNeedle();
            if (!q) {
                return STATE.rowsAll.slice();
            }

            return STATE.rowsAll.filter(function(row) {
                const hay = String(row.search_blob || '').toLowerCase();
                return hay.indexOf(q) !== -1;
            });
        }

        function computeTotals(rows) {
            let totalVenda = 0;
            let totalPago = 0;
            let totalRestante = 0;

            rows.forEach(function(r) {
                totalVenda += Number(r.valor_total || 0);
                totalPago += Number(r.valor_pago || 0);
                totalRestante += Number(r.valor_restante || 0);
            });

            return {
                totalVenda: totalVenda,
                totalPago: totalPago,
                totalRestante: totalRestante
            };
        }

        function updateCards(rows) {
            const totals = computeTotals(rows);
            document.getElementById('sumTotal').textContent = brlJs(totals.totalVenda);
            document.getElementById('sumPago').textContent = brlJs(totals.totalPago);
            document.getElementById('sumRestante').textContent = brlJs(totals.totalRestante);
        }

        function setPagerUI(totalRows) {
            STATE.totalRows = totalRows;
            STATE.totalPages = Math.max(1, Math.ceil(totalRows / STATE.per));

            if (STATE.page > STATE.totalPages) {
                STATE.page = STATE.totalPages;
            }

            $pgInfo.textContent = 'Página ' + STATE.page + '/' + STATE.totalPages;

            const canPrev = STATE.page > 1;
            const canNext = STATE.page < STATE.totalPages;

            $pgPrev.classList.toggle('btn-disabled', !canPrev);
            $pgNext.classList.toggle('btn-disabled', !canNext);
        }

        function renderRowsPage() {
            const rowsFiltered = getFilteredLocalRows();
            updateCards(rowsFiltered);
            setPagerUI(rowsFiltered.length);

            const start = (STATE.page - 1) * STATE.per;
            const end = start + STATE.per;
            const rowsPage = rowsFiltered.slice(start, end);

            if (!rowsPage.length) {
                $body.innerHTML = '<tr><td colspan="8" class="text-center p-5">Nenhuma venda à prazo encontrada.</td></tr>';
                $pgSummary.textContent = 'Mostrando 0-0 de 0';
                return;
            }

            let html = '';

            rowsPage.forEach(function(f) {
                const isPago = String(f.status || '') === 'PAGO';

                html += '<tr>';
                html += '<td class="ps-4 td-left text-nowrap-force"><b>#' + esc(f.venda_id) + '</b></td>';
                html += '<td class="text-nowrap-force">' + esc(f.created_at_fmt || '') + '</td>';
                html += '<td class="td-left text-nowrap-force">' + esc(f.cliente_nome || '') + '</td>';
                html += '<td class="text-nowrap-force"><span class="val-total">' + brlJs(f.valor_total) + '</span></td>';
                html += '<td class="text-nowrap-force"><span class="text-success">' + brlJs(f.valor_pago) + '</span></td>';
                html += '<td class="text-nowrap-force"><span class="val-restante">' + brlJs(f.valor_restante) + '</span></td>';
                html += '<td class="text-nowrap-force"><span class="status-badge ' + (isPago ? 'status-pago' : 'status-aberto') + '">' + esc(f.status || '') + '</span></td>';
                html += '<td class="td-right pe-4 text-nowrap-force">';
                html += '<button type="button" class="btn btn-light btn-pay js-detail" data-id="' + esc(f.id) + '"><i class="lni lni-eye"></i> Detalhes</button>';

                if (!isPago) {
                    html += ' <button type="button" class="btn btn-success btn-pay text-white ms-1 js-pay" data-id="' + esc(f.id) + '" data-cliente="' + esc(f.cliente_nome || '') + '" data-restante="' + esc(f.valor_restante) + '"><i class="lni lni-reply"></i> Pagar</button>';
                }

                html += '</td>';
                html += '</tr>';
            });

            $body.innerHTML = html;

            const shownFrom = rowsFiltered.length ? (start + 1) : 0;
            const shownTo = rowsFiltered.length ? Math.min(end, rowsFiltered.length) : 0;
            $pgSummary.textContent = 'Mostrando ' + shownFrom + '-' + shownTo + ' de ' + rowsFiltered.length;
        }

        async function loadFiadosRemote() {
            try {
                $body.innerHTML = '<tr><td colspan="8" class="text-center p-5">Carregando...</td></tr>';

                const qs = buildRemoteQs({
                    action: 'fetch'
                });

                const r = await fetch(API + '?' + qs.toString(), {
                    headers: {
                        'Accept': 'application/json'
                    }
                }).then(function(res) {
                    return res.json();
                });

                if (!r.ok) {
                    throw new Error(r.msg || 'Falha ao carregar fiados.');
                }

                STATE.rowsAll = Array.isArray(r.rows) ? r.rows : [];
                STATE.page = 1;
                renderRowsPage();
            } catch (e) {
                alert('Erro ao carregar dados: ' + (e.message || e));
                STATE.rowsAll = [];
                renderRowsPage();
            }
        }

        async function showDetails(id) {
            try {
                const r = await fetch(API + '?action=get_details&id=' + encodeURIComponent(id), {
                    headers: {
                        'Accept': 'application/json'
                    }
                }).then(function(res) {
                    return res.json();
                });

                if (!r.ok) {
                    throw new Error(r.msg || 'Falha ao buscar detalhes.');
                }

                const f = r.fiado || {};
                document.getElementById('detVendaId').innerText = f.venda_id || '';
                document.getElementById('detCliente').innerText = f.cliente_nome || '';
                document.getElementById('detCanal').innerText = f.canal || '';
                document.getElementById('detPagamento').innerText = f.pagamento || '';
                document.getElementById('detVencimento').innerText = f.data_vencimento || '—';
                document.getElementById('detEndereco').innerText = f.endereco || '—';
                document.getElementById('detObs').innerText = f.obs || '—';
                document.getElementById('detCreated').innerText = f.created_at || '—';
                document.getElementById('detTotal').innerText = brlJs(f.valor_total);
                document.getElementById('detPago').innerText = brlJs(f.valor_pago);
                document.getElementById('detRestante').innerText = brlJs(f.valor_restante);
                document.getElementById('detStatus').innerText = f.status || '';

                const items = Array.isArray(r.items) ? r.items : [];
                if (items.length) {
                    document.getElementById('detItemsBody').innerHTML = items.map(function(it) {
                        return '<tr>' +
                            '<td>' + esc(it.nome || '') + '</td>' +
                            '<td>' + esc(it.qtd || '') + ' ' + esc(it.unidade || '') + '</td>' +
                            '<td class="text-end">' + brlJs(it.preco_unit) + '</td>' +
                            '<td class="text-end">' + brlJs(it.subtotal) + '</td>' +
                            '</tr>';
                    }).join('');
                } else {
                    document.getElementById('detItemsBody').innerHTML = '<tr><td colspan="4" class="text-center">Sem itens.</td></tr>';
                }

                const pays = Array.isArray(r.payments) ? r.payments : [];
                if (pays.length) {
                    document.getElementById('detPaysBody').innerHTML = pays.map(function(p) {
                        return '<tr>' +
                            '<td>' + esc(p.created_at || '') + '</td>' +
                            '<td>' + esc(p.metodo || '') + '</td>' +
                            '<td class="text-end">' + brlJs(p.valor) + '</td>' +
                            '</tr>';
                    }).join('');
                } else {
                    document.getElementById('detPaysBody').innerHTML = '<tr><td colspan="3" class="text-center">Nenhum pagamento registrado.</td></tr>';
                }

                modalDetalhes.show();
            } catch (e) {
                alert(e.message || e);
            }
        }

        function openPay(id, cliente, restante) {
            document.getElementById('payFiadoId').value = id;
            document.getElementById('payCliente').innerText = cliente;
            document.getElementById('paySaldo').innerText = brlJs(restante);
            document.getElementById('payValor').value = '';
            modalPagamento.show();
            setTimeout(function() {
                document.getElementById('payValor').focus();
            }, 250);
        }

        document.getElementById('payForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const id = document.getElementById('payFiadoId').value;
            const valorRaw = document.getElementById('payValor').value
                .replace(/\./g, '')
                .replace(',', '.')
                .replace(/[^\d.]/g, '');
            const valor = parseFloat(valorRaw);
            const metodo = document.getElementById('payMetodo').value;

            if (isNaN(valor) || valor <= 0) {
                alert('Informe um valor válido.');
                return;
            }

            try {
                const r = await fetch(API + '?action=pay', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrf
                    },
                    body: JSON.stringify({
                        id: id,
                        valor: valor,
                        metodo: metodo
                    })
                }).then(function(res) {
                    return res.json();
                });

                if (!r.ok) {
                    throw new Error(r.msg || 'Falha ao pagar.');
                }

                alert(r.msg || 'Pagamento registrado!');
                modalPagamento.hide();
                await loadFiadosRemote();
            } catch (e) {
                alert(e.message || e);
            }
        });

        function triggerRemoteFilters() {
            clearTimeout(STATE.timerRemote);
            STATE.timerRemote = setTimeout(function() {
                loadFiadosRemote();
            }, 300);
        }

        function triggerLocalSearch() {
            clearTimeout(STATE.timerLocal);
            STATE.timerLocal = setTimeout(function() {
                STATE.page = 1;
                renderRowsPage();
            }, 120);
        }

        document.getElementById('fDi').addEventListener('change', triggerRemoteFilters);
        document.getElementById('fDf').addEventListener('change', triggerRemoteFilters);
        document.getElementById('fCanal').addEventListener('change', triggerRemoteFilters);
        document.getElementById('fStatus').addEventListener('change', triggerRemoteFilters);

        document.getElementById('fSearch').addEventListener('input', triggerLocalSearch);
        document.getElementById('fSearch').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(STATE.timerLocal);
                STATE.page = 1;
                renderRowsPage();
            }
        });

        document.getElementById('btnClear').addEventListener('click', function() {
            document.getElementById('fDi').value = '';
            document.getElementById('fDf').value = '';
            document.getElementById('fCanal').value = 'TODOS';
            document.getElementById('fStatus').value = 'TODOS';
            document.getElementById('fSearch').value = '';
            loadFiadosRemote();
        });

        document.getElementById('btnExcel').addEventListener('click', function() {
            const qs = buildRemoteQs({
                action: 'excel'
            });
            window.location.href = API + '?' + qs.toString();
        });

        $pgPrev.addEventListener('click', function(e) {
            e.preventDefault();
            if (STATE.page <= 1) return;
            STATE.page--;
            renderRowsPage();
        });

        $pgNext.addEventListener('click', function(e) {
            e.preventDefault();
            if (STATE.page >= STATE.totalPages) return;
            STATE.page++;
            renderRowsPage();
        });

        document.getElementById('payValor').addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '');
            v = (v / 100).toFixed(2).replace('.', ',');
            v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            this.value = v;
        });

        $body.addEventListener('click', function(e) {
            const btnDetail = e.target.closest('.js-detail');
            if (btnDetail) {
                showDetails(btnDetail.getAttribute('data-id'));
                return;
            }

            const btnPay = e.target.closest('.js-pay');
            if (btnPay) {
                openPay(
                    btnPay.getAttribute('data-id'),
                    btnPay.getAttribute('data-cliente') || '',
                    parseFloat(btnPay.getAttribute('data-restante') || '0')
                );
            }
        });

        document.querySelectorAll('.flash-auto-hide').forEach(function(el) {
            setTimeout(function() {
                el.classList.add('hide-now');
                setTimeout(function() {
                    el.remove();
                }, 350);
            }, 1600);
        });

        window.onload = function() {
            renderRowsPage();
        };
    </script>
</body>

</html>