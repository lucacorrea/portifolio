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
    echo 'ERRO: função db():PDO não encontrada. Verifique assets/conexao.php';
    exit;
}

$pdo = db();

/* =========================================================
   HELPERS LOCAIS
========================================================= */
function fi_e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fi_csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
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

function fi_get_str(string $k, string $def = ''): string
{
    return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def;
}

function fi_get_int(string $k, int $def = 0): int
{
    return isset($_GET[$k]) ? (int)$_GET[$k] : $def;
}

function fi_post_str(string $k, string $def = ''): string
{
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $def;
}

function fi_post_int(string $k, int $def = 0): int
{
    return isset($_POST[$k]) ? (int)$_POST[$k] : $def;
}

function fi_post_float(string $k, float $def = 0.0): float
{
    $v = $_POST[$k] ?? $def;
    if (is_numeric($v)) {
        return (float)$v;
    }
    $s = preg_replace('/[^\d,.\-]/', '', (string)$v);
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? (float)$s : $def;
}

function fi_brl(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function fi_fmt_date(?string $d): string
{
    $d = trim((string)$d);
    if ($d === '') return '—';
    $ts = strtotime($d);
    if ($ts === false) return '—';
    return date('d/m/Y', $ts);
}

function fi_fmt_datetime(?string $d): string
{
    $d = trim((string)$d);
    if ($d === '') return '—';
    $ts = strtotime($d);
    if ($ts === false) return '—';
    return date('d/m/Y H:i', $ts);
}

function fi_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function fi_flash_set(string $key, string $msg): void
{
    $_SESSION[$key] = $msg;
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

function fi_normalize_return_to(string $url, string $fallback = 'fiados.php'): string
{
    $url = trim($url);
    if ($url === '') return $fallback;
    if (preg_match('~[\r\n]~', $url)) return $fallback;
    if (preg_match('~^https?://~i', $url)) return $fallback;
    return $url;
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
                COALESCE(SUM(fp.valor), 0) AS total_pago
            FROM fiados_pagamentos fp
            GROUP BY fp.fiado_id
        ) pg
            ON pg.fiado_id = f.id
    ";

    $basePagoExpr = "COALESCE(f.valor_pago,0)";
    $sumPagExpr   = "COALESCE(pg.total_pago,0)";
    $pagoExpr     = "CASE WHEN {$sumPagExpr} > {$basePagoExpr} THEN {$sumPagExpr} ELSE {$basePagoExpr} END";
    $restanteExpr = "CASE WHEN (COALESCE(f.valor_total,0) - ({$pagoExpr})) > 0 THEN (COALESCE(f.valor_total,0) - ({$pagoExpr})) ELSE 0 END";
    $statusExpr   = "CASE WHEN {$restanteExpr} <= 0.00001 OR UPPER(COALESCE(f.status,'')) = 'PAGO' THEN 'PAGO' ELSE 'ABERTO' END";
    $dataRefExpr  = "COALESCE(v.created_at, f.created_at)";

    return [
        'from'         => $from,
        'pago_expr'    => $pagoExpr,
        'restante_expr' => $restanteExpr,
        'status_expr'  => $statusExpr,
        'data_ref_expr' => $dataRefExpr,
    ];
}

function fi_build_where(array &$params, array $parts): string
{
    $where = " WHERE 1=1 ";

    $di     = fi_get_str('di');
    $df     = fi_get_str('df');
    $canal  = strtoupper(fi_get_str('canal', 'TODOS'));
    $status = strtoupper(fi_get_str('status', 'TODOS'));
    $q      = fi_get_str('q');

    if ($di !== '') {
        $where .= " AND DATE({$parts['data_ref_expr']}) >= :di ";
        $params[':di'] = $di;
    }

    if ($df !== '') {
        $where .= " AND DATE({$parts['data_ref_expr']}) <= :df ";
        $params[':df'] = $df;
    }

    if ($canal !== '' && $canal !== 'TODOS') {
        $where .= " AND UPPER(COALESCE(v.canal,'')) = :canal ";
        $params[':canal'] = $canal;
    }

    if ($status !== '' && $status !== 'TODOS') {
        $where .= " AND {$parts['status_expr']} = :status ";
        $params[':status'] = $status;
    }

    if ($q !== '') {
        $params[':q1'] = '%' . $q . '%';
        $params[':q2'] = '%' . $q . '%';
        $params[':q3'] = '%' . $q . '%';
        $params[':q4'] = '%' . $q . '%';
        $params[':q5'] = '%' . $q . '%';
        $params[':q6'] = '%' . $q . '%';
        $params[':q7'] = '%' . $q . '%';
        $params[':q8'] = '%' . $q . '%';
        $params[':q9'] = '%' . $q . '%';
        $params[':q10'] = '%' . $q . '%';

        $where .= "
            AND (
                CAST(f.id AS CHAR) LIKE :q1
                OR CAST(f.venda_id AS CHAR) LIKE :q2
                OR COALESCE(c.nome,'') LIKE :q3
                OR COALESCE(v.cliente,'') LIKE :q4
                OR COALESCE(v.canal,'') LIKE :q5
                OR COALESCE(v.pagamento,'') LIKE :q6
                OR CAST(COALESCE(f.valor_total,0) AS CHAR) LIKE :q7
                OR CAST({$parts['pago_expr']} AS CHAR) LIKE :q8
                OR CAST({$parts['restante_expr']} AS CHAR) LIKE :q9
                OR EXISTS (
                    SELECT 1
                    FROM venda_itens vi
                    WHERE vi.venda_id = f.venda_id
                      AND (
                          COALESCE(vi.nome,'') LIKE :q10
                          OR COALESCE(vi.codigo,'') LIKE :q10
                      )
                )
            )
        ";
    }

    return $where;
}

function fi_fetch_rows_result(int $page, int $per, bool $forExport = false): array
{
    global $pdo;

    $parts = fi_sql_parts();
    $params = [];
    $where = fi_build_where($params, $parts);

    $sqlTot = "
        SELECT
            COUNT(*) AS qtd,
            COALESCE(SUM(f.valor_total),0) AS total_venda,
            COALESCE(SUM({$parts['pago_expr']}),0) AS total_pago,
            COALESCE(SUM({$parts['restante_expr']}),0) AS total_restante
        {$parts['from']}
        {$where}
    ";
    $stTot = $pdo->prepare($sqlTot);
    $stTot->execute($params);
    $tot = $stTot->fetch(PDO::FETCH_ASSOC) ?: [
        'qtd' => 0,
        'total_venda' => 0,
        'total_pago' => 0,
        'total_restante' => 0,
    ];

    $limitSql = '';
    $offset = 0;
    if (!$forExport) {
        $page = max(1, $page);
        $per  = in_array($per, [10, 20, 30, 40, 50, 100], true) ? $per : 10;
        $offset = ($page - 1) * $per;
        $limitSql = " LIMIT {$offset}, {$per} ";
    }

    $sql = "
        SELECT
            f.id AS fiado_id,
            f.venda_id,
            f.cliente_id,
            f.valor_total,
            {$parts['pago_expr']} AS valor_pago_real,
            {$parts['restante_expr']} AS valor_restante_real,
            {$parts['status_expr']} AS status_real,
            f.data_vencimento,
            f.created_at AS fiado_created_at,

            v.data AS venda_data,
            v.created_at AS venda_created_at,
            v.cliente AS venda_cliente,
            v.canal,
            v.pagamento,
            v.endereco,
            v.obs,

            c.nome AS cliente_nome
        {$parts['from']}
        {$where}
        ORDER BY {$parts['data_ref_expr']} DESC, f.id DESC
        {$limitSql}
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rowsRaw = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $rows = [];
    foreach ($rowsRaw as $r) {
        $cliente = trim((string)($r['cliente_nome'] ?: $r['venda_cliente'] ?: '—'));

        $rows[] = [
            'fiado_id'        => (int)($r['fiado_id'] ?? 0),
            'venda_id'        => (int)($r['venda_id'] ?? 0),
            'cliente'         => $cliente,
            'canal'           => (string)($r['canal'] ?? 'PRESENCIAL'),
            'pagamento'       => (string)($r['pagamento'] ?? ''),
            'valor_total'     => (float)($r['valor_total'] ?? 0),
            'valor_pago'      => (float)($r['valor_pago_real'] ?? 0),
            'valor_restante'  => (float)($r['valor_restante_real'] ?? 0),
            'status'          => (string)($r['status_real'] ?? 'ABERTO'),
            'data_vencimento' => (string)($r['data_vencimento'] ?? ''),
            'created_at'      => (string)($r['venda_created_at'] ?? $r['fiado_created_at'] ?? ''),
            'created_at_fmt'  => fi_fmt_datetime((string)($r['venda_created_at'] ?? $r['fiado_created_at'] ?? '')),
            'vencimento_fmt'  => fi_fmt_date((string)($r['data_vencimento'] ?? '')),
            'endereco'        => (string)($r['endereco'] ?? ''),
            'obs'             => (string)($r['obs'] ?? ''),
        ];
    }

    $total = (int)($tot['qtd'] ?? 0);
    $pages = max(1, (int)ceil($total / max(1, $per)));

    return [
        'meta' => [
            'page'  => $forExport ? 1 : max(1, $page),
            'per'   => $forExport ? $total : $per,
            'pages' => $forExport ? 1 : $pages,
            'total' => $total,
            'from'  => $forExport ? ($total > 0 ? 1 : 0) : ($total > 0 ? $offset + 1 : 0),
            'to'    => $forExport ? $total : ($total > 0 ? min($offset + $per, $total) : 0),
        ],
        'totais' => [
            'qtd'            => $total,
            'total_venda'    => (float)($tot['total_venda'] ?? 0),
            'total_pago'     => (float)($tot['total_pago'] ?? 0),
            'total_restante' => (float)($tot['total_restante'] ?? 0),
        ],
        'rows' => $rows
    ];
}

function fi_fetch_one(int $fiadoId): ?array
{
    global $pdo;

    $parts = fi_sql_parts();

    $sql = "
        SELECT
            f.id AS fiado_id,
            f.venda_id,
            f.cliente_id,
            f.valor_total,
            {$parts['pago_expr']} AS valor_pago_real,
            {$parts['restante_expr']} AS valor_restante_real,
            {$parts['status_expr']} AS status_real,
            f.data_vencimento,
            f.created_at AS fiado_created_at,

            v.data AS venda_data,
            v.created_at AS venda_created_at,
            v.cliente AS venda_cliente,
            v.canal,
            v.pagamento,
            v.endereco,
            v.obs,

            c.nome AS cliente_nome
        {$parts['from']}
        WHERE f.id = :id
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':id' => $fiadoId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $itens = [];
    $vendaId = (int)($row['venda_id'] ?? 0);
    if ($vendaId > 0) {
        $stItens = $pdo->prepare("
            SELECT nome, codigo, unidade, preco_unit, qtd, subtotal
            FROM venda_itens
            WHERE venda_id = :venda_id
            ORDER BY id ASC
        ");
        $stItens->execute([':venda_id' => $vendaId]);
        $itens = $stItens->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $pagamentos = [];
    $stPag = $pdo->prepare("
        SELECT id, valor, metodo, created_at
        FROM fiados_pagamentos
        WHERE fiado_id = :fiado_id
        ORDER BY created_at DESC, id DESC
    ");
    $stPag->execute([':fiado_id' => $fiadoId]);
    $pagamentos = $stPag->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'fiado' => [
            'fiado_id'       => (int)($row['fiado_id'] ?? 0),
            'venda_id'       => (int)($row['venda_id'] ?? 0),
            'cliente'        => trim((string)($row['cliente_nome'] ?: $row['venda_cliente'] ?: '—')),
            'canal'          => (string)($row['canal'] ?? 'PRESENCIAL'),
            'pagamento'      => (string)($row['pagamento'] ?? ''),
            'valor_total'    => (float)($row['valor_total'] ?? 0),
            'valor_pago'     => (float)($row['valor_pago_real'] ?? 0),
            'valor_restante' => (float)($row['valor_restante_real'] ?? 0),
            'status'         => (string)($row['status_real'] ?? 'ABERTO'),
            'data_vencimento' => (string)($row['data_vencimento'] ?? ''),
            'created_at'     => (string)($row['venda_created_at'] ?? $row['fiado_created_at'] ?? ''),
            'endereco'       => (string)($row['endereco'] ?? ''),
            'obs'            => (string)($row['obs'] ?? ''),
        ],
        'itens' => $itens,
        'pagamentos' => $pagamentos
    ];
}

function fi_render_rows(array $rows): string
{
    if (!$rows) {
        return '<tr><td colspan="9" class="muted text-center">Nenhum fiado encontrado.</td></tr>';
    }

    $html = '';
    foreach ($rows as $r) {
        $status = strtoupper((string)$r['status']);
        $statusBadge = $status === 'PAGO'
            ? '<span class="badge-soft b-done">PAGO</span>'
            : '<span class="badge-soft b-open">ABERTO</span>';

        $canalBadge = strtoupper((string)$r['canal']) === 'DELIVERY'
            ? '<span class="badge-soft b-open">DELIVERY</span>'
            : '<span class="badge-soft b-done">PRESENCIAL</span>';

        $btnPagar = '';
        if ($status !== 'PAGO' && (float)$r['valor_restante'] > 0) {
            $btnPagar = '<button class="main-btn success-btn btn-hover btn-action" onclick="openPagamento(' . (int)$r['fiado_id'] . ')"><i class="lni lni-wallet me-1"></i>Pagar</button>';
        }

        $html .= '
            <tr>
                <td class="td-nowrap"><b>#' . (int)$r['venda_id'] . '</b></td>
                <td class="td-nowrap">
                    <div class="mini">' . fi_e((string)$r['created_at_fmt']) . '</div>
                    <div class="muted2">Venc.: ' . fi_e((string)$r['vencimento_fmt']) . '</div>
                </td>
                <td>
                    <div class="td-clip mini">' . fi_e((string)$r['cliente']) . '</div>
                    ' . (!empty($r['endereco']) ? '<div class="td-clip muted2">' . fi_e((string)$r['endereco']) . '</div>' : '') . '
                </td>
                <td class="td-nowrap">' . $canalBadge . '</td>
                <td class="td-money">' . fi_brl((float)$r['valor_total']) . '</td>
                <td class="td-money text-success">' . fi_brl((float)$r['valor_pago']) . '</td>
                <td class="td-money text-danger">' . fi_brl((float)$r['valor_restante']) . '</td>
                <td class="td-nowrap">' . $statusBadge . '</td>
                <td>
                    <div class="actions-wrap">
                        <button class="main-btn light-btn btn-hover btn-action" onclick="openDetails(' . (int)$r['fiado_id'] . ')"><i class="lni lni-eye me-1"></i>Detalhes</button>
                        ' . $btnPagar . '
                    </div>
                </td>
            </tr>
        ';
    }

    return $html;
}

/* =========================================================
   ACTIONS
========================================================= */
$action = strtolower(fi_get_str('action'));

if ($action === 'fetch') {
    try {
        $page = max(1, fi_get_int('page', 1));
        $per  = fi_get_int('per', 10);
        $result = fi_fetch_rows_result($page, $per, false);

        fi_json_out([
            'ok'     => true,
            'meta'   => $result['meta'],
            'totais' => $result['totais'],
            'rows'   => $result['rows'],
            'html'   => fi_render_rows($result['rows']),
        ]);
    } catch (Throwable $e) {
        fi_json_out([
            'ok'  => false,
            'msg' => 'Erro ao carregar fiados: ' . $e->getMessage()
        ], 500);
    }
}

if ($action === 'one') {
    try {
        $id = fi_get_int('id', 0);
        if ($id <= 0) {
            fi_json_out(['ok' => false, 'msg' => 'ID inválido'], 400);
        }

        $one = fi_fetch_one($id);
        if (!$one) {
            fi_json_out(['ok' => false, 'msg' => 'Fiado não encontrado'], 404);
        }

        fi_json_out(['ok' => true, 'data' => $one]);
    } catch (Throwable $e) {
        fi_json_out(['ok' => false, 'msg' => 'Erro ao abrir detalhes: ' . $e->getMessage()], 500);
    }
}

if ($action === 'pay' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrf = (string)($_POST['_csrf'] ?? '');
    $sess = (string)($_SESSION['_csrf'] ?? '');

    if ($csrf === '' || $sess === '' || !hash_equals($sess, $csrf)) {
        fi_flash_set('flash_err', 'CSRF inválido. Atualize a página e tente novamente.');
        fi_redirect('fiados.php');
    }

    $fiadoId  = fi_post_int('fiado_id', 0);
    $valor    = fi_post_float('valor', 0);
    $metodo   = strtoupper(fi_post_str('metodo', 'DINHEIRO'));
    $returnTo = fi_normalize_return_to(fi_post_str('return_to', 'fiados.php'));

    if ($fiadoId <= 0) {
        fi_flash_set('flash_err', 'Fiado inválido.');
        fi_redirect($returnTo);
    }

    if ($valor <= 0) {
        fi_flash_set('flash_err', 'Informe um valor de pagamento válido.');
        fi_redirect($returnTo);
    }

    $permitidos = ['DINHEIRO', 'PIX', 'CARTAO', 'BOLETO'];
    if (!in_array($metodo, $permitidos, true)) {
        $metodo = 'DINHEIRO';
    }

    try {
        $pdo->beginTransaction();

        $one = fi_fetch_one($fiadoId);
        if (!$one) {
            throw new RuntimeException('Fiado não encontrado.');
        }

        $fiado = $one['fiado'];
        $restanteAtual = (float)($fiado['valor_restante'] ?? 0);
        $pagoAtual     = (float)($fiado['valor_pago'] ?? 0);
        $totalAtual    = (float)($fiado['valor_total'] ?? 0);

        if ($restanteAtual <= 0) {
            throw new RuntimeException('Este fiado já está quitado.');
        }

        if ($valor > $restanteAtual) {
            throw new RuntimeException('O valor informado é maior que o restante em aberto.');
        }

        $stIns = $pdo->prepare("
            INSERT INTO fiados_pagamentos (fiado_id, valor, metodo, created_at)
            VALUES (:fiado_id, :valor, :metodo, NOW())
        ");
        $stIns->execute([
            ':fiado_id' => $fiadoId,
            ':valor'    => $valor,
            ':metodo'   => $metodo,
        ]);

        $novoPago = $pagoAtual + $valor;
        if ($novoPago > $totalAtual) {
            $novoPago = $totalAtual;
        }

        $novoRestante = $totalAtual - $novoPago;
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
            ':id'             => $fiadoId,
        ]);

        $pdo->commit();

        fi_flash_set('flash_ok', 'Pagamento lançado com sucesso.');
        fi_redirect($returnTo);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fi_flash_set('flash_err', 'Erro ao registrar pagamento: ' . $e->getMessage());
        fi_redirect($returnTo);
    }
}

if ($action === 'excel') {
    $result = fi_fetch_rows_result(1, 100000, true);
    $rows = $result['rows'];
    $totais = $result['totais'];

    $agora = date('d/m/Y H:i:s');
    $di = fi_get_str('di') ?: '—';
    $df = fi_get_str('df') ?: '—';
    $canal = fi_get_str('canal', 'TODOS');
    $status = fi_get_str('status', 'TODOS');
    $q = fi_get_str('q') ?: '—';

    $fname = 'relatorio_fiados_resumo_' . date('Ymd_His') . '.xls';

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
            }

            table {
                border-collapse: collapse;
                width: 100%;
                table-layout: fixed;
            }

            td,
            th {
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
                <td colspan="8">Período: <?= fi_e($di) ?> até <?= fi_e($df) ?> | Canal: <?= fi_e($canal) ?> | Status: <?= fi_e($status) ?> | Busca: <?= fi_e($q) ?></td>
            </tr>
            <tr>
                <td colspan="8">Total fiados filtrados: <?= (int)$totais['qtd'] ?> | Total em fiados: <?= fi_e(fi_brl((float)$totais['total_venda'])) ?> | Total pago: <?= fi_e(fi_brl((float)$totais['total_pago'])) ?> | Restante: <?= fi_e(fi_brl((float)$totais['total_restante'])) ?></td>
            </tr>
        </table>

        <table style="margin-top:6px;">
            <thead>
                <tr>
                    <th class="head">Nº Venda</th>
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
                        <td><?= fi_e((string)$r['created_at_fmt']) ?></td>
                        <td class="left"><?= fi_e((string)$r['cliente']) ?></td>
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
   INITIAL
========================================================= */
$initial = fi_fetch_rows_result(1, 10, false);
$csrf = fi_csrf_token();
$flashOk  = fi_flash_take('flash_ok');
$flashErr = fi_flash_take('flash_err');
$currentReturn = fi_e((string)($_SERVER['REQUEST_URI'] ?? 'fiados.php'));
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
    <link rel="stylesheet" href="assets/css/lineicons.css" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" type="text/css" />
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
            height: 40px;
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
            padding: 14px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .cardx .body {
            padding: 16px;
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
            grid-template-columns: repeat(3, minmax(0, 1fr));
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
            border-left: 4px solid #166534;
        }

        .summary-card.s3 {
            background: #f8fafc;
            border-left: 4px solid #dc2626;
        }

        .summary-card.s1 .val {
            color: #0f172a;
        }

        .summary-card.s2 .val {
            color: #166534;
        }

        .summary-card.s3 .val {
            color: #dc2626;
        }

        .table-wrap {
            overflow: auto;
            border-radius: 14px;
        }

        #tbFiados {
            width: 100%;
            min-width: 1220px;
            table-layout: fixed;
        }

        #tbFiados thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fafc;
            border-bottom: 1px solid rgba(148, 163, 184, .25);
            font-size: 12px;
            color: #0f172a;
            padding: 14px 18px;
            white-space: nowrap;
            text-align: center;
            letter-spacing: .2px;
        }

        #tbFiados tbody td {
            border-top: 1px solid rgba(148, 163, 184, .18);
            padding: 16px 18px;
            font-size: 13px;
            vertical-align: top;
            color: #0f172a;
            background: #fff;
        }

        .page-nav {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            margin-top: 12px;
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

        .col-venda {
            width: 90px;
        }

        .col-data {
            width: 165px;
        }

        .col-cliente {
            width: 270px;
        }

        .col-canal {
            width: 130px;
        }

        .col-num {
            width: 145px;
        }

        .col-status {
            width: 130px;
        }

        .col-acoes {
            width: 220px;
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

        .success-btn {
            background: #16a34a !important;
            border-color: #16a34a !important;
            color: #fff !important;
        }

        .success-btn:hover {
            background: #15803d !important;
            border-color: #15803d !important;
            color: #fff !important;
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
            max-width: 360px;
        }

        .sale-row .left .cd {
            color: #64748b;
            font-size: 12px;
        }

        .sale-row .right {
            white-space: nowrap;
            text-align: right;
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

        .flash-auto-hide {
            transition: opacity .35s ease, transform .35s ease;
        }

        .flash-auto-hide.hide-now {
            opacity: 0;
            transform: translateY(-8px);
        }

        @media(max-width:1199.98px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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
                <?php if ($flashOk): ?>
                    <div class="alert alert-success flash-auto-hide mt-3" style="border-radius:14px;"><?= fi_e($flashOk) ?></div>
                <?php endif; ?>

                <?php if ($flashErr): ?>
                    <div class="alert alert-danger flash-auto-hide mt-3" style="border-radius:14px;"><?= fi_e($flashErr) ?></div>
                <?php endif; ?>

                <div class="title-wrapper pt-30">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="title">
                                <h2>Gestão de À Prazo</h2>
                                <p class="text-muted">Listagem, filtros automáticos e pagamentos</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cardx mb-3">
                    <div class="head">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="pill ok" id="pillCountTop"><?= (int)$initial['totais']['qtd'] ?> fiados</span>
                                <span class="muted" id="lblRange">Período: — até —</span>
                            </div>
                            <div class="muted mt-1">Os filtros abaixo são aplicados automaticamente, sem precisar clicar em botão.</div>
                        </div>

                        <div class="toolbar">
                            <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel">
                                <i class="lni lni-download me-1"></i> Exportar Excel
                            </button>

                            <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar">
                                <i class="lni lni-close me-1"></i> Limpar
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
                                <label class="form-label mini">Status</label>
                                <select class="form-select compact" id="status">
                                    <option value="TODOS" selected>Todos</option>
                                    <option value="ABERTO">Aberto</option>
                                    <option value="PAGO">Pago</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mini">Pesquisar em tudo da tabela</label>
                                <input type="text" class="form-control compact" id="q" placeholder="Venda, cliente, canal, status, itens, valores..." autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="summary-grid">
                    <div class="summary-card s1">
                        <div class="lbl">Total em À Prazo</div>
                        <div class="val" id="txtTotalVenda"><?= fi_e(fi_brl((float)$initial['totais']['total_venda'])) ?></div>
                    </div>
                    <div class="summary-card s2">
                        <div class="lbl">Total Pago</div>
                        <div class="val" id="txtTotalPago"><?= fi_e(fi_brl((float)$initial['totais']['total_pago'])) ?></div>
                    </div>
                    <div class="summary-card s3">
                        <div class="lbl">Total em Aberto</div>
                        <div class="val" id="txtTotalRestante"><?= fi_e(fi_brl((float)$initial['totais']['total_restante'])) ?></div>
                    </div>
                </div>

                <div class="cardx">
                    <div class="head">
                        <div class="muted"><b>À Prazo</b> • pesquisa AJAX automática em toda a tabela</div>
                        <div class="toolbar">
                            <div class="pill ok" id="pillCountTable"><?= (int)$initial['totais']['qtd'] ?> fiados</div>
                            <div class="pill" id="pillLoading" style="display:none;">
                                <i class="lni lni-spinner-arrow lni-spin"></i> Carregando...
                            </div>
                        </div>
                    </div>

                    <div class="body">
                        <div class="table-wrap">
                            <table class="table table-hover mb-0" id="tbFiados">
                                <thead>
                                    <tr>
                                        <th class="col-venda">Venda #</th>
                                        <th class="col-data">Data/Hora</th>
                                        <th class="col-cliente">Cliente</th>
                                        <th class="col-canal">Canal</th>
                                        <th class="col-num">Total Venda</th>
                                        <th class="col-num">Total Pago</th>
                                        <th class="col-num">Restante</th>
                                        <th class="col-status">Status</th>
                                        <th class="col-acoes">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody">
                                    <?= fi_render_rows($initial['rows']) ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                            <div class="page-info" id="rangeInfo">
                                Mostrando <?= (int)$initial['meta']['from'] ?>-<?= (int)$initial['meta']['to'] ?> de <?= (int)$initial['meta']['total'] ?>
                            </div>

                            <div class="page-nav">
                                <button class="page-btn" id="btnPrev">←</button>
                                <span class="page-info" id="pageInfo">Página <?= (int)$initial['meta']['page'] ?> / <?= (int)$initial['meta']['pages'] ?></span>
                                <button class="page-btn" id="btnNext">→</button>
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
                    <h5 class="modal-title fw-1000">Detalhes do À Prazo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="cardx">
                                <div class="head"><b>Dados</b></div>
                                <div class="body">
                                    <div class="tot-row"><span>Fiado</span><span id="dFiado">—</span></div>
                                    <div class="tot-row"><span>Venda</span><span id="dVenda">—</span></div>
                                    <div class="tot-row"><span>Data/Hora</span><span id="dDt">—</span></div>
                                    <div class="tot-row"><span>Cliente</span><span id="dCli">—</span></div>
                                    <div class="tot-row"><span>Canal</span><span id="dCanal">—</span></div>
                                    <div class="tot-row"><span>Pagamento</span><span id="dPag">—</span></div>
                                    <div class="tot-row"><span>Vencimento</span><span id="dVenc">—</span></div>
                                    <div class="tot-row"><span>Status</span><span id="dStatus">—</span></div>
                                    <div class="tot-row"><span>Endereço</span><span id="dEnd">—</span></div>
                                    <div class="tot-row"><span>Obs</span><span id="dObs">—</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="cardx">
                                <div class="head"><b>Totais</b></div>
                                <div class="body">
                                    <div class="tot-row"><span>Total Venda</span><span id="dTotal">R$ 0,00</span></div>
                                    <div class="tot-row"><span>Total Pago</span><span id="dPago">R$ 0,00</span></div>
                                    <div class="tot-row"><span>Restante</span><span id="dRestante">R$ 0,00</span></div>
                                    <div class="tot-hr"></div>
                                    <div class="grand">
                                        <div class="lbl">STATUS</div>
                                        <div class="val" id="dStatusBig">—</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="cardx">
                                <div class="head"><b>Itens da Venda</b></div>
                                <div class="body">
                                    <div class="sale-box" id="dItens">—</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="cardx">
                                <div class="head"><b>Pagamentos</b></div>
                                <div class="body">
                                    <div class="sale-box" id="dPagamentos">—</div>
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

    <div class="modal fade" id="mdPagamento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:16px;">
                <form method="post" action="fiados.php?action=pay">
                    <div class="modal-header">
                        <h5 class="modal-title fw-1000">Registrar Pagamento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="_csrf" value="<?= fi_e($csrf) ?>">
                        <input type="hidden" name="fiado_id" id="pFiadoId" value="">
                        <input type="hidden" name="return_to" value="<?= $currentReturn ?>">

                        <div class="mb-2 muted">Cliente: <b id="pCliente">—</b></div>
                        <div class="mb-2 muted">Venda: <b id="pVenda">—</b></div>
                        <div class="mb-2 muted">Total: <b id="pTotal">R$ 0,00</b></div>
                        <div class="mb-2 muted">Pago: <b id="pPago">R$ 0,00</b></div>
                        <div class="mb-3 muted">Restante: <b id="pRestante">R$ 0,00</b></div>

                        <div class="mb-3">
                            <label class="form-label mini">Valor do pagamento</label>
                            <input type="text" class="form-control compact" name="valor" id="pValor" placeholder="0,00" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label mini">Método</label>
                            <select class="form-select compact" name="metodo" id="pMetodo">
                                <option value="DINHEIRO">Dinheiro</option>
                                <option value="PIX">PIX</option>
                                <option value="CARTAO">Cartão</option>
                                <option value="BOLETO">Boleto</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="main-btn primary-btn btn-hover btn-compact" type="submit">Salvar pagamento</button>
                        <button class="main-btn light-btn btn-hover btn-compact" type="button" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const el = (id) => document.getElementById(id);

        const state = {
            page: <?= (int)$initial['meta']['page'] ?>,
            pages: <?= (int)$initial['meta']['pages'] ?>,
            per: 10,
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

        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, (m) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [m]));
        }

        function buildParams() {
            const p = new URLSearchParams();
            p.set('action', 'fetch');
            p.set('page', String(state.page));
            p.set('per', String(state.per));

            const di = el('di').value.trim();
            const df = el('df').value.trim();
            const canal = el('canal').value.trim();
            const status = el('status').value.trim();
            const q = el('q').value.trim();

            if (di) p.set('di', di);
            if (df) p.set('df', df);
            if (canal) p.set('canal', canal);
            if (status) p.set('status', status);
            if (q) p.set('q', q);

            return p;
        }

        function buildExportUrl() {
            const p = new URLSearchParams();
            p.set('action', 'excel');

            const di = el('di').value.trim();
            const df = el('df').value.trim();
            const canal = el('canal').value.trim();
            const status = el('status').value.trim();
            const q = el('q').value.trim();

            if (di) p.set('di', di);
            if (df) p.set('df', df);
            if (canal) p.set('canal', canal);
            if (status) p.set('status', status);
            if (q) p.set('q', q);

            return `fiados.php?${p.toString()}`;
        }

        function setLoading(on) {
            el('pillLoading').style.display = on ? '' : 'none';
        }

        function renderRows(rows) {
            if (!rows || !rows.length) {
                el('tbody').innerHTML = '<tr><td colspan="9" class="muted text-center">Nenhum fiado encontrado com este filtro.</td></tr>';
                return;
            }

            el('tbody').innerHTML = rows.map(r => {
                const status = String(r.status || '').toUpperCase();
                const statusBadge = status === 'PAGO' ?
                    '<span class="badge-soft b-done">PAGO</span>' :
                    '<span class="badge-soft b-open">ABERTO</span>';

                const canalBadge = String(r.canal || '').toUpperCase() === 'DELIVERY' ?
                    '<span class="badge-soft b-open">DELIVERY</span>' :
                    '<span class="badge-soft b-done">PRESENCIAL</span>';

                const btnPagar = (status !== 'PAGO' && Number(r.valor_restante || 0) > 0) ?
                    `<button class="main-btn success-btn btn-hover btn-action" onclick="openPagamento(${Number(r.fiado_id)})"><i class="lni lni-wallet me-1"></i>Pagar</button>` :
                    '';

                return `
                    <tr>
                        <td class="td-nowrap"><b>#${escapeHtml(r.venda_id)}</b></td>
                        <td class="td-nowrap">
                            <div class="mini">${escapeHtml(r.created_at_fmt)}</div>
                            <div class="muted2">Venc.: ${escapeHtml(r.vencimento_fmt)}</div>
                        </td>
                        <td>
                            <div class="td-clip mini">${escapeHtml(r.cliente || '—')}</div>
                            ${r.endereco ? `<div class="td-clip muted2">${escapeHtml(r.endereco)}</div>` : ``}
                        </td>
                        <td class="td-nowrap">${canalBadge}</td>
                        <td class="td-money">${brl(r.valor_total)}</td>
                        <td class="td-money text-success">${brl(r.valor_pago)}</td>
                        <td class="td-money text-danger">${brl(r.valor_restante)}</td>
                        <td class="td-nowrap">${statusBadge}</td>
                        <td>
                            <div class="actions-wrap">
                                <button class="main-btn light-btn btn-hover btn-action" onclick="openDetails(${Number(r.fiado_id)})"><i class="lni lni-eye me-1"></i>Detalhes</button>
                                ${btnPagar}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function fetchFiados() {
            setLoading(true);

            try {
                const res = await fetch(`fiados.php?${buildParams().toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF': csrf
                    }
                });

                const text = await res.text();
                let js = null;

                try {
                    js = JSON.parse(text);
                } catch (e) {
                    throw new Error('Resposta inválida do servidor.');
                }

                if (!res.ok || !js.ok) {
                    throw new Error(js?.msg || 'Falha ao carregar dados');
                }

                state.page = Number(js.meta.page || 1);
                state.pages = Number(js.meta.pages || 1);

                renderRows(js.rows || []);

                el('txtTotalVenda').textContent = brl(js.totais.total_venda || 0);
                el('txtTotalPago').textContent = brl(js.totais.total_pago || 0);
                el('txtTotalRestante').textContent = brl(js.totais.total_restante || 0);

                el('pillCountTop').textContent = `${js.totais.qtd} fiados`;
                el('pillCountTable').textContent = `${js.totais.qtd} fiados`;

                el('rangeInfo').textContent = `Mostrando ${js.meta.from}-${js.meta.to} de ${js.meta.total}`;
                el('pageInfo').textContent = `Página ${js.meta.page} / ${js.meta.pages}`;

                el('btnPrev').disabled = state.page <= 1;
                el('btnNext').disabled = state.page >= state.pages;

                const di = el('di').value ? el('di').value.split('-').reverse().join('/') : '—';
                const df = el('df').value ? el('df').value.split('-').reverse().join('/') : '—';
                el('lblRange').textContent = `Período: ${di} até ${df}`;

            } catch (err) {
                el('tbody').innerHTML = `<tr><td colspan="9" class="text-danger text-center">Erro: ${escapeHtml(err.message || String(err))}</td></tr>`;
            } finally {
                setLoading(false);
            }
        }

        function triggerAutoSearch() {
            clearTimeout(state.searchTimer);
            state.searchTimer = setTimeout(() => {
                state.page = 1;
                state.per = Number(el('per').value || 10);
                fetchFiados();
            }, 300);
        }

        async function openDetails(id) {
            try {
                const res = await fetch(`fiados.php?action=one&id=${id}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF': csrf
                    }
                });

                const text = await res.text();
                let js = null;

                try {
                    js = JSON.parse(text);
                } catch (e) {
                    throw new Error('Detalhes não vieram em JSON válido.');
                }

                if (!res.ok || !js.ok) {
                    throw new Error(js?.msg || 'Falha ao abrir detalhes');
                }

                const fiado = js.data.fiado || {};
                const itens = js.data.itens || [];
                const pagamentos = js.data.pagamentos || [];

                el('dFiado').textContent = `#${fiado.fiado_id || '—'}`;
                el('dVenda').textContent = `#${fiado.venda_id || '—'}`;
                el('dDt').textContent = fiado.created_at || '—';
                el('dCli').textContent = fiado.cliente || '—';
                el('dCanal').textContent = fiado.canal || '—';
                el('dPag').textContent = fiado.pagamento || '—';
                el('dVenc').textContent = fiado.data_vencimento || '—';
                el('dStatus').textContent = fiado.status || '—';
                el('dStatusBig').textContent = fiado.status || '—';
                el('dEnd').textContent = fiado.endereco || '—';
                el('dObs').textContent = fiado.obs || '—';

                el('dTotal').textContent = brl(fiado.valor_total || 0);
                el('dPago').textContent = brl(fiado.valor_pago || 0);
                el('dRestante').textContent = brl(fiado.valor_restante || 0);

                if (!itens.length) {
                    el('dItens').innerHTML = '<span class="muted">Sem itens encontrados para esta venda.</span>';
                } else {
                    el('dItens').innerHTML = itens.map(it => `
                        <div class="sale-row">
                            <div class="left">
                                <div class="nm">${escapeHtml(it.nome || 'Item')}</div>
                                ${it.codigo ? `<div class="cd">${escapeHtml(it.codigo)}</div>` : ``}
                                <div class="cd">${escapeHtml(it.unidade || '')} • ${brl(it.preco_unit || 0)}</div>
                            </div>
                            <div class="right">
                                <div><b>${escapeHtml(it.qtd || 0)}</b></div>
                                <div class="muted2">${brl(it.subtotal || 0)}</div>
                            </div>
                        </div>
                    `).join('');
                }

                if (!pagamentos.length) {
                    el('dPagamentos').innerHTML = '<span class="muted">Sem pagamentos lançados.</span>';
                } else {
                    el('dPagamentos').innerHTML = pagamentos.map(pg => `
                        <div class="sale-row">
                            <div class="left">
                                <div class="nm">${escapeHtml(pg.metodo || 'Pagamento')}</div>
                                <div class="cd">${escapeHtml(pg.created_at || '')}</div>
                            </div>
                            <div class="right">
                                <div><b>${brl(pg.valor || 0)}</b></div>
                            </div>
                        </div>
                    `).join('');
                }

                const modal = new bootstrap.Modal(el('mdDetalhes'));
                modal.show();
            } catch (err) {
                alert('Erro: ' + (err.message || String(err)));
            }
        }

        async function openPagamento(id) {
            try {
                const res = await fetch(`fiados.php?action=one&id=${id}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF': csrf
                    }
                });

                const text = await res.text();
                let js = null;

                try {
                    js = JSON.parse(text);
                } catch (e) {
                    throw new Error('Dados do pagamento inválidos.');
                }

                if (!res.ok || !js.ok) {
                    throw new Error(js?.msg || 'Falha ao abrir pagamento');
                }

                const fiado = js.data.fiado || {};

                el('pFiadoId').value = fiado.fiado_id || '';
                el('pCliente').textContent = fiado.cliente || '—';
                el('pVenda').textContent = '#' + (fiado.venda_id || '—');
                el('pTotal').textContent = brl(fiado.valor_total || 0);
                el('pPago').textContent = brl(fiado.valor_pago || 0);
                el('pRestante').textContent = brl(fiado.valor_restante || 0);
                el('pValor').value = String(Number(fiado.valor_restante || 0).toFixed(2)).replace('.', ',');
                el('pMetodo').value = 'DINHEIRO';

                const modal = new bootstrap.Modal(el('mdPagamento'));
                modal.show();
            } catch (err) {
                alert('Erro: ' + (err.message || String(err)));
            }
        }

        el('btnPrev').addEventListener('click', () => {
            if (state.page <= 1) return;
            state.page -= 1;
            fetchFiados();
        });

        el('btnNext').addEventListener('click', () => {
            if (state.page >= state.pages) return;
            state.page += 1;
            fetchFiados();
        });

        el('per').addEventListener('change', () => {
            state.per = Number(el('per').value || 10);
            state.page = 1;
            fetchFiados();
        });

        el('btnLimpar').addEventListener('click', () => {
            el('di').value = '';
            el('df').value = '';
            el('canal').value = 'TODOS';
            el('status').value = 'TODOS';
            el('q').value = '';
            state.page = 1;
            state.per = Number(el('per').value || 10);
            fetchFiados();
        });

        el('btnExcel').addEventListener('click', () => {
            window.location.href = buildExportUrl();
        });

        el('di').addEventListener('change', triggerAutoSearch);
        el('df').addEventListener('change', triggerAutoSearch);
        el('canal').addEventListener('change', triggerAutoSearch);
        el('status').addEventListener('change', triggerAutoSearch);
        el('q').addEventListener('input', triggerAutoSearch);

        el('q').addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                clearTimeout(state.searchTimer);
                state.page = 1;
                fetchFiados();
            }
        });

        document.querySelectorAll('.flash-auto-hide').forEach(elm => {
            setTimeout(() => {
                elm.classList.add('hide-now');
                setTimeout(() => elm.remove(), 350);
            }, 1600);
        });

        el('btnPrev').disabled = state.page <= 1;
        el('btnNext').disabled = state.page >= state.pages;

        window.openDetails = openDetails;
        window.openPagamento = openPagamento;
    </script>
</body>

</html>