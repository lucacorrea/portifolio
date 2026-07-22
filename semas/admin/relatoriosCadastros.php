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
require_once __DIR__ . '/../dist/lib/ImportacaoAtendimentosAutoSync.php';
require_once __DIR__ . '/includes/relatorio-cadastros-lotes.php';

try {
  // Garante que cadastros incompletos duplicados da importação sejam consolidados
  // antes de calcular totais, listas e lotes deste relatório.
  ImportacaoAtendimentosAutoSync::run($pdo);
} catch (Throwable $e) {
  error_log('Falha ao consolidar importações antes do relatório de cadastros: ' . $e->getMessage());
}

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

function calculateAge(?string $birthdate): string
{
  $birthdate = trim((string)$birthdate);
  if ($birthdate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) return '—';

  try {
    $birth = new DateTime($birthdate, new DateTimeZone('America/Manaus'));
    $today = new DateTime('today', new DateTimeZone('America/Manaus'));
    if ($birth > $today) return '—';
    return (string)$birth->diff($today)->y;
  } catch (Throwable $e) {
    return '—';
  }
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

function normalizeIds($value): array
{
  if (is_array($value)) {
    $raw = $value;
  } else {
    $raw = preg_split('/[,\s]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
  }

  $ids = [];
  foreach ($raw as $item) {
    $id = (int)$item;
    if ($id > 0) $ids[$id] = $id;
  }

  return array_values($ids);
}

function lookupNames(PDO $pdo, string $table, array $ids): array
{
  $ids = normalizeIds($ids);
  if (!$ids) return [];

  $names = [];
  foreach ($ids as $id) {
    $name = lookupName($pdo, $table, $id);
    $names[] = $name ?? ('ID ' . $id);
  }

  return $names;
}

function addInFilter(array &$where, array &$params, string $column, string $prefix, array $ids): void
{
  $ids = normalizeIds($ids);
  if (!$ids) return;

  $placeholders = [];
  foreach ($ids as $idx => $id) {
    $key = ':' . $prefix . $idx;
    $placeholders[] = $key;
    $params[$key] = $id;
  }

  $where[] = $column . ' IN (' . implode(',', $placeholders) . ')';
}

function addNotInFilter(array &$where, array &$params, string $column, string $prefix, array $ids): void
{
  $ids = normalizeIds($ids);
  if (!$ids) return;

  $placeholders = [];
  foreach ($ids as $idx => $id) {
    $key = ':' . $prefix . $idx;
    $placeholders[] = $key;
    $params[$key] = $id;
  }

  $where[] = $column . ' NOT IN (' . implode(',', $placeholders) . ')';
}

function addRequestedBenefitPersonFilter(
  array &$where,
  array &$params,
  string $personAlias,
  string $prefix,
  array $ids
): void {
  $ids = normalizeIds($ids);
  if (!$ids) return;
  if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $personAlias)) {
    throw new InvalidArgumentException('Alias de solicitante inválido.');
  }

  $primaryPlaceholders = [];
  $requestPlaceholders = [];
  foreach ($ids as $idx => $id) {
    $primaryKey = ':' . $prefix . '_primary_' . $idx;
    $requestKey = ':' . $prefix . '_request_' . $idx;
    $primaryPlaceholders[] = $primaryKey;
    $requestPlaceholders[] = $requestKey;
    $params[$primaryKey] = $id;
    $params[$requestKey] = $id;
  }
  $primaryIn = implode(',', $primaryPlaceholders);
  $requestIn = implode(',', $requestPlaceholders);
  $where[] = "(
    {$personAlias}.ajuda_tipo_id IN ({$primaryIn})
    OR EXISTS (
      SELECT 1
        FROM solicitacoes benefit_filter
       WHERE benefit_filter.solicitante_id = {$personAlias}.id
         AND benefit_filter.ajuda_tipo_id IN ({$requestIn})
         AND COALESCE(benefit_filter.origem, '') <> 'cadastro_duplicada'
    )
  )";
}

function requestedBenefitsSql(string $personAlias = 's', string $primaryHelpAlias = 'at'): string
{
  if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $personAlias)
      || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $primaryHelpAlias)) {
    throw new InvalidArgumentException('Alias de solicitante inválido.');
  }

  return "COALESCE(
    NULLIF((
      SELECT GROUP_CONCAT(
        DISTINCT CASE
          WHEN request_history.ajuda_tipo_id IS NULL OR request_history.ajuda_tipo_id = 0 THEN 'Sem benefício'
          ELSE COALESCE(request_help.nome, '—')
        END
        ORDER BY request_help.nome
        SEPARATOR ' | '
      )
        FROM solicitacoes request_history
        LEFT JOIN ajudas_tipos request_help ON request_help.id = request_history.ajuda_tipo_id
       WHERE request_history.solicitante_id = {$personAlias}.id
         AND COALESCE(request_history.origem, '') <> 'cadastro_duplicada'
    ), ''),
    CASE
      WHEN {$personAlias}.ajuda_tipo_id IS NULL OR {$personAlias}.ajuda_tipo_id = 0 THEN 'Sem benefício'
      ELSE COALESCE({$primaryHelpAlias}.nome, '—')
    END
  )";
}

