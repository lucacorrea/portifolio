<?php

declare(strict_types=1);

/* (Opcional) guard de auth */
@require_once __DIR__ . '/auth/authGuard.php';
if (function_exists('auth_guard')) auth_guard();

/* ===== Timezone ===== */
date_default_timezone_set('America/Manaus');

/* ===== Conexão (PDO) ===== */
$pdo = null;

/* 1) ANEXO: assets/conexao.php define $pdo */
@require_once __DIR__ . '/../dist/assets/conexao.php';

/* 2) SIGRelatórios: assets/php/conexao.php com db(): PDO */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  @require_once __DIR__ . '/assets/php/conexao.php';
  if (function_exists('db')) $pdo = db();
}

if (!($pdo instanceof PDO)) {
  echo "<div style='padding:12px;background:#fee2e2;border:1px solid #fecaca;border-radius:10px;color:#7f1d1d;font-family:system-ui'>Erro: conexão PDO não encontrada.</div>";
  exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* =========================
   HELPERS
========================= */
function h(?string $s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money_br(float $v): string
{
  return 'R$ ' . number_format($v, 2, ',', '.');
}

function valid_date(?string $s): string
{
  $s = trim((string)$s);
  return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
}

function filters_from_request(): array
{
  $dataInicio = valid_date($_GET['dataInicio'] ?? '');
  $dataFim    = valid_date($_GET['dataFim'] ?? '');
  $categoria  = trim((string)($_GET['categoria'] ?? ''));
  $status     = trim((string)($_GET['status'] ?? ''));
  $beneficio  = trim((string)($_GET['beneficio'] ?? ''));

  if (!in_array($status, ['Ativa', 'Inativa'], true)) $status = '';
  if ($categoria === '') $categoria = '';
  if ($beneficio !== '' && !preg_match('/^\d+$/', $beneficio)) $beneficio = '';

  return [
    'dataInicio' => $dataInicio,
    'dataFim'    => $dataFim,
    'categoria'  => $categoria,
    'status'     => $status,
    'beneficio'  => $beneficio,
  ];
}

function build_where_types(array $f, array &$params): string
{
  $w = " WHERE 1=1 ";

  if ($f['categoria'] !== '') {
    $w .= " AND t.categoria = :categoria ";
    $params[':categoria'] = $f['categoria'];
  }
  if ($f['status'] !== '') {
    $w .= " AND t.status = :status ";
    $params[':status'] = $f['status'];
  }
  if ($f['beneficio'] !== '') {
    $w .= " AND t.id = :beneficio ";
    $params[':beneficio'] = (int)$f['beneficio'];
  }

  return $w;
}

function build_where_entregas(array $f, array &$params): string
{
  // sempre considera somente entregas "sim" (se NULL, trata como sim)
  $w = " WHERE (LOWER(COALESCE(e.entregue,'sim')) = 'sim') ";

  if ($f['dataInicio'] !== '') {
    $w .= " AND e.data_entrega >= :di ";
    $params[':di'] = $f['dataInicio'];
  }
  if ($f['dataFim'] !== '') {
    $w .= " AND e.data_entrega <= :df ";
    $params[':df'] = $f['dataFim'];
  }

  return $w;
}

function filtros_texto(PDO $pdo, array $f): string
{
  $parts = [];

  if ($f['dataInicio'] !== '' || $f['dataFim'] !== '') {
    $di = $f['dataInicio'] ? date('d/m/Y', strtotime($f['dataInicio'])) : '—';
    $df = $f['dataFim'] ? date('d/m/Y', strtotime($f['dataFim'])) : '—';
    $parts[] = "Período: {$di} até {$df}";
  }
  if ($f['categoria'] !== '') $parts[] = "Categoria: {$f['categoria']}";
  if ($f['status'] !== '') $parts[] = "Status: {$f['status']}";
  if ($f['beneficio'] !== '') {
    $st = $pdo->prepare("SELECT nome FROM ajudas_tipos WHERE id = :id LIMIT 1");
    $st->execute([':id' => (int)$f['beneficio']]);
    $nome = (string)($st->fetchColumn() ?: $f['beneficio']);
    $parts[] = "Benefício: {$nome}";
  }

  return $parts ? implode(' • ', $parts) : 'Sem filtros';
}

/* =========================
   AJAX (retorna JSON com dados reais)
========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  header('Content-Type: application/json; charset=UTF-8');

  $f = filters_from_request();

  // KPIs de catálogo (gerais)
  $kpiAtivos = (int)$pdo->query("SELECT COUNT(*) FROM ajudas_tipos WHERE status='Ativa'")->fetchColumn();
  $kpiTipos  = (int)$pdo->query("SELECT COUNT(*) FROM ajudas_tipos")->fetchColumn();

  // filtros
  $paramsT = [];
  $paramsE = [];
  $whereT  = build_where_types($f, $paramsT);
  $whereE  = build_where_entregas($f, $paramsE);

  // KPIs do período (filtrado)
  $sqlKpi = "
    SELECT
      COUNT(e.id) AS entregas,
      COALESCE(SUM(e.quantidade),0) AS qtd_total,
      COALESCE(SUM(e.quantidade * COALESCE(e.valor_aplicado, t.valor_padrao, 0)),0) AS valor_total
    FROM ajudas_entregas e
    INNER JOIN ajudas_tipos t ON t.id = e.ajuda_tipo_id
    {$whereE}
      AND t.id IN (SELECT t2.id FROM ajudas_tipos t2 {$whereT})
  ";
  $stK = $pdo->prepare($sqlKpi);
  $stK->execute($paramsE + $paramsT);
  $k = $stK->fetch() ?: ['entregas' => 0, 'qtd_total' => 0, 'valor_total' => 0];

  // Gráfico: entregas por benefício (quantidade)
  $sqlBenef = "
    SELECT t.nome AS label, COALESCE(SUM(e.quantidade),0) AS v
    FROM ajudas_entregas e
    INNER JOIN ajudas_tipos t ON t.id = e.ajuda_tipo_id
    {$whereE}
      AND t.id IN (SELECT t2.id FROM ajudas_tipos t2 {$whereT})
    GROUP BY t.nome
    ORDER BY v DESC, t.nome ASC
  ";
  $stB = $pdo->prepare($sqlBenef);
  $stB->execute($paramsE + $paramsT);
  $rowsB = $stB->fetchAll();

  // Gráfico: participação por categoria (quantidade)
  $sqlCat = "
    SELECT COALESCE(NULLIF(TRIM(t.categoria),''),'Sem categoria') AS label,
           COALESCE(SUM(e.quantidade),0) AS v
    FROM ajudas_entregas e
    INNER JOIN ajudas_tipos t ON t.id = e.ajuda_tipo_id
    {$whereE}
      AND t.id IN (SELECT t2.id FROM ajudas_tipos t2 {$whereT})
    GROUP BY label
    ORDER BY v DESC, label ASC
  ";
  $stC = $pdo->prepare($sqlCat);
  $stC->execute($paramsE + $paramsT);
  $rowsC = $stC->fetchAll();

  // Gráfico: entregas por data (quantidade)
  $sqlD = "
    SELECT e.data_entrega AS d, COALESCE(SUM(e.quantidade),0) AS v
    FROM ajudas_entregas e
    INNER JOIN ajudas_tipos t ON t.id = e.ajuda_tipo_id
    {$whereE}
      AND t.id IN (SELECT t2.id FROM ajudas_tipos t2 {$whereT})
    GROUP BY e.data_entrega
    ORDER BY e.data_entrega ASC
  ";
  $stD = $pdo->prepare($sqlD);
  $stD->execute($paramsE + $paramsT);
  $rowsD = $stD->fetchAll();

  // Tabela: catálogo + uso no período (LEFT JOIN para aparecer mesmo sem entrega)
  $on = " AND (LOWER(COALESCE(e.entregue,'sim'))='sim') ";
  $paramsON = [];
  if ($f['dataInicio'] !== '') {
    $on .= " AND e.data_entrega >= :ondi ";
    $paramsON[':ondi'] = $f['dataInicio'];
  }
  if ($f['dataFim'] !== '') {
    $on .= " AND e.data_entrega <= :ondf ";
    $paramsON[':ondf'] = $f['dataFim'];
  }

  $sqlTable = "
    SELECT
      t.id,
      t.nome,
      COALESCE(t.categoria,'') AS categoria,
      COALESCE(t.periodicidade,'') AS periodicidade,
      COALESCE(t.valor_padrao,0) AS valor_padrao,
      t.status,
      COALESCE(SUM(e.quantidade),0) AS qtd_entregue,
      COALESCE(SUM(e.quantidade * COALESCE(e.valor_aplicado, t.valor_padrao, 0)),0) AS valor_total
    FROM ajudas_tipos t
    LEFT JOIN ajudas_entregas e
      ON e.ajuda_tipo_id = t.id
      {$on}
    {$whereT}
    GROUP BY t.id, t.nome, t.categoria, t.periodicidade, t.valor_padrao, t.status
    ORDER BY t.nome ASC
  ";
  $stT = $pdo->prepare($sqlTable);
  $stT->execute($paramsT + $paramsON);
  $table = $stT->fetchAll() ?: [];

  echo json_encode([
    'ok' => true,
    'kpis' => [
      'ativos'     => $kpiAtivos,
      'tipos'      => $kpiTipos,
      'entregas'   => (int)($k['entregas'] ?? 0),
      'qtd_total'  => (int)($k['qtd_total'] ?? 0),
      'valor_total' => (float)($k['valor_total'] ?? 0),
    ],
    'charts' => [
      'benef' => [
        'labels' => array_map(fn($r) => (string)$r['label'], $rowsB),
        'values' => array_map(fn($r) => (int)$r['v'], $rowsB),
      ],
      'cat' => [
        'labels' => array_map(fn($r) => (string)$r['label'], $rowsC),
        'values' => array_map(fn($r) => (int)$r['v'], $rowsC),
      ],
      'data' => [
        'labels' => array_map(fn($r) => (string)$r['d'], $rowsD),
        'values' => array_map(fn($r) => (int)$r['v'], $rowsD),
      ],
    ],
    'table' => $table,
    'filtros_texto' => filtros_texto($pdo, $f),
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

/* =========================
   EXPORT EXCEL (HTML para Excel)
   - Gerado em vem do DISPOSITIVO via gen_txt
   - Exporta SOMENTE o que está filtrado
========================= */
if (isset($_GET['export']) && $_GET['export'] === '1') {
  $f = filters_from_request();

  // "Gerado em" (do dispositivo)
  $genTxt = trim((string)($_GET['gen_txt'] ?? ''));
  if ($genTxt === '') $genTxt = (new DateTime('now'))->format('d/m/Y H:i');

  $fTxt = filtros_texto($pdo, $f);

  // KPIs catálogo (gerais)
  $kpiAtivos = (int)$pdo->query("SELECT COUNT(*) FROM ajudas_tipos WHERE status='Ativa'")->fetchColumn();
  $kpiTipos  = (int)$pdo->query("SELECT COUNT(*) FROM ajudas_tipos")->fetchColumn();

  // filtro tipos
  $paramsT = [];
  $whereT  = build_where_types($f, $paramsT);

  // left join com filtros de data no ON
  $on = " AND (LOWER(COALESCE(e.entregue,'sim'))='sim') ";
  $paramsON = [];
  if ($f['dataInicio'] !== '') {
    $on .= " AND e.data_entrega >= :ondi ";
    $paramsON[':ondi'] = $f['dataInicio'];
  }
  if ($f['dataFim'] !== '') {
    $on .= " AND e.data_entrega <= :ondf ";
    $paramsON[':ondf'] = $f['dataFim'];
  }

  $sqlTable = "
    SELECT
      t.nome,
      COALESCE(t.categoria,'') AS categoria,
      COALESCE(t.periodicidade,'') AS periodicidade,
      COALESCE(t.valor_padrao,0) AS valor_padrao,
      t.status,
      COALESCE(SUM(e.quantidade),0) AS qtd_entregue,
      COALESCE(SUM(e.quantidade * COALESCE(e.valor_aplicado, t.valor_padrao, 0)),0) AS valor_total
    FROM ajudas_tipos t
    LEFT JOIN ajudas_entregas e
      ON e.ajuda_tipo_id = t.id
      {$on}
    {$whereT}
    GROUP BY t.id, t.nome, t.categoria, t.periodicidade, t.valor_padrao, t.status
    ORDER BY t.nome ASC
  ";
  $st = $pdo->prepare($sqlTable);
  $st->execute($paramsT + $paramsON);
  $rows = $st->fetchAll() ?: [];

  $entregasTotal = 0;
  $valorTotal = 0.0;
  foreach ($rows as $r) {
    $entregasTotal += (int)($r['qtd_entregue'] ?? 0);
    $valorTotal += (float)($r['valor_total'] ?? 0);
  }

  $filename = 'relatorio_beneficios_' . date('Ymd_His') . '.xls';
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Pragma: no-cache');
  header('Expires: 0');

  echo "\xEF\xBB\xBF"; // BOM UTF-8
?>
  <html>

  <head>
    <meta charset="utf-8">
    <style>
      body {
        font-family: Calibri, Arial, sans-serif;
      }

      table {
        border-collapse: collapse;
      }

      th,
      td {
        border: 1px solid #222;
        padding: 6px 8px;
      }

      .title {
        font-size: 20px;
        font-weight: 700;
        text-align: center;
        background-color: #F2F4F7;
        color: #000000;
      }

      .meta {
        font-size: 14px;
        font-weight: 700;
        text-align: left;
        background-color: #FFFFFF;
        color: #000000;
      }

      .header-cell {
        background-color: #F2F4F7;
        color: #000000;
        font-size: 13px;
        font-weight: 700;
        text-align: center;
      }

      .cell-text {
        font-size: 12px;
      }

      .left {
        text-align: left;
      }

      .right {
        text-align: right;
      }

      .center {
        text-align: center;
      }

      td.center, th.center, .cell-center {
        text-align: center;
      }

      td.left, th.left, .cell-left {
        text-align: left;
      }

      .nowrap {
        white-space: nowrap;
      }
  

    @media (max-width: 991.98px) {
      .relatorio-table-head {
        flex-direction: column;
        align-items: stretch;
      }

      .dt-search-wrap {
        width: 100%;
        margin-left: 0;
      }

      .dt-search-input {
        min-width: 0;
        max-width: 100%;
        flex: 1 1 auto;
      }

      .custom-pagination-bar {
        flex-direction: column;
        align-items: stretch;
      }

      .custom-pagination-left,
      .custom-pagination-center,
      .custom-pagination-right {
        width: 100%;
        justify-content: center;
      }
    }

  </style>
  </head>

  <body>
    <table>
      <colgroup>
        <col style="width:320px">
        <col style="width:160px">
        <col style="width:120px">
        <col style="width:130px">
        <col style="width:90px">
        <col style="width:120px">
        <col style="width:140px">
      </colgroup>

      <tr>
        <th class="title" colspan="7" style="background-color:#F2F4F7;color:#000000;text-align:center;font-size:20px;font-weight:700;">Relatório de Benefícios — ANEXO</th>
      </tr>

      <tr>
        <th class="meta" colspan="7" style="text-align:left;font-size:14px;font-weight:700;">
          Gerado em: <?= h($genTxt) ?> &nbsp;&nbsp;&nbsp; Filtros: <?= h($fTxt) ?>
        </th>
      </tr>

      <tr>
        <th class="meta" colspan="7" style="text-align:left;font-size:14px;font-weight:700;">
          Benefícios ativos: <?= (int)$kpiAtivos ?> &nbsp;&nbsp; Tipos de benefícios: <?= (int)$kpiTipos ?>
          &nbsp;&nbsp; Quantidade entregue (no período): <?= (int)$entregasTotal ?>
          &nbsp;&nbsp; Valor total (no período): <?= h(money_br((float)$valorTotal)) ?>
        </th>
      </tr>

      <tr>
        <th class="nowrap header-cell" style="background-color:#F2F4F7;color:#000000;text-align:center;font-size:13px;font-weight:700;">Nome</th>
        <th class="nowrap header-cell" style="background-color:#F2F4F7;color:#000000;text-align:center;font-size:13px;font-weight:700;">Categoria</th>
        <th class="nowrap header-cell" style="background-color:#F2F4F7;color:#000000;text-align:center;font-size:13px;font-weight:700;">Periodicidade</th>
        <th class="nowrap header-cell" style="background-color:#F2F4F7;color:#000000;text-align:center;font-size:13px;font-weight:700;">Valor Padrão</th>
        <th class="nowrap header-cell" style="background-color:#F2F4F7;color:#000000;text-align:center;font-size:13px;font-weight:700;">Status</th>
        <th class="nowrap header-cell" style="background-color:#F2F4F7;color:#000000;text-align:center;font-size:13px;font-weight:700;">Qtd Entregue</th>
        <th class="nowrap header-cell" style="background-color:#F2F4F7;color:#000000;text-align:center;font-size:13px;font-weight:700;">Valor Total</th>
      </tr>

      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="cell-text left" style="text-align:left;"><?= h((string)$r['nome']) ?></td>
          <td class="cell-text center" style="text-align:center;"><?= h((string)$r['categoria']) ?></td>
          <td class="cell-text center" style="text-align:center;"><?= h((string)$r['periodicidade']) ?></td>
          <td class="cell-text center" style="text-align:center;mso-number-format:'\@';"><?= h(money_br((float)$r['valor_padrao'])) ?></td>
          <td class="cell-text center" style="text-align:center;"><?= h((string)$r['status']) ?></td>
          <td class="cell-text center" style="text-align:center;"><?= (int)$r['qtd_entregue'] ?></td>
          <td class="cell-text center" style="text-align:center;mso-number-format:'\@';"><?= h(money_br((float)$r['valor_total'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </body>

  </html>
<?php
  exit;
}

/* =========================
   Listas (selects) – carregadas no HTML
========================= */
$cats = $pdo->query("
  SELECT DISTINCT categoria
  FROM ajudas_tipos
  WHERE categoria IS NOT NULL AND TRIM(categoria) <> ''
  ORDER BY categoria ASC
")->fetchAll();

$benefs = $pdo->query("
  SELECT id, nome
  FROM ajudas_tipos
  ORDER BY nome ASC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <title>Relatório de Benefícios — ANEXO</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Estilos do projeto -->
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
  <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
  <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../dist/assets/css/app.css">
  <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

  <style>
    :root {
      --gap-xs: .5rem;
      --gap-sm: .75rem;
      --gap-md: 1rem;
      --gap-lg: 1.25rem;
      --card-radius: 14px
    }

    body {
      font-family: 'Nunito', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif
    }

    .card {
      border: 0;
      border-radius: var(--card-radius);
      box-shadow: 0 1px 2px rgba(16, 24, 40, .06), 0 1px 3px rgba(16, 24, 40, .1)
    }

    .card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: var(--gap-sm);
      flex-wrap: wrap
    }

    .card-header .actions {
      display: flex;
      gap: var(--gap-xs);
      flex-wrap: wrap
    }

    .stat .label {
      color: #667085;
      font-size: .85rem
    }

    .stat .value {
      font-size: 1.6rem;
      font-weight: 700;
      line-height: 1.1
    }

    .table thead th {
      white-space: nowrap
    }

    .truncate {
      max-width: 260px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap
    }

    .chart-wrap {
      height: 320px
    }

    .chart-wrap canvas {
      height: 100% !important;
      width: 100% !important
    }

    .legend-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: .5rem .75rem;
      margin-top: .75rem
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: .5rem;
      font-size: .925rem
    }

    .legend-swatch {
      width: 12px;
      height: 12px;
      border-radius: 3px;
      flex: 0 0 12px
    }

    .legend-label {
      flex: 1 1 auto;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap
    }

    .legend-value {
      font-weight: 600
    }

    .row.g-3 {
      --bs-gutter-x: var(--gap-md);
      --bs-gutter-y: var(--gap-md)
    }

    .row.g-4 {
      --bs-gutter-x: var(--gap-lg);
      --bs-gutter-y: var(--gap-lg)
    }

    .section {
      margin-bottom: var(--gap-lg)
    }

    .col-12>.card+.card {
      margin-top: var(--gap-md)
    }

    .table-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      flex-wrap: wrap;
      margin-top: .75rem
    }

    .table-footer .btn {
      white-space: nowrap
    }

    .table-footer .page-info {
      font-weight: 600;
      color: #475467
    }

    .table-footer .perpage {
      display: flex;
      align-items: center;
      gap: .5rem;
      white-space: nowrap
    }


    /* ===== PADRÃO CLEAN DA TABELA / ORDENAÇÃO ===== */
    .relatorio-table-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 1.15rem;
    }

    .relatorio-table-title {
      color: #25396f;
      font-size: 1.05rem;
      font-weight: 800;
      margin: 0;
    }

    .relatorio-table-subtitle {
      color: #7c8db5;
      margin: .3rem 0 0;
      font-size: .95rem;
    }

    .dt-search-wrap {
      display: inline-flex;
      align-items: center;
      justify-content: flex-end;
      gap: .5rem;
      flex-wrap: nowrap;
      margin-left: auto;
    }

    .dt-search-input {
      width: 100%;
      min-width: 330px;
      max-width: 460px;
      height: 38px;
      padding: .55rem .9rem;
      border: 1px solid #b8c7f7;
      border-radius: 4px;
      background: #fff;
      color: #495057;
      font-size: 14px;
      box-shadow: none;
      outline: none;
      transition: border-color .2s ease, box-shadow .2s ease;
    }

    .dt-search-input::placeholder {
      color: #7f8a99;
      opacity: 1;
    }

    .dt-search-input:focus {
      border-color: #9ab0f5;
      box-shadow: 0 0 0 .12rem rgba(67, 94, 190, .12);
    }

    .dt-search-clear {
      width: 38px;
      height: 38px;
      min-width: 38px;
      border: 1px solid #cfd6df;
      border-radius: 4px;
      background: #fff;
      color: #495057;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all .2s ease;
      padding: 0;
    }

    .dt-search-clear i {
      font-size: 13px;
      font-weight: 700;
      line-height: 1;
    }

    .dt-search-clear:hover {
      border-color: #435ebe;
      color: #435ebe;
      background: #f8f9ff;
    }

    #table1.beneficios-table {
      width: 100% !important;
      border-collapse: separate;
      border-spacing: 0;
      color: #526a80;
      white-space: nowrap;
    }

    #table1.beneficios-table thead th {
      position: relative;
      background: #fff !important;
      color: #2f3f4f;
      font-weight: 800;
      font-size: .94rem;
      text-align: center;
      vertical-align: middle;
      white-space: nowrap;
      padding: .9rem 2.05rem .9rem .75rem;
      border-top: 0 !important;
      border-left: 0 !important;
      border-right: 0 !important;
      border-bottom: 2px solid #dbe1ea !important;
    }

    #table1.beneficios-table thead th.sortable-th {
      cursor: pointer;
      user-select: none;
    }

    #table1.beneficios-table thead th.sortable-th:hover {
      color: #25396f;
    }

    .sort-prisma {
      position: absolute;
      right: .78rem;
      top: 50%;
      width: 10px;
      height: 16px;
      transform: translateY(-50%);
      pointer-events: none;
    }

    .sort-prisma::before,
    .sort-prisma::after {
      content: "";
      position: absolute;
      left: 1px;
      width: 0;
      height: 0;
      border-left: 4px solid transparent;
      border-right: 4px solid transparent;
    }

    .sort-prisma::before {
      top: 0;
      border-bottom: 7px solid #e3e7ec;
    }

    .sort-prisma::after {
      bottom: 0;
      border-top: 7px solid #e3e7ec;
    }

    #table1.beneficios-table thead th.sort-asc .sort-prisma::before {
      border-bottom-color: #9aa4b2;
    }

    #table1.beneficios-table thead th.sort-desc .sort-prisma::after {
      border-top-color: #9aa4b2;
    }

    #table1.beneficios-table tbody td {
      padding: .78rem .75rem;
      vertical-align: middle;
      border-top: 0 !important;
      border-left: 0 !important;
      border-right: 0 !important;
      border-bottom: 1px solid #e2e6ea !important;
      color: #526a80;
      font-size: .96rem;
      background: #fff;
    }

    #table1.beneficios-table tbody tr:nth-child(even) td {
      background: #f6f7f9;
    }

    #table1.beneficios-table tbody tr:hover td {
      background: #eef1f5;
    }

    #table1.beneficios-table .td-nome {
      text-align: left;
      font-weight: 700;
      color: #3f5870;
    }

    #table1.beneficios-table .td-center {
      text-align: center;
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 62px;
      padding: .22rem .58rem;
      border-radius: 999px;
      font-size: .78rem;
      font-weight: 800;
      line-height: 1.2;
      border: 1px solid transparent;
    }

    .status-pill.status-active {
      background: #108757;
      color: #fff;
      border-color: #108757;
    }

    .status-pill.status-muted {
      background: #f2f4f7;
      color: #667085;
      border-color: #d9dee7;
    }

    .custom-pagination-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #eef1f4;
      flex-wrap: wrap;
    }

    .custom-pagination-left,
    .custom-pagination-center,
    .custom-pagination-right {
      display: flex;
      align-items: center;
      gap: .75rem;
    }

    .custom-pagination-center {
      flex: 1;
      justify-content: center;
    }

    .custom-page-info {
      font-size: 1.05rem;
      font-weight: 800;
      color: #435ebe;
      white-space: nowrap;
    }

    .custom-length-label {
      color: #6c757d;
      font-weight: 600;
      margin-bottom: 0;
      white-space: nowrap;
    }

    .custom-length-select {
      min-width: 72px;
      padding: .45rem 2rem .45rem .75rem;
      border: 1px solid #d0d7de;
      border-radius: 6px;
      background-color: #fff;
      color: #495057;
      font-weight: 600;
      outline: none;
    }

    .custom-length-select:focus {
      border-color: #435ebe;
      box-shadow: 0 0 0 .15rem rgba(67, 94, 190, .15);
    }

    @media (max-width:576.98px) {
      .page-title h3 {
        font-size: 1.25rem
      }

      .text-subtitle {
        font-size: .9rem
      }

      .kpi-col {
        flex: 0 0 100%;
        max-width: 100%
      }

      .stat .value {
        font-size: 1.4rem
      }

      .card-header .actions .btn {
        flex: 1 1 100%
      }

      .chart-wrap {
        height: 220px
      }

      .table td,
      .table th {
        padding: .625rem .5rem
      }

      .table-responsive-md {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch
      }

      .section .row {
        row-gap: var(--gap-md)
      }
    }
  </style>
