<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* ===== CONEXÃO (PDO) ===== */
require_once __DIR__ . '/../dist/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  echo "<div class='alert alert-danger'>Erro: conexão com o banco não encontrada.</div>";
  exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Timezone (Amazonas) ===== */
@date_default_timezone_set('America/Manaus');

/* ===== Helpers ===== */
function e(?string $v): string
{
  return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
function only_digits(?string $s): string
{
  return preg_replace('/\D+/', '', (string)$s) ?? '';
}

function fmtDateBR(?string $ymd): string
{
  $ymd = trim((string)$ymd);
  if ($ymd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return '—';
  return substr($ymd, 8, 2) . '/' . substr($ymd, 5, 2) . '/' . substr($ymd, 0, 4);
}
function fmtTimeBR(?string $his): string
{
  $his = trim((string)$his);
  if ($his === '') return '—';
  if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $his)) return substr($his, 0, 5);
  if (preg_match('/^\d{2}:\d{2}$/', $his)) return $his;
  return $his;
}
function normalizeDate(?string $s): ?string
{
  $s = trim((string)$s);
  if ($s === '') return null;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
  return $s;
}

function checkCestaBasica(?string $text): bool
{
  if ($text === null || $text === '') {
    return false;
  }
  return (bool)preg_match('/cesta.*?b[aá]sica/ius', $text);
}

// Função para formatar valor monetário - CORRIGIDA
function formatarMoeda($valor)
{
    if ($valor === null || $valor === '' || $valor === 0) return 'R$ 0,00';
    
    // Converter para float, removendo vírgulas e pontos não numéricos
    if (is_string($valor)) {
        // Remove R$, espaços e caracteres não numéricos, exceto ponto e vírgula
        $valor = str_replace(['R$', ' ', '.'], '', $valor);
        $valor = str_replace(',', '.', $valor);
    }
    
    // Garantir que é um número
    $valor_float = (float)$valor;
    
    return 'R$ ' . number_format($valor_float, 2, ',', '.');
}

/* ===========================================================
   EXPORT EXCEL
   - Por padrão exporta SOMENTE as linhas filtradas (IDs enviados pelo JS)
   - Se não vier IDs, exporta pelo filtro/busca (fallback)
   =========================================================== */
