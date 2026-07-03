<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

require_once __DIR__ . '/../dist/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo 'Erro: conexão com o banco não encontrada.';
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
@date_default_timezone_set('America/Manaus');

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function normalizeDate(?string $value): ?string
{
    $value = trim((string)$value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function normalizePeriod(?string $value): string
{
    $value = strtolower(trim((string)$value));
    return in_array($value, ['diario', 'semanal', 'mensal', 'anual', 'personalizado'], true) ? $value : 'personalizado';
}

function normalizeIds($value): array
{
    $raw = is_array($value) ? $value : preg_split('/[,\s]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
    $ids = [];
    foreach ($raw ?: [] as $item) {
        $id = (int)$item;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function computeRangeByPeriod(string $periodo, ?string $di, ?string $df): array
{
    if ($periodo === 'personalizado') {
        return [$di, $df];
    }

    $today = new DateTime('now', new DateTimeZone('America/Manaus'));
    if ($periodo === 'diario') {
        $date = $today->format('Y-m-d');
        return [$date, $date];
    }
    if ($periodo === 'semanal') {
        $end = clone $today;
        $start = clone $today;
        $start->modify('-6 day');
        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
    if ($periodo === 'mensal') {
        $start = new DateTime($today->format('Y-m-01'), new DateTimeZone('America/Manaus'));
        $end = clone $start;
        $end->modify('last day of this month');
        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    $start = new DateTime($today->format('Y-01-01'), new DateTimeZone('America/Manaus'));
    $end = new DateTime($today->format('Y-12-31'), new DateTimeZone('America/Manaus'));
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function fmtDateBR(?string $ymd): string
{
    $ymd = trim((string)$ymd);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return '-';
    }
    return substr($ymd, 8, 2) . '/' . substr($ymd, 5, 2) . '/' . substr($ymd, 0, 4);
}

function lookupNames(PDO $pdo, string $table, array $ids): array
{
    $ids = normalizeIds($ids);
    if (!$ids || !in_array($table, ['bairros', 'ajudas_tipos'], true)) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($ids as $idx => $id) {
        $key = ':id' . $idx;
        $placeholders[] = $key;
        $params[$key] = $id;
    }

    $stmt = $pdo->prepare("SELECT id, nome FROM {$table} WHERE id IN (" . implode(',', $placeholders) . ") ORDER BY nome ASC");
    $stmt->execute($params);
    return array_map(static fn(array $row): string => (string)$row['nome'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function addInFilter(array &$where, array &$params, string $column, string $prefix, array $ids): void
{
    $ids = normalizeIds($ids);
    if (!$ids) {
        return;
    }

    $placeholders = [];
    foreach ($ids as $idx => $id) {
        $key = ':' . $prefix . $idx;
        $placeholders[] = $key;
        $params[$key] = $id;
    }
    $where[] = $column . ' IN (' . implode(',', $placeholders) . ')';
}

function normalizeWorkStatus(?string $trabalho): string
{
    $value = strtolower(trim((string)$trabalho));
    $value = strtr($value, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
        'é' => 'e', 'ê' => 'e',
        'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u',
        'ç' => 'c',
    ]);
    return ($value === 'empregado(a)' || $value === 'empregado') ? 'Sim' : 'Não';
}

function highlightResumo(string $resumo): string
{
    $safe = e($resumo);
    $safe = preg_replace('/(?<![\p{L}\p{N}_])(garis?)(?![\p{L}\p{N}_])/iu', '<mark>$1</mark>', $safe) ?? $safe;
    $safe = preg_replace('/(?<![\p{L}\p{N}_])(servi(?:&ccedil;|ç|c)[oó]s?\s+gera(?:l|is))(?![\p{L}\p{N}_])/iu', '<mark>$1</mark>', $safe) ?? $safe;
    return $safe;
}

$periodo = normalizePeriod($_GET['periodo'] ?? 'personalizado');
$di = normalizeDate($_GET['di'] ?? null);
$df = normalizeDate($_GET['df'] ?? null);
[$di, $df] = computeRangeByPeriod($periodo, $di, $df);

$bairroIds = normalizeIds($_GET['bairro_id'] ?? []);
$beneficioIds = normalizeIds($_GET['beneficio_id'] ?? []);
$sexo = trim((string)($_GET['sexo'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$baseDate = 'COALESCE(s.created_at, s.updated_at)';

$where = ['1=1'];
$params = [];

if ($di !== null) {
    $where[] = "DATE($baseDate) >= :di";
    $params[':di'] = $di;
}
if ($df !== null) {
    $where[] = "DATE($baseDate) <= :df";
    $params[':df'] = $df;
}

addInFilter($where, $params, 's.bairro_id', 'bairro', $bairroIds);
addInFilter($where, $params, 's.ajuda_tipo_id', 'beneficio', $beneficioIds);

if ($sexo !== '') {
    $where[] = "LOWER(TRIM(COALESCE(s.genero,''))) = LOWER(TRIM(:sexo))";
    $params[':sexo'] = $sexo;
}

if ($q !== '') {
    $where[] = "(s.nome LIKE :q OR s.cpf LIKE :q OR s.telefone LIKE :q OR s.resumo_caso LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$where[] = "(
    LOWER(COALESCE(s.resumo_caso,'')) REGEXP :kwGari
    OR LOWER(COALESCE(s.resumo_caso,'')) REGEXP :kwServicosGerais
)";
$params[':kwGari'] = '(^|[^[:alnum:]_])garis?([^[:alnum:]_]|$)';
$params[':kwServicosGerais'] = '(^|[^[:alnum:]_])servi(c|ç)[oó]s?[[:space:]]+gera(l|is)([^[:alnum:]_]|$)';

$totalSql = "SELECT COUNT(*) FROM solicitantes s WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare($totalSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$limit = 5000;
$sql = "
    SELECT
        s.id,
        s.nome,
        s.cpf,
        CONCAT(COALESCE(s.endereco,''), ', Nº ', COALESCE(s.numero,''), ' - ', COALESCE(b.nome,'-')) AS endereco_completo,
        COALESCE(b.nome,'-') AS bairro_nome,
        s.telefone,
        CASE
            WHEN s.ajuda_tipo_id IS NULL OR s.ajuda_tipo_id = 0 THEN 'Sem benefício'
            ELSE COALESCE(at.nome,'-')
        END AS beneficio,
        DATE($baseDate) AS data_cadastro,
        s.trabalho,
        s.resumo_caso
    FROM solicitantes s
    LEFT JOIN bairros b ON b.id = s.bairro_id
    LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY DATE($baseDate) ASC, s.nome ASC
    LIMIT $limit
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$bairroLabel = ($names = lookupNames($pdo, 'bairros', $bairroIds)) ? implode(', ', $names) : 'Todos';
$beneficioLabel = ($names = lookupNames($pdo, 'ajudas_tipos', $beneficioIds)) ? implode(', ', $names) : 'Todos';
$sexoLabel = $sexo !== '' ? $sexo : 'Todos';
$backUrl = 'relatoriosCadastros.php?' . http_build_query(array_filter([
    'periodo' => $periodo,
    'di' => $di,
    'df' => $df,
    'bairro_id' => implode(',', $bairroIds),
    'beneficio_id' => implode(',', $beneficioIds),
    'sexo' => $sexo,
    'q' => $q,
    'print' => '1',
], static fn($value): bool => $value !== '' && $value !== null));
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <title>Teste - Resumo com Serviços Gerais e Gari</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
    <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <style>
        body {
            margin: 0;
            background: #f5f7fb;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #fff;
            border-bottom: 1px solid #d8e0ec;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .wrap {
            width: min(1600px, 100%);
            margin: 0 auto;
            padding: 14px;
        }

        .meta {
            border: 1px solid #b6d4fe;
            background: #eaf3ff;
            margin-bottom: 12px;
        }

        .meta-row {
            padding: 8px 10px;
            border-top: 1px solid #cfe2ff;
            font-weight: 700;
        }

        .meta-row:first-child {
            border-top: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #fff;
            border: 1px solid #1e40af;
        }

        th,
        td {
            border: 1px solid #93c5fd;
            padding: 6px 7px;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        th {
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            text-align: left;
        }

        tbody tr:nth-child(even) td {
            background: #f8fbff;
        }

        .col-n {
            width: 44px;
            text-align: right;
        }

        .col-name {
            width: 18%;
        }

        .col-cpf {
            width: 8%;
        }

        .col-address {
            width: 20%;
        }

        .col-phone {
            width: 8%;
        }

        .col-benefit {
            width: 12%;
        }

        .col-date {
            width: 7%;
        }

        .col-work {
            width: 6%;
        }

        .col-summary {
            width: auto;
        }

        mark {
            background: #fde68a;
            color: #111827;
            padding: 0 2px;
            border-radius: 2px;
            font-weight: 700;
        }

        .empty {
            padding: 24px;
            text-align: center;
            color: #4b5563;
        }

        @media print {
            body {
                background: #fff;
                font-size: 10px;
            }

            .toolbar {
                display: none;
            }

            .wrap {
                padding: 0;
            }

            thead {
                display: table-header-group;
            }

            tr {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <strong>Teste: resumo do caso com Serviços Gerais ou Gari</strong>
        <div class="actions">
            <a class="btn btn-outline-secondary btn-sm" href="<?= e($backUrl) ?>">Voltar ao relatório</a>
            <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Baixar PDF / Imprimir</button>
        </div>
    </div>

    <main class="wrap">
        <section class="meta" aria-label="Filtros aplicados">
            <div class="meta-row">Período: <?= e($periodo) ?> | Data inicial: <?= e(fmtDateBR($di)) ?> | Data final: <?= e(fmtDateBR($df)) ?> | Bairro: <?= e($bairroLabel) ?> | Benefício: <?= e($beneficioLabel) ?> | Sexo/Gênero: <?= e($sexoLabel) ?></div>
            <div class="meta-row">Filtro extra no Resumo do Caso: gari OU serviços gerais | Total encontrado: <?= $total ?><?= $total > $limit ? ' | Exibindo os primeiros ' . $limit : '' ?></div>
        </section>

        <table>
            <thead>
                <tr>
                    <th class="col-n">Nº</th>
                    <th class="col-name">Nome</th>
                    <th class="col-cpf">CPF</th>
                    <th class="col-address">Endereço Completo</th>
                    <th class="col-phone">Telefone</th>
                    <th class="col-benefit">Benefício</th>
                    <th class="col-date">Data cadastro</th>
                    <th class="col-work">Empregado</th>
                    <th class="col-summary">Resumo do Caso</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="9" class="empty">Nenhuma pessoa encontrada com esses filtros e termos no resumo.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $idx => $row): ?>
                        <?php $resumo = (string)($row['resumo_caso'] ?? ''); ?>
                        <tr>
                            <td class="col-n"><?= $idx + 1 ?></td>
                            <td><?= e((string)($row['nome'] ?? '-')) ?></td>
                            <td><?= e((string)($row['cpf'] ?? '')) ?></td>
                            <td><?= e((string)($row['endereco_completo'] ?? '-')) ?></td>
                            <td><?= e((string)($row['telefone'] ?? '')) ?></td>
                            <td><?= e((string)($row['beneficio'] ?? '-')) ?></td>
                            <td><?= e(fmtDateBR((string)($row['data_cadastro'] ?? ''))) ?></td>
                            <td><?= e(normalizeWorkStatus((string)($row['trabalho'] ?? ''))) ?></td>
                            <td><?= highlightResumo($resumo) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</body>

</html>
