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
  return preg_replace('/\D+/', '', (string)$s);
}
function normalizeDate(?string $s): ?string
{
  $s = trim((string)$s);
  if ($s === '') return null;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
  return $s;
}
function normalizePeriod(?string $p): string
{
  $p = strtolower(trim((string)$p));
  $allowed = ['diario', 'semanal', 'mensal', 'anual', 'personalizado'];
  return in_array($p, $allowed, true) ? $p : 'mensal';
}
function periodLabel(string $p): string
{
  switch ($p) {
    case 'diario':
      return 'Diário (Hoje)';
    case 'semanal':
      return 'Semanal (Últimos 7 dias)';
    case 'mensal':
      return 'Mensal (Mês atual)';
    case 'anual':
      return 'Anual (Ano atual)';
    default:
      return 'Personalizado';
  }
}
function fmtDateBR(?string $ymd): string
{
  $ymd = trim((string)$ymd);
  if ($ymd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return '—';
  return substr($ymd, 8, 2) . '/' . substr($ymd, 5, 2) . '/' . substr($ymd, 0, 4);
}

/**
 * Define di/df conforme período (server-side fallback).
 * Retorna [di, df] em YYYY-MM-DD.
 */
function computeRangeByPeriod(string $periodo, ?string $di, ?string $df): array
{
  $today = new DateTime('now', new DateTimeZone('America/Manaus'));

  if ($periodo === 'personalizado') return [$di, $df];

  if ($periodo === 'diario') {
    $x = $today->format('Y-m-d');
    return [$x, $x];
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

  // anual
  $start = new DateTime($today->format('Y-01-01'), new DateTimeZone('America/Manaus'));
  $end = new DateTime($today->format('Y-12-31'), new DateTimeZone('America/Manaus'));
  return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

/** Pega nome do bairro/benefício por ID (pra mostrar bonito no Excel) */
function lookupName(PDO $pdo, string $table, int $id): ?string
{
  if ($id <= 0) return null;
  $allowed = ['bairros' => 'bairros', 'ajudas_tipos' => 'ajudas_tipos'];
  if (!isset($allowed[$table])) return null;

  try {
    $st = $pdo->prepare("SELECT nome FROM {$allowed[$table]} WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $x = $st->fetchColumn();
    return $x ? (string)$x : null;
  } catch (Throwable $e) {
    return null;
  }
}

/**
 * Monta WHERE/PARAMS iguais para relatório/Excel.
 * Retorna: [periodo, di, df, bairroId, beneficioId, sexo, q, baseDT, where[], params[]]
 */
function buildFilters(array $in): array
{
  $periodo = normalizePeriod($in['periodo'] ?? 'mensal');
  $di = normalizeDate($in['di'] ?? null);
  $df = normalizeDate($in['df'] ?? null);

  // Fallback: se não veio di/df, calcula pelo período
  [$di2, $df2] = computeRangeByPeriod($periodo, $di, $df);
  $di = $di2;
  $df = $df2;

  $bairroId = (int)($in['bairro_id'] ?? 0);
  $beneficioId = (int)($in['beneficio_id'] ?? 0);
  $q = trim((string)($in['q'] ?? ''));

  // ✅ NOVO: sexo/gênero
  $sexo = strtolower(trim((string)($in['sexo'] ?? '')));

  // Base de data do cadastro
  $baseDT = "COALESCE(s.created_at, s.updated_at)";

  $where = ["1=1"];
  $params = [];

  if ($di) {
    $where[] = "DATE($baseDT) >= :di";
    $params[':di'] = $di;
  }
  if ($df) {
    $where[] = "DATE($baseDT) <= :df";
    $params[':df'] = $df;
  }

  if ($bairroId > 0) {
    $where[] = "s.bairro_id = :bid";
    $params[':bid'] = $bairroId;
  }
  if ($beneficioId > 0) {
    $where[] = "s.ajuda_tipo_id = :tid";
    $params[':tid'] = $beneficioId;
  }
  $sexo = trim((string)($in['sexo'] ?? ''));

  if ($sexo !== '') {
    $where[] = "LOWER(TRIM(COALESCE(s.genero,''))) = LOWER(TRIM(:sexo))";
    $params[':sexo'] = $sexo; // "Masculino", "Feminino", "Outro"
  }


  return [$periodo, $di, $df, $bairroId, $beneficioId, $sexo, $q, $baseDT, $where, $params];
}

/**
 * Busca dados agregados (AJAX / Export / Primeira carga).
 * Retorna array com KPIs + series + tabela de benefícios.
 */
function fetchAggregates(PDO $pdo, array $in): array
{
  [$periodo, $di, $df, $bairroId, $beneficioId, $sexo, $q, $baseDT, $where, $params] = buildFilters($in);

  // Total geral (ALL TIME)
  $totalGeral = 0;
  try {
    $totalGeral = (int)$pdo->query("SELECT COUNT(*) FROM solicitantes")->fetchColumn();
  } catch (Throwable $e) {
    $totalGeral = 0;
  }

  // Total no período (com filtros base)
  $sqlTotalPeriodo = "SELECT COUNT(*) FROM solicitantes s WHERE " . implode(' AND ', $where);
  $st = $pdo->prepare($sqlTotalPeriodo);
  $st->execute($params);
  $totalPeriodo = (int)$st->fetchColumn();

  // Com benefício / sem benefício (no período)
  $sqlComSem = "
    SELECT
      SUM(CASE WHEN s.ajuda_tipo_id IS NOT NULL AND s.ajuda_tipo_id > 0 THEN 1 ELSE 0 END) AS com_benef,
      SUM(CASE WHEN s.ajuda_tipo_id IS NULL OR s.ajuda_tipo_id = 0 THEN 1 ELSE 0 END) AS sem_benef,
      COUNT(DISTINCT NULLIF(s.bairro_id,0)) AS bairros_dist,
      COUNT(DISTINCT NULLIF(s.ajuda_tipo_id,0)) AS benef_dist
    FROM solicitantes s
    WHERE " . implode(' AND ', $where) . "
  ";
  $st = $pdo->prepare($sqlComSem);
  $st->execute($params);
  $k = $st->fetch(PDO::FETCH_ASSOC) ?: [];
  $comBenef = (int)($k['com_benef'] ?? 0);
  $semBenef = (int)($k['sem_benef'] ?? 0);
  $bairrosDist = (int)($k['bairros_dist'] ?? 0);
  $benefDist = (int)($k['benef_dist'] ?? 0);

  // Decide agrupamento por data (dia ou mês)
  $grouping = 'day';
  $daysSpan = 0;
  if ($di && $df) {
    try {
      $d1 = new DateTime($di);
      $d2 = new DateTime($df);
      $daysSpan = (int)$d1->diff($d2)->format('%a') + 1;
      if ($daysSpan > 62) $grouping = 'month';
    } catch (Throwable $e) {
      $grouping = 'day';
    }
  }

  // Série por data (dia ou mês)
  if ($grouping === 'month') {
    $sqlByDate = "
      SELECT DATE_FORMAT($baseDT, '%Y-%m') AS dkey, COUNT(*) AS c
      FROM solicitantes s
      WHERE " . implode(' AND ', $where) . "
      GROUP BY dkey
      ORDER BY dkey ASC
    ";
  } else {
    $sqlByDate = "
      SELECT DATE($baseDT) AS dkey, COUNT(*) AS c
      FROM solicitantes s
      WHERE " . implode(' AND ', $where) . "
      GROUP BY dkey
      ORDER BY dkey ASC
    ";
  }
  $st = $pdo->prepare($sqlByDate);
  $st->execute($params);
  $seriesDate = [];
  foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $seriesDate[] = ['key' => (string)$r['dkey'], 'count' => (int)$r['c']];
  }

  // Série por bairro (para donut)
  $sqlByBairro = "
    SELECT COALESCE(b.nome,'—') AS nome, COALESCE(s.bairro_id,0) AS id, COUNT(*) AS c
    FROM solicitantes s
    LEFT JOIN bairros b ON b.id = s.bairro_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY id, nome
    ORDER BY c DESC, nome ASC
  ";
  $st = $pdo->prepare($sqlByBairro);
  $st->execute($params);
  $seriesBairro = [];
  foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $seriesBairro[] = [
      'id' => (int)$r['id'],
      'nome' => (string)$r['nome'],
      'count' => (int)$r['c']
    ];
  }

  // Tabela principal: benefícios (ajudas_tipos) com total de pessoas
  $paramsBenef = $params;
  $benefNameWhere = "";
  if ($q !== '') {
    $benefNameWhere = " AND COALESCE(at.nome,'') LIKE :qLike ";
    $paramsBenef[':qLike'] = '%' . $q . '%';
  }

  $sqlByBenef = "
    SELECT
      CASE
        WHEN s.ajuda_tipo_id IS NULL OR s.ajuda_tipo_id = 0 THEN 'Sem benefício'
        ELSE COALESCE(at.nome,'—')
      END AS nome,
      COALESCE(s.ajuda_tipo_id,0) AS id,
      COUNT(*) AS c
    FROM solicitantes s
    LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
    WHERE " . implode(' AND ', $where) . "
    $benefNameWhere
    GROUP BY id, nome
    ORDER BY c DESC, nome ASC
  ";
  $st = $pdo->prepare($sqlByBenef);
  $st->execute($paramsBenef);

  $benefTable = [];
  foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $cnt = (int)$r['c'];
    $benefTable[] = [
      'id' => (int)$r['id'],
      'nome' => (string)$r['nome'],
      'count' => $cnt,
      'pct' => ($totalPeriodo > 0 ? round(($cnt / $totalPeriodo) * 100, 2) : 0.0),
    ];
  }

  return [
    'ok' => true,
    'periodo' => $periodo,
    'periodo_label' => periodLabel($periodo),
    'di' => $di,
    'df' => $df,
    'grouping' => $grouping, // day|month
    'days_span' => $daysSpan,
    'kpis' => [
      'total_geral' => $totalGeral,
      'total_periodo' => $totalPeriodo,
      'com_benef' => $comBenef,
      'sem_benef' => $semBenef,
      'bairros_dist' => $bairrosDist,
      'benef_dist' => $benefDist
    ],
    'series_date' => $seriesDate,
    'series_bairro' => $seriesBairro,
    'benef_table' => $benefTable
  ];
}

/**
 * ✅ lista pessoas (solicitantes) para exportação.
 */
function fetchPeopleForExport(PDO $pdo, array $in, int $maxRows = 10000): array
{
  [$periodo, $di, $df, $bairroId, $beneficioId, $sexo, $q, $baseDT, $where, $params] = buildFilters($in);

  $sqlTotal = "SELECT COUNT(*) FROM solicitantes s WHERE " . implode(' AND ', $where);
  $st = $pdo->prepare($sqlTotal);
  $st->execute($params);
  $total = (int)$st->fetchColumn();

  $limit = max(1, min($maxRows, $total > 0 ? $total : $maxRows));
  $truncated = ($total > $limit);

  $sql = "
    SELECT
      s.nome,
      s.cpf,
      CONCAT(
        s.endereco,
        ', Nº ',
        s.numero,
        ' – ',
        COALESCE(b.nome,'—')
      ) AS endereco_completo,
      s.telefone,
      CASE
        WHEN s.ajuda_tipo_id IS NULL OR s.ajuda_tipo_id = 0 THEN 'Sem benefício'
        ELSE COALESCE(at.nome,'—')
      END AS beneficio,
      DATE($baseDT) AS data_cadastro,
      s.resumo_caso,
      s.trabalho
    FROM solicitantes s
    LEFT JOIN bairros b ON b.id = s.bairro_id
    LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.nome ASC
    LIMIT $limit
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  return ['total' => $total, 'rows' => $rows, 'truncated' => $truncated];
}

/**
 * ✅ Busca Histórico de Solicitações para a aba solicitacoes.
 */
function fetchSolicitationsForExport(PDO $pdo, array $in, int $maxRows = 10000): array
{
  // Reaproveita normalizações do buildFilters
  [$periodo, $di, $df, $bairroId, $beneficioId, $sexo, $q, $baseDT, $wherePessoa, $paramsPessoa] = buildFilters($in);

  // ✅ AQUI É O PONTO: na aba "solicitacoes" a data do filtro deve ser da SOLICITAÇÃO
  // Então montamos um WHERE próprio, sem usar DATE($baseDT)
  $where = ["1=1"];
  $params = [];

  // Data da solicitação (nova)
  if (!empty($di)) {
    $where[] = "DATE(sol.data_solicitacao) >= :di";
    $params[':di'] = $di;
  }
  if (!empty($df)) {
    $where[] = "DATE(sol.data_solicitacao) <= :df";
    $params[':df'] = $df;
  }

  // Bairro é do solicitante
  if ($bairroId > 0) {
    $where[] = "s.bairro_id = :bid";
    $params[':bid'] = $bairroId;
  }

  // Benefício filtrado deve ser o SOLICITADO na solicitação
  if ($beneficioId > 0) {
    $where[] = "sol.ajuda_tipo_id = :tid";
    $params[':tid'] = $beneficioId;
  }

  // Sexo/Gênero é do solicitante
  $sexoIn = trim((string)($in['sexo'] ?? '')); // "Masculino" | "Feminino" | "Outro"
  if ($sexoIn !== '') {
    $where[] = "LOWER(TRIM(COALESCE(s.genero,''))) = LOWER(TRIM(:sexo))";
    $params[':sexo'] = $sexoIn;
  }

  // Total
  $sqlTotal = "
    SELECT COUNT(*)
    FROM solicitacoes sol
    JOIN solicitantes s ON s.id = sol.solicitante_id
    WHERE " . implode(' AND ', $where);

  $st = $pdo->prepare($sqlTotal);
  $st->execute($params);
  $total = (int)$st->fetchColumn();

  $limit = max(1, min($maxRows, $total > 0 ? $total : $maxRows));
  $truncated = ($total > $limit);

  // Lista
  $sql = "
    SELECT
      s.nome,
      s.cpf,
      CONCAT(
        s.endereco,
        ', Nº ',
        s.numero,
        ' – ',
        COALESCE(b.nome,'—')
      ) AS endereco_completo,
      s.telefone,
      CASE
        WHEN sol.ajuda_tipo_id IS NULL OR sol.ajuda_tipo_id = 0 THEN 'Sem benefício'
        ELSE COALESCE(at.nome,'—')
      END AS beneficio,
      DATE(sol.data_solicitacao) AS data_cadastro,
      sol.resumo_caso
    FROM solicitacoes sol
    JOIN solicitantes s ON s.id = sol.solicitante_id
    LEFT JOIN bairros b ON b.id = s.bairro_id
    LEFT JOIN ajudas_tipos at ON at.id = sol.ajuda_tipo_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.nome ASC, sol.data_solicitacao DESC
    LIMIT $limit
  ";


  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  return ['total' => $total, 'rows' => $rows, 'truncated' => $truncated];
}


/* ===========================
   AJAX (JSON)
   =========================== */
if ((string)($_GET['ajax'] ?? '') === '1') {
  header('Content-Type: application/json; charset=UTF-8');
  try {
    $payload = $_POST ?: $_GET;
    $out = fetchAggregates($pdo, $payload);
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro ao gerar dados.'], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

/* ===========================
   EXPORT EXCEL (XML Spreadsheet)
   =========================== */
$exportFlag = (string)($_POST['export'] ?? $_GET['export'] ?? '');
if ($exportFlag === '1') {

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

  $payload = $_POST ?: $_GET;

  // Agregados (aba beneficios)
  $data = fetchAggregates($pdo, $payload);

  $periodoLabel = (string)($data['periodo_label'] ?? '—');
  $di = (string)($data['di'] ?? '');
  $df = (string)($data['df'] ?? '');
  $k = $data['kpis'] ?? [];
  $totalGeral = (int)($k['total_geral'] ?? 0);
  $totalPeriodo = (int)($k['total_periodo'] ?? 0);

  $bairroId = (int)($payload['bairro_id'] ?? 0);
  $beneficioId = (int)($payload['beneficio_id'] ?? 0);
  $q = trim((string)($payload['q'] ?? ''));

  // ✅ sexo no export
  $sexo = strtolower(trim((string)($payload['sexo'] ?? '')));
  $sexoLabel = 'Todos';
  if ($sexo === 'masculino') $sexoLabel = 'Masculino';
  elseif ($sexo === 'feminino') $sexoLabel = 'Feminino';
  elseif ($sexo === 'outro') $sexoLabel = 'Outro';
  elseif ($sexo === 'nao_informado') $sexoLabel = 'Não informado';

  $bairroNome = $bairroId > 0 ? (lookupName($pdo, 'bairros', $bairroId) ?? ('ID ' . $bairroId)) : 'Todos';
  $benefNome = $beneficioId > 0 ? (lookupName($pdo, 'ajudas_tipos', $beneficioId) ?? ('ID ' . $beneficioId)) : 'Todos';

  $linhaFiltros = [];
  $linhaFiltros[] = 'Período: ' . 'Mensal (Mês atual)'; // sempre mensal no export
  $linhaFiltros[] = $di ? ('Data inicial: ' . fmtDateBR($di)) : 'Data inicial: —';
  $linhaFiltros[] = $df ? ('Data final: ' . fmtDateBR($df)) : 'Data final: —';
  $linhaFiltros[] = 'Bairro: ' . $bairroNome;
  $linhaFiltros[] = 'Benefício: ' . $benefNome;
  $linhaFiltros[] = 'Sexo/Gênero: ' . $sexoLabel;
  if ($q !== '') $linhaFiltros[] = 'Busca benefício: ' . $q;

  // Aba PESSOAS
  $peoplePack = fetchPeopleForExport($pdo, $payload, 10000);
  $peopleTotal = (int)($peoplePack['total'] ?? 0);
  $peopleRows = $peoplePack['rows'] ?? [];
  $peopleTrunc = (bool)($peoplePack['truncated'] ?? false);

  // Aba SOLICITAÇÕES
  $solicPack = fetchSolicitationsForExport($pdo, $payload, 10000);
  $solicTotal = (int)($solicPack['total'] ?? 0);
  $solicRows = $solicPack['rows'] ?? [];
  $solicTrunc = (bool)($solicPack['truncated'] ?? false);

  // Limpa qualquer saída anterior
  while (ob_get_level()) {
    ob_end_clean();
  }

  $filename = 'relatorio_beneficios_' . date('Ymd_His') . '.xls';
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Cache-Control: max-age=0');

  $xmlEsc = function ($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
  };

  $wrapWords = function (string $text, int $every = 7): string {
    $words = preg_split('/\s+/', trim($text));
    if (count($words) <= $every) return trim($text);
    $lines = array_chunk($words, $every);
    return implode("\n", array_map(function ($chunk) {
      return implode(' ', $chunk);
    }, $lines));
  };

  // Print area da aba beneficios (A:C)
  $rowsData = is_array($data['benef_table'] ?? null) ? count($data['benef_table']) : 0;
  $lastRowBenef = 5 + max(1, $rowsData); // 1..4 meta, 5 header, dados a partir do 6
  $printAreaBenef = "beneficios!R1C1:R{$lastRowBenef}C3";

  // Print area da aba pessoas (A:I)
  $rowsPeople = is_array($peopleRows) ? count($peopleRows) : 0;
  $lastRowPeople = 5 + max(1, $rowsPeople);
  $printAreaPeople = "pessoas!R1C1:R{$lastRowPeople}C9";

  // Print area da aba solicitacoes (A:H)
  $rowsSolic = is_array($solicRows) ? count($solicRows) : 0;
  $lastRowSolic = 5 + max(1, $rowsSolic);
  $printAreaSolic = "solicitacoes!R1C1:R{$lastRowSolic}C8";

  echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
  <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:html="http://www.w3.org/TR/REC-html40">

    <Styles>
      <Style ss:ID="sTitle">
        <Font ss:Bold="1" ss:Size="16" /><Alignment ss:Horizontal="Center" ss:Vertical="Center" />
      </Style>

      <Style ss:ID="sMeta">
        <Font ss:Bold="1" ss:Size="12" /><Alignment ss:Vertical="Center" ss:WrapText="1" />
      </Style>

      <Style ss:ID="sHeader">
        <Font ss:Bold="1" ss:Size="13" /><Interior ss:Color="#F2F4F7" ss:Pattern="Solid" /><Alignment ss:Vertical="Center" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders>
      </Style>

      <Style ss:ID="sText">
        <Font ss:Size="13" /><Alignment ss:Vertical="Center" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders>
      </Style>

      <Style ss:ID="sNum">
        <Font ss:Size="13" /><Alignment ss:Horizontal="Right" ss:Vertical="Center" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders><NumberFormat ss:Format="0" />
      </Style>

      <Style ss:ID="sPct">
        <Font ss:Size="13" /><Alignment ss:Horizontal="Right" ss:Vertical="Center" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders><NumberFormat ss:Format="0.00%" />
      </Style>

      <Style ss:ID="sLongText">
        <Font ss:Size="13" /><Alignment ss:Vertical="Top" ss:WrapText="1" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" /><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" /></Borders>
      </Style>
    </Styles>

    <!-- ======================
       ABA 1: BENEFÍCIOS
       ====================== -->
    <Worksheet ss:Name="beneficios">
      <Names>
        <NamedRange ss:Name="Print_Area" ss:RefersTo="<?= $xmlEsc($printAreaBenef) ?>" />
      </Names>

      <Table ss:DefaultRowHeight="22" ss:ExpandedColumnCount="3" ss:ExpandedRowCount="<?= (int) ($lastRowBenef) ?>">
        <Column ss:Width="600" />
        <Column ss:Width="140" />
        <Column ss:Width="160" />

        <Row ss:Height="30">
          <Cell ss:StyleID="sTitle" ss:MergeAcross="2">
            <Data ss:Type="String"><?= $xmlEsc('Relatório de Benefícios (ajudas_tipos) — ANEXO') ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="24">
          <Cell ss:StyleID="sMeta" ss:MergeAcross="2">
            <Data ss:Type="String"><?= $xmlEsc('Gerado em: ' . $geradoEm) ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="26">
          <Cell ss:StyleID="sMeta" ss:MergeAcross="2">
            <Data ss:Type="String"><?= $xmlEsc(implode('  |  ', $linhaFiltros)) ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="24">
          <Cell ss:StyleID="sMeta" ss:MergeAcross="2">
            <Data
              ss:Type="String"><?= $xmlEsc("Total de pessoas cadastradas (GERAL): {$totalGeral}  |  Total no período: {$totalPeriodo}") ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="26">
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Benefício (ajuda_tipo)</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Pessoas</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">% do período</Data></Cell>
        </Row>

        <?php foreach (($data['benef_table'] ?? []) as $r): ?>
          <?php
          $count = (int) ($r['count'] ?? 0);
          $pct = (float) ($r['pct'] ?? 0.0);
          $pct01 = $pct / 100.0;
          ?>
          <Row ss:Height="24">
            <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc((string) ($r['nome'] ?? '—')) ?></Data></Cell>
            <Cell ss:StyleID="sNum"><Data ss:Type="Number"><?= $count ?></Data></Cell>
            <Cell ss:StyleID="sPct"><Data ss:Type="Number"><?= $pct01 ?></Data></Cell>
          </Row>
        <?php endforeach; ?>
      </Table>

      <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
        <FreezePanes />
        <FrozenNoSplit />
        <SplitHorizontal>5</SplitHorizontal>
        <TopRowBottomPane>5</TopRowBottomPane>
        <ActivePane>2</ActivePane>
        <Panes>
          <Pane>
            <Number>2</Number>
            <ActiveRow>5</ActiveRow>
            <ActiveCol>0</ActiveCol>
          </Pane>
        </Panes>
        <PageSetup>
          <Layout x:Orientation="Landscape" />
          <PageMargins x:Left="0.3" x:Right="0.3" x:Top="0.4" x:Bottom="0.4" />
        </PageSetup>
        <FitToPage />
        <Print>
          <ValidPrinterInfo />
          <FitWidth>1</FitWidth>
          <FitHeight>0</FitHeight>
        </Print>
      </WorksheetOptions>
    </Worksheet>

    <!-- ======================
       ABA 2: PESSOAS (NOMES) - ✅ FONTE 13pt E COLUNAS MAIORES
       ====================== -->
    <Worksheet ss:Name="pessoas">
      <Names>
        <NamedRange ss:Name="Print_Area" ss:RefersTo="<?= $xmlEsc($printAreaPeople) ?>" />
      </Names>

      <Table ss:DefaultRowHeight="22" ss:ExpandedColumnCount="9" ss:ExpandedRowCount="<?= (int) ($lastRowPeople) ?>">
        <Column ss:Width="70" />
        <Column ss:Width="380" />
        <Column ss:Width="150" />
        <Column ss:Width="450" />
        <Column ss:Width="140" />
        <Column ss:Width="280" />
        <Column ss:Width="120" />
        <Column ss:Width="120" />
        <Column ss:Width="350" />

        <Row ss:Height="30">
          <Cell ss:StyleID="sTitle" ss:MergeAcross="8">
            <Data ss:Type="String"><?= $xmlEsc('Pessoas do Benefício — ' . $benefNome) ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="24">
          <Cell ss:StyleID="sMeta" ss:MergeAcross="8">
            <Data ss:Type="String"><?= $xmlEsc('Gerado em: ' . $geradoEm) ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="26">
          <Cell ss:StyleID="sMeta" ss:MergeAcross="8">
            <Data ss:Type="String"><?= $xmlEsc(implode('  |  ', $linhaFiltros)) ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="24">
          <Cell ss:StyleID="sMeta" ss:MergeAcross="8">
            <Data
              ss:Type="String"><?= $xmlEsc("Total de pessoas listadas: {$peopleTotal}" . ($peopleTrunc ? "  (⚠ lista truncada por limite de exportação)" : "")) ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="26">
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Nº</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Nome</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">CPF</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Endereço Completo</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Telefone</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Benefício</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Data cadastro</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Empregado</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Resumo do Caso</Data></Cell>
        </Row>

        <?php if (empty($peopleRows)): ?>
          <Row ss:Height="24">
            <Cell ss:StyleID="sText" ss:MergeAcross="8">
              <Data ss:Type="String"><?= $xmlEsc('Nenhum registro encontrado para os filtros selecionados.') ?></Data>
            </Cell>
          </Row>
        <?php else: ?>
          <?php $i = 1;
          foreach ($peopleRows as $p): ?>
            <?php
            $nome = (string) ($p['nome'] ?? '—');
            $cpf = (string) ($p['cpf'] ?? '');
            $endComp = (string) ($p['endereco_completo'] ?? '—');
            $tel = (string) ($p['telefone'] ?? '');
            $benef = (string) ($p['beneficio'] ?? '—');
            $dtcad = (string) ($p['data_cadastro'] ?? '');
            $dtcadBR = $dtcad ? fmtDateBR($dtcad) : '—';
            $trab = trim((string) ($p['trabalho'] ?? $_POST['trabalho'] ?? ''));

            // normaliza: minúsculo + remove acento
            $trabNorm = mb_strtolower($trab, 'UTF-8');
            $trabNorm = strtr($trabNorm, [
              'á' => 'a',
              'à' => 'a',
              'ã' => 'a',
              'â' => 'a',
              'é' => 'e',
              'ê' => 'e',
              'í' => 'i',
              'ó' => 'o',
              'ô' => 'o',
              'õ' => 'o',
              'ú' => 'u',
              'ç' => 'c'
            ]);

            $isEmpregado = ($trabNorm === 'empregado(a)' || $trabNorm === 'empregado')
              ? 'Sim'
              : 'Não';




            $resumo = $wrapWords((string) ($p['resumo_caso'] ?? ''), 7);
            ?>
            <Row ss:Height="24">
              <Cell ss:StyleID="sNum"><Data ss:Type="Number"><?= $i ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($nome) ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($cpf) ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($endComp) ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($tel) ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($benef) ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($dtcadBR) ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($isEmpregado) ?></Data></Cell>
              <Cell ss:StyleID="sLongText"><Data ss:Type="String"><?= $xmlEsc($resumo) ?></Data></Cell>
            </Row>
          <?php $i++;
          endforeach; ?>
        <?php endif; ?>
      </Table>

      <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
        <FreezePanes />
        <FrozenNoSplit />
        <SplitHorizontal>5</SplitHorizontal>
        <TopRowBottomPane>5</TopRowBottomPane>
        <ActivePane>2</ActivePane>
        <Panes>
          <Pane>
            <Number>2</Number>
            <ActiveRow>5</ActiveRow>
            <ActiveCol>0</ActiveCol>
          </Pane>
        </Panes>
        <PageSetup>
          <Layout x:Orientation="Landscape" />
          <PageMargins x:Left="0.3" x:Right="0.3" x:Top="0.4" x:Bottom="0.4" />
        </PageSetup>
        <FitToPage />
        <Print>
          <ValidPrinterInfo />
          <FitWidth>1</FitWidth>
          <FitHeight>0</FitHeight>
        </Print>
      </WorksheetOptions>
    </Worksheet>

    <!-- ======================
       ABA 3: SOLICITAÇÕES - ✅ FONTE 13pt E COLUNAS MAIORES
       ====================== -->
    <Worksheet ss:Name="solicitacoes">
      <Names>
        <NamedRange ss:Name="Print_Area" ss:RefersTo="<?= $xmlEsc($printAreaSolic) ?>" />
      </Names>

      <Table ss:DefaultRowHeight="22" ss:ExpandedColumnCount="8" ss:ExpandedRowCount="<?= (int) ($lastRowSolic) ?>">
        <Column ss:Width="70" />
        <Column ss:Width="380" />
        <Column ss:Width="150" />
        <Column ss:Width="450" />
        <Column ss:Width="140" />
        <Column ss:Width="280" />
        <Column ss:Width="120" />
        <Column ss:Width="450" />

        <Row ss:Height="30">
          <Cell ss:StyleID="sTitle" ss:MergeAcross="7">
            <Data ss:Type="String"><?= $xmlEsc('Histórico de Solicitações (Conforme Filtro)') ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="24">
          <Cell ss:StyleID="sMeta" ss:MergeAcross="7">
            <Data ss:Type="String"><?= $xmlEsc('Gerado em: ' . $geradoEm) ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="26">
          <Cell ss:StyleID="sMeta" ss:MergeAcross="7">
            <Data ss:Type="String"><?= $xmlEsc(implode('  |  ', $linhaFiltros)) ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="24">
          <Cell ss:StyleID="sMeta" ss:MergeAcross="7">
            <Data
              ss:Type="String"><?= $xmlEsc("Total de solicitações listadas: {$solicTotal}" . ($solicTrunc ? "  (⚠ lista truncada)" : "")) ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="26">
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Nº</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Nome</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">CPF</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Endereço Completo</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Telefone</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Benefício (Solicitado)</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Data Solicitação</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Resumo do Caso</Data></Cell>
        </Row>

        <?php if (empty($solicRows)): ?>
          <Row ss:Height="24">
            <Cell ss:StyleID="sText" ss:MergeAcross="7">
              <Data ss:Type="String"><?= $xmlEsc('Nenhuma solicitação encontrada para os filtros selecionados.') ?></Data>
            </Cell>
          </Row>
        <?php else: ?>
          <?php $i = 1;
          foreach ($solicRows as $p): ?>
            <?php
            $nome = (string) ($p['nome'] ?? '—');
            $cpf = (string) ($p['cpf'] ?? '');
            $endComp = (string) ($p['endereco_completo'] ?? '—');
            $tel = (string) ($p['telefone'] ?? '');
            $benef = (string) ($p['beneficio'] ?? '—');
            $dtcad = (string) ($p['data_cadastro'] ?? '');
            $dtcadBR = $dtcad ? fmtDateBR($dtcad) : '—';
            $resumo = $wrapWords((string) ($p['resumo_caso'] ?? ''), 7);
            ?>
            <Row ss:Height="24">
              <Cell ss:StyleID="sNum"><Data ss:Type="Number"><?= $i ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($nome) ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($cpf) ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($endComp) ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($tel) ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($benef) ?></Data></Cell>
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($dtcadBR) ?></Data></Cell>
              <Cell ss:StyleID="sLongText"><Data ss:Type="String"><?= $xmlEsc($resumo) ?></Data></Cell>
            </Row>
          <?php $i++;
          endforeach; ?>
        <?php endif; ?>
      </Table>
      <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
        <PageSetup>
          <Layout x:Orientation="Landscape" />
        </PageSetup>
      </WorksheetOptions>
    </Worksheet>

  </Workbook>
<?php
  exit;
}
/* ===========================
   DADOS PARA SELECTS + PRIMEIRA CARGA
   =========================== */
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

// Primeira carga (mensal padrão)
$initial = fetchAggregates($pdo, ['periodo' => 'mensal']);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <title>Relatório de Benefícios — ANEXO</title>
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
      font-weight: 800;
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

    @media(min-width:768px) {
      .legend-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }
    }

    @media(min-width:1200px) {
      .legend-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr));
      }
    }

    .table-responsive-md {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    #tblBenef {
      white-space: nowrap;
    }

    #tblBenef thead th {
      white-space: nowrap;
    }

    #tblBenef th:nth-child(1),
    #tblBenef td:nth-child(1) {
      min-width: 320px;
    }

    #tblBenef th:nth-child(2),
    #tblBenef td:nth-child(2) {
      min-width: 120px;
    }

    #tblBenef th:nth-child(3),
    #tblBenef td:nth-child(3) {
      min-width: 120px;
    }

    .td-nome {
      max-width: 520px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .tfoot-pager {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: .75rem 1rem;
      flex-wrap: wrap;
    }

    @media(max-width:576.98px) {
      .page-title h3 {
        font-size: 1.25rem;
      }

      .text-subtitle {
        font-size: .9rem;
      }

      .stat .value {
        font-size: 1.35rem;
      }

      .actions .btn {
        flex: 1 1 100%;
      }

      .chart-wrap {
        height: 240px;
      }

      #tblBenef {
        font-size: 12px;
      }

      .td-nome {
        max-width: 220px;
      }
    }
  </style>