$exportFlag = (string)($_POST['export'] ?? $_GET['export'] ?? '');
if ($exportFlag === '1') {

  // “Gerado em” baseado no dispositivo (JS manda client_now)
  $clientNow = trim((string)($_POST['client_now'] ?? $_GET['client_now'] ?? ''));
  $geradoEm = null;
  if ($clientNow !== '') {
    try {
      $dt = new DateTime($clientNow);
      $dt->setTimezone(new DateTimeZone('America/Manaus'));
      $geradoEm = $dt->format('d/m/Y H:i:s');
    } catch (Throwable $e) {
      $geradoEm = null;
    }
  }
  if ($geradoEm === null) $geradoEm = date('d/m/Y H:i:s');

  // filtros (só para “texto de filtros” no excel)
  $di = normalizeDate($_POST['di'] ?? $_GET['di'] ?? '');
  $df = normalizeDate($_POST['df'] ?? $_GET['df'] ?? '');
  $bairroId   = (int)($_POST['bairro_id'] ?? $_GET['bairro_id'] ?? 0);
  $beneficioId = (int)($_POST['beneficio_id'] ?? $_GET['beneficio_id'] ?? 0);
  $q = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));

  // ====== PRINCIPAL: IDs filtrados vindos da página ======
  $idsRaw = (string)($_POST['ids'] ?? $_GET['ids'] ?? '');
  $ids = [];
  if ($idsRaw !== '') {
    foreach (preg_split('/[,\s]+/', $idsRaw) as $p) {
      $p = trim($p);
      if ($p !== '' && ctype_digit($p)) $ids[] = (int)$p;
    }
    $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));
  }

  $params = [];
  $where = ["ae.entregue = 'Sim'"];

  if ($ids) {
    // garante exportar EXATAMENTE o que está filtrado na tela
    $ph = [];
    foreach ($ids as $i => $id) {
      $k = ":id{$i}";
      $ph[] = $k;
      $params[$k] = $id;
    }
    $where[] = "ae.id IN (" . implode(',', $ph) . ")";
  } else {
    // fallback (se por algum motivo ids não vierem)
    if ($di) {
      $where[] = "ae.data_entrega >= :di";
      $params[':di'] = $di;
    }
    if ($df) {
      $where[] = "ae.data_entrega <= :df";
      $params[':df'] = $df;
    }
    if ($bairroId > 0) {
      $where[] = "s.bairro_id = :bid";
      $params[':bid'] = $bairroId;
    }
    if ($beneficioId > 0) {
      $where[] = "ae.ajuda_tipo_id = :tid";
      $params[':tid'] = $beneficioId;
    }

    if ($q !== '') {
      $qLike = '%' . $q . '%';
      $qDigits = only_digits($q);
      $where[] = "(
        COALESCE(s.nome,'') LIKE :qLike
        OR COALESCE(b.nome,'') LIKE :qLike
        OR COALESCE(at.nome,'') LIKE :qLike
        OR COALESCE(ae.responsavel,'') LIKE :qLike
        OR COALESCE(s.cpf,'') LIKE :qCpf
      )";
      $params[':qLike'] = $qLike;
      $params[':qCpf']  = ($qDigits !== '' ? ('%' . $qDigits . '%') : '%');
    }
  }

  $sql = "
    SELECT
      ae.id,
      ae.data_entrega,
      ae.hora_entrega,
      ae.quantidade,
      COALESCE(ae.valor_aplicado, 0) AS valor_unit,
      COALESCE(ae.responsavel,'') AS responsavel,
      ae.pessoa_id,
      ae.pessoa_cpf,
      COALESCE(s.nome,'Não identificado') AS solicitante_nome,
      COALESCE(s.cpf,'')  AS solicitante_cpf,
      COALESCE(b.nome,'')  AS bairro_nome,
      COALESCE(at.nome,'Não identificado') AS beneficio_nome
    FROM ajudas_entregas ae
    LEFT JOIN solicitantes s ON s.id = ae.pessoa_id
    LEFT JOIN bairros b ON b.id = s.bairro_id
    LEFT JOIN ajudas_tipos at ON at.id = ae.ajuda_tipo_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ae.data_entrega DESC, ae.hora_entrega DESC, ae.id DESC
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // KPIs
  $totalAtend = count($rows);
  $uniq = [];
  $qtdTotal = 0;
  $valorTotal = 0.0;
  foreach ($rows as $r) {
    // Usar pessoa_id ou pessoa_cpf para identificar pessoas únicas
    $pid = (string)($r['pessoa_id'] ?? '');
    $cpf = (string)($r['pessoa_cpf'] ?? $r['solicitante_cpf'] ?? '');
    $key = $pid !== '' ? $pid : $cpf;
    if ($key !== '') $uniq[$key] = true;
    $qtd = (int)($r['quantidade'] ?? 0);
    $vu  = (float)($r['valor_unit'] ?? 0);
    $qtdTotal += $qtd;
    $valorTotal += ($qtd * $vu);
  }
  $pessoasUnicas = count($uniq);

  $filtrosTxt = [];
  $filtrosTxt[] = $di ? ('Data inicial: ' . fmtDateBR($di)) : 'Data inicial: —';
  $filtrosTxt[] = $df ? ('Data final: ' . fmtDateBR($df)) : 'Data final: —';
  $filtrosTxt[] = ($bairroId > 0) ? ('Bairro ID: ' . $bairroId) : 'Bairro: Todos';
  $filtrosTxt[] = ($beneficioId > 0) ? ('Benefício ID: ' . $beneficioId) : 'Benefício: Todos';
  if ($q !== '') $filtrosTxt[] = 'Busca: ' . $q;
  if ($ids) $filtrosTxt[] = 'Exportação: Somente resultados filtrados (' . count($ids) . ')';

  while (ob_get_level()) {
    @ob_end_clean();
  }

  $filename = 'relatorio_atendimentos_' . date('Ymd_His') . '.xls';
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Cache-Control: max-age=0');

  $xmlEsc = function ($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
  };

  $toExcelDateTime = function (?string $d, ?string $t): string {
    $d = trim((string)$d);
    $t = trim((string)$t);
    if ($d === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return '';
    if ($t === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $t)) $t = '00:00:00';
    if (preg_match('/^\d{2}:\d{2}$/', $t)) $t .= ':00';
    return $d . 'T' . $t . '.000';
  };

  echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
  <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:html="http://www.w3.org/TR/REC-html40">
    <Styles>
      <Style ss:ID="sTitle">
        <Font ss:Bold="1" ss:Size="14" /><Alignment ss:Horizontal="Center" ss:Vertical="Center" />
      </Style>
      <Style ss:ID="sMeta">
        <Font ss:Bold="1" />
      </Style>
      <Style ss:ID="sHeader">
        <Font ss:Bold="1" /><Interior ss:Color="#F2F4F7" ss:Pattern="Solid" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders>
      </Style>
      <Style ss:ID="sText">
        <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders>
      </Style>
      <Style ss:ID="sInt">
        <NumberFormat ss:Format="0" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders>
      </Style>
      <Style ss:ID="sMoney">
        <NumberFormat ss:Format="R$ #,##0.00" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders>
      </Style>
      <Style ss:ID="sDateTime">
        <NumberFormat ss:Format="dd/mm/yyyy\ hh:mm" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders>
      </Style>
    </Styles>

    <Worksheet ss:Name="relatorio_atendimentos">
      <Table ss:DefaultRowHeight="18">
        <!-- larguras (evita ###) -->
        <Column ss:Width="120" />
        <Column ss:Width="220" />
        <Column ss:Width="140" />
        <Column ss:Width="180" />
        <Column ss:Width="200" />
        <Column ss:Width="60" />
        <Column ss:Width="110" />
        <Column ss:Width="120" />

        <Row ss:Height="24">
          <Cell ss:StyleID="sTitle" ss:MergeAcross="7"><Data ss:Type="String"><?= $xmlEsc('Relatório de Atendimentos — ANEXO') ?></Data></Cell>
        </Row>
        <Row>
          <Cell ss:StyleID="sMeta" ss:MergeAcross="7"><Data ss:Type="String"><?= $xmlEsc('Gerado em: ' . $geradoEm . '   |   ' . implode('   |   ', $filtrosTxt)) ?></Data></Cell>
        </Row>
        <Row>
          <Cell ss:StyleID="sMeta" ss:MergeAcross="7"><Data ss:Type="String"><?= $xmlEsc(
                                                                                "Total de atendimentos: {$totalAtend}   |   Pessoas únicas: {$pessoasUnicas}   |   Quantidade entregue: {$qtdTotal}   |   Valor total: R$ " . number_format($valorTotal, 2, ',', '.')
                                                                              ) ?></Data></Cell>
        </Row>

        <Row>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Data / Hora</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Solicitante</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Bairro</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Benefício</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Responsável</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Qtd</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Valor Unit.</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Valor Total</Data></Cell>
        </Row>

        <?php foreach ($rows as $r): ?>
          <?php
          $qtd = (int)($r['quantidade'] ?? 0);
          $vu  = (float)($r['valor_unit'] ?? 0);
          $vt  = $qtd * $vu;
          $excelDT = $toExcelDateTime($r['data_entrega'] ?? null, $r['hora_entrega'] ?? null);
          ?>
          <Row>
            <?php if ($excelDT !== ''): ?>
              <Cell ss:StyleID="sDateTime"><Data ss:Type="DateTime"><?= $xmlEsc($excelDT) ?></Data></Cell>
            <?php else: ?>
              <Cell ss:StyleID="sText"><Data ss:Type="String">—</Data></Cell>
            <?php endif; ?>

            <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc((string)($r['solicitante_nome'] ?? '')) ?></Data></Cell>
            <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc((string)($r['bairro_nome'] ?? '')) ?></Data></Cell>
            <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc((string)($r['beneficio_nome'] ?? '')) ?></Data></Cell>
            <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc((string)($r['responsavel'] ?? '')) ?></Data></Cell>

            <Cell ss:StyleID="sInt"><Data ss:Type="Number"><?= (string)$qtd ?></Data></Cell>
            <Cell ss:StyleID="sMoney"><Data ss:Type="Number"><?= number_format($vu, 2, '.', '') ?></Data></Cell>
            <Cell ss:StyleID="sMoney"><Data ss:Type="Number"><?= number_format($vt, 2, '.', '') ?></Data></Cell>
          </Row>
        <?php endforeach; ?>
      </Table>
    </Worksheet>
  </Workbook>
<?php
  exit;
}