</head>

<body>
  <div id="app">
    <!-- ===== SIDEBAR ===== -->
    <div id="sidebar" class="active">
      <div class="sidebar-wrapper active">
        <div class="sidebar-header">
          <div class="d-flex justify-content-between">
            <div class="logo"><a href="#"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo"></a></div>
            <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a></div>
          </div>
        </div>

        <!-- MENU -->
        <div class="sidebar-menu">
          <ul class="menu">
            <li class="sidebar-item"><a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a></li>

            <!-- ENTREGAS DE BENEFÍCIOS -->
            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link">
                <i class="bi bi-hand-thumbs-up-fill"></i>
                <span>Entregas</span>
              </a>
              <ul class="submenu">
                <li class="submenu-item">
                  <a href="entregasRealizadas.php">Histórico de Entregas</a>
                </li>
              </ul>
            </li>

            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link"><i class="bi bi-geo-alt-fill"></i><span>Bairros</span></a>
              <ul class="submenu">
                <li class="submenu-item"><a href="bairrosCadastrados.php">Bairros Cadastrados</a></li>
                <li class="submenu-item"><a href="cadastrarBairro.php">Cadastrar Bairro</a></li>
              </ul>
            </li>

            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link"><i class="bi bi-house-fill"></i><span>Beneficiarios</span></a>
              <ul class="submenu">
                <li class="submenu-item"><a href="beneficiariosBolsaFamilia.php">Bolsa Família</a></li>
                <li class="submenu-item"><a href="beneficiariosEstadual.php">Estadual</a></li>
                <li class="submenu-item"><a href="beneficiariosMunicipal.php">Municipal</a></li>
                <li class="submenu-item"><a href="beneficiariosSemas.php">ANEXO</a></li>
              </ul>
            </li>

            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link"><i class="bi bi-hand-thumbs-up-fill"></i><span>Ajuda Social</span></a>
              <ul class="submenu">
                <li class="submenu-item"><a href="cadastrarBeneficio.php">Cadastrar Benefício</a></li>
                <li class="submenu-item"><a href="beneficiosCadastrados.php">Benefícios Cadastrados</a></li>
              </ul>
            </li>

            <li class="sidebar-item has-sub active">
              <a href="#" class="sidebar-link"><i class="bi bi-bar-chart-line-fill"></i><span>Relatórios</span></a>
              <ul class="submenu active">
                <li class="submenu-item"><a href="relatoriosCadastros.php">Cadastros</a></li>
                <li class="submenu-item"><a href="relatorioAtendimentos.php">Atendimentos</a></li>
                <li class="submenu-item active"><a href="relatorioBeneficios.php">Benefícios</a></li>
              </ul>
            </li>

            <!-- CONTROLE DE VALORES -->
            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link">
                <i class="bi bi-cash-stack"></i>
                <span>Controle Financeiro</span>
              </a>
              <ul class="submenu">
                <li class="submenu-item">
                  <a href="valoresAplicados.php">Valores Aplicados</a>
                </li>
                <li class="submenu-item">
                  <a href="beneficiosAcimaMil.php">Acima de R$ 1.000</a>
                </li>
              </ul>
            </li>

            <!-- 🔒 USUÁRIOS (ÚNICO COM CONTROLE DE PERFIL) -->
            <?php if (($_SESSION['user_role'] ?? '') === 'suporte'): ?>
              <li class="sidebar-item has-sub">
                <a href="#" class="sidebar-link">
                  <i class="bi bi-people-fill"></i>
                  <span>Usuários</span>
                </a>
                <ul class="submenu">
                  <li class="submenu-item">
                    <a href="usuariosPermitidos.php">Permitidos</a>
                  </li>
                  <li class="submenu-item">
                    <a href="usuariosNaoPermitidos.php">Não Permitidos</a>
                  </li>
                </ul>
              </li>
            <?php endif; ?>

            <!-- AUDITORIA / LOG -->
            <li class="sidebar-item">
              <a href="auditoria.php" class="sidebar-link">
                <i class="bi bi-shield-lock-fill"></i>
                <span>Auditoria</span>
              </a>
            </li>

            <li class="sidebar-item"><a href="./auth/logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a></li>
          </ul>
        </div>
        <!-- /MENU -->
      </div>
    </div>
    <!-- ===== /SIDEBAR ===== -->

    <!-- ===== MAIN ===== -->
    <div id="main" class="d-flex flex-column min-vh-100">
      <header class="mb-3">
        <a href="#" class="burger-btn d-block d-xl-none" aria-label="Alternar menu"><i class="bi bi-justify fs-3"></i></a>
      </header>

      <div class="page-heading">
        <div class="page-title">
          <div class="row align-items-end g-3">
            <div class="col-12 col-md-6 order-md-1 order-last">
              <h3>Relatório de Benefícios</h3>
              <p class="text-subtitle text-muted mb-0">Catálogo e uso por período / categoria / status</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
              <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                <ol class="breadcrumb mb-0">
                  <li class="breadcrumb-item"><a href="#">Relatórios</a></li>
                  <li class="breadcrumb-item active" aria-current="page">Benefícios</li>
                </ol>
              </nav>
            </div>
          </div>
        </div>

        <!-- Filtros -->
        <section class="section">
          <div class="card">
            <div class="card-header">
              <span class="fw-semibold">Filtros</span>
              <div class="actions">
                <button type="button" id="btnReset" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-counterclockwise me-1"></i> Limpar filtros
                </button>
                <button type="button" id="btnExportXLS" class="btn btn-primary">
                  <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
                </button>
              </div>
            </div>
            <div class="card-body pt-2">
              <div class="small text-muted mb-2" id="txtFiltros">Filtros: —</div>

              <form id="filters" class="row g-3 g-sm-3 g-md-4">
                <div class="col-12 col-sm-6 col-md-4">
                  <label class="form-label" for="dataInicio">Data inicial</label>
                  <input type="date" id="dataInicio" class="form-control" inputmode="numeric">
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                  <label class="form-label" for="dataFim">Data final</label>
                  <input type="date" id="dataFim" class="form-control" inputmode="numeric">
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                  <label class="form-label" for="categoria">Categoria</label>
                  <select id="categoria" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($cats as $c): ?>
                      <option value="<?= h((string)$c['categoria']) ?>"><?= h((string)$c['categoria']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12 col-sm-6 col-md-6">
                  <label class="form-label" for="status">Status</label>
                  <select id="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="Ativa">Ativa</option>
                    <option value="Inativa">Inativa</option>
                  </select>
                </div>

                <div class="col-12 col-sm-6 col-md-6">
                  <label class="form-label" for="beneficio">Benefício</label>
                  <select id="beneficio" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($benefs as $b): ?>
                      <option value="<?= (int)$b['id'] ?>"><?= h((string)$b['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

              </form>
            </div>
          </div>
        </section>

        <!-- KPIs -->
        <section class="section">
          <div class="row g-3">
            <div class="col-12 col-md-3 kpi-col">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Benefícios ativos</div>
                  <div id="kpiAtivos" class="value">0</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-md-3 kpi-col">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Tipos de benefícios</div>
                  <div id="kpiTipos" class="value">0</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-md-3 kpi-col">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Entregas no período</div>
                  <div id="kpiEntregas" class="value">0</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-md-3 kpi-col">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Valor total no período</div>
                  <div id="kpiValor" class="value">R$ 0,00</div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Gráficos -->
        <section class="section">
          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <div class="card h-100">
                <div class="card-header fw-semibold">Entregas por benefício (quantidade)</div>
                <div class="card-body">
                  <div class="chart-wrap"><canvas id="chartBarBenef"></canvas></div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-6">
              <div class="card h-100">
                <div class="card-header fw-semibold">Participação por categoria (quantidade)</div>
                <div class="card-body">
                  <div class="chart-wrap"><canvas id="chartDonutCat"></canvas></div>
                  <div id="legendCategorias" class="legend-grid"></div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="card h-100">
                <div class="card-header fw-semibold">Entregas por data (quantidade)</div>
                <div class="card-body">
                  <div class="chart-wrap"><canvas id="chartLinhaData"></canvas></div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Tabela + paginação -->
        <section class="section mb-4">
          <div class="card">
            <div class="card-body">
              <div class="relatorio-table-head">
                <div>
                  <h5 class="relatorio-table-title">Catálogo de Benefícios (com uso no período)</h5>
                  <p class="relatorio-table-subtitle" id="tableRowsLabel">0 registros encontrados</p>
                </div>

                <div class="dt-search-wrap">
                  <input id="qTable" class="dt-search-input" placeholder="Buscar por nome/categoria/periodicidade/status..." autocomplete="off">
                  <button class="dt-search-clear" type="button" id="btnClearTable" title="Limpar pesquisa" aria-label="Limpar pesquisa">
                    <i class="bi bi-x-circle"></i>
                  </button>
                </div>
              </div>

              <div class="table-responsive-md">
                <table id="table1" class="table align-middle w-100 text-nowrap beneficios-table">
                  <thead>
                    <tr>
                      <th class="sortable-th sort-asc" data-sort-key="nome" data-sort-type="text" tabindex="0" role="button" aria-label="Ordenar por Nome"><span>Nome</span><span class="sort-prisma" aria-hidden="true"></span></th>
                      <th class="sortable-th" data-sort-key="categoria" data-sort-type="text" tabindex="0" role="button" aria-label="Ordenar por Categoria"><span>Categoria</span><span class="sort-prisma" aria-hidden="true"></span></th>
                      <th class="sortable-th" data-sort-key="periodicidade" data-sort-type="text" tabindex="0" role="button" aria-label="Ordenar por Periodicidade"><span>Periodicidade</span><span class="sort-prisma" aria-hidden="true"></span></th>
                      <th class="sortable-th" data-sort-key="valor_padrao" data-sort-type="number" tabindex="0" role="button" aria-label="Ordenar por Valor Padrão"><span>Valor Padrão</span><span class="sort-prisma" aria-hidden="true"></span></th>
                      <th class="sortable-th" data-sort-key="status" data-sort-type="text" tabindex="0" role="button" aria-label="Ordenar por Status"><span>Status</span><span class="sort-prisma" aria-hidden="true"></span></th>
                      <th class="sortable-th" data-sort-key="qtd_entregue" data-sort-type="number" tabindex="0" role="button" aria-label="Ordenar por Quantidade Entregue"><span>Qtd Entregue</span><span class="sort-prisma" aria-hidden="true"></span></th>
                      <th class="sortable-th" data-sort-key="valor_total" data-sort-type="number" tabindex="0" role="button" aria-label="Ordenar por Valor Total"><span>Valor Total</span><span class="sort-prisma" aria-hidden="true"></span></th>
                    </tr>
                  </thead>
                  <tbody><!-- via JS --></tbody>
                </table>
              </div>

              <div class="custom-pagination-bar">
                <div class="custom-pagination-left">
                  <button class="btn btn-outline-secondary btn-sm" id="btnPrev">Anterior</button>
                  <button class="btn btn-outline-secondary btn-sm" id="btnNext">Próxima</button>
                </div>

                <div class="custom-pagination-center">
                  <span class="custom-page-info" id="pageInfo">Página 1 de 1</span>
                </div>

                <div class="custom-pagination-right">
                  <label for="perPage" class="custom-length-label">por página</label>
                  <select id="perPage" class="form-select form-select-sm custom-length-select" style="width:auto">
                    <option value="6">6</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <footer>
        <div class="footer clearfix mb-0 text-muted">
          <div class="float-start text-black">
            <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de Coari-AM.</b></p>
            <script>
              document.getElementById('current-year').textContent = new Date().getFullYear();
            </script>
          </div>
          <div class="float-end text-black">
            <p>Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p>
          </div>
        </div>
      </footer>
    </div>
    <!-- ===== /MAIN ===== -->
  </div>

  <!-- Vendors base -->
  <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
  <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
  <script src="../dist/assets/js/main.js"></script>

  <!-- Chart.js (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const money = n => 'R$ ' + (Number(n || 0)).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      const fmtDateBR = s => {
        if (!s) return '';
        const d = new Date(String(s) + 'T00:00:00');
        return Number.isNaN(d.getTime()) ? String(s) : d.toLocaleDateString('pt-BR');
      };

      const normalizeText = value => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

      const els = {
        dataInicio: document.getElementById('dataInicio'),
        dataFim: document.getElementById('dataFim'),
        categoria: document.getElementById('categoria'),
        status: document.getElementById('status'),
        beneficio: document.getElementById('beneficio'),
        btnReset: document.getElementById('btnReset'),
        btnExportXLS: document.getElementById('btnExportXLS'),
        txtFiltros: document.getElementById('txtFiltros'),

        kpiAtivos: document.getElementById('kpiAtivos'),
        kpiTipos: document.getElementById('kpiTipos'),
        kpiEntregas: document.getElementById('kpiEntregas'),
        kpiValor: document.getElementById('kpiValor'),

        tbody: document.querySelector('#table1 tbody'),
        qTable: document.getElementById('qTable'),
        btnClearTable: document.getElementById('btnClearTable'),
        tableRowsLabel: document.getElementById('tableRowsLabel'),
        btnPrev: document.getElementById('btnPrev'),
        btnNext: document.getElementById('btnNext'),
        pageInfo: document.getElementById('pageInfo'),
        perPage: document.getElementById('perPage'),
      };

      const sortableHeaders = Array.from(document.querySelectorAll('#table1 thead th[data-sort-key]'));

      const palette = n => Array.from({
        length: Math.max(0, n)
      }, (_, i) => `hsl(${Math.round((360 / Math.max(1, n)) * i)} 70% 55%)`);

      let chartBarBenef = null;
      let chartDonutCat = null;
      let chartLinhaData = null;

      const state = {
        data: {
          kpis: {
            ativos: 0,
            tipos: 0,
            entregas: 0,
            valor_total: 0
          },
          charts: {
            benef: { labels: [], values: [] },
            cat: { labels: [], values: [] },
            data: { labels: [], values: [] }
          },
          table: []
        },
        page: 1,
        perPage: Number(els.perPage.value || 10),
        sortKey: 'nome',
        sortDir: 'asc',
        lastParamsKey: ''
      };

      function buildParams() {
        const p = new URLSearchParams();
        p.set('ajax', '1');

        if (els.dataInicio.value) p.set('dataInicio', els.dataInicio.value);
        if (els.dataFim.value) p.set('dataFim', els.dataFim.value);
        if (els.categoria.value) p.set('categoria', els.categoria.value);
        if (els.status.value) p.set('status', els.status.value);
        if (els.beneficio.value) p.set('beneficio', els.beneficio.value);

        return p;
      }

      async function loadData() {
        const p = buildParams();
        const key = p.toString();
        state.lastParamsKey = key;

        try {
          const resp = await fetch('relatorioBeneficios.php?' + key, {
            cache: 'no-store'
          });
          const json = await resp.json();
          if (!json || !json.ok) throw new Error('Resposta inválida');

          if (state.lastParamsKey !== key) return;

          state.data = json;
          state.page = 1;

          renderAll();
        } catch (e) {
          console.error(e);
          els.tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Não foi possível carregar os dados.</td></tr>';
        }
      }

      function renderKPIs() {
        const k = state.data.kpis || {};
        els.kpiAtivos.textContent = k.ativos ?? 0;
        els.kpiTipos.textContent = k.tipos ?? 0;
        els.kpiEntregas.textContent = k.entregas ?? 0;
        els.kpiValor.textContent = money(k.valor_total ?? 0);
        els.txtFiltros.textContent = 'Filtros: ' + (state.data.filtros_texto || '—');
      }

      function renderLegend(containerId, labels, values, colors) {
        const el = document.getElementById(containerId);
        if (!el) return;
        el.innerHTML = '';

        labels.forEach((lab, i) => {
          const item = document.createElement('div');
          item.className = 'legend-item';

          const swatch = document.createElement('span');
          swatch.className = 'legend-swatch';
          swatch.style.background = colors[i];

          const label = document.createElement('span');
          label.className = 'legend-label';
          label.title = String(lab || '');
          label.textContent = String(lab || '');

          const value = document.createElement('span');
          value.className = 'legend-value';
          value.textContent = String(values[i] ?? 0);

          item.appendChild(swatch);
          item.appendChild(label);
          item.appendChild(value);
          el.appendChild(item);
        });
      }

      function renderCharts() {
        const benefLabels = state.data.charts?.benef?.labels || [];
        const benefValues = state.data.charts?.benef?.values || [];
        const catLabels = state.data.charts?.cat?.labels || [];
        const catValues = state.data.charts?.cat?.values || [];
        const dateLabels = state.data.charts?.data?.labels || [];
        const dateValues = state.data.charts?.data?.values || [];

        if (chartBarBenef) chartBarBenef.destroy();
        if (chartDonutCat) chartDonutCat.destroy();
        if (chartLinhaData) chartLinhaData.destroy();

        chartBarBenef = new Chart(document.getElementById('chartBarBenef'), {
          type: 'bar',
          data: {
            labels: benefLabels,
            datasets: [{
              label: 'Qtd entregue',
              data: benefValues,
              backgroundColor: palette(Math.max(1, benefLabels.length))
            }]
          },
          options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              x: { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0 } },
              y: { beginAtZero: true, ticks: { precision: 0 } }
            }
          }
        });

        const catColors = palette(Math.max(1, catLabels.length));
        chartDonutCat = new Chart(document.getElementById('chartDonutCat'), {
          type: 'doughnut',
          data: {
            labels: catLabels,
            datasets: [{ data: catValues, backgroundColor: catColors }]
          },
          options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            cutout: '55%'
          }
        });
        renderLegend('legendCategorias', catLabels, catValues, catColors);

        chartLinhaData = new Chart(document.getElementById('chartLinhaData'), {
          type: 'line',
          data: {
            labels: dateLabels.map(fmtDateBR),
            datasets: [{
              label: 'Entregas',
              data: dateValues,
              tension: .35,
              fill: true
            }]
          },
          options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              x: { grid: { display: false } },
              y: { beginAtZero: true, ticks: { precision: 0 } }
            }
          }
        });
      }

      function getComparableValue(row, key, type) {
        const value = row?.[key];
        if (type === 'number') return Number(value || 0);
        return normalizeText(value);
      }

      function getFilteredSortedRows() {
        const rows = Array.isArray(state.data.table) ? state.data.table.slice() : [];
        const q = normalizeText(els.qTable.value || '');

        let visible = rows;
        if (q) {
          visible = rows.filter(row => {
            const haystack = normalizeText([
              row.nome,
              row.categoria,
              row.periodicidade,
              row.status,
              money(row.valor_padrao || 0),
              row.qtd_entregue,
              money(row.valor_total || 0)
            ].join(' '));
            return haystack.includes(q);
          });
        }

        const activeHeader = sortableHeaders.find(th => th.dataset.sortKey === state.sortKey);
        const type = activeHeader?.dataset.sortType || 'text';
        const dirFactor = state.sortDir === 'desc' ? -1 : 1;

        visible.sort((a, b) => {
          const av = getComparableValue(a, state.sortKey, type);
          const bv = getComparableValue(b, state.sortKey, type);

          if (type === 'number') return (av - bv) * dirFactor;
          return String(av).localeCompare(String(bv), 'pt-BR', { sensitivity: 'base' }) * dirFactor;
        });

        return visible;
      }

      function getPagedRows() {
        const rows = getFilteredSortedRows();
        const total = rows.length;
        const per = state.perPage;
        const pages = Math.max(1, Math.ceil(total / per));
        state.page = Math.min(Math.max(1, state.page), pages);

        const start = (state.page - 1) * per;
        const end = start + per;
        return { slice: rows.slice(start, end), total, pages };
      }

      function appendCell(tr, text, className = '') {
        const td = document.createElement('td');
        td.className = className;
        td.textContent = text ?? '';
        tr.appendChild(td);
        return td;
      }

      function renderTable() {
        const { slice, total, pages } = getPagedRows();
        els.tbody.innerHTML = '';

        if (total === 0) {
          const tr = document.createElement('tr');
          const td = document.createElement('td');
          td.colSpan = 7;
          td.className = 'text-center text-muted py-4';
          td.textContent = 'Sem dados para os filtros selecionados.';
          tr.appendChild(td);
          els.tbody.appendChild(tr);
          els.tableRowsLabel.textContent = '0 registros encontrados';
          els.pageInfo.textContent = 'Página 1 de 1';
          els.btnPrev.disabled = true;
          els.btnNext.disabled = true;
          updateSortHeaders();
          return;
        }

        for (const r of slice) {
          const tr = document.createElement('tr');

          const nomeCell = appendCell(tr, String(r.nome || ''), 'td-nome');
          nomeCell.title = String(r.nome || '');

          appendCell(tr, String(r.categoria || ''), 'td-center');
          appendCell(tr, String(r.periodicidade || ''), 'td-center');
          appendCell(tr, money(r.valor_padrao || 0), 'td-center');

          const statusCell = document.createElement('td');
          statusCell.className = 'td-center';
          const status = String(r.status || '');
          const pill = document.createElement('span');
          pill.className = status === 'Ativa' ? 'status-pill status-active' : 'status-pill status-muted';
          pill.textContent = status || '—';
          statusCell.appendChild(pill);
          tr.appendChild(statusCell);

          appendCell(tr, String(Number(r.qtd_entregue || 0)), 'td-center');
          appendCell(tr, money(r.valor_total || 0), 'td-center');

          els.tbody.appendChild(tr);
        }

        els.tableRowsLabel.textContent = `${total} registros encontrados`;
        els.pageInfo.textContent = `Página ${state.page} de ${pages}`;
        els.btnPrev.disabled = state.page <= 1;
        els.btnNext.disabled = state.page >= pages;
        updateSortHeaders();
      }

      function updateSortHeaders() {
        sortableHeaders.forEach(th => {
          th.classList.remove('sort-asc', 'sort-desc');
          if (th.dataset.sortKey === state.sortKey) {
            th.classList.add(state.sortDir === 'asc' ? 'sort-asc' : 'sort-desc');
          }
        });
      }

      function renderAll() {
        renderKPIs();
        renderCharts();
        renderTable();
      }

      [els.dataInicio, els.dataFim, els.categoria, els.status, els.beneficio].forEach(el => {
        el.addEventListener('change', loadData);
      });

      els.btnReset.addEventListener('click', () => {
        document.getElementById('filters').reset();
        els.qTable.value = '';
        state.page = 1;
        loadData();
      });

      els.qTable.addEventListener('input', () => {
        state.page = 1;
        renderTable();
      });

      els.btnClearTable.addEventListener('click', () => {
        els.qTable.value = '';
        state.page = 1;
        renderTable();
        els.qTable.focus();
      });

      sortableHeaders.forEach(th => {
        const toggleSort = () => {
          const key = th.dataset.sortKey;
          if (!key) return;

          if (state.sortKey === key) {
            state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
          } else {
            state.sortKey = key;
            state.sortDir = 'asc';
          }

          state.page = 1;
          renderTable();
        };

        th.addEventListener('click', toggleSort);
        th.addEventListener('keydown', event => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleSort();
          }
        });
      });

      els.perPage.addEventListener('change', () => {
        state.perPage = Number(els.perPage.value || 10);
        state.page = 1;
        renderTable();
      });

      els.btnPrev.addEventListener('click', () => {
        if (state.page > 1) {
          state.page--;
          renderTable();
        }
      });

      els.btnNext.addEventListener('click', () => {
        state.page++;
        renderTable();
      });

      els.btnExportXLS.addEventListener('click', () => {
        const p = new URLSearchParams();
        if (els.dataInicio.value) p.set('dataInicio', els.dataInicio.value);
        if (els.dataFim.value) p.set('dataFim', els.dataFim.value);
        if (els.categoria.value) p.set('categoria', els.categoria.value);
        if (els.status.value) p.set('status', els.status.value);
        if (els.beneficio.value) p.set('beneficio', els.beneficio.value);

        p.set('export', '1');
        p.set('gen_txt', new Date().toLocaleString('pt-BR'));

        window.open('relatorioBeneficios.php?' + p.toString(), '_blank');
      });

      loadData();
    });
  </script>
</body>

</html>