</head>

<body>
  <div id="app">
    <!-- ===== SIDEBAR (mesmo layout) ===== -->
    <div id="sidebar" class="active">
      <div class="sidebar-wrapper active">
        <div class="sidebar-header">
          <div class="d-flex justify-content-between">
            <div class="logo"><a href="#"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo"></a></div>
            <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i
                  class="bi bi-x bi-middle"></i></a></div>
          </div>
        </div>

        <div class="sidebar-menu">
          <ul class="menu">
            <li class="sidebar-item"><a href="dashboard.php" class="sidebar-link"><i
                  class="bi bi-grid-fill"></i><span>Dashboard</span></a></li>

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
                <li class="submenu-item active"><a href="#">Cadastros</a></li>
                <li class="submenu-item"><a href="relatorioAtendimentos.php">Atendimentos</a></li>
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

            <li class="sidebar-item"><a href="./auth/logout.php" class="sidebar-link"><i
                  class="bi bi-box-arrow-right"></i><span>Sair</span></a></li>
          </ul>
        </div>
      </div>
    </div>
    <!-- ===== /SIDEBAR ===== -->

    <div id="main" class="d-flex flex-column min-vh-100">
      <header class="mb-3">
        <a href="#" class="burger-btn d-block d-xl-none" aria-label="Alternar menu"><i
            class="bi bi-justify fs-3"></i></a>
      </header>

      <div class="page-heading">
        <div class="page-title">
          <div class="row align-items-end g-2">
            <div class="col-12 col-md-6 order-md-1 order-last">
              <h3>Relatório de Benefícios</h3>
              <p class="text-subtitle text-muted mb-0">Lista de ajudas (ajudas_tipos) e quantas pessoas solicitaram</p>
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
              <form id="filters" class="row g-3 g-sm-3 g-md-4">
                <div class="col-12 col-md-3">
                  <label class="form-label" for="periodo">Período</label>
                  <select id="periodo" class="form-select">
                    <option value="diario">Diário (Hoje)</option>
                    <option value="semanal">Semanal (Últimos 7 dias)</option>
                    <option value="mensal" selected>Mensal (Mês atual)</option>
                    <option value="anual">Anual (Ano atual)</option>
                    <option value="personalizado">Personalizado</option>
                  </select>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                  <label class="form-label" for="dataInicio">Data inicial</label>
                  <input type="date" id="dataInicio" class="form-control" inputmode="numeric">
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                  <label class="form-label" for="dataFim">Data final</label>
                  <input type="date" id="dataFim" class="form-control" inputmode="numeric">
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                  <label class="form-label" for="bairro">Bairro (opcional)</label>
                  <select id="bairro" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($bairros as $b): ?>
                      <option value="<?= (int) $b['id'] ?>"><?= e((string) $b['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                  <label class="form-label" for="beneficio">Benefício (opcional)</label>
                  <select id="beneficio" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($beneficios as $t): ?>
                      <option value="<?= (int) $t['id'] ?>"><?= e((string) $t['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>


                </div>
                <div class="col-12 col-sm-6 col-md-3">
                  <label class="form-label" for="sexo">Sexo (opcional)</label>
                  <select id="sexo" class="form-select">
                    <option value="">Todos</option>
                    <option value="Feminino">Feminino</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Outro">Outro</option>
                  </select>
                </div>
              </form>
            </div>
          </div>
        </section>

        <!-- KPIs -->
        <section class="section">
          <div class="row g-3">
            <div class="col-12 col-md-4 mb-3">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Total pessoas cadastradas (GERAL)</div>
                  <div id="kpiGeral" class="value">0</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-md-4 mb-3">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Total no período</div>
                  <div id="kpiPeriodo" class="value">0</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-md-4 mb-3">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Com benefício</div>
                  <div id="kpiCom" class="value">0</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-md-4 mb-3">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Sem benefício</div>
                  <div id="kpiSem" class="value">0</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-md-4 mb-3">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Bairros distintos (período)</div>
                  <div id="kpiBairros" class="value">0</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-md-4 mb-3">
              <div class="card stat h-100">
                <div class="card-body">
                  <div class="label">Benefícios distintos (período)</div>
                  <div id="kpiBenefs" class="value">0</div>
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
                <div class="card-header fw-semibold">Cadastros no período (por data)</div>
                <div class="card-body">
                  <div class="chart-wrap"><canvas id="chartData"></canvas></div>
                  <div class="text-muted small mt-2" id="lblAgrupamento"></div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-6">
              <div class="card h-100">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <span>Benefícios mais solicitados</span>
                  <span class="text-muted small">Top 12 + Outros</span>
                </div>
                <div class="card-body">
                  <div class="chart-wrap"><canvas id="chartBenef"></canvas></div>
                </div>
              </div>
            </div>

            <div class="col-12 mb-4">
              <div class="card h-100">
                <div class="card-header fw-semibold">Distribuição por bairro (período)</div>
                <div class="card-body">
                  <div class="chart-wrap"><canvas id="chartBairro"></canvas></div>
                  <div id="legendBairros" class="legend-grid"></div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Tabela principal: benefícios -->
        <section class="section mb-4">
          <div class="card">
            <div class="card-header d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
              <span class="fw-semibold">Lista de Benefícios (ajudas_tipos)</span>
              <div class="d-flex gap-2 align-items-center">
                <input id="qLive" class="form-control form-control-sm" placeholder="Buscar benefício (nome)..."
                  autocomplete="off" />
                <button class="btn btn-sm btn-outline-secondary" type="button" id="btnClear"><i
                    class="bi bi-x-circle"></i></button>
              </div>
            </div>

            <div class="card-body">
              <div class="table-responsive-md">
                <table id="tblBenef" class="table table-striped table-hover align-middle w-100 text-nowrap">
                  <thead class="table-light">
                    <tr>
                      <th>Benefício</th>
                      <th class="text-end">Pessoas</th>
                      <th class="text-end">% do período</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyBenef"></tbody>
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

              <!-- FORM POST para exportar -->
              <form id="frmExport" method="post" action="" style="display:none;">
                <input type="hidden" name="export" value="1">
                <input type="hidden" name="client_now" id="exp_client_now" value="">
                <input type="hidden" name="sexo" id="exp_sexo" value="">
                <input type="hidden" name="periodo" id="exp_periodo" value="">
                <input type="hidden" name="di" id="exp_di" value="">
                <input type="hidden" name="df" id="exp_df" value="">
                <input type="hidden" name="bairro_id" id="exp_bairro" value="">
                <input type="hidden" name="beneficio_id" id="exp_beneficio" value="">
                <input type="hidden" name="q" id="exp_q" value="">
              </form>

            </div>
          </div>
        </section>

      </div>

      <footer>
        <div class="footer clearfix mb-0 text-muted">
          <div class="float-start text-black">
            <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de
                Coari-AM.</b></p>
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

      const initial = <?= json_encode($initial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

      const selPeriodo = document.getElementById('periodo');
      const inpDI = document.getElementById('dataInicio');
      const inpDF = document.getElementById('dataFim');
      const selBairro = document.getElementById('bairro');
      const selBenef = document.getElementById('beneficio');

      // ✅ filtro gênero (igual cadastro: Feminino/Masculino/Outro)
      const selSexo = document.getElementById('sexo');
      const expSexo = document.getElementById('exp_sexo');


      const inpSearch = document.getElementById('qLive');
      const btnClear = document.getElementById('btnClear');
      const btnReset = document.getElementById('btnReset');

      const selPerPage = document.getElementById('selPerPage');
      const btnPrev = document.getElementById('btnPrev');
      const btnNext = document.getElementById('btnNext');
      const lblPagina = document.getElementById('lblPagina');

      const btnExport = document.getElementById('btnExportXLS');
      const tbodyBenef = document.getElementById('tbodyBenef');

      // Export hidden
      const frmExport = document.getElementById('frmExport');
      const expClientNow = document.getElementById('exp_client_now');
      const expPeriodo = document.getElementById('exp_periodo');
      const expDI = document.getElementById('exp_di');
      const expDF = document.getElementById('exp_df');
      const expBairro = document.getElementById('exp_bairro');
      const expBenef = document.getElementById('exp_beneficio');
      const expQ = document.getElementById('exp_q');

      // ✅ hidden gênero no export


      // KPIs
      const elGeral = document.getElementById('kpiGeral');
      const elPeriodo = document.getElementById('kpiPeriodo');
      const elCom = document.getElementById('kpiCom');
      const elSem = document.getElementById('kpiSem');
      const elBairros = document.getElementById('kpiBairros');
      const elBenefs = document.getElementById('kpiBenefs');
      const lblAgrupamento = document.getElementById('lblAgrupamento');

      selPerPage.value = '10';
      let page = 1;
      let perPage = parseInt(selPerPage.value, 10) || 10;
      let currentBenefTable = [];

      const palette = (n) =>
        Array.from({
          length: n
        }, (_, i) => `hsl(${Math.round((360 / Math.max(1, n)) * i)} 70% 55%)`);

      let chartData, chartBenef, chartBairro;

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

      function pad2(n) {
        return String(n).padStart(2, '0');
      }

      function toLocalISODate(d) {
        return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
      }

      function setPeriodDates(p) {
        const today = new Date();
        let start = null,
          end = null;

        if (p === 'diario') {
          start = new Date(today.getFullYear(), today.getMonth(), today.getDate());
          end = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        } else if (p === 'semanal') {
          end = new Date(today.getFullYear(), today.getMonth(), today.getDate());
          start = new Date(end);
          start.setDate(start.getDate() - 6);
        } else if (p === 'mensal') {
          start = new Date(today.getFullYear(), today.getMonth(), 1);
          end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        } else if (p === 'anual') {
          start = new Date(today.getFullYear(), 0, 1);
          end = new Date(today.getFullYear(), 11, 31);
        }

        const isCustom = (p === 'personalizado');
        inpDI.disabled = !isCustom;
        inpDF.disabled = !isCustom;

        if (!isCustom && start && end) {
          inpDI.value = toLocalISODate(start);
          inpDF.value = toLocalISODate(end);
        }
      }

      function getPayload() {
        return {
          periodo: selPeriodo.value || 'mensal',
          di: inpDI.value || '',
          df: inpDF.value || '',
          bairro_id: selBairro.value || '',
          beneficio_id: selBenef.value || '',
          sexo: selSexo ? (selSexo.value || '') : '',
          q: (inpSearch.value || '').trim()
        };
      }

      async function fetchData() {
        const url = window.location.pathname + '?ajax=1';
        const payload = getPayload();

        try {
          const res = await fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams(payload)
          });

          const data = await res.json();
          if (!data || !data.ok) throw new Error('Sem dados');
          renderAll(data);
        } catch (err) {
          renderAll({
            ok: true,
            grouping: 'day',
            kpis: {
              total_geral: 0,
              total_periodo: 0,
              com_benef: 0,
              sem_benef: 0,
              bairros_dist: 0,
              benef_dist: 0
            },
            series_date: [],
            series_bairro: [],
            benef_table: []
          });
        }
      }

      function renderKPIs(data) {
        const k = data.kpis || {};
        elGeral.textContent = String(k.total_geral ?? 0);
        elPeriodo.textContent = String(k.total_periodo ?? 0);
        elCom.textContent = String(k.com_benef ?? 0);
        elSem.textContent = String(k.sem_benef ?? 0);
        elBairros.textContent = String(k.bairros_dist ?? 0);
        elBenefs.textContent = String(k.benef_dist ?? 0);
        lblAgrupamento.textContent =
          data.grouping === 'month' ? 'Agrupado por mês (período grande)' : 'Agrupado por dia';
      }

      function renderTable() {
        const total = currentBenefTable.length;
        const pages = Math.max(1, Math.ceil(total / perPage));
        if (page > pages) page = pages;

        const start = (page - 1) * perPage;
        const end = start + perPage;
        const slice = currentBenefTable.slice(start, end);

        tbodyBenef.innerHTML = '';
        if (!slice.length) {
          tbodyBenef.innerHTML =
            `<tr><td colspan="3" class="text-center text-muted">Sem resultados.</td></tr>`;
        } else {
          for (const r of slice) {
            const pct = (r.pct ?? 0).toLocaleString('pt-BR', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            });
            const nome = (r.nome || '—');
            const tr = document.createElement('tr');
            tr.innerHTML = `
            <td class="td-nome" title="${String(nome).replace(/"/g, '&quot;')}">${nome}</td>
            <td class="text-end">${r.count ?? 0}</td>
            <td class="text-end">${pct}%</td>
          `;
            tbodyBenef.appendChild(tr);
          }
        }

        lblPagina.textContent = `Página ${page} de ${pages}`;
        btnPrev.disabled = page <= 1;
        btnNext.disabled = page >= pages;
      }

      function renderCharts(data) {
        const seriesDate = data.series_date || [];
        const seriesBairro = data.series_bairro || [];
        const table = data.benef_table || [];

        const labelsDateFmt = (seriesDate.map(x => x.key)).map(k => {
          if (!k) return '—';
          if (data.grouping === 'month') {
            const m = String(k).match(/^(\d{4})-(\d{2})$/);
            return m ? `${m[2]}/${m[1]}` : k;
          }
          const d = String(k).match(/^(\d{4})-(\d{2})-(\d{2})$/);
          return d ? `${d[3]}/${d[2]}/${d[1]}` : k;
        });

        const valuesDate = seriesDate.map(x => x.count);

        if (chartData) chartData.destroy();
        chartData = new Chart(document.getElementById('chartData'), {
          type: 'bar',
          data: {
            labels: labelsDateFmt,
            datasets: [{
              label: 'Cadastros',
              data: valuesDate
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
                },
                ticks: {
                  autoSkip: true,
                  maxTicksLimit: (data.grouping === 'month' ? 12 : 10)
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

        const sorted = [...table].sort((a, b) => (b.count || 0) - (a.count || 0));
        const top = sorted.slice(0, 12);
        const rest = sorted.slice(12);
        const othersCount = rest.reduce((acc, x) => acc + (x.count || 0), 0);

        const benefLabels = top.map(x => x.nome || '—');
        const benefValues = top.map(x => x.count || 0);
        if (othersCount > 0) {
          benefLabels.push('Outros');
          benefValues.push(othersCount);
        }

        if (chartBenef) chartBenef.destroy();
        chartBenef = new Chart(document.getElementById('chartBenef'), {
          type: 'bar',
          data: {
            labels: benefLabels,
            datasets: [{
              label: 'Pessoas',
              data: benefValues,
              backgroundColor: palette(benefLabels.length)
            }]
          },
          options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              }
            },
            scales: {
              x: {
                beginAtZero: true,
                ticks: {
                  precision: 0
                }
              },
              y: {
                grid: {
                  display: false
                },
                ticks: {
                  callback: function(value) {
                    const label = this.getLabelForValue(value);
                    return (label && label.length > 26) ? (label.slice(0, 26) + '…') : label;
                  }
                }
              }
            }
          }
        });

        const sB = [...seriesBairro].sort((a, b) => (b.count || 0) - (a.count || 0));
        const topB = sB.slice(0, 12);
        const restB = sB.slice(12);
        const othersB = restB.reduce((acc, x) => acc + (x.count || 0), 0);

        const bairroLabels = topB.map(x => x.nome || '—');
        const bairroValues = topB.map(x => x.count || 0);
        if (othersB > 0) {
          bairroLabels.push('Outros');
          bairroValues.push(othersB);
        }

        if (chartBairro) chartBairro.destroy();
        chartBairro = new Chart(document.getElementById('chartBairro'), {
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

      function renderAll(data) {
        renderKPIs(data);
        currentBenefTable = (data.benef_table || []).slice();
        page = 1;
        renderTable();
        renderCharts(data);
      }

      // listeners
      selPeriodo.addEventListener('change', () => {
        setPeriodDates(selPeriodo.value);
        fetchData();
      });
      inpDI.addEventListener('change', fetchData);
      inpDF.addEventListener('change', fetchData);
      selBairro.addEventListener('change', fetchData);
      selBenef.addEventListener('change', fetchData);

      // ✅ dispara filtro ao trocar gênero
      if (selSexo) selSexo.addEventListener('change', fetchData);

      inpSearch.addEventListener('input', () => {
        clearTimeout(window.__tq);
        window.__tq = setTimeout(fetchData, 250);
      });

      btnClear.addEventListener('click', () => {
        inpSearch.value = '';
        fetchData();
        inpSearch.focus();
      });

      selPerPage.addEventListener('change', () => {
        perPage = parseInt(selPerPage.value, 10) || 10;
        page = 1;
        renderTable();
      });

      btnPrev.addEventListener('click', () => {
        if (page > 1) {
          page--;
          renderTable();
        }
      });

      btnNext.addEventListener('click', () => {
        const pages = Math.max(1, Math.ceil(currentBenefTable.length / perPage));
        if (page < pages) {
          page++;
          renderTable();
        }
      });

      btnReset.addEventListener('click', () => {
        document.getElementById('filters').reset();
        selPeriodo.value = 'mensal';
        setPeriodDates('mensal');
        inpSearch.value = '';
        selPerPage.value = '10';
        perPage = 10;
        page = 1;

        // ✅ reset do gênero
        if (selSexo) selSexo.value = '';

        fetchData();
      });

      btnExport.addEventListener('click', () => {
        const payload = getPayload();

        expClientNow.value = new Date().toISOString();
        expPeriodo.value = payload.periodo;
        expDI.value = payload.di;
        expDF.value = payload.df;
        expBairro.value = payload.bairro_id;
        expBenef.value = payload.beneficio_id;
        expQ.value = payload.q;

        // ✅ ESSENCIAL: manda o sexo no export
        if (expSexo) expSexo.value = payload.sexo || '';

        frmExport.submit();
      });


      setPeriodDates(selPeriodo.value || 'mensal');
      renderAll(initial);
    });
  </script>

</body>

</html>