/* ===========================================================
   DADOS DA PÁGINA (renderiza do BD) - CORRIGIDO: traz todas as entregas
   =========================================================== */
$bairros = [];
$beneficios = [];

try {
  $bairros = $pdo->query("SELECT id, nome FROM bairros ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $bairros = [];
}

try {
  $beneficios = $pdo->query("SELECT id, nome FROM ajudas_tipos WHERE status='Ativa' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $beneficios = [];
}

// Query principal CORRIGIDA: usando LEFT JOIN para trazer TODAS as entregas
try {
  $rows = $pdo->query("
    SELECT
      ae.id,
      ae.ajuda_tipo_id,
      ae.pessoa_id,
      ae.pessoa_cpf,
      ae.data_entrega,
      ae.hora_entrega,
      ae.quantidade,
      COALESCE(ae.valor_aplicado, 0) AS valor_unit,
      COALESCE(ae.responsavel,'') AS responsavel,

      COALESCE(s.nome,'Não identificado') AS solicitante_nome,
      COALESCE(s.cpf,'')  AS solicitante_cpf,
      s.bairro_id,
      s.resumo_caso,

      COALESCE(b.nome,'')  AS bairro_nome,
      COALESCE(at.nome,'Não identificado') AS beneficio_nome
    FROM ajudas_entregas ae
    LEFT JOIN solicitantes s ON s.id = ae.pessoa_id
    LEFT JOIN bairros b ON b.id = s.bairro_id
    LEFT JOIN ajudas_tipos at ON at.id = ae.ajuda_tipo_id
    WHERE ae.entregue = 'Sim'
    ORDER BY ae.data_entrega DESC, ae.hora_entrega DESC, ae.id DESC
  ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  echo "<div class='alert alert-danger'>Erro ao consultar atendimentos: " . e($e->getMessage()) . "</div>";
  exit;
}

// Buscar total geral de entregas
try {
  $stmtTotal = $pdo->query("SELECT COUNT(*) as total FROM ajudas_entregas WHERE entregue = 'Sim'");
  $totalGeral = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Throwable $e) {
  $totalGeral = 0;
}

// Buscar valor total geral
try {
  $stmtValorTotal = $pdo->query("SELECT SUM(COALESCE(valor_aplicado, 0)) as valor_total FROM ajudas_entregas WHERE entregue = 'Sim'");
  $valorTotalGeral = $stmtValorTotal->fetch(PDO::FETCH_ASSOC)['valor_total'] ?? 0;
} catch (Throwable $e) {
  $valorTotalGeral = 0;
}

// ===== FIX: Contagem exata de Solicitações de Cesta Básica (Direto do Banco) =====
$totalCestaBasica = 0;
try {
  // Regex fornecido pelo usuário para garantir contagem exata (16 registros)
  $stmtCesta = $pdo->query("SELECT COUNT(*) FROM solicitantes WHERE resumo_caso REGEXP '(?i)cesta[[:space:]]+b[aá]sica'");
  $totalCestaBasica = (int)$stmtCesta->fetchColumn();
} catch (Throwable $e) {
  $totalCestaBasica = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <title>Relatório de Atendimentos — ANEXO</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

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
      --card-radius: 14px;
    }

    body {
      font-family: 'Nunito', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }

    .card {
      border: 0;
      border-radius: var(--card-radius);
      box-shadow: 0 1px 2px rgba(16, 24, 40, .06), 0 1px 3px rgba(16, 24, 40, .1);
    }

    .card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: var(--gap-sm);
      flex-wrap: wrap;
    }

    .card-header .actions {
      display: flex;
      gap: var(--gap-xs);
      flex-wrap: wrap;
    }

    .stat .label {
      color: #667085;
      font-size: .85rem;
    }

    .stat .value {
      font-size: 1.6rem;
      font-weight: 700;
      line-height: 1.1;
    }

    .chart-wrap {
      height: 320px;
    }

    .chart-wrap canvas {
      height: 100% !important;
      width: 100% !important;
    }

    .legend-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: .5rem 1rem;
      margin-top: .75rem;
      padding: 0 .5rem .25rem;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: .5rem;
      min-width: 0;
    }

    .legend-color {
      width: 14px;
      height: 14px;
      border-radius: 4px;
      flex: 0 0 14px;
    }

    .legend-label {
      font-size: .95rem;
      color: #344054;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    @media (min-width:768px) {
      .legend-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }
    }

    @media (min-width:1200px) {
      .legend-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr));
      }
    }

    /* tabela sem sobreposição: vira scroll */
    .table-responsive-md {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    #tblAtend {
      white-space: nowrap;
    }

    #tblAtend thead th {
      white-space: nowrap;
    }

    /* min-width por coluna (força scroll ao invés de "esmagar"/sobrepor) */
    #tblAtend th:nth-child(1),
    #tblAtend td:nth-child(1) {
      min-width: 120px;
    }

    #tblAtend th:nth-child(2),
    #tblAtend td:nth-child(2) {
      min-width: 220px;
    }

    #tblAtend th:nth-child(3),
    #tblAtend td:nth-child(3) {
      min-width: 140px;
    }

    #tblAtend th:nth-child(4),
    #tblAtend td:nth-child(4) {
      min-width: 180px;
    }

    #tblAtend th:nth-child(5),
    #tblAtend td:nth-child(5) {
      min-width: 200px;
    }

    #tblAtend th:nth-child(6),
    #tblAtend td:nth-child(6) {
      min-width: 70px;
    }

    #tblAtend th:nth-child(7),
    #tblAtend td:nth-child(7) {
      min-width: 110px;
    }

    #tblAtend th:nth-child(8),
    #tblAtend td:nth-child(8) {
      min-width: 120px;
    }

    .td-solicitante {
      max-width: 280px;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .td-beneficio {
      max-width: 240px;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .td-resp {
      max-width: 240px;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .tfoot-pager {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: .75rem 1rem;
      flex-wrap: wrap;
    }

    /* Informação do total geral */
    .total-geral-info {
      background-color: #e7f3ff;
      border-left: 4px solid #007bff;
      padding: 12px 16px;
      margin-bottom: 20px;
      border-radius: 6px;
      font-size: 0.95rem;
    }

    .total-geral-info strong {
      color: #0056b3;
    }

    .total-geral-info i {
      color: #007bff;
      margin-right: 8px;
    }

    @media (max-width:576.98px) {
      .page-title h3 {
        font-size: 1.25rem;
      }

      .text-subtitle {
        font-size: .9rem;
      }

      .stat .value {
        font-size: 1.4rem;
      }

      .actions .btn {
        flex: 1 1 100%;
      }

      .chart-wrap {
        height: 220px;
      }

      #tblAtend {
        font-size: 12px;
      }

      .td-solicitante,
      .td-beneficio,
      .td-resp {
        max-width: 160px;
      }
    }
  </style>
</head>

<body>
  <div id="app">
    <!-- ===== SIDEBAR (seu mesmo layout) ===== -->
    <div id="sidebar" class="active">
      <div class="sidebar-wrapper active">
        <div class="sidebar-header">
          <div class="d-flex justify-content-between">
            <div class="logo"><a href="#"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo"></a></div>
            <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a></div>
          </div>
        </div>

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
                  <a href="registrarEntrega.php">Registrar Entrega</a>
                </li>
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
                <li class="submenu-item active"><a href="#">Atendimentos</a></li>
                <li class="submenu-item"><a href="relatorioBeneficios.php">Benefícios</a></li>
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
      </div>
    </div>
    <!-- ===== /SIDEBAR ===== -->

    <div id="main" class="d-flex flex-column min-vh-100">
      <header class="mb-3">
        <a href="#" class="burger-btn d-block d-xl-none" aria-label="Alternar menu"><i class="bi bi-justify fs-3"></i></a>
      </header>

      <div class="page-heading">
        <div class="page-title">
          <div class="row align-items-end g-2">
            <div class="col-12 col-md-6 order-md-1 order-last">
              <h3>Relatório de Atendimentos</h3>
              <p class="text-subtitle text-muted mb-0">Visão consolidada de entregas</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
              <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                <ol class="breadcrumb mb-0">
                  <li class="breadcrumb-item"><a href="#">Relatórios</a></li>
                  <li class="breadcrumb-item active" aria-current="page">Atendimentos</li>
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

                <!-- Exporta SEMPRE o que está filtrado/pesquisado -->
                <button type="button" id="btnExportXLS" class="btn btn-primary">
                  <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel (somente filtrados)
                </button>
              </div>
            </div>

            <div class="card-body pt-2">
              <form id="filters" class="row g-3 g-sm-3 g-md-4">
                <div class="col-12 col-sm-12 col-md-3">
                  <label class="form-label" for="dataInicio">Data inicial</label>
                  <input type="date" id="dataInicio" class="form-control" inputmode="numeric">
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                  <label class="form-label" for="dataFim">Data final</label>
                  <input type="date" id="dataFim" class="form-control" inputmode="numeric">
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                  <label class="form-label" for="bairro">Bairro</label>
                  <select id="bairro" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($bairros as $b): ?>
                      <option value="<?= (int)$b['id'] ?>"><?= e((string)$b['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                  <label class="form-label" for="beneficio">Benefício</label>
                  <select id="beneficio" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($beneficios as $t): ?>
                      <option value="<?= (int)$t['id'] ?>"><?= e((string)$t['nome']) ?></option>
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
            <div class="col-12 col-md-6 mb-3">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Total de atendimentos</div>
                  <div id="kpiAtend" class="value"><?= $totalGeral ?></div>
                </div>
              </div>
            </div>

            <div class="col-12 col-md-6 mb-3">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Pessoas únicas</div>
                  <div id="kpiPessoas" class="value">0</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-md-6 mb-3">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Quantidade entregue</div>
                  <div id="kpiQtd" class="value">0</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-md-6 mb-3">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Valor total</div>
                  <div id="kpiValor" class="value"><?= formatarMoeda($valorTotalGeral) ?></div>
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
                <div class="card-header fw-semibold">Atendimentos por data</div>
                <div class="card-body">
                  <div class="chart-wrap"><canvas id="chartLinha"></canvas></div>
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="card h-100">
                <div class="card-header fw-semibold">Atendimentos por benefício</div>
                <div class="card-body">
                  <div class="chart-wrap"><canvas id="chartBar"></canvas></div>
                </div>
              </div>
            </div>

            <div class="col-12 mb-4">
              <div class="card h-100">
                <div class="card-header fw-semibold">Distribuição por bairro</div>
                <div class="card-body">
                  <div class="chart-wrap"><canvas id="chartDonut"></canvas></div>
                  <div id="legendBairros" class="legend-grid"></div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Tabela -->
        <section class="section mb-4">
          <div class="card">
            <div class="card-header d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
              <span class="fw-semibold">Detalhe de atendimentos</span>
              <div class="d-flex gap-2 align-items-center">
                <input id="qLive" class="form-control form-control-sm" placeholder="Buscar por solicitante/bairro/benefício/responsável/CPF..." autocomplete="off" />
                <button class="btn btn-sm btn-outline-secondary" type="button" id="btnClear"><i class="bi bi-x-circle"></i></button>
              </div>
            </div>

            <div class="card-body">
              <div class="table-responsive-md">
                <table id="tblAtend" class="table table-striped table-hover align-middle w-100 text-nowrap">
                  <thead class="table-light">
                    <tr>
                      <th>Data / Hora</th>
                      <th>Solicitante</th>
                      <th>Bairro</th>
                      <th>Benefício</th>
                      <th>Responsável</th>
                      <th class="text-end">Qtd</th>
                      <th class="text-end">Valor Unit.</th>
                      <th class="text-end">Valor Total</th>
                    </tr>
                  </thead>
                  <tbody id="tbody">
                    <?php if (!$rows): ?>
                      <tr>
                        <td colspan="8" class="text-center text-muted">Nenhum atendimento encontrado.</td>
                      </tr>
                      <?php else: foreach ($rows as $r): ?>
                        <?php
                        $id = (int)$r['id'];
                        $pid = (int)$r['pessoa_id'];
                        $cpfDigits = only_digits((string)($r['pessoa_cpf'] ?? $r['solicitante_cpf'] ?? ''));

                        $dataISO = (string)($r['data_entrega'] ?? '');
                        $horaISO = (string)($r['hora_entrega'] ?? '');
                        $dtBR = fmtDateBR($dataISO);
                        $hrBR = fmtTimeBR($horaISO);
                        $dtHoraBR = trim($dtBR . ' ' . ($hrBR !== '—' ? $hrBR : ''));

                        $qtd = (int)($r['quantidade'] ?? 0);
                        $vu  = (float)($r['valor_unit'] ?? 0);
                        $vt  = $qtd * $vu;

                        // Verifica Cesta Básica
                        $isCesta = checkCestaBasica($r['resumo_caso'] ?? '') ? 1 : 0;

                        $bairroId = (int)($r['bairro_id'] ?? 0);
                        $beneficioId = (int)($r['ajuda_tipo_id'] ?? 0);

                        $solNome = (string)($r['solicitante_nome'] ?? 'Não identificado');
                        $bairroNome = (string)($r['bairro_nome'] ?? '');
                        $benefNome  = (string)($r['beneficio_nome'] ?? 'Não identificado');
                        $respNome   = (string)($r['responsavel'] ?? '');

                        $kSol = mb_strtolower($solNome, 'UTF-8');
                        $kBai = mb_strtolower($bairroNome, 'UTF-8');
                        $kBen = mb_strtolower($benefNome, 'UTF-8');
                        $kRes = mb_strtolower($respNome, 'UTF-8');
                        ?>
                        <tr
                          data-id="<?= $id ?>"
                          data-pessoa-id="<?= $pid ?>"
                          data-pessoa-cpf="<?= e($r['pessoa_cpf'] ?? '') ?>"
                          data-cpf="<?= e($cpfDigits) ?>"
                          data-date="<?= e($dataISO) ?>"
                          data-time="<?= e($horaISO) ?>"
                          data-bairro-id="<?= $bairroId ?>"
                          data-beneficio-id="<?= $beneficioId ?>"
                          data-solicitante="<?= e($kSol) ?>"
                          data-bairro="<?= e($kBai) ?>"
                          data-beneficio="<?= e($kBen) ?>"
                          data-responsavel="<?= e($kRes) ?>"
                          data-qtd="<?= $qtd ?>"
                          data-cesta="<?= $isCesta ?>"
                          data-valor-unit="<?= number_format($vu, 2, '.', '') ?>">
                          <td><?= e($dtHoraBR) ?></td>
                          <td class="td-solicitante" title="<?= e($solNome) ?>"><?= e($solNome) ?></td>
                          <td title="<?= e($bairroNome) ?>"><?= e($bairroNome !== '' ? $bairroNome : '—') ?></td>
                          <td class="td-beneficio" title="<?= e($benefNome) ?>"><?= e($benefNome) ?></td>
                          <td class="td-resp" title="<?= e($respNome) ?>"><?= e($respNome !== '' ? $respNome : '—') ?></td>
                          <td class="text-end"><?= (int)$qtd ?></td>
                          <td class="text-end"><?= e('R$ ' . number_format($vu, 2, ',', '.')) ?></td>
                          <td class="text-end"><?= e('R$ ' . number_format($vt, 2, ',', '.')) ?></td>
                        </tr>
                    <?php endforeach;
                    endif; ?>
                  </tbody>
                </table>
              </div>

              <div class="mt-2 tfoot-pager">
                <div class="d-flex align-items-center gap-2">
                  <button class="btn btn-outline-secondary btn-sm" id="btnPrev">Anterior</button>
                  <button class="btn btn-outline-secondary btn-sm" id="btnNext">Próxima</button>
                </div>
                <div class="flex-grow-1 d-flex justify-content-center">
                  <strong id="lblPagina">Página 1 de 1</strong>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <label for="selPerPage" class="form-label m-0">por página</label>
                  <select id="selPerPage" class="form-select form-select-sm" style="width:auto">
                    <option>10</option>
                    <option>20</option>
                    <option>50</option>
                    <option>100</option>
                  </select>
                </div>
              </div>

              <!-- FORM POST para exportar (envia IDs filtrados) -->
              <form id="frmExport" method="post" action="" style="display:none;">
                <input type="hidden" name="export" value="1">
                <input type="hidden" name="client_now" id="exp_client_now" value="">
                <input type="hidden" name="di" id="exp_di" value="">
                <input type="hidden" name="df" id="exp_df" value="">
                <input type="hidden" name="bairro_id" id="exp_bairro" value="">
                <input type="hidden" name="beneficio_id" id="exp_beneficio" value="">
                <input type="hidden" name="q" id="exp_q" value="">
                <input type="hidden" name="ids" id="exp_ids" value="">
              </form>
            </div>
          </div>
        </section>
      </div>

      <footer>
        <div class="footer clearfix mb-0 text-muted">
          <div class="float-start text-black">
            <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de Coari-AM.</b></p>
          </div>
          <div class="float-end text-black">
            <p>Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
  <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
  <script src="../dist/assets/js/main.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('current-year').textContent = String(new Date().getFullYear());

      const tbody = document.getElementById('tbody');
      const allRows = Array.from(tbody?.querySelectorAll('tr[data-id]') || []);

      const inpSearch = document.getElementById('qLive');
      const btnClear = document.getElementById('btnClear');

      const inpDI = document.getElementById('dataInicio');
      const inpDF = document.getElementById('dataFim');
      const selBairro = document.getElementById('bairro');
      const selBenef = document.getElementById('beneficio');
      const btnReset = document.getElementById('btnReset');

      const selPerPage = document.getElementById('selPerPage');
      const btnPrev = document.getElementById('btnPrev');
      const btnNext = document.getElementById('btnNext');
      const lblPagina = document.getElementById('lblPagina');

      const btnExport = document.getElementById('btnExportXLS');

      // Export form hidden inputs
      const frmExport = document.getElementById('frmExport');
      const expClientNow = document.getElementById('exp_client_now');
      const expDI = document.getElementById('exp_di');
      const expDF = document.getElementById('exp_df');
      const expBairro = document.getElementById('exp_bairro');
      const expBenef = document.getElementById('exp_beneficio');
      const expQ = document.getElementById('exp_q');
      const expIds = document.getElementById('exp_ids');

      // KPIs
      const elAtend = document.getElementById('kpiAtend');
      const elPessoas = document.getElementById('kpiPessoas');
      const elQtd = document.getElementById('kpiQtd');
      const elValor = document.getElementById('kpiValor');
      const money = n => 'R$ ' + (Number(n || 0)).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      selPerPage.value = '10';
      let page = 1;
      let perPage = parseInt(selPerPage.value, 10) || 10;
      let filteredRows = allRows.slice();

      // Charts
      const palette = (n) => Array.from({
        length: n
      }, (_, i) => `hsl(${Math.round((360/n)*i)} 70% 55%)`);
      let chartLinha, chartBar, chartDonut;

      const htmlLegendPlugin = {
        id: 'htmlLegend',
        afterUpdate(chart, args, options) {
          const container = document.getElementById(options.containerID);
          if (!container) return;
          container.innerHTML = '';
          const items = chart.options.plugins.legend.labels.generateLabels(chart);
          for (const item of items) {
            const row = document.createElement('div');
            row.className = 'legend-item';
            const box = document.createElement('span');
            box.className = 'legend-color';
            box.style.background = item.fillStyle;
            const label = document.createElement('span');
            label.className = 'legend-label';
            label.textContent = item.text;
            row.appendChild(box);
            row.appendChild(label);
            container.appendChild(row);
          }
        }
      };
      Chart.register(htmlLegendPlugin);

      function renderPage() {
        const total = filteredRows.length;
        const pages = Math.max(1, Math.ceil(total / perPage));
        if (page > pages) page = pages;

        const start = (page - 1) * perPage;
        const end = start + perPage;

        allRows.forEach(r => r.style.display = 'none');
        filteredRows.slice(start, end).forEach(r => r.style.display = '');

        lblPagina.textContent = `Página ${page} de ${pages}`;
        btnPrev.disabled = page <= 1;
        btnNext.disabled = page >= pages;
      }

      function getFilteredMeta() {
        return filteredRows.map(tr => {
          const ds = tr.dataset || {};
          const qtd = parseInt(ds.qtd || '0', 10) || 0;
          const vu = parseFloat(ds.valorUnit || '0') || 0;
          const isCesta = (ds.cesta === '1');
          const pessoaId = ds.pessoaId || '';
          const pessoaCpf = ds.pessoaCpf || '';
          return {
            date: ds.date || '',
            bairro: ds.bairro || '',
            beneficio: ds.beneficio || '',
            pessoaId: pessoaId || pessoaCpf, // Usar ID ou CPF para identificar pessoas
            qtd,
            vu,
            isCesta
          };
        });
      }

      function renderKPIsAndCharts() {
        const meta = getFilteredMeta();

        const atend = meta.length;
        
        // Calcular pessoas únicas considerando pessoa_id ou pessoa_cpf
        const pessoasUnicas = new Set();
        meta.forEach(x => {
          if (x.pessoaId) {
            pessoasUnicas.add(x.pessoaId);
          }
        });
        const pessoas = pessoasUnicas.size;
        
        const qtd = meta.reduce((a, x) => a + (x.qtd || 0), 0);
        const valor = meta.reduce((a, x) => a + (x.qtd || 0) * (x.vu || 0), 0);

        elAtend.textContent = String(atend);
        elPessoas.textContent = String(pessoas);
        elQtd.textContent = String(qtd);
        elValor.textContent = money(valor);

        const byDate = {};
        const byBenef = {};
        const byBairro = {};
        meta.forEach(r => {
          if (r.date) byDate[r.date] = (byDate[r.date] || 0) + 1;
          const ben = r.beneficio || '—';
          const bai = r.bairro || '—';
          byBenef[ben] = (byBenef[ben] || 0) + 1;
          byBairro[bai] = (byBairro[bai] || 0) + 1;
        });

        const dates = Object.keys(byDate).sort();
        const lineValues = dates.map(d => byDate[d]);

        const benefLabels = Object.keys(byBenef).sort();
        const benefValues = benefLabels.map(k => byBenef[k]);

        const bairroLabels = Object.keys(byBairro).sort();
        const bairroValues = bairroLabels.map(k => byBairro[k]);

        if (chartLinha) chartLinha.destroy();
        if (chartBar) chartBar.destroy();
        if (chartDonut) chartDonut.destroy();

        chartLinha = new Chart(document.getElementById('chartLinha'), {
          type: 'line',
          data: {
            labels: dates.map(d => {
              const m = String(d).match(/^(\d{4})-(\d{2})-(\d{2})$/);
              return m ? `${m[3]}/${m[2]}/${m[1]}` : d;
            }),
            datasets: [{
              label: 'Atendimentos',
              data: lineValues,
              tension: .35,
              fill: true
            }]
          },
          options: {
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              }
            },
            scales: {
              x: {
                grid: {
                  display: false
                }
              },
              y: {
                beginAtZero: true,
                ticks: {
                  precision: 0
                }
              }
            }
          }
        });

        chartBar = new Chart(document.getElementById('chartBar'), {
          type: 'bar',
          data: {
            labels: benefLabels,
            datasets: [{
              label: 'Atendimentos',
              data: benefValues,
              backgroundColor: palette(benefLabels.length)
            }]
          },
          options: {
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              }
            },
            scales: {
              x: {
                grid: {
                  display: false
                }
              },
              y: {
                beginAtZero: true,
                ticks: {
                  precision: 0
                }
              }
            }
          }
        });

        chartDonut = new Chart(document.getElementById('chartDonut'), {
          type: 'doughnut',
          data: {
            labels: bairroLabels,
            datasets: [{
              data: bairroValues,
              backgroundColor: palette(bairroLabels.length)
            }]
          },
          options: {
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              },
              htmlLegend: {
                containerID: 'legendBairros'
              }
            },
            cutout: '55%'
          }
        });
      }

      function applyFilters() {
        const di = (inpDI.value || '').trim();
        const df = (inpDF.value || '').trim();
        const bid = (selBairro.value || '').trim();
        const tid = (selBenef.value || '').trim();

        const q = (inpSearch.value || '').trim().toLowerCase();
        const qDigits = q.replace(/\D+/g, '');

        filteredRows = allRows.filter(tr => {
          const ds = tr.dataset || {};
          const d = ds.date || '';

          if (di && d && d < di) return false;
          if (df && d && d > df) return false;
          if (bid && String(ds.bairroId || '') !== String(bid)) return false;
          if (tid && String(ds.beneficioId || '') !== String(tid)) return false;

          if (q) {
            const sol = ds.solicitante || '';
            const bai = ds.bairro || '';
            const ben = ds.beneficio || '';
            const res = ds.responsavel || '';
            const cpf = ds.cpf || '';
            const hitText = sol.includes(q) || bai.includes(q) || ben.includes(q) || res.includes(q);
            const hitDigits = qDigits && cpf.includes(qDigits);
            if (!hitText && !hitDigits) return false;
          }
          return true;
        });

        page = 1;
        renderPage();
        renderKPIsAndCharts();
      }

      inpDI.addEventListener('change', applyFilters);
      inpDF.addEventListener('change', applyFilters);
      selBairro.addEventListener('change', applyFilters);
      selBenef.addEventListener('change', applyFilters);

      inpSearch.addEventListener('input', applyFilters);
      btnClear.addEventListener('click', () => {
        inpSearch.value = '';
        applyFilters();
        inpSearch.focus();
      });

      selPerPage.addEventListener('change', () => {
        perPage = parseInt(selPerPage.value, 10) || 10;
        page = 1;
        renderPage();
      });
      btnPrev.addEventListener('click', () => {
        if (page > 1) {
          page--;
          renderPage();
        }
      });
      btnNext.addEventListener('click', () => {
        page++;
        renderPage();
      });

      btnReset.addEventListener('click', () => {
        document.getElementById('filters').reset();
        inpSearch.value = '';
        selPerPage.value = '10';
        perPage = 10;
        page = 1;
        applyFilters();
      });

      // ✅ EXPORTA SOMENTE OS FILTRADOS (ex.: digitou "tainara" -> exporta só ela)
      btnExport.addEventListener('click', () => {
        const ids = filteredRows.map(tr => tr.dataset.id).filter(Boolean);

        expClientNow.value = new Date().toISOString();
        expDI.value = inpDI.value || '';
        expDF.value = inpDF.value || '';
        expBairro.value = selBairro.value || '';
        expBenef.value = selBenef.value || '';
        expQ.value = (inpSearch.value || '').trim();
        expIds.value = ids.join(',');

        frmExport.submit();
      });

      // Inicializar com todos os dados
      renderPage();
      renderKPIsAndCharts();
    });
  </script>
</body>

</html>