function ensureEmploymentOptionsTable(PDO $pdo): void
{
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS empregos_tipos (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      nome VARCHAR(120) NOT NULL,
      termos TEXT NOT NULL,
      status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uk_empregos_tipos_nome (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ");

  $st = $pdo->prepare("
    INSERT IGNORE INTO empregos_tipos (nome, termos, status)
    VALUES (:nome, :termos, 'Ativo')
  ");
  $selectTerms = $pdo->prepare("SELECT termos FROM empregos_tipos WHERE nome = :nome LIMIT 1");
  $updateTerms = $pdo->prepare("UPDATE empregos_tipos SET termos = :termos WHERE nome = :nome");

  $defaults = [
    [
      'Gari',
      [
        'gari',
        'garis',
        'cari',
        'caris',
        'serviço de gari',
        'servico de gari',
        'limpeza pública',
        'limpeza publica',
        'coletor de lixo',
        'coleta de lixo',
        'varredor',
        'varredeira',
        'varrição',
        'varricao',
      ],
    ],
    [
      'Serviços gerais',
      [
        'serviços gerais',
        'serviço geral',
        'servicos gerais',
        'servico geral',
        'serviços geral',
        'serviço gerais',
        'servicos geral',
        'servico gerais',
        'serv. gerais',
        'serv gerais',
        'serviços gerias',
        'servicos gerias',
        'serviço gerias',
        'servico gerias',
        'servis gerais',
        'serviso gerais',
        'servisos gerais',
        'servi gerais',
        'auxiliar de serviços gerais',
        'auxiliar serviços gerais',
        'auxiliar de servicos gerais',
        'auxiliar servicos gerais',
        'asg',
      ],
    ],
  ];

  foreach ($defaults as [$nome, $defaultTerms]) {
    $defaultTermsText = implode("\n", $defaultTerms);
    $st->execute([':nome' => $nome, ':termos' => $defaultTermsText]);

    $selectTerms->execute([':nome' => $nome]);
    $currentTerms = (string)($selectTerms->fetchColumn() ?: '');
    $mergedTerms = mergeEmploymentTerms($currentTerms, $defaultTerms);

    if ($mergedTerms !== $currentTerms) {
      $updateTerms->execute([':nome' => $nome, ':termos' => $mergedTerms]);
    }
  }
}

function mergeEmploymentTerms(string $currentTerms, array $newTerms): string
{
  $lines = preg_split('/[\r\n,;|]+/', $currentTerms, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  $seen = [];
  $merged = [];

  foreach ($lines as $line) {
    $term = trim((string)$line);
    if ($term === '') continue;

    $key = normalizeSearchTerm($term);
    if ($key === '' || isset($seen[$key])) continue;

    $seen[$key] = true;
    $merged[] = $term;
  }

  foreach ($newTerms as $line) {
    $term = trim((string)$line);
    if ($term === '') continue;

    $key = normalizeSearchTerm($term);
    if ($key === '' || isset($seen[$key])) continue;

    $seen[$key] = true;
    $merged[] = $term;
  }

  return implode("\n", $merged);
}

function normalizeSearchTerm(string $value): string
{
  $value = trim($value);
  $value = strtr($value, [
    'Á' => 'a', 'À' => 'a', 'Ã' => 'a', 'Â' => 'a', 'Ä' => 'a',
    'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
    'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
    'é' => 'e', 'ê' => 'e', 'ë' => 'e',
    'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
    'í' => 'i', 'î' => 'i', 'ï' => 'i',
    'Ó' => 'o', 'Ò' => 'o', 'Õ' => 'o', 'Ô' => 'o', 'Ö' => 'o',
    'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
    'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
    'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
    'Ç' => 'c', 'ç' => 'c',
  ]);
  $value = strtolower($value);
  return preg_replace('/\s+/', ' ', $value) ?? $value;
}

function sqlNormalizedTextExpr(string $column): string
{
  $expr = "LOWER(COALESCE($column,''))";
  $replacements = [
    'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
    'é' => 'e', 'ê' => 'e', 'ë' => 'e',
    'í' => 'i', 'î' => 'i', 'ï' => 'i',
    'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
    'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
    'ç' => 'c',
  ];

  foreach ($replacements as $from => $to) {
    $expr = "REPLACE($expr, '$from', '$to')";
  }

  return $expr;
}

function employmentRegexFromTerms(?string $terms): ?string
{
  $parts = preg_split('/[\r\n,;|]+/', (string)$terms, -1, PREG_SPLIT_NO_EMPTY);
  $patterns = [];

  foreach ($parts ?: [] as $term) {
    $term = normalizeSearchTerm((string)$term);
    if ($term === '') continue;

    $quoted = preg_quote($term, '/');
    $quoted = preg_replace('/\s+/', '[[:space:]]+', $quoted) ?? $quoted;
    $patterns[$quoted] = $quoted;
  }

  if (!$patterns) return null;

  return '(^|[^[:alnum:]_])(' . implode('|', array_values($patterns)) . ')([^[:alnum:]_]|$)';
}

function fetchEmploymentOptions(PDO $pdo, array $ids = []): array
{
  $ids = normalizeIds($ids);
  $where = ["status = 'Ativo'"];
  $params = [];

  if ($ids) {
    $placeholders = [];
    foreach ($ids as $idx => $id) {
      $key = ':id' . $idx;
      $placeholders[] = $key;
      $params[$key] = $id;
    }
    $where[] = 'id IN (' . implode(',', $placeholders) . ')';
  }

  try {
    $st = $pdo->prepare("
      SELECT id, nome, termos
      FROM empregos_tipos
      WHERE " . implode(' AND ', $where) . "
      ORDER BY nome ASC
    ");
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    return [];
  }
}

function addEmploymentSummaryFilter(PDO $pdo, array &$where, array &$params, string $summaryColumn, array $empregoIds, string $prefix): void
{
  $empregoIds = normalizeIds($empregoIds);
  if (!$empregoIds) return;

  $options = fetchEmploymentOptions($pdo, $empregoIds);
  if (!$options) return;

  $summaryExpr = sqlNormalizedTextExpr($summaryColumn);
  $clauses = [];
  foreach ($options as $idx => $option) {
    $pattern = employmentRegexFromTerms((string)($option['termos'] ?? ''));
    if ($pattern === null) continue;

    $key = ':' . $prefix . $idx;
    $clauses[] = "$summaryExpr REGEXP $key";
    $params[$key] = $pattern;
  }

  if ($clauses) {
    $where[] = '(' . implode(' OR ', $clauses) . ')';
  }
}

function shouldShowRequestedEmployment(?string $beneficio): bool
{
  $items = preg_split('/\s*\|\s*/', (string)$beneficio, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  foreach ($items as $item) {
    if (in_array(normalizeSearchTerm((string)$item), ['atendimento ao prefeito', 'emprego'], true)) {
      return true;
    }
  }
  return false;
}

function detectRequestedEmployment(PDO $pdo, ?string $summary, ?string $beneficio, array $employmentOptions = []): string
{
  if (!shouldShowRequestedEmployment($beneficio)) {
    return '—';
  }

  $summaryNorm = normalizeSearchTerm((string)$summary);
  if ($summaryNorm === '') {
    return '—';
  }

  static $allEmploymentOptions = null;
  if (!$employmentOptions) {
    if ($allEmploymentOptions === null) {
      $allEmploymentOptions = fetchEmploymentOptions($pdo);
    }
    $options = $allEmploymentOptions;
  } else {
    $options = $employmentOptions;
  }

  $matched = [];
  foreach ($options as $option) {
    $pattern = employmentRegexFromTerms((string)($option['termos'] ?? ''));
    if ($pattern === null) continue;

    if (preg_match('/' . $pattern . '/u', $summaryNorm) === 1) {
      $matched[] = (string)($option['nome'] ?? '');
    }
  }

  $matched = array_values(array_filter(array_unique($matched), static fn(string $value): bool => trim($value) !== ''));
  return $matched ? implode(', ', $matched) : '—';
}

/**
 * Monta WHERE/PARAMS iguais para relatório/Excel.
 * Retorna: [periodo, di, df, bairroIds[], beneficioIds[], empregoIds[], sexo, q, baseDT, where[], params[]]
 */
function buildFilters(PDO $pdo, array $in): array
{
  $periodo = normalizePeriod($in['periodo'] ?? 'mensal');
  $di = normalizeDate($in['di'] ?? null);
  $df = normalizeDate($in['df'] ?? null);

  // Fallback: se não veio di/df, calcula pelo período
  [$di2, $df2] = computeRangeByPeriod($periodo, $di, $df);
  $di = $di2;
  $df = $df2;

  $bairroIds = normalizeIds($in['bairro_id'] ?? []);
  $beneficioIds = normalizeIds($in['beneficio_id'] ?? []);
  $empregoIds = normalizeIds($in['emprego_id'] ?? []);
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

  addInFilter($where, $params, 's.bairro_id', 'bid', $bairroIds);
  addRequestedBenefitPersonFilter($where, $params, 's', 'tid', $beneficioIds);
  addEmploymentSummaryFilter($pdo, $where, $params, 's.resumo_caso', $empregoIds, 'emp');
  $sexo = trim((string)($in['sexo'] ?? ''));

  if ($sexo !== '') {
    $where[] = "LOWER(TRIM(COALESCE(s.genero,''))) = LOWER(TRIM(:sexo))";
    $params[':sexo'] = $sexo; // "Masculino", "Feminino", "Outro"
  }

  return [$periodo, $di, $df, $bairroIds, $beneficioIds, $empregoIds, $sexo, $q, $baseDT, $where, $params];
}

/**
 * Busca dados agregados (AJAX / Export / Primeira carga).
 * Retorna array com KPIs + series + tabela de benefícios.
 */
function fetchAggregates(PDO $pdo, array $in): array
{
  [$periodo, $di, $df, $bairroIds, $beneficioIds, $empregoIds, $sexo, $q, $baseDT, $where, $params] = buildFilters($pdo, $in);

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
      SUM(CASE WHEN
        (s.ajuda_tipo_id IS NOT NULL AND s.ajuda_tipo_id > 0)
        OR EXISTS (
          SELECT 1
          FROM solicitacoes request_kpi
          WHERE request_kpi.solicitante_id = s.id
            AND request_kpi.ajuda_tipo_id IS NOT NULL
            AND request_kpi.ajuda_tipo_id > 0
            AND COALESCE(request_kpi.origem, '') <> 'cadastro_duplicada'
        )
        THEN 1 ELSE 0 END) AS com_benef,
      SUM(CASE WHEN
        (s.ajuda_tipo_id IS NULL OR s.ajuda_tipo_id = 0)
        AND NOT EXISTS (
          SELECT 1
          FROM solicitacoes request_kpi
          WHERE request_kpi.solicitante_id = s.id
            AND request_kpi.ajuda_tipo_id IS NOT NULL
            AND request_kpi.ajuda_tipo_id > 0
            AND COALESCE(request_kpi.origem, '') <> 'cadastro_duplicada'
        )
        THEN 1 ELSE 0 END) AS sem_benef,
      COUNT(DISTINCT NULLIF(s.bairro_id,0)) AS bairros_dist
    FROM solicitantes s
    WHERE " . implode(' AND ', $where) . "
  ";
  $st = $pdo->prepare($sqlComSem);
  $st->execute($params);
  $k = $st->fetch(PDO::FETCH_ASSOC) ?: [];
  $comBenef = (int)($k['com_benef'] ?? 0);
  $semBenef = (int)($k['sem_benef'] ?? 0);
  $bairrosDist = (int)($k['bairros_dist'] ?? 0);
  $sqlBenefDist = "
    SELECT COUNT(DISTINCT COALESCE(request_dist.ajuda_tipo_id, s.ajuda_tipo_id))
    FROM solicitantes s
    LEFT JOIN solicitacoes request_dist
      ON request_dist.solicitante_id = s.id
     AND COALESCE(request_dist.origem, '') <> 'cadastro_duplicada'
    WHERE " . implode(' AND ', $where);
  $st = $pdo->prepare($sqlBenefDist);
  $st->execute($params);
  $benefDist = (int)$st->fetchColumn();

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
        WHEN COALESCE(request_benefit.ajuda_tipo_id, s.ajuda_tipo_id) IS NULL
          OR COALESCE(request_benefit.ajuda_tipo_id, s.ajuda_tipo_id) = 0 THEN 'Sem benefício'
        ELSE COALESCE(at.nome,'—')
      END AS nome,
      COALESCE(request_benefit.ajuda_tipo_id, s.ajuda_tipo_id, 0) AS id,
      COUNT(DISTINCT s.id) AS c
    FROM solicitantes s
    LEFT JOIN solicitacoes request_benefit
      ON request_benefit.solicitante_id = s.id
     AND COALESCE(request_benefit.origem, '') <> 'cadastro_duplicada'
    LEFT JOIN ajudas_tipos at ON at.id = COALESCE(request_benefit.ajuda_tipo_id, s.ajuda_tipo_id)
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
  [$periodo, $di, $df, $bairroIds, $beneficioIds, $empregoIds, $sexo, $q, $baseDT, $where, $params] = buildFilters($pdo, $in);

  $sqlTotal = "SELECT COUNT(*) FROM solicitantes s WHERE " . implode(' AND ', $where);
  $st = $pdo->prepare($sqlTotal);
  $st->execute($params);
  $total = (int)$st->fetchColumn();

  $limit = max(1, min($maxRows, $total > 0 ? $total : $maxRows));
  $truncated = ($total > $limit);
  $benefitsSql = requestedBenefitsSql('s', 'at');

  $sql = "
    SELECT
      s.nome,
      s.cpf,
      s.data_nascimento,
      CONCAT(
        s.endereco,
        ', Nº ',
        s.numero,
        ' – ',
        COALESCE(b.nome,'—')
      ) AS endereco_completo,
      COALESCE(b.nome,'—') AS bairro_nome,
      s.telefone,
      {$benefitsSql} AS beneficio,
      DATE($baseDT) AS data_cadastro,
      s.resumo_caso,
      s.trabalho
    FROM solicitantes s
    LEFT JOIN bairros b ON b.id = s.bairro_id
    LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY DATE($baseDT) ASC, s.nome ASC
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
  [$periodo, $di, $df, $bairroIds, $beneficioIds, $empregoIds, $sexo, $q, $baseDT] = buildFilters($pdo, $in);

  // No relatório de cadastros, a data principal do filtro é a data de cadastro da pessoa.
  $wherePerson = ["1=1"];
  $whereRequest = ["COALESCE(sol.origem, '') <> 'cadastro_duplicada'"];
  $params = [];

  if (!empty($di)) {
    $wherePerson[] = "DATE($baseDT) >= :di";
    $params[':di'] = $di;
  }
  if (!empty($df)) {
    $wherePerson[] = "DATE($baseDT) <= :df";
    $params[':df'] = $df;
  }

  // Bairro é do solicitante
  addInFilter($wherePerson, $params, 's.bairro_id', 'bid', $bairroIds);

  // Benefício filtrado deve ser o SOLICITADO na solicitação
  addInFilter($whereRequest, $params, 'sol.ajuda_tipo_id', 'tid', $beneficioIds);

  // Emprego pretendido filtrado pelo resumo da solicitação
  addEmploymentSummaryFilter($pdo, $whereRequest, $params, 'sol.resumo_caso', $empregoIds, 'solemp');

  // Sexo/Gênero é do solicitante
  $sexoIn = trim((string)($in['sexo'] ?? '')); // "Masculino" | "Feminino" | "Outro"
  if ($sexoIn !== '') {
    $wherePerson[] = "LOWER(TRIM(COALESCE(s.genero,''))) = LOWER(TRIM(:sexo))";
    $params[':sexo'] = $sexoIn;
  }

  $requestAggregateSql = "
    SELECT
      sol.solicitante_id,
      GROUP_CONCAT(
        DISTINCT CASE
          WHEN sol.ajuda_tipo_id IS NULL OR sol.ajuda_tipo_id = 0 THEN 'Sem benefício'
          ELSE COALESCE(at.nome, '—')
        END
        ORDER BY at.nome
        SEPARATOR ' | '
      ) AS beneficio,
      GROUP_CONCAT(
        DISTINCT DATE_FORMAT(DATE(sol.data_solicitacao), '%d/%m/%Y')
        ORDER BY DATE(sol.data_solicitacao)
        SEPARATOR ' | '
      ) AS data_solicitacao,
      GROUP_CONCAT(
        DISTINCT NULLIF(TRIM(COALESCE(sol.resumo_caso, '')), '')
        ORDER BY sol.data_solicitacao
        SEPARATOR ' | '
      ) AS resumo_caso,
      MIN(sol.data_solicitacao) AS primeira_solicitacao
    FROM solicitacoes sol
    LEFT JOIN ajudas_tipos at ON at.id = sol.ajuda_tipo_id
    WHERE " . implode(' AND ', $whereRequest) . "
    GROUP BY sol.solicitante_id
  ";

  // Total de pessoas, não de linhas de benefício.
  $sqlTotal = "
    SELECT COUNT(*)
    FROM solicitantes s
    INNER JOIN ({$requestAggregateSql}) requested ON requested.solicitante_id = s.id
    WHERE " . implode(' AND ', $wherePerson);

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
      COALESCE(b.nome,'—') AS bairro_nome,
      s.telefone,
      requested.beneficio,
      DATE(COALESCE(s.created_at, s.updated_at)) AS data_cadastro,
      requested.data_solicitacao,
      requested.resumo_caso
    FROM solicitantes s
    INNER JOIN ({$requestAggregateSql}) requested ON requested.solicitante_id = s.id
    LEFT JOIN bairros b ON b.id = s.bairro_id
    WHERE " . implode(' AND ', $wherePerson) . "
    ORDER BY DATE(COALESCE(s.created_at, s.updated_at)) ASC, s.nome ASC, requested.primeira_solicitacao ASC
    LIMIT $limit
  ";


  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  return ['total' => $total, 'rows' => $rows, 'truncated' => $truncated];
}

function normalizeWorkStatus(?string $trabalho): string
{
  $trab = trim((string)$trabalho);
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

  return ($trabNorm === 'empregado(a)' || $trabNorm === 'empregado') ? 'Sim' : 'Não';
}

function reportExportContext(PDO $pdo, array $payload, string $geradoEm, int $maxRows = 10000): array
{
  $data = fetchAggregates($pdo, $payload);

  $di = (string)($data['di'] ?? '');
  $df = (string)($data['df'] ?? '');
  $k = $data['kpis'] ?? [];

  $bairroIds = normalizeIds($payload['bairro_id'] ?? []);
  $beneficioIds = normalizeIds($payload['beneficio_id'] ?? []);
  $empregoIds = normalizeIds($payload['emprego_id'] ?? []);
  $q = trim((string)($payload['q'] ?? ''));

  $sexo = strtolower(trim((string)($payload['sexo'] ?? '')));
  $sexoLabel = 'Todos';
  if ($sexo === 'masculino') $sexoLabel = 'Masculino';
  elseif ($sexo === 'feminino') $sexoLabel = 'Feminino';
  elseif ($sexo === 'outro') $sexoLabel = 'Outro';
  elseif ($sexo === 'nao_informado') $sexoLabel = 'Não informado';

  $bairroNomes = lookupNames($pdo, 'bairros', $bairroIds);
  $bairroNome = $bairroNomes ? implode(', ', $bairroNomes) : 'Todos';
  $benefNomes = lookupNames($pdo, 'ajudas_tipos', $beneficioIds);
  $benefNome = $benefNomes ? implode(', ', $benefNomes) : 'Todos';
  $empregoRows = fetchEmploymentOptions($pdo, $empregoIds);
  $empregoNome = $empregoRows ? implode(', ', array_map(static fn(array $row): string => (string)$row['nome'], $empregoRows)) : 'Todos';

  $linhaFiltros = [];
  $linhaFiltros[] = 'Período: ' . (string)($data['periodo_label'] ?? periodLabel(normalizePeriod($payload['periodo'] ?? 'mensal')));
  $linhaFiltros[] = $di ? ('Data inicial: ' . fmtDateBR($di)) : 'Data inicial: —';
  $linhaFiltros[] = $df ? ('Data final: ' . fmtDateBR($df)) : 'Data final: —';
  $linhaFiltros[] = 'Bairro: ' . $bairroNome;
  $linhaFiltros[] = 'Benefício: ' . $benefNome;
  $linhaFiltros[] = 'Emprego pretendido: ' . $empregoNome;
  $linhaFiltros[] = 'Sexo/Gênero: ' . $sexoLabel;
  if ($q !== '') $linhaFiltros[] = 'Busca benefício: ' . $q;

  $peoplePack = fetchPeopleForExport($pdo, $payload, $maxRows);
  $solicPack = fetchSolicitationsForExport($pdo, $payload, $maxRows);

  return [
    'data' => $data,
    'gerado_em' => $geradoEm,
    'di' => $di,
    'df' => $df,
    'total_geral' => (int)($k['total_geral'] ?? 0),
    'total_periodo' => (int)($k['total_periodo'] ?? 0),
    'bairro_nome' => $bairroNome,
    'beneficio_nome' => $benefNome,
    'linha_filtros' => $linhaFiltros,
    'people_pack' => $peoplePack,
    'solic_pack' => $solicPack,
  ];
}

function reportGeneratedAt(array $source): string
{
  $clientNow = trim((string)($source['client_now'] ?? ''));
  if ($clientNow !== '') {
    try {
      $dt = new DateTime($clientNow);
      $dt->setTimezone(new DateTimeZone('America/Manaus'));
      return $dt->format('d/m/Y H:i:s');
    } catch (Throwable $e) {
      // Usa o horário do servidor quando o horário do cliente vier inválido.
    }
  }

  return date('d/m/Y H:i:s');
}

function groupRowsByNeighborhood(array $rows): array
{
  $groups = [];
  foreach ($rows as $row) {
    $bairro = trim((string)($row['bairro_nome'] ?? '—'));
    if ($bairro === '') $bairro = '—';
    if (!isset($groups[$bairro])) $groups[$bairro] = [];
    $groups[$bairro][] = $row;
  }

  ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
  return $groups;
}

/** @return array<string,mixed> */
function cadastroPdfFilterSnapshot(PDO $pdo, array $input): array
{
  [$periodo, $di, $df, $bairroIds, $beneficioIds, $empregoIds, $sexo, $q] = buildFilters($pdo, $input);
  return [
    'periodo' => $periodo,
    'di' => $di,
    'df' => $df,
    'bairro_id' => $bairroIds,
    'beneficio_id' => $beneficioIds,
    'emprego_id' => $empregoIds,
    'sexo' => relatorio_cadastros_text($sexo, 30),
    'q' => relatorio_cadastros_text($q, 120),
  ];
}

function cadastroPdfFilterSummary(PDO $pdo, array $input): string
{
  $filters = cadastroPdfFilterSnapshot($pdo, $input);
  $parts = [
    'Período: ' . periodLabel((string)$filters['periodo']),
    'Data inicial: ' . ($filters['di'] ? fmtDateBR((string)$filters['di']) : '—'),
    'Data final: ' . ($filters['df'] ? fmtDateBR((string)$filters['df']) : '—'),
  ];

  $bairroNames = lookupNames($pdo, 'bairros', (array)$filters['bairro_id']);
  $parts[] = 'Bairros: ' . ($bairroNames ? implode(', ', $bairroNames) : 'Todos');

  $benefitNames = lookupNames($pdo, 'ajudas_tipos', (array)$filters['beneficio_id']);
  $parts[] = 'Benefícios: ' . ($benefitNames ? implode(', ', $benefitNames) : 'Todos');

  $employmentRows = fetchEmploymentOptions($pdo, (array)$filters['emprego_id']);
  $parts[] = 'Emprego pretendido: ' . ($employmentRows
    ? implode(', ', array_map(static fn(array $row): string => (string)$row['nome'], $employmentRows))
    : 'Todos');

  $parts[] = 'Sexo/Gênero: ' . ($filters['sexo'] !== '' ? (string)$filters['sexo'] : 'Todos');
  if ($filters['q'] !== '') {
    $parts[] = 'Busca de benefício: ' . (string)$filters['q'];
  }

  return implode(' | ', $parts);
}

/** @return array{total:int,available:int,used:int,page:int,pages:int,scope:string,rows:array<int,array<string,mixed>>} */
function fetchCadastroPdfCandidates(
  PDO $pdo,
  array $input,
  int $page,
  int $perPage,
  string $selectionSearch
): array {
  $selectionSearch = relatorio_cadastros_text($selectionSearch, 120);
  if ($selectionSearch !== '') {
    $baseDT = "COALESCE(s.created_at, s.updated_at)";
    $where = ['1=1'];
    $params = [];
  } else {
    [, , , , , , , , $baseDT, $where, $params] = buildFilters($pdo, $input);
  }

  if ($selectionSearch !== '') {
    $digits = only_digits($selectionSearch);
    $like = '%' . $selectionSearch . '%';
    $where[] = "(
      COALESCE(s.nome, '') LIKE :sel_nome
      OR COALESCE(s.cpf, '') LIKE :sel_cpf
      OR COALESCE(s.telefone, '') LIKE :sel_telefone
    )";
    $params[':sel_nome'] = $like;
    $params[':sel_cpf'] = $digits !== '' ? '%' . $digits . '%' : $like;
    $params[':sel_telefone'] = $digits !== '' ? '%' . $digits . '%' : $like;
  }

  $countStmt = $pdo->prepare("
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN used.solicitante_id IS NULL THEN 1 ELSE 0 END) AS disponiveis
    FROM solicitantes s
    LEFT JOIN relatorio_cadastro_lote_solicitantes used ON used.solicitante_id = s.id
    WHERE " . implode(' AND ', $where));
  $countStmt->execute($params);
  $counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];
  $total = (int)($counts['total'] ?? 0);
  $available = (int)($counts['disponiveis'] ?? 0);

  $perPage = max(10, min(100, $perPage));
  $pages = max(1, (int)ceil($total / $perPage));
  $page = max(1, min($pages, $page));
  $offset = ($page - 1) * $perPage;
  $benefitsSql = requestedBenefitsSql('s', 'at');

  $stmt = $pdo->prepare("
    SELECT
      s.id AS solicitante_id,
      s.nome,
      s.cpf,
      s.telefone,
      COALESCE(b.nome, '—') AS bairro_nome,
      {$benefitsSql} AS beneficio,
      DATE($baseDT) AS data_cadastro,
      used.lote_id,
      batch.criado_em AS lote_criado_em
    FROM solicitantes s
    LEFT JOIN bairros b ON b.id = s.bairro_id
    LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
    LEFT JOIN relatorio_cadastro_lote_solicitantes used ON used.solicitante_id = s.id
    LEFT JOIN relatorio_cadastro_lotes batch ON batch.id = used.lote_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY DATE($baseDT) ASC, s.nome ASC, s.id ASC
    LIMIT {$perPage} OFFSET {$offset}
  ");
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $reservedIdentityBatches = relatorio_cadastros_identity_batch_map($pdo);
  foreach ($rows as &$row) {
    if (!empty($row['lote_id'])) {
      continue;
    }
    foreach (relatorio_cadastros_person_identity_keys($row) as $identityKey) {
      if (isset($reservedIdentityBatches[$identityKey])) {
        $row['lote_id'] = $reservedIdentityBatches[$identityKey];
        $row['lote_criado_em'] = null;
        break;
      }
    }
  }
  unset($row);

  return [
    'total' => $total,
    'available' => $available,
    'used' => max(0, $total - $available),
    'page' => $page,
    'pages' => $pages,
    'scope' => $selectionSearch !== '' ? 'global' : 'filters',
    'rows' => $rows,
  ];
}

/** @return array<int,array<string,mixed>> */
function fetchCadastroPdfBatchRows(
  PDO $pdo,
  array $input,
  string $mode,
  array $requestedIds,
  int $limit,
  bool $continueAfterEndDate = false,
  array $excludedIds = [],
  array $excludedIdentityKeys = []
): array {
  if ($mode === 'manual') {
    $baseDT = "COALESCE(s.created_at, s.updated_at)";
    $where = ['1=1'];
    $params = [];
    addInFilter($where, $params, 's.id', 'pdfsid', $requestedIds);
  } else {
    [, , , , , , , , $baseDT, $where, $params] = buildFilters($pdo, $input);
    if ($continueAfterEndDate) {
      // Mantém todos os filtros de perfil, mas remove somente o teto da data.
      // Assim, um lote diário com 5 pessoas continua no dia seguinte até completar a quantidade.
      $where = array_values(array_filter(
        $where,
        static fn(string $condition): bool => !str_contains($condition, ' <= :df')
      ));
      unset($params[':df']);
    }
  }
  $where[] = 'used.solicitante_id IS NULL';
  addNotInFilter($where, $params, 's.id', 'pdfexclude', $excludedIds);

  // Busca uma margem maior para ignorar fichas alternativas da mesma pessoa e ainda completar o lote.
  $fetchLimit = $mode === 'primeiros'
    ? (normalizeIds($excludedIds) || $excludedIdentityKeys
      ? 5000
      : min(5000, max($limit, ($limit * 4), $limit + 100)))
    : 0;
  $sqlLimit = $fetchLimit > 0 ? ' LIMIT ' . $fetchLimit : '';
  $benefitsSql = requestedBenefitsSql('s', 'at');
  $stmt = $pdo->prepare("
    SELECT
      s.id AS solicitante_id,
      s.nome,
      s.cpf,
      s.data_nascimento,
      TRIM(CONCAT_WS(', ',
        NULLIF(TRIM(COALESCE(s.endereco, '')), ''),
        NULLIF(TRIM(CONCAT('Nº ', COALESCE(s.numero, ''))), 'Nº'),
        NULLIF(TRIM(COALESCE(s.complemento, '')), ''),
        NULLIF(TRIM(COALESCE(b.nome, '')), '')
      )) AS endereco_completo,
      COALESCE(b.nome, '—') AS bairro_nome,
      s.telefone,
      {$benefitsSql} AS beneficio,
      DATE($baseDT) AS data_cadastro,
      s.resumo_caso,
      s.trabalho
    FROM solicitantes s
    LEFT JOIN bairros b ON b.id = s.bairro_id
    LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
    LEFT JOIN relatorio_cadastro_lote_solicitantes used ON used.solicitante_id = s.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY DATE($baseDT) ASC, s.nome ASC, s.id ASC
    {$sqlLimit}
  ");
  $stmt->execute($params);
  $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $reservedIdentityKeys = relatorio_cadastros_identity_batch_map($pdo);
  $selectedIdentityKeys = [];
  foreach ($excludedIdentityKeys as $identityKey) {
    if (is_string($identityKey) && $identityKey !== '') {
      $selectedIdentityKeys[$identityKey] = true;
    }
  }
  $rows = [];
  foreach ($rawRows as $row) {
    $identityKeys = relatorio_cadastros_person_identity_keys($row);
    $alreadyReserved = false;
    foreach ($identityKeys as $identityKey) {
      if (isset($reservedIdentityKeys[$identityKey]) || isset($selectedIdentityKeys[$identityKey])) {
        $alreadyReserved = true;
        break;
      }
    }
    if ($alreadyReserved) {
      continue;
    }
    foreach ($identityKeys as $identityKey) {
      $selectedIdentityKeys[$identityKey] = true;
    }
    $rows[] = $row;
    if ($mode === 'primeiros' && count($rows) >= $limit) {
      break;
    }
  }

  $employmentOptions = fetchEmploymentOptions($pdo);
  foreach ($rows as &$row) {
    $row['emprego_pedido'] = detectRequestedEmployment(
      $pdo,
      (string)($row['resumo_caso'] ?? ''),
      (string)($row['beneficio'] ?? ''),
      $employmentOptions
    );
  }
  unset($row);

  return $rows;
}

try {
  ensureEmploymentOptionsTable($pdo);
} catch (Throwable $e) {
  // O relatório continua funcionando sem o filtro de empregos se a migração automática falhar.
}

$pdfCadastroFeatureError = null;
$recentCadastroPdfBatches = [];
try {
  relatorio_cadastros_ensure_schema($pdo);
  $recentCadastroPdfBatches = relatorio_cadastros_recent_batches($pdo, 20);
} catch (Throwable $e) {
  error_log('Falha ao preparar lotes do relatório de cadastros: ' . $e->getMessage());
  $pdfCadastroFeatureError = 'Não foi possível preparar as tabelas de controle do PDF.';
}

if (empty($_SESSION['relatorio_cadastros_csrf'])) {
  $_SESSION['relatorio_cadastros_csrf'] = bin2hex(random_bytes(32));
}
$pdfCadastroCsrf = (string)$_SESSION['relatorio_cadastros_csrf'];

if ((string)($_GET['pdf_candidates'] ?? '') === '1') {
  header('Content-Type: application/json; charset=UTF-8');

  $csrf = (string)($_POST['csrf_token'] ?? '');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || $csrf === ''
    || !hash_equals($pdfCadastroCsrf, $csrf)
  ) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'A sessão expirou. Atualize a página.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($pdfCadastroFeatureError !== null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $pdfCadastroFeatureError], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    $result = fetchCadastroPdfCandidates(
      $pdo,
      $_POST,
      max(1, (int)($_POST['page'] ?? 1)),
      max(10, min(100, (int)($_POST['per_page'] ?? 20))),
      (string)($_POST['selection_search'] ?? '')
    );
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } catch (Throwable $e) {
    error_log('Falha ao listar candidatos do PDF de cadastros: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível carregar os solicitantes.'], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

if ((string)($_GET['pdf_batch_create'] ?? '') === '1') {
  header('Content-Type: application/json; charset=UTF-8');

  $csrf = (string)($_POST['csrf_token'] ?? '');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || $csrf === ''
    || !hash_equals($pdfCadastroCsrf, $csrf)
  ) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'A sessão expirou. Atualize a página.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($pdfCadastroFeatureError !== null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $pdfCadastroFeatureError], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    $mode = (string)($_POST['modo'] ?? '');
    if (!in_array($mode, ['primeiros', 'manual'], true)) {
      throw new InvalidArgumentException('Modo de seleção inválido.');
    }

    $batchTitle = relatorio_cadastros_batch_title($_POST['titulo'] ?? '');

    $limit = 0;
    $requestedIds = [];
    if ($mode === 'primeiros') {
      $limitValue = filter_var($_POST['limite'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 5000],
      ]);
      if ($limitValue === false) {
        throw new InvalidArgumentException('Informe uma quantidade entre 1 e 5.000.');
      }
      $limit = (int)$limitValue;
    } else {
      $requestedIds = relatorio_cadastros_parse_ids($_POST['solicitante_ids'] ?? '');
      if (!$requestedIds) {
        throw new InvalidArgumentException('Selecione pelo menos um solicitante.');
      }
      if (count($requestedIds) > 5000) {
        throw new InvalidArgumentException('Selecione no máximo 5.000 solicitantes por lote.');
      }
    }

    $rows = fetchCadastroPdfBatchRows($pdo, $_POST, $mode, $requestedIds, $limit, $mode === 'primeiros');
    if ($mode === 'manual' && count($rows) !== count($requestedIds)) {
      throw new RuntimeException('Um ou mais solicitantes não existem mais ou já foram utilizados em outro lote. Atualize a lista.');
    }

    $filterSnapshot = cadastroPdfFilterSnapshot($pdo, $_POST);
    $filterSummary = cadastroPdfFilterSummary($pdo, $_POST);
    if ($mode === 'manual') {
      $filterSnapshot['selecao_manual_global'] = true;
      $filterSummary = 'Seleção manual: cadastros escolhidos diretamente em todo o sistema, sem limitação pelos filtros da página.';
    } elseif ($mode === 'primeiros') {
      $filterSnapshot['continuar_apos_data_final'] = true;
      $filterSummary .= ' | Seleção automática: continuou pelos próximos dias disponíveis quando necessário para completar o lote.';
    }

    $result = relatorio_cadastros_create_batch(
      $pdo,
      $mode,
      $batchTitle,
      $filterSnapshot,
      $filterSummary,
      $rows,
      (int)($_SESSION['user_id'] ?? 0),
      (string)($_SESSION['user_nome'] ?? '')
    );

    echo json_encode([
      'ok' => true,
      'lote_id' => $result['lote_id'],
      'total_solicitantes' => $result['total_solicitantes'],
      'solicitante_ids' => $result['solicitante_ids'],
      'pdf_url' => 'relatorioCadastrosPdf.php?lote_id=' . $result['lote_id'] . '&autoprint=1',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } catch (PDOException $e) {
    error_log('Falha de banco ao criar lote do PDF de cadastros: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'O banco não concluiu o lote. Nenhum solicitante foi reservado.'], JSON_UNESCAPED_UNICODE);
  } catch (InvalidArgumentException | RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    error_log('Falha ao criar lote do PDF de cadastros: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível criar o lote. Nenhum solicitante foi reservado.'], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

if ((string)($_GET['pdf_batch_group_preview'] ?? '') === '1') {
  header('Content-Type: application/json; charset=UTF-8');
  $csrf = (string)($_POST['csrf_token'] ?? '');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $csrf === '' || !hash_equals($pdfCadastroCsrf, $csrf)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'A sessão expirou. Atualize a página.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    if ($pdfCadastroFeatureError !== null) {
      throw new RuntimeException($pdfCadastroFeatureError);
    }
    $batchCount = filter_var($_POST['quantidade_lotes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    $batchSize = filter_var($_POST['tamanho_lote'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]);
    if ($batchCount === false || $batchSize === false || ((int)$batchCount * (int)$batchSize) > 5000) {
      throw new InvalidArgumentException('Informe uma quantidade válida de lotes e pessoas, com no máximo 5.000 cadastros no total.');
    }
    $requestedTotal = (int)$batchCount * (int)$batchSize;
    $rows = fetchCadastroPdfBatchRows($pdo, $_POST, 'primeiros', [], $requestedTotal, true);
    if (count($rows) < $requestedTotal) {
      $startDate = normalizeDate($_POST['di'] ?? null) ?: 'a data inicial selecionada';
      throw new RuntimeException("Há apenas " . count($rows) . " cadastro(s) disponível(is) a partir de {$startDate}, mesmo pesquisando os próximos dias. São necessários {$requestedTotal}.");
    }
    echo json_encode([
      'ok' => true,
      'quantidade_lotes' => (int)$batchCount,
      'tamanho_lote' => (int)$batchSize,
      'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } catch (InvalidArgumentException | RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    error_log('Falha ao preparar múltiplos lotes do PDF de cadastros: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível preparar os lotes agora.'], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

if ((string)($_GET['pdf_batch_group_autofill'] ?? '') === '1') {
  header('Content-Type: application/json; charset=UTF-8');
  $csrf = (string)($_POST['csrf_token'] ?? '');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $csrf === '' || !hash_equals($pdfCadastroCsrf, $csrf)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'A sessão expirou. Atualize a página.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    if ($pdfCadastroFeatureError !== null) {
      throw new RuntimeException($pdfCadastroFeatureError);
    }
    $batchSize = filter_var($_POST['tamanho_lote'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]);
    $assignments = json_decode((string)($_POST['lotes_json'] ?? ''), true);
    if ($batchSize === false || !is_array($assignments) || !$assignments || count($assignments) > 100) {
      throw new InvalidArgumentException('Informe os lotes e a quantidade de pessoas por lote para completar as vagas.');
    }
    if ((int)$batchSize * count($assignments) > 5000) {
      throw new InvalidArgumentException('Os lotes podem ter, no total, no máximo 5.000 pessoas.');
    }

    $allIds = [];
    foreach ($assignments as $assignment) {
      if (!is_array($assignment)) {
        throw new InvalidArgumentException('A composição dos lotes é inválida.');
      }
      $personIds = normalizeIds((array)($assignment['solicitante_ids'] ?? []));
      if (count($personIds) !== count((array)($assignment['solicitante_ids'] ?? [])) || count($personIds) > (int)$batchSize) {
        throw new InvalidArgumentException('Há um lote com pessoas repetidas ou acima da quantidade configurada.');
      }
      foreach ($personIds as $personId) {
        if (isset($allIds[$personId])) {
          throw new InvalidArgumentException('Um cadastro não pode aparecer em mais de um lote.');
        }
        $allIds[$personId] = $personId;
      }
    }

    $totalCapacity = count($assignments) * (int)$batchSize;
    $remaining = $totalCapacity - count($allIds);
    if ($remaining <= 0) {
      echo json_encode(['ok' => true, 'preenchimentos' => [], 'total_adicionado' => 0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $currentPeople = [];
    $groupId = filter_var($_POST['grupo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($groupId !== false) {
      $currentPack = relatorio_cadastros_get_group($pdo, (int)$groupId);
      if (!$currentPack) {
        throw new RuntimeException('Grupo de lotes não encontrado. Atualize a tela.');
      }
      foreach ($currentPack['lotes'] as $batch) {
        foreach ((array)($batch['pessoas'] ?? []) as $person) {
          $currentPeople[(int)($person['solicitante_id'] ?? 0)] = $person;
        }
      }
    }

    $manualPeople = [];
    $lookupIds = [];
    foreach ($allIds as $personId) {
      if (isset($currentPeople[$personId])) {
        $manualPeople[$personId] = $currentPeople[$personId];
      } else {
        $lookupIds[] = $personId;
      }
    }
    if ($lookupIds) {
      $lookupRows = fetchCadastroPdfBatchRows($pdo, $_POST, 'manual', $lookupIds, 0);
      if (count($lookupRows) !== count($lookupIds)) {
        throw new RuntimeException('Uma pessoa escolhida não está mais disponível. Atualize a lista antes de completar as vagas.');
      }
      foreach ($lookupRows as $person) {
        $manualPeople[(int)$person['solicitante_id']] = $person;
      }
    }

    $manualIdentityKeys = [];
    foreach ($manualPeople as $person) {
      foreach (relatorio_cadastros_person_identity_keys($person) as $identityKey) {
        $manualIdentityKeys[$identityKey] = true;
      }
    }
    $automaticPeople = fetchCadastroPdfBatchRows(
      $pdo,
      $_POST,
      'primeiros',
      [],
      $remaining,
      true,
      array_values($allIds),
      array_keys($manualIdentityKeys)
    );
    if (count($automaticPeople) < $remaining) {
      throw new RuntimeException('Não há cadastros disponíveis suficientes para completar todas as vagas sem repetir pessoas.');
    }

    $automaticIndex = 0;
    $fills = [];
    foreach ($assignments as $batchIndex => $assignment) {
      $vacancies = (int)$batchSize - count((array)($assignment['solicitante_ids'] ?? []));
      if ($vacancies <= 0) continue;
      $fills[] = [
        'lote_indice' => $batchIndex,
        'pessoas' => array_slice($automaticPeople, $automaticIndex, $vacancies),
      ];
      $automaticIndex += $vacancies;
    }

    echo json_encode([
      'ok' => true,
      'preenchimentos' => $fills,
      'total_adicionado' => $automaticIndex,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } catch (InvalidArgumentException | RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    error_log('Falha ao completar automaticamente os lotes do PDF de cadastros: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível completar os lotes agora.'], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

if ((string)($_GET['pdf_batch_group_create'] ?? '') === '1'
  || (string)($_GET['pdf_batch_group_save'] ?? '') === '1'
  || (string)($_GET['pdf_batch_group_load'] ?? '') === '1') {
  header('Content-Type: application/json; charset=UTF-8');
  $csrf = (string)($_POST['csrf_token'] ?? '');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $csrf === '' || !hash_equals($pdfCadastroCsrf, $csrf)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'A sessão expirou. Atualize a página.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    if ($pdfCadastroFeatureError !== null) {
      throw new RuntimeException($pdfCadastroFeatureError);
    }
    $action = (string)($_GET['pdf_batch_group_create'] ?? '') === '1'
      ? 'create'
      : ((string)($_GET['pdf_batch_group_save'] ?? '') === '1' ? 'save' : 'load');

    if ($action === 'load') {
      $groupId = filter_var($_POST['grupo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
      if ($groupId !== false) {
        $pack = relatorio_cadastros_get_group($pdo, (int)$groupId);
      } else {
        $batchId = filter_var($_POST['lote_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($batchId === false) {
          throw new InvalidArgumentException('Lote inválido.');
        }
        $groupId = relatorio_cadastros_group_for_batch($pdo, (int)$batchId);
        $pack = relatorio_cadastros_get_group($pdo, $groupId);
      }
      if (!$pack) {
        throw new RuntimeException('Grupo de lotes não encontrado.');
      }
      echo json_encode(['ok' => true] + $pack, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $groupTitle = relatorio_cadastros_batch_title($_POST['titulo'] ?? '');
    $assignments = json_decode((string)($_POST['lotes_json'] ?? ''), true);
    if (!is_array($assignments) || !$assignments || count($assignments) > 100) {
      throw new InvalidArgumentException('A composição dos lotes é inválida.');
    }
    $allIds = [];
    foreach ($assignments as $assignment) {
      if (!is_array($assignment)) {
        throw new InvalidArgumentException('A composição dos lotes é inválida.');
      }
      foreach ((array)($assignment['solicitante_ids'] ?? []) as $personId) {
        $personId = filter_var($personId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($personId === false || isset($allIds[(int)$personId])) {
          throw new InvalidArgumentException('Um cadastro não pode aparecer em mais de um lote.');
        }
        $allIds[(int)$personId] = (int)$personId;
      }
    }
    if (!$allIds || count($allIds) > 5000) {
      throw new InvalidArgumentException('Informe entre 1 e 5.000 cadastros para os lotes.');
    }

    if ($action === 'create') {
      foreach ($assignments as $index => &$assignment) {
        $assignment['titulo'] = relatorio_cadastros_batch_title($assignment['titulo'] ?? ($groupTitle . ' - Lote ' . ($index + 1)));
      }
      unset($assignment);
      $rows = fetchCadastroPdfBatchRows($pdo, $_POST, 'manual', array_values($allIds), 0);
      if (count($rows) !== count($allIds)) {
        throw new RuntimeException('Um ou mais cadastros não estão mais disponíveis. Atualize a prévia dos lotes.');
      }
      $rowsById = [];
      foreach ($rows as $row) {
        $rowsById[(int)$row['solicitante_id']] = $row;
      }
      $definitions = [];
      foreach ($assignments as $assignment) {
        $definitionRows = [];
        foreach ((array)$assignment['solicitante_ids'] as $personId) {
          $definitionRows[] = $rowsById[(int)$personId];
        }
        $definitions[] = ['titulo' => $assignment['titulo'], 'rows' => $definitionRows];
      }
      $filterSnapshot = cadastroPdfFilterSnapshot($pdo, $_POST);
      $filterSnapshot['continuar_apos_data_final'] = true;
      $configuredBatchSize = filter_var($_POST['tamanho_lote'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]);
      if ($configuredBatchSize !== false) {
        $filterSnapshot['tamanho_lote'] = (int)$configuredBatchSize;
      }
      $filterSummary = cadastroPdfFilterSummary($pdo, $_POST)
        . ' | Seleção automática: quando a data final não completou a quantidade, continuou pelos próximos dias disponíveis.';
      $result = relatorio_cadastros_create_group(
        $pdo,
        $groupTitle,
        $filterSnapshot,
        $filterSummary,
        $definitions,
        (int)($_SESSION['user_id'] ?? 0),
        (string)($_SESSION['user_nome'] ?? '')
      );
      $pack = relatorio_cadastros_get_group($pdo, (int)$result['grupo_id']);
      echo json_encode(['ok' => true, 'created' => true] + ($pack ?: []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $groupId = filter_var($_POST['grupo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($groupId === false) {
      throw new InvalidArgumentException('Grupo de lotes inválido.');
    }
    $currentPack = relatorio_cadastros_get_group($pdo, (int)$groupId);
    if (!$currentPack) {
      throw new RuntimeException('Grupo de lotes não encontrado.');
    }
    $currentIds = [];
    foreach ($currentPack['lotes'] as $batch) {
      foreach ((array)($batch['pessoas'] ?? []) as $person) {
        $currentIds[(int)$person['solicitante_id']] = true;
      }
    }
    $newIds = array_values(array_filter($allIds, static fn(int $id): bool => !isset($currentIds[$id])));
    $newRows = $newIds ? fetchCadastroPdfBatchRows($pdo, $_POST, 'manual', $newIds, 0) : [];
    if (count($newRows) !== count($newIds)) {
      throw new RuntimeException('Um ou mais cadastros adicionados não estão mais disponíveis. Atualize a lista.');
    }
    $pack = relatorio_cadastros_save_group($pdo, (int)$groupId, $groupTitle, $assignments, $newRows);
    echo json_encode(['ok' => true] + ($pack ?: []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } catch (InvalidArgumentException | RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    error_log('Falha ao manipular grupo de lotes do PDF de cadastros: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível salvar os lotes agora.'], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

if ((string)($_GET['pdf_batch_update'] ?? '') === '1') {
  header('Content-Type: application/json; charset=UTF-8');

  $csrf = (string)($_POST['csrf_token'] ?? '');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || $csrf === ''
    || !hash_equals($pdfCadastroCsrf, $csrf)
  ) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'A sessão expirou. Atualize a página.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    if ($pdfCadastroFeatureError !== null) {
      throw new RuntimeException($pdfCadastroFeatureError);
    }
    $batchId = filter_var($_POST['lote_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($batchId === false) {
      throw new InvalidArgumentException('Lote inválido.');
    }
    $result = relatorio_cadastros_update_batch_title($pdo, (int)$batchId, $_POST['titulo'] ?? '');
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } catch (InvalidArgumentException | RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    error_log('Falha ao editar lote do PDF de cadastros: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível editar o lote agora.'], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

if ((string)($_GET['pdf_batch_delete'] ?? '') === '1') {
  header('Content-Type: application/json; charset=UTF-8');

  $csrf = (string)($_POST['csrf_token'] ?? '');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || $csrf === ''
    || !hash_equals($pdfCadastroCsrf, $csrf)
  ) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'A sessão expirou. Atualize a página.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    if ($pdfCadastroFeatureError !== null) {
      throw new RuntimeException($pdfCadastroFeatureError);
    }
    $batchId = filter_var($_POST['lote_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($batchId === false) {
      throw new InvalidArgumentException('Lote inválido.');
    }
    $result = relatorio_cadastros_delete_batch($pdo, (int)$batchId);
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } catch (InvalidArgumentException | RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    error_log('Falha ao excluir lote do PDF de cadastros: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível excluir o lote agora.'], JSON_UNESCAPED_UNICODE);
  }
  exit;
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

  $payload = $_POST ?: $_GET;
  $ctx = reportExportContext($pdo, $payload, reportGeneratedAt($payload), 10000);
  $data = $ctx['data'];
  $geradoEm = $ctx['gerado_em'];
  $totalGeral = $ctx['total_geral'];
  $totalPeriodo = $ctx['total_periodo'];
  $benefNome = $ctx['beneficio_nome'];
  $linhaFiltros = $ctx['linha_filtros'];
  $peoplePack = $ctx['people_pack'];
  $peopleTotal = (int)($peoplePack['total'] ?? 0);
  $peopleRows = $peoplePack['rows'] ?? [];
  $peopleTrunc = (bool)($peoplePack['truncated'] ?? false);
  $solicPack = $ctx['solic_pack'];
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

  // Print area da aba solicitacoes (A:I)
  $rowsSolic = is_array($solicRows) ? count($solicRows) : 0;
  $lastRowSolic = 5 + max(1, $rowsSolic);
  $printAreaSolic = "solicitacoes!R1C1:R{$lastRowSolic}C9";

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
            $isEmpregado = normalizeWorkStatus((string) ($p['trabalho'] ?? ''));
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

      <Table ss:DefaultRowHeight="22" ss:ExpandedColumnCount="9" ss:ExpandedRowCount="<?= (int) ($lastRowSolic) ?>">
        <Column ss:Width="70" />
        <Column ss:Width="380" />
        <Column ss:Width="150" />
        <Column ss:Width="450" />
        <Column ss:Width="140" />
        <Column ss:Width="280" />
        <Column ss:Width="120" />
        <Column ss:Width="120" />
        <Column ss:Width="450" />

        <Row ss:Height="30">
          <Cell ss:StyleID="sTitle" ss:MergeAcross="8">
            <Data ss:Type="String"><?= $xmlEsc('Histórico de Solicitações (Conforme Filtro)') ?></Data>
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
              ss:Type="String"><?= $xmlEsc("Total de pessoas com solicitações: {$solicTotal}" . ($solicTrunc ? "  (⚠ lista truncada)" : "")) ?></Data>
          </Cell>
        </Row>

        <Row ss:Height="26">
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Nº</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Nome</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">CPF</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Endereço Completo</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Telefone</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Benefício (Solicitado)</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Data cadastro</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Data Solicitação</Data></Cell>
          <Cell ss:StyleID="sHeader"><Data ss:Type="String">Resumo do Caso</Data></Cell>
        </Row>

        <?php if (empty($solicRows)): ?>
          <Row ss:Height="24">
            <Cell ss:StyleID="sText" ss:MergeAcross="8">
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
            $dtsol = (string) ($p['data_solicitacao'] ?? '');
            $dtsolBR = $dtsol !== '' ? $dtsol : '—';
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
              <Cell ss:StyleID="sText"><Data ss:Type="String"><?= $xmlEsc($dtsolBR) ?></Data></Cell>
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
   PRINT/PDF (HTML para imprimir/salvar PDF)
   =========================== */
$printFlag = (string)($_GET['print'] ?? $_POST['print'] ?? '');
if ($printFlag === '1') {
  $payload = $_POST ?: $_GET;
  $ctx = reportExportContext($pdo, $payload, reportGeneratedAt($payload), 10000);

  $data = $ctx['data'];
  $geradoEm = (string)$ctx['gerado_em'];
  $benefNome = (string)$ctx['beneficio_nome'];
  $linhaFiltros = $ctx['linha_filtros'];
  $totalGeral = (int)$ctx['total_geral'];
  $totalPeriodo = (int)$ctx['total_periodo'];

  $peoplePack = $ctx['people_pack'];
  $peopleTotal = (int)($peoplePack['total'] ?? 0);
  $peopleRows = is_array($peoplePack['rows'] ?? null) ? $peoplePack['rows'] : [];
  $peopleTrunc = (bool)($peoplePack['truncated'] ?? false);

  $benefRows = is_array($data['benef_table'] ?? null) ? $data['benef_table'] : [];
  $selectedNeighborhoods = normalizeIds($payload['bairro_id'] ?? []);
  $selectedEmploymentRows = fetchEmploymentOptions($pdo, normalizeIds($payload['emprego_id'] ?? []));
  $splitByNeighborhood = count($selectedNeighborhoods) !== 1;
  $peopleGroups = empty($peopleRows) ? [] : ($splitByNeighborhood ? groupRowsByNeighborhood($peopleRows) : ['' => $peopleRows]);

  while (ob_get_level()) {
    ob_end_clean();
  }
?>
  <!DOCTYPE html>
  <html lang="pt-br">

  <head>
    <meta charset="utf-8">
    <title>Relatório de Benefícios - PDF</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      @page {
        size: A4 landscape;
        margin: 7mm;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        color: #111827;
        background: #fff;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10px;
        line-height: 1.25;
      }

      .toolbar {
        position: sticky;
        top: 0;
        z-index: 10;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding: 10px;
        background: #f5f7fb;
        border-bottom: 1px solid #d9dee8;
      }

      .toolbar button,
      .toolbar a {
        border: 1px solid #c8d0dc;
        border-radius: 4px;
        background: #fff;
        color: #1f2937;
        cursor: pointer;
        font: 600 13px Arial, Helvetica, sans-serif;
        padding: 8px 12px;
        text-decoration: none;
      }

      .toolbar .primary {
        border-color: #435ebe;
        background: #435ebe;
        color: #fff;
      }

      .sheet {
        width: 100%;
        page-break-after: always;
      }

      .sheet:last-child {
        page-break-after: auto;
      }

      .report-title {
        margin: 0;
        padding: 7px 10px;
        border: 1px solid #1d4ed8;
        background: #1d4ed8;
        color: #fff;
        text-align: center;
        font-size: 15px;
        font-weight: 700;
      }

      .report-head {
        margin-bottom: 8px;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
      }

      .report-meta {
        display: grid;
        gap: 0;
      }

      .bairro-label {
        margin: 0;
        padding: 6px 8px;
        border-top: 1px solid #93c5fd;
        background: #bfdbfe;
        color: #1e3a8a;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
      }

      .meta {
        margin: 0;
        padding: 5px 8px;
        border-top: 1px solid #bfdbfe;
        font-weight: 700;
      }

      .meta:nth-child(even) {
        background: #dbeafe;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        border: 1px solid #1e3a8a;
      }

      th,
      td {
        border: 1px solid #93c5fd;
        padding: 4px 5px;
        vertical-align: top;
        word-break: break-word;
        overflow-wrap: anywhere;
      }

      th {
        border-color: #1e40af;
        background: #2563eb;
        color: #fff;
        font-weight: 700;
        text-align: left;
      }

      tbody tr:nth-child(even) td {
        background: #f8fbff;
      }

      tbody tr:nth-child(odd) td {
        background: #fff;
      }

      tbody tr:hover td {
        background: #eff6ff;
      }

      .text-end {
        text-align: right;
      }

      .text-center {
        text-align: center;
      }

      .nowrap {
        white-space: nowrap;
      }

      .benef-col-name {
        width: 68%;
      }

      .benef-col-count {
        width: 14%;
      }

      .benef-col-pct {
        width: 18%;
      }

      .people-col-n {
        width: 3%;
      }

      .people-col-name {
        width: 15%;
      }

      .people-col-cpf {
        width: 6.5%;
      }

      .people-col-age {
        width: 4%;
      }

      .people-col-address {
        width: 16.5%;
      }

      .people-col-phone {
        width: 6.5%;
      }

      .people-col-benefit {
        width: 11.5%;
      }

      .people-col-requested-job {
        width: 8.5%;
      }

      .people-col-date {
        width: 5.5%;
      }

      .people-col-work {
        width: 5%;
      }

      .people-col-summary {
        width: 17.5%;
      }

      .solic-col-summary {
        width: 16%;
      }

      @media print {
        .toolbar {
          display: none;
        }

        body {
          font-size: 9px;
        }

        thead {
          display: table-header-group;
        }

        tr {
          break-inside: avoid;
        }

        tbody tr:hover td {
          background: inherit;
        }
      }
    </style>
  </head>

  <body>
    <div class="toolbar">
      <a href="relatoriosCadastros.php">Voltar</a>
      <button type="button" class="primary" onclick="window.print()">Baixar PDF / Imprimir</button>
    </div>

    <section class="sheet">
      <div class="report-head">
        <h1 class="report-title">Relatório de Benefícios (ajudas_tipos) - ANEXO</h1>
        <div class="report-meta">
          <p class="meta">Gerado em: <?= e($geradoEm) ?></p>
          <p class="meta"><?= e(implode('  |  ', $linhaFiltros)) ?></p>
          <p class="meta">Total de pessoas cadastradas (GERAL): <?= $totalGeral ?> | Total no período: <?= $totalPeriodo ?></p>
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th class="benef-col-name">Benefício (ajuda_tipo)</th>
            <th class="benef-col-count text-end">Pessoas</th>
            <th class="benef-col-pct text-end">% do período</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($benefRows)): ?>
            <tr>
              <td colspan="3" class="text-center">Nenhum registro encontrado para os filtros selecionados.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($benefRows as $r): ?>
              <tr>
                <td><?= e((string)($r['nome'] ?? '—')) ?></td>
                <td class="text-end"><?= (int)($r['count'] ?? 0) ?></td>
                <td class="text-end"><?= e(number_format((float)($r['pct'] ?? 0), 2, ',', '.')) ?>%</td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <?php if (empty($peopleGroups)): ?>
      <section class="sheet">
        <div class="report-head">
          <h1 class="report-title">Pessoas do Benefício - <?= e($benefNome) ?></h1>
          <div class="report-meta">
            <p class="meta">Gerado em: <?= e($geradoEm) ?></p>
            <p class="meta"><?= e(implode('  |  ', $linhaFiltros)) ?></p>
            <p class="meta">Total de pessoas listadas: <?= $peopleTotal ?><?= $peopleTrunc ? ' (lista truncada por limite de exportação)' : '' ?></p>
          </div>
        </div>
        <table>
          <tbody>
            <tr>
              <td class="text-center">Nenhum registro encontrado para os filtros selecionados.</td>
            </tr>
          </tbody>
        </table>
      </section>
    <?php else: ?>
      <?php foreach ($peopleGroups as $bairroGrupo => $rowsGrupo): ?>
        <section class="sheet">
          <div class="report-head">
            <h1 class="report-title">Pessoas do Benefício - <?= e($benefNome) ?><?= $splitByNeighborhood ? ' | Bairro: ' . e($bairroGrupo) : '' ?></h1>
            <div class="report-meta">
              <?php if ($splitByNeighborhood): ?>
                <p class="bairro-label">Bairro: <?= e($bairroGrupo) ?></p>
              <?php endif; ?>
              <p class="meta">Gerado em: <?= e($geradoEm) ?></p>
              <p class="meta"><?= e(implode('  |  ', $linhaFiltros)) ?></p>
              <p class="meta">Total de pessoas listadas: <?= $peopleTotal ?><?= $peopleTrunc ? ' (lista truncada por limite de exportação)' : '' ?><?= $splitByNeighborhood ? ' | Nesta página: ' . count($rowsGrupo) : '' ?></p>
            </div>
          </div>

          <table>
            <thead>
              <tr>
                <th class="people-col-n text-end">Nº</th>
                <th class="people-col-name">Nome</th>
                <th class="people-col-cpf">CPF</th>
                <th class="people-col-age text-end">Idade</th>
                <th class="people-col-address">Endereço Completo</th>
                <th class="people-col-phone">Telefone</th>
                <th class="people-col-benefit">Benefício</th>
                <th class="people-col-requested-job">Emprego pedido</th>
                <th class="people-col-date">Data cadastro</th>
                <th class="people-col-work">Empregado</th>
                <th class="people-col-summary">Resumo do Caso</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 1;
              foreach ($rowsGrupo as $p): ?>
                <?php
                $dtcad = (string)($p['data_cadastro'] ?? '');
                ?>
                <tr>
                  <td class="text-end"><?= $i ?></td>
                  <td><?= e((string)($p['nome'] ?? '—')) ?></td>
                  <td><?= e((string)($p['cpf'] ?? '')) ?></td>
                  <td class="text-end"><?= e(calculateAge((string)($p['data_nascimento'] ?? ''))) ?></td>
                  <td><?= e((string)($p['endereco_completo'] ?? '—')) ?></td>
                  <td><?= e((string)($p['telefone'] ?? '')) ?></td>
                  <td><?= e((string)($p['beneficio'] ?? '—')) ?></td>
                  <td><?= e(detectRequestedEmployment($pdo, (string)($p['resumo_caso'] ?? ''), (string)($p['beneficio'] ?? ''), $selectedEmploymentRows)) ?></td>
                  <td><?= e($dtcad ? fmtDateBR($dtcad) : '—') ?></td>
                  <td><?= e(normalizeWorkStatus((string)($p['trabalho'] ?? ''))) ?></td>
                  <td><?= e((string)($p['resumo_caso'] ?? '')) ?></td>
                </tr>
              <?php $i++;
              endforeach; ?>
            </tbody>
          </table>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>

    <script>
      window.addEventListener('load', function() {
        setTimeout(function() {
          window.print();
        }, 300);
      });
    </script>
  </body>

  </html>
<?php
  exit;
}

/* ===========================
   DADOS PARA SELECTS + PRIMEIRA CARGA
   =========================== */
$bairros = [];
$beneficios = [];
$empregos = [];
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

$empregos = fetchEmploymentOptions($pdo);

// Primeira carga (mensal padrão)
$initial = fetchAggregates($pdo, ['periodo' => 'mensal']);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <title>Relatório de Cadastros — ANEXO</title>
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

    .beneficio-multiselect {
      position: relative;
    }

    .beneficio-multiselect .dropdown-toggle {
      display: flex;
      align-items: center;
      justify-content: space-between;
      min-height: 38px;
      text-align: left;
      white-space: normal;
    }

    .beneficio-menu {
      width: 100%;
      max-height: 280px;
      overflow-y: auto;
      padding: .5rem;
    }

    .beneficio-option {
      display: flex;
      align-items: flex-start;
      gap: .5rem;
      padding: .4rem .45rem;
      border-radius: 6px;
      cursor: pointer;
      line-height: 1.25;
    }

    .beneficio-option:hover {
      background: #f2f7ff;
    }

    .beneficio-option input {
      margin-top: .12rem;
      flex: 0 0 auto;
    }

    .beneficio-summary {
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .pdf-builder-modal .modal-content {
      border: 0;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 24px 64px rgba(15, 23, 42, .24);
    }

    .pdf-builder-modal .modal-header {
      align-items: flex-start;
      padding: 1.35rem 1.5rem;
      color: #fff;
      border: 0;
      background: linear-gradient(135deg, #1f3b8f 0%, #3457d5 58%, #4f73eb 100%);
    }

    .pdf-builder-modal .btn-close {
      margin-top: .1rem;
      filter: invert(1) grayscale(100%) brightness(200%);
    }

    .pdf-builder-modal .modal-body {
      background: #f8fafc;
    }

    .pdf-mode-panel {
      padding: 1rem;
      border: 1px solid #d7e2ff;
      border-radius: 14px;
      background: linear-gradient(135deg, #f5f8ff 0%, #fff 100%);
    }

    .pdf-title-panel {
      padding: 1rem;
      border: 1px solid #cfd8ea;
      border-radius: 14px;
      background: #fff;
      box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
    }

    .pdf-title-panel .form-control {
      min-height: 44px;
      border-color: #b8c6e6;
      font-weight: 700;
    }

    .pdf-title-panel .form-control:focus {
      border-color: #4f73eb;
      box-shadow: 0 0 0 .2rem rgba(79, 115, 235, .14);
    }

    .pdf-mode-icon {
      display: inline-grid;
      width: 42px;
      height: 42px;
      place-items: center;
      flex: 0 0 42px;
      color: #2f55ca;
      border-radius: 12px;
      background: #e8efff;
      font-size: 1.15rem;
    }

    .pdf-count-pill {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      min-height: 31px;
      padding: .3rem .65rem;
      border: 1px solid #e4e7ec;
      border-radius: 999px;
      color: #475467;
      background: #fff;
      font-size: .82rem;
      font-weight: 700;
    }

    .pdf-candidates-wrap {
      max-height: 420px;
      overflow: auto;
      border: 1px solid #e4e7ec;
      border-radius: 12px;
      background: #fff;
    }

    .pdf-batch-workspace {
      border: 1px solid #dbe5ff;
      border-radius: 16px;
      padding: 1rem;
      background: linear-gradient(135deg, #f8faff 0%, #fff 54%);
    }

    .pdf-builder-steps {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: .65rem;
      margin-bottom: 1.25rem;
    }

    .pdf-builder-step {
      display: flex;
      align-items: center;
      gap: .65rem;
      min-width: 0;
      padding: .7rem .8rem;
      border: 1px solid #e4e7ec;
      border-radius: 12px;
      color: #667085;
      background: #fff;
    }

    .pdf-builder-step-number {
      display: inline-grid;
      flex: 0 0 28px;
      width: 28px;
      height: 28px;
      place-items: center;
      border-radius: 50%;
      color: #fff;
      background: #2f63d8;
      font-size: .78rem;
      font-weight: 800;
    }

    .pdf-builder-step strong,
    .pdf-builder-step span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pdf-builder-step strong { color: #344054; font-size: .84rem; }
    .pdf-builder-step span { font-size: .72rem; }

    .pdf-candidate-panel {
      padding: 1rem;
      border: 1px solid #e4e7ec;
      border-radius: 16px;
      background: #fff;
    }

    .pdf-candidate-panel.is-locked {
      background: #fafafa;
    }

    .pdf-candidate-panel.is-locked .pdf-candidates-wrap {
      opacity: .55;
      pointer-events: none;
    }

    .pdf-active-batch-chip {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .35rem .65rem;
      border-radius: 999px;
      color: #1d4ed8;
      background: #eaf0ff;
      font-size: .78rem;
      font-weight: 800;
    }

    .pdf-batch-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1rem;
    }

    .pdf-batch-card {
      overflow: hidden;
      border: 1px solid #dfe7f5;
      border-radius: 14px;
      background: #fff;
      box-shadow: 0 6px 18px rgba(15, 23, 42, .05);
    }

    .pdf-batch-card.is-active {
      border-color: #2f63d8;
      box-shadow: 0 0 0 3px rgba(47, 99, 216, .13), 0 8px 22px rgba(47, 99, 216, .10);
    }

    .pdf-batch-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      padding: .8rem .9rem;
      color: #fff;
      background: #2748b3;
    }

    .pdf-batch-card-header .form-control {
      min-width: 0;
      color: #fff;
      font-weight: 700;
      border-color: rgba(255, 255, 255, .38);
      background: rgba(255, 255, 255, .12);
    }

    .pdf-batch-card-header .form-control::placeholder { color: rgba(255, 255, 255, .8); }
    .pdf-batch-card-header .form-control:focus { box-shadow: 0 0 0 .18rem rgba(255, 255, 255, .3); }

    .pdf-batch-people {
      max-height: 300px;
      overflow: auto;
      padding: .5rem;
    }

    .pdf-batch-person {
      display: flex;
      align-items: center;
      gap: .55rem;
      padding: .55rem;
      border-bottom: 1px solid #eef1f6;
    }

    .pdf-batch-person:last-child { border-bottom: 0; }
    .pdf-batch-person-data { min-width: 0; flex: 1 1 auto; }
    .pdf-batch-person-name { display: block; overflow: hidden; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .pdf-batch-person-detail { display: block; overflow: hidden; color: #667085; font-size: .76rem; text-overflow: ellipsis; white-space: nowrap; }
    .pdf-batch-person .form-select { width: 112px; min-width: 112px; font-size: .75rem; }

    @media(max-width:576.98px) {
      .pdf-builder-steps { grid-template-columns: 1fr; }
      .pdf-batch-workspace { padding: .7rem; }
      .pdf-batch-cards { grid-template-columns: 1fr; }
      .pdf-batch-person { align-items: flex-start; flex-wrap: wrap; }
      .pdf-batch-person .form-select { flex: 1 1 130px; }
    }

    .pdf-candidate-table {
      min-width: 940px;
      margin-bottom: 0;
    }

    .pdf-candidate-table thead th {
      position: sticky;
      z-index: 2;
      top: 0;
      border-bottom: 1px solid #dfe3ea;
      color: #475467;
      background: #f8fafc;
      font-size: .75rem;
      letter-spacing: .02em;
      text-transform: uppercase;
    }

    .pdf-candidate-table td {
      vertical-align: middle;
    }

    .pdf-candidate-used {
      color: #9f1239;
      background: #fff1f2;
    }

    .pdf-candidate-used td {
      color: #9f1239;
      background: #fff1f2 !important;
    }

    .pdf-candidate-used .pdf-person-name,
    .pdf-candidate-used .pdf-person-detail {
      color: #9f1239;
    }

    .pdf-candidate-lock {
      color: #9f1239;
      font-size: 1rem;
    }

    .pdf-person-name {
      color: #1d2939;
      font-weight: 800;
    }

    .pdf-person-detail {
      display: block;
      margin-top: .12rem;
      color: #667085;
      font-size: .78rem;
    }

    .pdf-history-list {
      display: grid;
      gap: .45rem;
    }

    .pdf-history-item {
      display: flex;
      align-items: stretch;
      gap: .45rem;
    }

    .pdf-history-link {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      padding: .65rem .75rem;
      color: #344054;
      border: 1px solid #eaecf0;
      border-radius: 10px;
      background: #fff;
      text-decoration: none;
    }

    .pdf-history-link:hover {
      color: #2748b3;
      border-color: #b9c9f7;
      background: #f7f9ff;
    }

    .pdf-history-item .pdf-history-link {
      flex: 1 1 auto;
      min-width: 0;
    }

    .pdf-history-actions {
      display: flex;
      align-items: center;
      gap: .35rem;
      flex: 0 0 auto;
    }

    .pdf-history-actions .btn {
      width: 34px;
      height: 34px;
      padding: 0;
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

      .pdf-builder-modal .modal-dialog {
        margin: .5rem;
      }

      .pdf-builder-modal .modal-header,
      .pdf-builder-modal .modal-body,
      .pdf-builder-modal .modal-footer {
        padding: 1rem;
      }

      .pdf-first-action,
      .pdf-builder-modal .modal-footer {
        align-items: stretch !important;
        flex-direction: column;
      }

      .pdf-first-action .btn,
      .pdf-builder-modal .modal-footer .btn {
        width: 100%;
      }

      .pdf-history-item {
        flex-wrap: wrap;
      }

      .pdf-history-item .pdf-history-link {
        flex-basis: 100%;
      }

      .pdf-history-actions {
        width: 100%;
        justify-content: flex-end;
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
              <h3>Relatório de Cadastros</h3>
              <p class="text-subtitle text-muted mb-0">Analise os cadastros e monte lotes de solicitantes para PDF</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
              <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                <ol class="breadcrumb mb-0">
                  <li class="breadcrumb-item"><a href="#">Relatórios</a></li>
                  <li class="breadcrumb-item active" aria-current="page">Cadastros</li>
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

                <button type="button" id="btnExportPDFReport" class="btn btn-outline-danger">
                  <i class="bi bi-file-earmark-pdf me-1"></i> PDF do relatório
                </button>

                <button type="button" id="btnExportPDF" class="btn btn-danger">
                  <i class="bi bi-people-fill me-1"></i> Montar PDF da lista
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
                  <label class="form-label" for="bairro">Bairros (opcional)</label>
                  <select id="bairro" class="form-select d-none" multiple aria-hidden="true" tabindex="-1">
                    <?php foreach ($bairros as $b): ?>
                      <option value="<?= (int) $b['id'] ?>"><?= e((string) $b['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>

                  <div class="dropdown beneficio-multiselect">
                    <button class="btn btn-outline-secondary dropdown-toggle w-100" type="button"
                      id="bairroDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                      aria-expanded="false">
                      <span id="bairroResumo" class="beneficio-summary">Todos</span>
                    </button>
                    <div class="dropdown-menu beneficio-menu" aria-labelledby="bairroDropdown">
                      <div class="d-flex justify-content-between align-items-center px-1 pb-2 mb-1 border-bottom">
                        <span class="small text-muted">Selecione um ou mais</span>
                        <button type="button" class="btn btn-sm btn-link p-0" id="btnClearBairros">Limpar</button>
                      </div>
                      <?php foreach ($bairros as $b): ?>
                        <label class="beneficio-option">
                          <input class="form-check-input bairro-check" type="checkbox"
                            value="<?= (int) $b['id'] ?>"
                            data-label="<?= e((string) $b['nome']) ?>">
                          <span><?= e((string) $b['nome']) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                  <label class="form-label" for="beneficio">Benefícios (opcional)</label>
                  <select id="beneficio" class="form-select d-none" multiple aria-hidden="true" tabindex="-1">
                    <?php foreach ($beneficios as $t): ?>
                      <option value="<?= (int) $t['id'] ?>"><?= e((string) $t['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>

                  <div class="dropdown beneficio-multiselect">
                    <button class="btn btn-outline-secondary dropdown-toggle w-100" type="button"
                      id="beneficioDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                      aria-expanded="false">
                      <span id="beneficioResumo" class="beneficio-summary">Todos</span>
                    </button>
                    <div class="dropdown-menu beneficio-menu" aria-labelledby="beneficioDropdown">
                      <div class="d-flex justify-content-between align-items-center px-1 pb-2 mb-1 border-bottom">
                        <span class="small text-muted">Selecione um ou mais</span>
                        <button type="button" class="btn btn-sm btn-link p-0" id="btnClearBeneficios">Limpar</button>
                      </div>
                      <?php foreach ($beneficios as $t): ?>
                        <label class="beneficio-option">
                          <input class="form-check-input beneficio-check" type="checkbox"
                            value="<?= (int) $t['id'] ?>"
                            data-label="<?= e((string) $t['nome']) ?>">
                          <span><?= e((string) $t['nome']) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                  <label class="form-label" for="emprego">Empregos no resumo (opcional)</label>
                  <select id="emprego" class="form-select d-none" multiple aria-hidden="true" tabindex="-1">
                    <?php foreach ($empregos as $emp): ?>
                      <option value="<?= (int) $emp['id'] ?>"><?= e((string) $emp['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>

                  <div class="dropdown beneficio-multiselect">
                    <button class="btn btn-outline-secondary dropdown-toggle w-100" type="button"
                      id="empregoDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                      aria-expanded="false">
                      <span id="empregoResumo" class="beneficio-summary">Todos</span>
                    </button>
                    <div class="dropdown-menu beneficio-menu" aria-labelledby="empregoDropdown">
                      <div class="d-flex justify-content-between align-items-center px-1 pb-2 mb-1 border-bottom">
                        <span class="small text-muted">Selecione um ou mais</span>
                        <button type="button" class="btn btn-sm btn-link p-0" id="btnClearEmpregos">Limpar</button>
                      </div>
                      <?php if (empty($empregos)): ?>
                        <div class="px-1 py-2 small text-muted">Nenhum tipo de emprego configurado.</div>
                      <?php else: ?>
                        <?php foreach ($empregos as $emp): ?>
                          <label class="beneficio-option">
                            <input class="form-check-input emprego-check" type="checkbox"
                              value="<?= (int) $emp['id'] ?>"
                              data-label="<?= e((string) $emp['nome']) ?>">
                            <span><?= e((string) $emp['nome']) ?></span>
                          </label>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>
                  </div>
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
                <input type="hidden" name="emprego_id" id="exp_emprego" value="">
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

  <div class="modal fade pdf-builder-modal" id="pdfBatchModal" tabindex="-1"
    aria-labelledby="pdfBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <div class="small text-white-50 fw-bold text-uppercase mb-1">Relatório operacional</div>
            <h4 class="modal-title mb-1" id="pdfBatchModalLabel">Montar PDF de cadastros</h4>
            <p class="mb-0 text-white-50">Organize vários lotes, mova pessoas entre eles e salve tudo de uma vez.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body p-3 p-lg-4">
          <?php if ($pdfCadastroFeatureError !== null): ?>
            <div class="alert alert-danger mb-0" role="alert">
              <div class="d-flex gap-2">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div>
                  <strong>Geração de lotes indisponível.</strong>
                  <div><?= e($pdfCadastroFeatureError) ?> Atualize a página após verificar o banco de dados.</div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="alert alert-primary border-0 d-flex gap-2 align-items-start" role="status">
              <i class="bi bi-shield-check fs-5"></i>
              <div>
                <strong>Cada cadastro só pode entrar uma vez.</strong>
                Os solicitantes já reservados aparecem bloqueados, mesmo quando outro usuário criou o lote.
                Os primeiros disponíveis respeitam os filtros da página; a busca manual consulta todo o sistema.
              </div>
            </div>

            <div id="pdfBatchFeedback" class="d-none" role="alert" aria-live="polite"></div>

            <div class="pdf-builder-steps" aria-label="Etapas para montar os lotes">
              <div class="pdf-builder-step">
                <span class="pdf-builder-step-number">1</span>
                <div><strong>Configurar</strong><span>Defina quantidade e tamanho</span></div>
              </div>
              <div class="pdf-builder-step">
                <span class="pdf-builder-step-number">2</span>
                <div><strong>Organizar</strong><span>Revise, mova ou adicione pessoas</span></div>
              </div>
              <div class="pdf-builder-step">
                <span class="pdf-builder-step-number">3</span>
                <div><strong>Salvar</strong><span>O sistema confirma e reabre os lotes</span></div>
              </div>
            </div>

            <div class="pdf-title-panel mb-4">
              <label class="form-label fw-bold mb-1" for="pdfBatchTitle">
                <i class="bi bi-type me-1 text-primary"></i> Título ou finalidade da lista
                <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <input type="text" class="form-control" id="pdfBatchTitle" maxlength="180" required
                placeholder="Ex.: Lista para entrega de cestas - julho/2026"
                aria-describedby="pdfBatchTitleHelp">
              <div class="form-text" id="pdfBatchTitleHelp">
                Esse texto será salvo no lote e aparecerá como título principal do PDF.
              </div>
            </div>

            <section class="pdf-mode-panel mb-4" aria-labelledby="pdfFirstModeTitle">
              <div class="d-flex gap-3 align-items-start">
                <span class="pdf-mode-icon" aria-hidden="true"><i class="bi bi-sort-numeric-down"></i></span>
                <div class="flex-grow-1">
                  <h5 class="mb-1" id="pdfFirstModeTitle">Criar vários lotes sequenciais</h5>
                  <p class="text-muted mb-3">Você pode começar vazio para escolher pessoas manualmente e, depois, completar somente as vagas restantes de forma automática. A seleção automática segue a ordem de cadastro e continua no próximo dia quando necessário.</p>
                  <div class="d-flex gap-2 align-items-end flex-wrap pdf-first-action">
                    <div>
                      <label class="form-label mb-1" for="pdfBatchCount">Lotes</label>
                      <input type="number" class="form-control" id="pdfBatchCount" min="1" max="100"
                        value="3" inputmode="numeric" style="width: 105px">
                    </div>
                    <div>
                      <label class="form-label mb-1" for="pdfBatchSize">Pessoas por lote</label>
                      <input type="number" class="form-control" id="pdfBatchSize" min="1" max="5000"
                        value="50" inputmode="numeric" style="width: 145px">
                    </div>
                    <button type="button" class="btn btn-outline-primary" id="btnStartPdfBatchesManual">
                      <i class="bi bi-hand-index-thumb me-1"></i> Montar manualmente
                    </button>
                    <button type="button" class="btn btn-primary" id="btnPreparePdfBatches">
                      <i class="bi bi-collection me-1"></i> Preencher automático
                    </button>
                    <div class="ms-lg-auto d-flex gap-2 flex-wrap">
                      <span class="pdf-count-pill"><i class="bi bi-check-circle text-success"></i>
                        <span id="pdfAvailableBadge">— disponíveis</span></span>
                      <span class="pdf-count-pill"><i class="bi bi-lock text-secondary"></i>
                        <span id="pdfUsedBadge">— já utilizados</span></span>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <section class="pdf-batch-workspace d-none mb-4" id="pdfBatchWorkspace" aria-labelledby="pdfWorkspaceTitle">
              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                <div>
                  <h5 class="mb-1" id="pdfWorkspaceTitle"><i class="bi bi-grid-3x3-gap me-1"></i> Cartões dos lotes</h5>
                  <p class="text-muted mb-0" id="pdfWorkspaceHint">Escolha um lote ativo para adicionar pessoas. Depois, use “Completar vagas” para preencher somente o que faltar, sem repetir cadastros.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                  <button type="button" class="btn btn-outline-secondary" id="btnClearPdfBatchDraft">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Limpar rascunho
                  </button>
                  <button type="button" class="btn btn-outline-primary" id="btnAutoFillPdfBatchGaps">
                    <i class="bi bi-magic me-1"></i> Completar vagas
                  </button>
                  <button type="button" class="btn btn-success" id="btnSavePdfBatchGroup">
                    <i class="bi bi-save2 me-1"></i> Salvar lotes
                  </button>
                </div>
              </div>
              <div class="pdf-batch-cards" id="pdfBatchCards" aria-live="polite"></div>
            </section>

            <section class="pdf-candidate-panel is-locked" id="pdfCandidatePanel" aria-labelledby="pdfManualModeTitle">
              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                <div class="d-flex gap-3 align-items-start">
                  <span class="pdf-mode-icon" aria-hidden="true"><i class="bi bi-ui-checks-grid"></i></span>
                  <div>
                    <h5 class="mb-1" id="pdfManualModeTitle">Adicionar pessoas aos lotes</h5>
                    <p class="text-muted mb-0">Pesquise em todo o sistema, marque as pessoas disponíveis e adicione-as ao cartão ativo.</p>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <span class="pdf-active-batch-chip" id="pdfActiveBatchLabel"><i class="bi bi-cursor-fill"></i> Nenhum lote ativo</span>
                  <span class="badge bg-primary rounded-pill px-3 py-2" id="pdfSelectedBadge">0 selecionados</span>
                </div>
              </div>

              <div class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-7">
                  <label class="form-label mb-1" for="pdfCandidateSearch">Buscar solicitante</label>
                  <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="pdfCandidateSearch"
                      placeholder="Nome, CPF ou telefone - busca geral" autocomplete="off">
                  </div>
                  <div class="form-text">Ao digitar, os filtros da página são ignorados para localizar qualquer pessoa cadastrada.</div>
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label mb-1" for="pdfCandidatePerPage">Por página</label>
                  <select class="form-select" id="pdfCandidatePerPage">
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                  </select>
                </div>
                <div class="col-6 col-md-3 d-grid">
                  <button type="button" class="btn btn-outline-primary" id="btnAddPdfCandidates">
                    <i class="bi bi-person-plus me-1"></i> Adicionar ao lote ativo
                  </button>
                </div>
              </div>

              <div class="pdf-candidates-wrap" aria-live="polite" aria-busy="false" id="pdfCandidatesWrap">
                <table class="table table-hover pdf-candidate-table">
                  <thead>
                    <tr>
                      <th class="text-center" style="width:54px"><span class="visually-hidden">Selecionar</span></th>
                      <th>Solicitante</th>
                      <th>CPF</th>
                      <th>Bairro</th>
                      <th>Benefício</th>
                      <th>Cadastro</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody id="pdfCandidatesBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Abra o seletor para carregar os cadastros.</td></tr>
                  </tbody>
                </table>
              </div>

              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mt-3">
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSelectCandidatePage">Marcar página</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearPdfSelection">Limpar seleção</button>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPdfCandidatesPrev"
                    aria-label="Página anterior"><i class="bi bi-chevron-left"></i></button>
                  <span class="small text-muted" id="pdfCandidatesPageLabel">Página 1 de 1</span>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPdfCandidatesNext"
                    aria-label="Próxima página"><i class="bi bi-chevron-right"></i></button>
                </div>
              </div>
            </section>

            <section class="mt-4 pt-4 border-top" aria-labelledby="pdfHistoryTitle">
              <h6 class="mb-2" id="pdfHistoryTitle"><i class="bi bi-clock-history me-1"></i> Lotes recentes</h6>
              <?php if (!$recentCadastroPdfBatches): ?>
                <p class="small text-muted mb-0">Nenhum lote foi gerado ainda.</p>
              <?php else: ?>
                <div class="pdf-history-list">
                  <?php foreach ($recentCadastroPdfBatches as $batch): ?>
                    <?php
                    $batchDate = strtotime((string)($batch['criado_em'] ?? ''));
                    $batchMode = ($batch['modo'] ?? '') === 'manual' ? 'Seleção manual' : 'Primeiros disponíveis';
                    $batchTitle = (string)($batch['titulo'] ?: ('Lista de cadastros - Lote #' . (int)$batch['id']));
                    ?>
                    <div class="pdf-history-item" data-batch-id="<?= (int)$batch['id'] ?>"
                      data-batch-title="<?= e($batchTitle) ?>">
                      <a class="pdf-history-link" target="_blank" rel="noopener"
                        href="relatorioCadastrosPdf.php?lote_id=<?= (int)$batch['id'] ?>">
                        <span>
                          <strong class="pdf-history-title"><?= e($batchTitle) ?></strong>
                          <span class="pdf-person-detail">Lote #<?= (int)$batch['id'] ?> · <?= e($batchMode) ?> · <?= (int)$batch['total_solicitantes'] ?> cadastros</span>
                        </span>
                        <span class="text-end small text-muted">
                          <?= $batchDate ? e(date('d/m/Y H:i', $batchDate)) : '—' ?>
                          <i class="bi bi-box-arrow-up-right ms-1"></i>
                        </span>
                      </a>
                      <div class="pdf-history-actions">
                        <button type="button" class="btn btn-sm btn-outline-success pdf-batch-manage"
                          title="Gerenciar pessoas deste lote" aria-label="Gerenciar lote <?= (int)$batch['id'] ?>">
                          <i class="bi bi-people"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary pdf-batch-edit"
                          title="Editar título do lote" aria-label="Editar título do lote <?= (int)$batch['id'] ?>">
                          <i class="bi bi-pencil-square"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger pdf-batch-delete"
                          title="Excluir lote e liberar cadastros" aria-label="Excluir lote <?= (int)$batch['id'] ?>">
                          <i class="bi bi-trash3"></i>
                        </button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>
          <?php endif; ?>
        </div>

        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
          <span class="small text-muted">Os PDFs continuam disponíveis em cada lote salvo.</span>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="pdfBatchEditModal" tabindex="-1" aria-labelledby="pdfBatchEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-primary text-white">
          <div>
            <h5 class="modal-title" id="pdfBatchEditModalLabel">Editar lote</h5>
            <p class="small mb-0 text-white-50" id="pdfBatchEditModalSubtitle">Altere o título ou a finalidade da lista.</p>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div id="pdfBatchEditFeedback" class="d-none" role="alert" aria-live="polite"></div>
          <label class="form-label fw-bold" for="pdfBatchEditTitle">Título ou finalidade da lista</label>
          <input type="text" class="form-control" id="pdfBatchEditTitle" maxlength="180" required>
          <p class="form-text mb-0">Para alterar os participantes, exclua o lote. Os cadastros voltarão a ficar disponíveis para uma nova seleção.</p>
        </div>
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-outline-danger" id="btnDeletePdfBatch">
            <i class="bi bi-trash3 me-1"></i> Excluir lote
          </button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnSavePdfBatch">
              <i class="bi bi-check2 me-1"></i> Salvar título
            </button>
          </div>
        </div>
      </div>
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
      const bairroChecks = Array.from(document.querySelectorAll('.bairro-check'));
      const bairroResumo = document.getElementById('bairroResumo');
      const btnClearBairros = document.getElementById('btnClearBairros');
      const selBenef = document.getElementById('beneficio');
      const beneficioChecks = Array.from(document.querySelectorAll('.beneficio-check'));
      const beneficioResumo = document.getElementById('beneficioResumo');
      const btnClearBeneficios = document.getElementById('btnClearBeneficios');
      const selEmprego = document.getElementById('emprego');
      const empregoChecks = Array.from(document.querySelectorAll('.emprego-check'));
      const empregoResumo = document.getElementById('empregoResumo');
      const btnClearEmpregos = document.getElementById('btnClearEmpregos');

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
      const btnExportPDF = document.getElementById('btnExportPDF');
      const btnExportPDFReport = document.getElementById('btnExportPDFReport');
      const tbodyBenef = document.getElementById('tbodyBenef');

      // Lotes do PDF de cadastros
      const pdfCsrfToken = <?= json_encode($pdfCadastroCsrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const pdfModalElement = document.getElementById('pdfBatchModal');
      const pdfModal = pdfModalElement ? new bootstrap.Modal(pdfModalElement) : null;
      const pdfBatchFeedback = document.getElementById('pdfBatchFeedback');
      const pdfBatchEditModalElement = document.getElementById('pdfBatchEditModal');
      const pdfBatchEditModal = pdfBatchEditModalElement ? new bootstrap.Modal(pdfBatchEditModalElement) : null;
      const pdfBatchEditModalSubtitle = document.getElementById('pdfBatchEditModalSubtitle');
      const pdfBatchEditFeedback = document.getElementById('pdfBatchEditFeedback');
      const pdfBatchEditTitle = document.getElementById('pdfBatchEditTitle');
      const btnSavePdfBatch = document.getElementById('btnSavePdfBatch');
      const btnDeletePdfBatch = document.getElementById('btnDeletePdfBatch');
      const pdfBatchTitle = document.getElementById('pdfBatchTitle');
      const pdfBatchCount = document.getElementById('pdfBatchCount');
      const pdfBatchSize = document.getElementById('pdfBatchSize');
      const btnPreparePdfBatches = document.getElementById('btnPreparePdfBatches');
      const btnStartPdfBatchesManual = document.getElementById('btnStartPdfBatchesManual');
      const pdfBatchWorkspace = document.getElementById('pdfBatchWorkspace');
      const pdfBatchCards = document.getElementById('pdfBatchCards');
      const btnSavePdfBatchGroup = document.getElementById('btnSavePdfBatchGroup');
      const btnClearPdfBatchDraft = document.getElementById('btnClearPdfBatchDraft');
      const btnAutoFillPdfBatchGaps = document.getElementById('btnAutoFillPdfBatchGaps');
      const btnAddPdfCandidates = document.getElementById('btnAddPdfCandidates');
      const pdfCandidatePanel = document.getElementById('pdfCandidatePanel');
      const pdfActiveBatchLabel = document.getElementById('pdfActiveBatchLabel');
      const pdfFirstLimit = document.getElementById('pdfFirstLimit');
      const btnGenerateFirstPdf = document.getElementById('btnGenerateFirstPdf');
      const btnGenerateManualPdf = document.getElementById('btnGenerateManualPdf');
      const pdfAvailableBadge = document.getElementById('pdfAvailableBadge');
      const pdfUsedBadge = document.getElementById('pdfUsedBadge');
      const pdfSelectedBadge = document.getElementById('pdfSelectedBadge');
      const pdfCandidateSearch = document.getElementById('pdfCandidateSearch');
      const pdfCandidatePerPage = document.getElementById('pdfCandidatePerPage');
      const pdfCandidatesWrap = document.getElementById('pdfCandidatesWrap');
      const pdfCandidatesBody = document.getElementById('pdfCandidatesBody');
      const pdfCandidatesPageLabel = document.getElementById('pdfCandidatesPageLabel');
      const btnSelectCandidatePage = document.getElementById('btnSelectCandidatePage');
      const btnClearPdfSelection = document.getElementById('btnClearPdfSelection');
      const btnPdfCandidatesPrev = document.getElementById('btnPdfCandidatesPrev');
      const btnPdfCandidatesNext = document.getElementById('btnPdfCandidatesNext');
      const pdfSelectedIds = new Set();
      const pdfCandidateCache = new Map();
      let pdfCandidateRows = [];
      let pdfCandidatePage = 1;
      let pdfCandidatePages = 1;
      let pdfCandidateRequestId = 0;
      let pdfSearchTimer = null;
      let pdfEditingBatchId = 0;
      let pdfEditingBatchItem = null;
      let pdfActiveBatchIndex = -1;
      let pdfBatchDraft = { grupoId: 0, titulo: '', capacidade: 0, lotes: [] };
      const pdfBatchReloadStorageKey = 'semas_pdf_batch_reopen';
      let pdfBatchReloadPending = false;

      // Export hidden
      const frmExport = document.getElementById('frmExport');
      const expClientNow = document.getElementById('exp_client_now');
      const expPeriodo = document.getElementById('exp_periodo');
      const expDI = document.getElementById('exp_di');
      const expDF = document.getElementById('exp_df');
      const expBairro = document.getElementById('exp_bairro');
      const expBenef = document.getElementById('exp_beneficio');
      const expEmprego = document.getElementById('exp_emprego');
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
        const bairrosSelecionados = getSelectedBairros();
        const beneficiosSelecionados = getSelectedBeneficios();
        const empregosSelecionados = getSelectedEmpregos();

        return {
          periodo: selPeriodo.value || 'mensal',
          di: inpDI.value || '',
          df: inpDF.value || '',
          bairro_id: bairrosSelecionados.join(','),
          beneficio_id: beneficiosSelecionados.join(','),
          emprego_id: empregosSelecionados.join(','),
          sexo: selSexo ? (selSexo.value || '') : '',
          q: (inpSearch.value || '').trim()
        };
      }

      function pdfRequestPayload(extra = {}) {
        return {
          ...getPayload(),
          csrf_token: pdfCsrfToken,
          ...extra
        };
      }

      function setPdfFeedback(type, message, pdfUrl = '') {
        if (!pdfBatchFeedback) return;
        pdfBatchFeedback.className = `alert alert-${type}`;
        pdfBatchFeedback.replaceChildren();

        const messageNode = document.createElement('span');
        messageNode.textContent = message;
        pdfBatchFeedback.appendChild(messageNode);

        if (pdfUrl) {
          const link = document.createElement('a');
          link.className = 'alert-link ms-2';
          link.href = pdfUrl;
          link.target = '_blank';
          link.rel = 'noopener';
          link.textContent = 'Abrir novamente';
          pdfBatchFeedback.appendChild(link);
        }
      }

      function setPdfBatchEditFeedback(type, message) {
        if (!pdfBatchEditFeedback) return;
        pdfBatchEditFeedback.className = `alert alert-${type}`;
        pdfBatchEditFeedback.textContent = message;
      }

      function resetPdfBatchEditFeedback() {
        if (!pdfBatchEditFeedback) return;
        pdfBatchEditFeedback.className = 'd-none';
        pdfBatchEditFeedback.replaceChildren();
      }

      function openPdfBatchEditor(button) {
        const item = button.closest('.pdf-history-item');
        const batchId = Number(item?.dataset.batchId) || 0;
        if (!item || batchId <= 0 || !pdfBatchEditTitle) return;

        pdfEditingBatchId = batchId;
        pdfEditingBatchItem = item;
        pdfBatchEditTitle.value = item.dataset.batchTitle || '';
        if (pdfBatchEditModalSubtitle) {
          pdfBatchEditModalSubtitle.textContent = `Lote #${batchId}: altere o título ou a finalidade da lista.`;
        }
        resetPdfBatchEditFeedback();
        pdfBatchEditModal?.show();
        window.setTimeout(() => pdfBatchEditTitle.focus(), 180);
      }

      async function requestPdfBatchAction(endpoint, payload) {
        const response = await fetch(`${window.location.pathname}?${endpoint}=1`, {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
          body: new URLSearchParams(pdfRequestPayload(payload))
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || !data || !data.ok) {
          throw new Error(data && data.message ? data.message : 'Não foi possível atualizar o lote.');
        }
        return data;
      }

      function updatePdfSelectionState() {
        const total = pdfSelectedIds.size;
        const hasTitle = Boolean(pdfBatchTitle && pdfBatchTitle.value.trim());
        const activeBatch = pdfBatchDraft.lotes[pdfActiveBatchIndex] || null;
        const canAddPeople = Boolean(activeBatch);
        if (pdfSelectedBadge) {
          pdfSelectedBadge.textContent = `${total} ${total === 1 ? 'selecionado' : 'selecionados'}`;
        }
        if (pdfActiveBatchLabel) {
          pdfActiveBatchLabel.replaceChildren();
          const icon = document.createElement('i');
          icon.className = canAddPeople ? 'bi bi-cursor-fill' : 'bi bi-info-circle';
          pdfActiveBatchLabel.append(icon, document.createTextNode(canAddPeople ? ` Lote ativo: ${pdfActiveBatchIndex + 1}` : ' Nenhum lote ativo'));
        }
        pdfCandidatePanel?.classList.toggle('is-locked', !canAddPeople);
        if (pdfCandidateSearch) pdfCandidateSearch.disabled = !canAddPeople;
        if (pdfCandidatePerPage) pdfCandidatePerPage.disabled = !canAddPeople;
        if (btnSelectCandidatePage) btnSelectCandidatePage.disabled = !canAddPeople;
        if (btnClearPdfSelection) btnClearPdfSelection.disabled = !canAddPeople || total === 0;
        if (btnPdfCandidatesPrev) btnPdfCandidatesPrev.disabled = !canAddPeople || pdfCandidatePage <= 1;
        if (btnPdfCandidatesNext) btnPdfCandidatesNext.disabled = !canAddPeople || pdfCandidatePage >= pdfCandidatePages;
        if (btnGenerateFirstPdf) btnGenerateFirstPdf.disabled = !hasTitle;
        if (btnGenerateManualPdf) btnGenerateManualPdf.disabled = total === 0 || !hasTitle;
        if (btnPreparePdfBatches) btnPreparePdfBatches.disabled = !hasTitle;
        if (btnStartPdfBatchesManual) btnStartPdfBatchesManual.disabled = !hasTitle;
        if (btnAddPdfCandidates) btnAddPdfCandidates.disabled = total === 0 || !canAddPeople;
        if (btnSavePdfBatchGroup) btnSavePdfBatchGroup.disabled = !hasTitle || !pdfBatchDraft.lotes.length;
        const capacity = Number(pdfBatchDraft.capacidade) || 0;
        const hasVacancy = capacity > 0 && pdfBatchDraft.lotes.some((batch) => batch.pessoas.length < capacity);
        if (btnAutoFillPdfBatchGaps) btnAutoFillPdfBatchGaps.disabled = !hasTitle || !pdfBatchDraft.lotes.length || !hasVacancy;
      }

      function isPdfPersonInDraft(personId) {
        const id = Number(personId) || 0;
        return pdfBatchDraft.lotes.some((batch) => batch.pessoas.some((person) => Number(person.solicitante_id) === id));
      }

      function formatPdfCpf(value) {
        const digits = String(value || '').replace(/\D/g, '');
        if (digits.length !== 11) return value || '—';
        return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
      }

      function formatPdfDate(value) {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        return match ? `${match[3]}/${match[2]}/${match[1]}` : '—';
      }

      function pdfCell(text, className = '') {
        const cell = document.createElement('td');
        if (className) cell.className = className;
        cell.textContent = text || '—';
        return cell;
      }

      function renderPdfCandidates(data) {
        if (!pdfCandidatesBody) return;

        pdfCandidateRows = Array.isArray(data.rows) ? data.rows : [];
        pdfCandidatePage = Number(data.page) || 1;
        pdfCandidatePages = Math.max(1, Number(data.pages) || 1);

        if (pdfAvailableBadge) {
          const count = Number(data.available) || 0;
          pdfAvailableBadge.textContent = `${count} ${count === 1 ? 'disponível' : 'disponíveis'}`;
        }
        if (pdfUsedBadge) {
          const count = Number(data.used) || 0;
          pdfUsedBadge.textContent = `${count} já ${count === 1 ? 'utilizado' : 'utilizados'}`;
        }
        if (pdfCandidatesPageLabel) {
          pdfCandidatesPageLabel.textContent = `Página ${pdfCandidatePage} de ${pdfCandidatePages}`;
        }
        if (btnPdfCandidatesPrev) btnPdfCandidatesPrev.disabled = pdfCandidatePage <= 1;
        if (btnPdfCandidatesNext) btnPdfCandidatesNext.disabled = pdfCandidatePage >= pdfCandidatePages;

        pdfCandidatesBody.replaceChildren();
        if (!pdfCandidateRows.length) {
          const row = document.createElement('tr');
          const cell = document.createElement('td');
          cell.colSpan = 7;
          cell.className = 'text-center text-muted py-4';
          cell.textContent = data.scope === 'global'
            ? 'Nenhum cadastro encontrado no sistema com essa busca.'
            : 'Nenhum cadastro encontrado com os filtros atuais.';
          row.appendChild(cell);
          pdfCandidatesBody.appendChild(row);
          return;
        }

        pdfCandidateRows.forEach((candidate) => {
          const id = Number(candidate.solicitante_id) || 0;
          if (id > 0) pdfCandidateCache.set(id, candidate);
          const isUsed = Number(candidate.lote_id) > 0;
          const isInDraft = isPdfPersonInDraft(id);
          const canAddPeople = Boolean(pdfBatchDraft.lotes[pdfActiveBatchIndex]);
          if (isUsed || isInDraft) pdfSelectedIds.delete(id);

          const row = document.createElement('tr');
          if (isUsed || isInDraft) row.className = 'pdf-candidate-used';

          const selectCell = document.createElement('td');
          selectCell.className = 'text-center';
          if (isUsed || isInDraft) {
            const lock = document.createElement('i');
            lock.className = 'bi bi-lock-fill pdf-candidate-lock';
            lock.setAttribute('aria-label', isInDraft ? 'Cadastro já está em um lote deste rascunho' : 'Cadastro já utilizado em um lote');
            lock.title = isInDraft ? 'Cadastro já está em um lote deste rascunho' : 'Cadastro já utilizado em um lote';
            selectCell.appendChild(lock);
          } else {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            checkbox.value = String(id);
            checkbox.disabled = id <= 0 || !canAddPeople;
            checkbox.checked = pdfSelectedIds.has(id);
            checkbox.setAttribute('aria-label', `Selecionar ${candidate.nome || 'cadastro'}`);
            checkbox.addEventListener('change', () => {
              if (checkbox.checked) pdfSelectedIds.add(id);
              else pdfSelectedIds.delete(id);
              updatePdfSelectionState();
            });
            selectCell.appendChild(checkbox);
          }
          row.appendChild(selectCell);

          const personCell = document.createElement('td');
          const name = document.createElement('span');
          name.className = 'pdf-person-name';
          name.textContent = candidate.nome || 'Sem nome';
          personCell.appendChild(name);
          if (candidate.telefone) {
            const phone = document.createElement('span');
            phone.className = 'pdf-person-detail';
            phone.textContent = candidate.telefone;
            personCell.appendChild(phone);
          }
          row.appendChild(personCell);
          row.appendChild(pdfCell(formatPdfCpf(candidate.cpf)));
          row.appendChild(pdfCell(candidate.bairro_nome));
          row.appendChild(pdfCell(candidate.beneficio));
          row.appendChild(pdfCell(formatPdfDate(candidate.data_cadastro)));

          const statusCell = document.createElement('td');
          if (isUsed) {
            const link = document.createElement('a');
            link.className = 'badge bg-secondary text-decoration-none';
            link.href = `relatorioCadastrosPdf.php?lote_id=${Number(candidate.lote_id)}`;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = `Lote #${Number(candidate.lote_id)}`;
            link.title = 'Abrir o lote que já utilizou este cadastro';
            statusCell.appendChild(link);
          } else if (isInDraft) {
            const status = document.createElement('span');
            status.className = 'badge bg-warning text-dark';
            status.textContent = 'No rascunho';
            statusCell.appendChild(status);
          } else {
            const status = document.createElement('span');
            status.className = 'badge bg-light-success text-success';
            status.textContent = 'Disponível';
            statusCell.appendChild(status);
          }
          row.appendChild(statusCell);
          pdfCandidatesBody.appendChild(row);
        });

        updatePdfSelectionState();
      }

      async function loadPdfCandidates() {
        if (!pdfCandidatesBody) return;
        const requestId = ++pdfCandidateRequestId;
        if (pdfCandidatesWrap) pdfCandidatesWrap.setAttribute('aria-busy', 'true');
        pdfCandidatesBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Carregando cadastros...</td></tr>';

        try {
          const response = await fetch(`${window.location.pathname}?pdf_candidates=1`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: new URLSearchParams(pdfRequestPayload({
              page: String(pdfCandidatePage),
              per_page: pdfCandidatePerPage ? pdfCandidatePerPage.value : '20',
              selection_search: pdfCandidateSearch ? pdfCandidateSearch.value.trim() : ''
            }))
          });
          const data = await response.json().catch(() => null);
          if (requestId !== pdfCandidateRequestId) return;
          if (!response.ok || !data || !data.ok) {
            throw new Error(data && data.message ? data.message : 'Não foi possível carregar os cadastros.');
          }
          renderPdfCandidates(data);
        } catch (error) {
          if (requestId !== pdfCandidateRequestId) return;
          pdfCandidatesBody.innerHTML = '';
          const row = document.createElement('tr');
          const cell = document.createElement('td');
          cell.colSpan = 7;
          cell.className = 'text-center text-danger py-4';
          cell.textContent = error instanceof Error ? error.message : 'Não foi possível carregar os cadastros.';
          row.appendChild(cell);
          pdfCandidatesBody.appendChild(row);
          setPdfFeedback('danger', cell.textContent);
        } finally {
          if (requestId === pdfCandidateRequestId && pdfCandidatesWrap) {
            pdfCandidatesWrap.setAttribute('aria-busy', 'false');
          }
        }
      }

      function pdfDraftAssignments() {
        return pdfBatchDraft.lotes.map((batch, index) => ({
          lote_id: Number(batch.id) || 0,
          titulo: String(batch.titulo || `${pdfBatchTitle?.value.trim() || 'Lista de cadastros'} - Lote ${index + 1}`).trim(),
          solicitante_ids: batch.pessoas.map((person) => Number(person.solicitante_id)).filter((id) => id > 0)
        }));
      }

      function renderPdfBatchCards() {
        if (!pdfBatchCards) return;
        pdfBatchCards.replaceChildren();
        if (!pdfBatchDraft.lotes.length) {
          pdfBatchWorkspace?.classList.add('d-none');
          pdfActiveBatchIndex = -1;
          updatePdfSelectionState();
          return;
        }

        pdfBatchWorkspace?.classList.remove('d-none');
        if (pdfActiveBatchIndex < 0 || !pdfBatchDraft.lotes[pdfActiveBatchIndex]) pdfActiveBatchIndex = 0;

        pdfBatchDraft.lotes.forEach((batch, batchIndex) => {
          const card = document.createElement('article');
          card.className = `pdf-batch-card${batchIndex === pdfActiveBatchIndex ? ' is-active' : ''}`;
          card.tabIndex = 0;
          card.addEventListener('click', () => {
            pdfActiveBatchIndex = batchIndex;
            renderPdfBatchCards();
          });
          card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              pdfActiveBatchIndex = batchIndex;
              renderPdfBatchCards();
            }
          });

          const header = document.createElement('div');
          header.className = 'pdf-batch-card-header';
          const titleWrap = document.createElement('div');
          titleWrap.className = 'flex-grow-1';
          const label = document.createElement('label');
          label.className = 'visually-hidden';
          label.textContent = `Título do lote ${batchIndex + 1}`;
          const titleInput = document.createElement('input');
          titleInput.type = 'text';
          titleInput.maxLength = 180;
          titleInput.className = 'form-control form-control-sm';
          titleInput.value = batch.titulo || '';
          titleInput.placeholder = `Lote ${batchIndex + 1}`;
          titleInput.addEventListener('click', (event) => event.stopPropagation());
          titleInput.addEventListener('input', () => { batch.titulo = titleInput.value; updatePdfSelectionState(); });
          titleWrap.append(label, titleInput);
          const count = document.createElement('span');
          count.className = 'badge text-bg-light text-primary';
          const capacity = Number(pdfBatchDraft.capacidade) || 0;
          count.textContent = capacity > 0
            ? `${batch.pessoas.length}/${capacity} pessoa(s)`
            : `${batch.pessoas.length} pessoa(s)`;
          header.append(titleWrap, count);
          card.appendChild(header);

          const people = document.createElement('div');
          people.className = 'pdf-batch-people';
          if (!batch.pessoas.length) {
            const empty = document.createElement('p');
            empty.className = 'small text-muted text-center py-3 mb-0';
            empty.textContent = 'Nenhuma pessoa neste lote. Selecione-o e adicione pessoas pela lista ao lado.';
            people.appendChild(empty);
          }
          batch.pessoas.forEach((person, personIndex) => {
            const row = document.createElement('div');
            row.className = 'pdf-batch-person';
            const data = document.createElement('div');
            data.className = 'pdf-batch-person-data';
            const name = document.createElement('span');
            name.className = 'pdf-batch-person-name';
            name.textContent = person.nome || 'Sem nome';
            const detail = document.createElement('span');
            detail.className = 'pdf-batch-person-detail';
            detail.textContent = [formatPdfCpf(person.cpf), person.telefone || '', person.bairro_nome || ''].filter(Boolean).join(' · ');
            data.append(name, detail);

            const move = document.createElement('select');
            move.className = 'form-select form-select-sm';
            move.setAttribute('aria-label', `Mover ${person.nome || 'pessoa'} para outro lote`);
            const currentOption = document.createElement('option');
            currentOption.value = '';
            currentOption.textContent = 'Mover...';
            move.appendChild(currentOption);
            pdfBatchDraft.lotes.forEach((target, targetIndex) => {
              if (targetIndex === batchIndex) return;
              const option = document.createElement('option');
              option.value = String(targetIndex);
              option.textContent = `Lote ${targetIndex + 1}`;
              move.appendChild(option);
            });
            move.addEventListener('click', (event) => event.stopPropagation());
            move.addEventListener('change', () => {
              const targetIndex = Number(move.value);
              if (!Number.isInteger(targetIndex) || !pdfBatchDraft.lotes[targetIndex]) return;
              const [moved] = batch.pessoas.splice(personIndex, 1);
              pdfBatchDraft.lotes[targetIndex].pessoas.push(moved);
              pdfActiveBatchIndex = targetIndex;
              renderPdfBatchCards();
              loadPdfCandidates();
            });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger';
            remove.title = 'Retirar deste lote';
            remove.setAttribute('aria-label', `Retirar ${person.nome || 'pessoa'} deste lote`);
            remove.innerHTML = '<i class="bi bi-person-dash"></i>';
            remove.addEventListener('click', (event) => {
              event.stopPropagation();
              batch.pessoas.splice(personIndex, 1);
              renderPdfBatchCards();
              loadPdfCandidates();
            });
            row.append(data, move, remove);
            people.appendChild(row);
          });
          card.appendChild(people);

          if (Number(batch.id) > 0) {
            const footer = document.createElement('div');
            footer.className = 'px-3 pb-3';
            const pdfLink = document.createElement('a');
            pdfLink.className = 'btn btn-sm btn-outline-primary w-100';
            pdfLink.href = `relatorioCadastrosPdf.php?lote_id=${Number(batch.id)}`;
            pdfLink.target = '_blank';
            pdfLink.rel = 'noopener';
            pdfLink.innerHTML = '<i class="bi bi-file-earmark-pdf me-1"></i> Abrir PDF deste lote';
            footer.appendChild(pdfLink);
            card.appendChild(footer);
          }
          pdfBatchCards.appendChild(card);
        });
        updatePdfSelectionState();
      }

      function applyPdfBatchGroup(pack) {
        const group = pack && pack.grupo ? pack.grupo : {};
        const batches = Array.isArray(pack?.lotes) ? pack.lotes : [];
        let savedCapacity = 0;
        try {
          const savedFilters = JSON.parse(String(group.filtros_json || '{}'));
          savedCapacity = Number(savedFilters?.tamanho_lote) || 0;
        } catch (error) {
          savedCapacity = 0;
        }
        pdfBatchDraft = {
          grupoId: Number(group.id) || 0,
          titulo: group.titulo || (pdfBatchTitle?.value.trim() || ''),
          capacidade: Math.max(
            savedCapacity || Number(pdfBatchSize?.value) || 0,
            ...batches.map((batch) => Array.isArray(batch.pessoas) ? batch.pessoas.length : 0)
          ),
          lotes: batches.map((batch) => ({
            id: Number(batch.id) || 0,
            titulo: batch.titulo || '',
            pessoas: Array.isArray(batch.pessoas) ? batch.pessoas : []
          }))
        };
        if (pdfBatchTitle && pdfBatchDraft.titulo) pdfBatchTitle.value = pdfBatchDraft.titulo;
        pdfActiveBatchIndex = pdfBatchDraft.lotes.length ? 0 : -1;
        pdfSelectedIds.clear();
        renderPdfBatchCards();
      }

      function startPdfBatchesManually() {
        if (pdfBatchDraft.grupoId > 0) {
          setPdfFeedback('warning', 'Este grupo já está salvo. Faça os ajustes nos cartões e salve; abra um novo construtor para iniciar outros lotes.');
          return;
        }
        const title = pdfBatchTitle ? pdfBatchTitle.value.trim() : '';
        const count = Number(pdfBatchCount?.value);
        const size = Number(pdfBatchSize?.value);
        if (!title) {
          setPdfFeedback('warning', 'Informe o título ou a finalidade da lista.');
          pdfBatchTitle?.focus();
          return;
        }
        if (!Number.isInteger(count) || count < 1 || count > 100 || !Number.isInteger(size) || size < 1 || size > 5000 || count * size > 5000) {
          setPdfFeedback('warning', 'Informe entre 1 e 100 lotes, com até 5.000 pessoas no total.');
          return;
        }
        if (pdfBatchDraft.lotes.length && !window.confirm('Substituir o rascunho atual por lotes vazios?')) return;

        pdfBatchDraft = {
          grupoId: 0,
          titulo: title,
          capacidade: size,
          lotes: Array.from({ length: count }, (_, index) => ({
            id: 0,
            titulo: `${title} - Lote ${index + 1}`,
            pessoas: []
          }))
        };
        pdfActiveBatchIndex = 0;
        pdfSelectedIds.clear();
        renderPdfBatchCards();
        loadPdfCandidates();
        setPdfFeedback('info', `Foram criados ${count} lote(s) vazio(s). Escolha as pessoas manualmente e depois use “Completar vagas” se desejar.`);
      }

      async function preparePdfBatches() {
        if (pdfBatchDraft.grupoId > 0) {
          setPdfFeedback('warning', 'Este grupo já está salvo. Para preservar seus dados, faça os ajustes nos cartões e clique em salvar; abra um novo construtor para iniciar outros lotes.');
          return;
        }
        const title = pdfBatchTitle ? pdfBatchTitle.value.trim() : '';
        const count = Number(pdfBatchCount?.value);
        const size = Number(pdfBatchSize?.value);
        if (!title) {
          setPdfFeedback('warning', 'Informe o título ou a finalidade da lista.');
          pdfBatchTitle?.focus();
          return;
        }
        if (!Number.isInteger(count) || count < 1 || count > 100 || !Number.isInteger(size) || size < 1 || size > 5000 || count * size > 5000) {
          setPdfFeedback('warning', 'Informe entre 1 e 100 lotes, com até 5.000 pessoas no total.');
          return;
        }
        const originalHtml = btnPreparePdfBatches ? btnPreparePdfBatches.innerHTML : '';
        if (btnPreparePdfBatches) {
          btnPreparePdfBatches.disabled = true;
          btnPreparePdfBatches.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Preparando...';
        }
        try {
          const data = await requestPdfBatchAction('pdf_batch_group_preview', {
            quantidade_lotes: String(count),
            tamanho_lote: String(size)
          });
          const rows = Array.isArray(data.rows) ? data.rows : [];
          pdfBatchDraft = {
            grupoId: 0,
            titulo: title,
            capacidade: size,
            lotes: Array.from({ length: count }, (_, index) => ({
              id: 0,
              titulo: `${title} - Lote ${index + 1}`,
              pessoas: rows.slice(index * size, (index + 1) * size)
            }))
          };
          pdfActiveBatchIndex = 0;
          pdfSelectedIds.clear();
          renderPdfBatchCards();
          loadPdfCandidates();
          setPdfFeedback('success', `${count} lote(s) com ${size} pessoa(s) foram preparados. Revise os cartões e salve quando estiver pronto.`);
        } catch (error) {
          setPdfFeedback('danger', error instanceof Error ? error.message : 'Não foi possível preparar os lotes.');
        } finally {
          if (btnPreparePdfBatches) {
            btnPreparePdfBatches.innerHTML = originalHtml;
            updatePdfSelectionState();
          }
        }
      }

      async function autoFillPdfBatchGaps() {
        if (pdfBatchDraft.grupoId === 0 && !pdfBatchDraft.lotes.length) {
          setPdfFeedback('warning', 'Primeiro prepare os lotes ou monte os cartões manualmente.');
          return;
        }
        const title = pdfBatchTitle ? pdfBatchTitle.value.trim() : '';
        const capacity = Number(pdfBatchDraft.capacidade) || 0;
        const assignments = pdfDraftAssignments();
        if (!title || !capacity || !assignments.length) {
          setPdfFeedback('warning', 'Informe o título e a quantidade de pessoas antes de completar as vagas.');
          return;
        }
        if (assignments.some((batch) => batch.solicitante_ids.length > capacity)) {
          setPdfFeedback('warning', 'Há um lote acima da quantidade configurada. Remova ou mova pessoas antes de completar as vagas.');
          return;
        }
        const originalHtml = btnAutoFillPdfBatchGaps ? btnAutoFillPdfBatchGaps.innerHTML : '';
        if (btnAutoFillPdfBatchGaps) {
          btnAutoFillPdfBatchGaps.disabled = true;
          btnAutoFillPdfBatchGaps.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Completando...';
        }
        try {
          const payload = {
            tamanho_lote: String(capacity),
            lotes_json: JSON.stringify(assignments)
          };
          if (pdfBatchDraft.grupoId > 0) payload.grupo_id = String(pdfBatchDraft.grupoId);
          const data = await requestPdfBatchAction('pdf_batch_group_autofill', payload);
          const fills = Array.isArray(data.preenchimentos) ? data.preenchimentos : [];
          fills.forEach((fill) => {
            const batch = pdfBatchDraft.lotes[Number(fill?.lote_indice)];
            const people = Array.isArray(fill?.pessoas) ? fill.pessoas : [];
            if (!batch || !people.length) return;
            batch.pessoas.push(...people.filter((person) => !isPdfPersonInDraft(person.solicitante_id)));
          });
          pdfSelectedIds.clear();
          renderPdfBatchCards();
          await loadPdfCandidates();
          setPdfFeedback('success', `${Number(data.total_adicionado) || 0} pessoa(s) foram adicionadas automaticamente somente nas vagas restantes.`);
        } catch (error) {
          setPdfFeedback('danger', error instanceof Error ? error.message : 'Não foi possível completar as vagas.');
        } finally {
          if (btnAutoFillPdfBatchGaps) {
            btnAutoFillPdfBatchGaps.innerHTML = originalHtml;
            updatePdfSelectionState();
          }
        }
      }

      async function savePdfBatchGroup() {
        if (pdfBatchReloadPending) return;
        const title = pdfBatchTitle ? pdfBatchTitle.value.trim() : '';
        const assignments = pdfDraftAssignments();
        if (!title || !assignments.length) {
          setPdfFeedback('warning', 'Prepare os cartões e informe o título antes de salvar.');
          return;
        }
        if (assignments.some((batch) => !batch.titulo || !batch.solicitante_ids.length)) {
          setPdfFeedback('warning', 'Cada lote precisa ter título e ao menos uma pessoa antes de salvar.');
          return;
        }
        const endpoint = pdfBatchDraft.grupoId > 0 ? 'pdf_batch_group_save' : 'pdf_batch_group_create';
        const payload = {
          titulo: title,
          lotes_json: JSON.stringify(assignments),
          tamanho_lote: String(Number(pdfBatchDraft.capacidade) || '')
        };
        if (pdfBatchDraft.grupoId > 0) payload.grupo_id = String(pdfBatchDraft.grupoId);
        const originalHtml = btnSavePdfBatchGroup ? btnSavePdfBatchGroup.innerHTML : '';
        if (btnSavePdfBatchGroup) {
          btnSavePdfBatchGroup.disabled = true;
          btnSavePdfBatchGroup.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Salvando...';
        }
        try {
          const data = await requestPdfBatchAction(endpoint, payload);
          const savedGroupId = Number(data?.grupo?.id) || Number(pdfBatchDraft.grupoId) || 0;
          reloadWithSavedPdfBatchGroup(savedGroupId);
        } catch (error) {
          setPdfFeedback('danger', error instanceof Error ? error.message : 'Não foi possível salvar os lotes.');
        } finally {
          if (btnSavePdfBatchGroup) {
            btnSavePdfBatchGroup.innerHTML = originalHtml;
            updatePdfSelectionState();
          }
        }
      }

      async function openPdfBatchManager(button) {
        const item = button.closest('.pdf-history-item');
        const batchId = Number(item?.dataset.batchId) || 0;
        if (batchId <= 0) return;
        setPdfFeedback('info', 'Carregando os cartões do lote...');
        pdfModal?.show();
        try {
          const data = await requestPdfBatchAction('pdf_batch_group_load', { lote_id: String(batchId) });
          applyPdfBatchGroup(data);
          await loadPdfCandidates();
          setPdfFeedback('success', 'Lote carregado. Faça os ajustes e clique em salvar lotes.');
        } catch (error) {
          setPdfFeedback('danger', error instanceof Error ? error.message : 'Não foi possível carregar o lote.');
        }
      }

      function reloadWithSavedPdfBatchGroup(groupId) {
        const validGroupId = Number(groupId) || 0;
        if (validGroupId <= 0) {
          setPdfFeedback('danger', 'Os lotes foram salvos, mas não foi possível reabrir o grupo automaticamente.');
          return;
        }
        pdfBatchReloadPending = true;
        if (btnSavePdfBatchGroup) {
          btnSavePdfBatchGroup.disabled = true;
          btnSavePdfBatchGroup.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Lotes salvos';
        }
        try {
          window.sessionStorage.setItem(pdfBatchReloadStorageKey, JSON.stringify({
            grupo_id: validGroupId,
            message: 'Lotes salvos com sucesso. A lista abaixo foi recarregada diretamente do banco de dados.'
          }));
        } catch (error) {
          // A confirmação já foi exibida; o recarregamento continua mesmo se o navegador bloquear o armazenamento temporário.
        }
        setPdfFeedback('success', 'Lotes salvos no banco de dados. Recarregando a página e reabrindo esta modal...');
        window.setTimeout(() => window.location.reload(), 450);
      }

      async function restoreSavedPdfBatchGroup() {
        let savedState = null;
        try {
          const raw = window.sessionStorage.getItem(pdfBatchReloadStorageKey);
          if (raw) {
            window.sessionStorage.removeItem(pdfBatchReloadStorageKey);
            savedState = JSON.parse(raw);
          }
        } catch (error) {
          return;
        }
        const groupId = Number(savedState?.grupo_id) || 0;
        if (groupId <= 0) return;

        pdfModal?.show();
        setPdfFeedback('info', 'Confirmando os lotes salvos e carregando os cartões...');
        try {
          const data = await requestPdfBatchAction('pdf_batch_group_load', { grupo_id: String(groupId) });
          applyPdfBatchGroup(data);
          pdfCandidatePage = 1;
          await loadPdfCandidates();
          setPdfFeedback('success', savedState?.message || 'Lotes salvos e recarregados com sucesso.');
        } catch (error) {
          setPdfFeedback('danger', error instanceof Error ? error.message : 'Os lotes foram salvos, mas não puderam ser recarregados automaticamente.');
        }
      }

      async function createPdfBatch(mode) {
        const isManual = mode === 'manual';
        const limit = pdfFirstLimit ? Number(pdfFirstLimit.value) : 0;
        const batchTitle = pdfBatchTitle ? pdfBatchTitle.value.trim() : '';
        if (!batchTitle) {
          setPdfFeedback('warning', 'Informe o título ou a finalidade da lista.');
          pdfBatchTitle?.focus();
          return;
        }
        if (batchTitle.length > 180) {
          setPdfFeedback('warning', 'O título da lista pode ter no máximo 180 caracteres.');
          pdfBatchTitle?.focus();
          return;
        }
        if (!isManual && (!Number.isInteger(limit) || limit < 1 || limit > 5000)) {
          setPdfFeedback('warning', 'Informe uma quantidade entre 1 e 5.000.');
          pdfFirstLimit?.focus();
          return;
        }
        if (isManual && pdfSelectedIds.size === 0) {
          setPdfFeedback('warning', 'Selecione pelo menos um solicitante.');
          return;
        }

        const pdfWindow = window.open('', '_blank');
        if (!pdfWindow) {
          setPdfFeedback('warning', 'O navegador bloqueou a nova aba. Autorize pop-ups e tente novamente.');
          return;
        }
        pdfWindow.opener = null;
        pdfWindow.document.title = 'Preparando PDF...';
        pdfWindow.document.body.textContent = 'Preparando o PDF de cadastros...';

        const actionButton = isManual ? btnGenerateManualPdf : btnGenerateFirstPdf;
        const originalHtml = actionButton ? actionButton.innerHTML : '';
        if (actionButton) {
          actionButton.disabled = true;
          actionButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Gerando...';
        }
        setPdfFeedback('info', 'Validando os cadastros e reservando o lote...');

        try {
          const response = await fetch(`${window.location.pathname}?pdf_batch_create=1`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: new URLSearchParams(pdfRequestPayload({
              titulo: batchTitle,
              modo: mode,
              limite: isManual ? '' : String(limit),
              solicitante_ids: isManual ? Array.from(pdfSelectedIds).join(',') : ''
            }))
          });
          const data = await response.json().catch(() => null);
          if (!response.ok || !data || !data.ok) {
            throw new Error(data && data.message ? data.message : 'Não foi possível gerar o lote.');
          }

          const pdfUrl = new URL(data.pdf_url, window.location.href).href;
          pdfSelectedIds.clear();
          if (pdfBatchTitle) pdfBatchTitle.value = '';
          updatePdfSelectionState();
          setPdfFeedback(
            'success',
            `Lote #${data.lote_id} criado com ${data.total_solicitantes} cadastros. O PDF foi aberto em outra aba.`,
            pdfUrl
          );
          pdfCandidatePage = 1;
          loadPdfCandidates();
          pdfWindow.location.replace(pdfUrl);
        } catch (error) {
          pdfWindow.close();
          setPdfFeedback('danger', error instanceof Error ? error.message : 'Não foi possível gerar o lote.');
        } finally {
          if (actionButton) {
            actionButton.innerHTML = originalHtml;
          }
          updatePdfSelectionState();
        }
      }

      function getSelectedBairros() {
        return bairroChecks
          .filter((check) => check.checked)
          .map((check) => check.value)
          .filter(Boolean);
      }

      function getSelectedBeneficios() {
        return beneficioChecks
          .filter((check) => check.checked)
          .map((check) => check.value)
          .filter(Boolean);
      }

      function getSelectedEmpregos() {
        return empregoChecks
          .filter((check) => check.checked)
          .map((check) => check.value)
          .filter(Boolean);
      }

      function syncBairroSelect() {
        const selecionados = new Set(getSelectedBairros());
        Array.from(selBairro.options || []).forEach((option) => {
          option.selected = selecionados.has(option.value);
        });
      }

      function syncBeneficioSelect() {
        const selecionados = new Set(getSelectedBeneficios());
        Array.from(selBenef.options || []).forEach((option) => {
          option.selected = selecionados.has(option.value);
        });
      }

      function syncEmpregoSelect() {
        if (!selEmprego) return;
        const selecionados = new Set(getSelectedEmpregos());
        Array.from(selEmprego.options || []).forEach((option) => {
          option.selected = selecionados.has(option.value);
        });
      }

      function updateBairroResumo() {
        syncBairroSelect();
        const selecionados = bairroChecks.filter((check) => check.checked);
        if (!bairroResumo) return;

        if (!selecionados.length) {
          bairroResumo.textContent = 'Todos';
          return;
        }

        if (selecionados.length <= 2) {
          bairroResumo.textContent = selecionados.map((check) => check.dataset.label || check.value).join(', ');
          return;
        }

        bairroResumo.textContent = `${selecionados.length} bairros selecionados`;
      }

      function updateBeneficioResumo() {
        syncBeneficioSelect();
        const selecionados = beneficioChecks.filter((check) => check.checked);
        if (!beneficioResumo) return;

        if (!selecionados.length) {
          beneficioResumo.textContent = 'Todos';
          return;
        }

        if (selecionados.length <= 2) {
          beneficioResumo.textContent = selecionados.map((check) => check.dataset.label || check.value).join(', ');
          return;
        }

        beneficioResumo.textContent = `${selecionados.length} benefícios selecionados`;
      }

      function updateEmpregoResumo() {
        syncEmpregoSelect();
        const selecionados = empregoChecks.filter((check) => check.checked);
        if (!empregoResumo) return;

        if (!selecionados.length) {
          empregoResumo.textContent = 'Todos';
          return;
        }

        if (selecionados.length <= 2) {
          empregoResumo.textContent = selecionados.map((check) => check.dataset.label || check.value).join(', ');
          return;
        }

        empregoResumo.textContent = `${selecionados.length} empregos selecionados`;
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
      if (selEmprego) selEmprego.addEventListener('change', fetchData);

      bairroChecks.forEach((check) => {
        check.addEventListener('change', () => {
          updateBairroResumo();
          fetchData();
        });
      });

      if (btnClearBairros) {
        btnClearBairros.addEventListener('click', () => {
          bairroChecks.forEach((check) => {
            check.checked = false;
          });
          updateBairroResumo();
          fetchData();
        });
      }

      beneficioChecks.forEach((check) => {
        check.addEventListener('change', () => {
          updateBeneficioResumo();
          fetchData();
        });
      });

      if (btnClearBeneficios) {
        btnClearBeneficios.addEventListener('click', () => {
          beneficioChecks.forEach((check) => {
            check.checked = false;
          });
          updateBeneficioResumo();
          fetchData();
        });
      }

      empregoChecks.forEach((check) => {
        check.addEventListener('change', () => {
          updateEmpregoResumo();
          fetchData();
        });
      });

      if (btnClearEmpregos) {
        btnClearEmpregos.addEventListener('click', () => {
          empregoChecks.forEach((check) => {
            check.checked = false;
          });
          updateEmpregoResumo();
          fetchData();
        });
      }

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
        bairroChecks.forEach((check) => {
          check.checked = false;
        });
        updateBairroResumo();
        beneficioChecks.forEach((check) => {
          check.checked = false;
        });
        updateBeneficioResumo();
        empregoChecks.forEach((check) => {
          check.checked = false;
        });
        updateEmpregoResumo();

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
        if (expEmprego) expEmprego.value = payload.emprego_id;
        expQ.value = payload.q;

        // ✅ ESSENCIAL: manda o sexo no export
        if (expSexo) expSexo.value = payload.sexo || '';

        frmExport.submit();
      });

      btnExportPDFReport?.addEventListener('click', () => {
        const payload = getPayload();
        const params = new URLSearchParams(payload);
        params.set('print', '1');
        params.set('client_now', new Date().toISOString());
        window.open(`${window.location.pathname}?${params.toString()}`, '_blank', 'noopener');
      });

      btnExportPDF.addEventListener('click', () => {
        pdfSelectedIds.clear();
        pdfCandidateCache.clear();
        pdfBatchDraft = { grupoId: 0, titulo: '', lotes: [] };
        pdfActiveBatchIndex = -1;
        pdfCandidatePage = 1;
        if (pdfBatchTitle) pdfBatchTitle.value = '';
        if (pdfCandidateSearch) pdfCandidateSearch.value = '';
        if (pdfBatchFeedback) {
          pdfBatchFeedback.className = 'd-none';
          pdfBatchFeedback.replaceChildren();
        }
        updatePdfSelectionState();
        renderPdfBatchCards();
        pdfModal?.show();
        loadPdfCandidates();
      });

      document.querySelectorAll('.pdf-batch-edit').forEach((button) => {
        button.addEventListener('click', () => openPdfBatchEditor(button));
      });

      document.querySelectorAll('.pdf-batch-manage').forEach((button) => {
        button.addEventListener('click', () => openPdfBatchManager(button));
      });

      btnPreparePdfBatches?.addEventListener('click', preparePdfBatches);
      btnStartPdfBatchesManual?.addEventListener('click', startPdfBatchesManually);
      btnAutoFillPdfBatchGaps?.addEventListener('click', autoFillPdfBatchGaps);
      btnSavePdfBatchGroup?.addEventListener('click', savePdfBatchGroup);
      btnClearPdfBatchDraft?.addEventListener('click', () => {
        if (!pdfBatchDraft.lotes.length) return;
        if (!window.confirm('Limpar os cartões desta tela? Lotes já salvos continuarão no histórico.')) return;
        pdfSelectedIds.clear();
        pdfBatchDraft = { grupoId: 0, titulo: '', capacidade: 0, lotes: [] };
        pdfActiveBatchIndex = -1;
        renderPdfBatchCards();
        loadPdfCandidates();
        setPdfFeedback('info', 'Rascunho limpo.');
      });

      btnAddPdfCandidates?.addEventListener('click', () => {
        const activeBatch = pdfBatchDraft.lotes[pdfActiveBatchIndex];
        if (!activeBatch) {
          setPdfFeedback('warning', 'Prepare os lotes e escolha um cartão ativo antes de adicionar pessoas.');
          return;
        }
        const people = Array.from(pdfSelectedIds)
          .map((id) => pdfCandidateCache.get(id))
          .filter((candidate) => candidate && !Number(candidate.lote_id) && !isPdfPersonInDraft(candidate.solicitante_id));
        if (!people.length) {
          setPdfFeedback('warning', 'Selecione pessoas disponíveis na lista para adicionar.');
          return;
        }
        const capacity = Number(pdfBatchDraft.capacidade) || 0;
        const vacancies = capacity > 0 ? Math.max(0, capacity - activeBatch.pessoas.length) : people.length;
        if (vacancies <= 0) {
          setPdfFeedback('warning', 'Este lote já atingiu a quantidade configurada. Selecione outro cartão ou remova uma pessoa.');
          return;
        }
        const peopleToAdd = people.slice(0, vacancies);
        activeBatch.pessoas.push(...peopleToAdd);
        pdfSelectedIds.clear();
        renderPdfBatchCards();
        loadPdfCandidates();
        const remainderMessage = peopleToAdd.length < people.length
          ? ` Apenas ${peopleToAdd.length} couberam neste lote; as demais não foram adicionadas.`
          : '';
        setPdfFeedback('success', `${peopleToAdd.length} pessoa(s) adicionada(s) ao lote ativo.${remainderMessage}`);
      });

      async function deletePdfBatch() {
        if (!pdfEditingBatchId) return;
        const title = pdfBatchEditTitle ? pdfBatchEditTitle.value.trim() : '';
        const confirmed = window.confirm(
          `Excluir o lote #${pdfEditingBatchId}${title ? ` (${title})` : ''}? Os cadastros dele voltarão a ficar disponíveis para novas listas.`
        );
        if (!confirmed) return;

        const originalHtml = btnDeletePdfBatch ? btnDeletePdfBatch.innerHTML : '';
        if (btnDeletePdfBatch) {
          btnDeletePdfBatch.disabled = true;
          btnDeletePdfBatch.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Excluindo...';
        }
        if (btnSavePdfBatch) btnSavePdfBatch.disabled = true;
        resetPdfBatchEditFeedback();

        try {
          const data = await requestPdfBatchAction('pdf_batch_delete', {
            lote_id: String(pdfEditingBatchId)
          });
          pdfEditingBatchItem?.remove();
          const historyList = document.querySelector('.pdf-history-list');
          if (historyList && !historyList.querySelector('.pdf-history-item')) {
            historyList.innerHTML = '<p class="small text-muted mb-0">Nenhum lote foi gerado ainda.</p>';
          }
          setPdfFeedback('success', `Lote #${data.id} excluído. ${data.total_solicitantes} cadastro(s) voltaram a ficar disponíveis.`);
          pdfBatchEditModal?.hide();
          pdfEditingBatchId = 0;
          pdfEditingBatchItem = null;
          pdfCandidatePage = 1;
          loadPdfCandidates();
        } catch (error) {
          const message = error instanceof Error ? error.message : 'Não foi possível excluir o lote.';
          setPdfBatchEditFeedback('danger', message);
          setPdfFeedback('danger', message);
        } finally {
          if (btnDeletePdfBatch) {
            btnDeletePdfBatch.innerHTML = originalHtml;
            btnDeletePdfBatch.disabled = false;
          }
          if (btnSavePdfBatch) btnSavePdfBatch.disabled = false;
        }
      }

      document.querySelectorAll('.pdf-batch-delete').forEach((button) => {
        button.addEventListener('click', () => {
          const item = button.closest('.pdf-history-item');
          const batchId = Number(item?.dataset.batchId) || 0;
          if (!item || batchId <= 0) return;

          pdfEditingBatchId = batchId;
          pdfEditingBatchItem = item;
          if (pdfBatchEditTitle) pdfBatchEditTitle.value = item.dataset.batchTitle || '';
          deletePdfBatch();
        });
      });

      btnSavePdfBatch?.addEventListener('click', async () => {
        const title = pdfBatchEditTitle ? pdfBatchEditTitle.value.trim() : '';
        if (!pdfEditingBatchId || !title) {
          setPdfBatchEditFeedback('warning', 'Informe o título ou a finalidade da lista.');
          pdfBatchEditTitle?.focus();
          return;
        }

        const originalHtml = btnSavePdfBatch.innerHTML;
        btnSavePdfBatch.disabled = true;
        if (btnDeletePdfBatch) btnDeletePdfBatch.disabled = true;
        btnSavePdfBatch.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Salvando...';
        resetPdfBatchEditFeedback();

        try {
          const data = await requestPdfBatchAction('pdf_batch_update', {
            lote_id: String(pdfEditingBatchId),
            titulo: title
          });
          if (pdfEditingBatchItem) {
            pdfEditingBatchItem.dataset.batchTitle = data.titulo;
            const titleNode = pdfEditingBatchItem.querySelector('.pdf-history-title');
            if (titleNode) titleNode.textContent = data.titulo;
          }
          setPdfBatchEditFeedback('success', 'Título do lote atualizado.');
          setPdfFeedback('success', `Lote #${data.id} atualizado.`);
        } catch (error) {
          setPdfBatchEditFeedback('danger', error instanceof Error ? error.message : 'Não foi possível editar o lote.');
        } finally {
          btnSavePdfBatch.innerHTML = originalHtml;
          btnSavePdfBatch.disabled = false;
          if (btnDeletePdfBatch) btnDeletePdfBatch.disabled = false;
        }
      });

      btnDeletePdfBatch?.addEventListener('click', deletePdfBatch);

      pdfCandidateSearch?.addEventListener('input', () => {
        window.clearTimeout(pdfSearchTimer);
        pdfSearchTimer = window.setTimeout(() => {
          pdfCandidatePage = 1;
          loadPdfCandidates();
        }, 300);
      });

      pdfBatchTitle?.addEventListener('input', updatePdfSelectionState);

      pdfCandidatePerPage?.addEventListener('change', () => {
        pdfCandidatePage = 1;
        loadPdfCandidates();
      });

      btnPdfCandidatesPrev?.addEventListener('click', () => {
        if (pdfCandidatePage > 1) {
          pdfCandidatePage--;
          loadPdfCandidates();
        }
      });

      btnPdfCandidatesNext?.addEventListener('click', () => {
        if (pdfCandidatePage < pdfCandidatePages) {
          pdfCandidatePage++;
          loadPdfCandidates();
        }
      });

      btnSelectCandidatePage?.addEventListener('click', () => {
        pdfCandidateRows.forEach((candidate) => {
          const id = Number(candidate.solicitante_id) || 0;
          if (id > 0 && !Number(candidate.lote_id) && !isPdfPersonInDraft(id)) pdfSelectedIds.add(id);
        });
        pdfCandidatesBody?.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach((checkbox) => {
          checkbox.checked = true;
        });
        updatePdfSelectionState();
      });

      btnClearPdfSelection?.addEventListener('click', () => {
        pdfSelectedIds.clear();
        pdfCandidatesBody?.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach((checkbox) => {
          checkbox.checked = false;
        });
        updatePdfSelectionState();
      });

      btnGenerateFirstPdf?.addEventListener('click', () => createPdfBatch('primeiros'));
      btnGenerateManualPdf?.addEventListener('click', () => createPdfBatch('manual'));


      setPeriodDates(selPeriodo.value || 'mensal');
      updateBairroResumo();
      updateBeneficioResumo();
      updateEmpregoResumo();
      renderAll(initial);
      restoreSavedPdfBatchGroup();
    });
  </script>

</body>

</html>
