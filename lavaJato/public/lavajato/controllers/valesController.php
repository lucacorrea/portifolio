<?php
// public/lavajato/controllers/valesController.php
declare(strict_types=1);

if (!isset($pdo) || !($pdo instanceof PDO)) die('Controller sem PDO.');

function ymd_or_empty(string $s): string {
  return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
}

$vm = [
  'ok' => (int)($_GET['ok'] ?? 0),
  'err' => (int)($_GET['err'] ?? 0),
  'msg' => (string)($_GET['msg'] ?? ''),
  'de'  => '',
  'ate' => '',
  'cpf' => '',
  'empresaNome' => 'Sua empresa',
  'porDia' => [],
];

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  $vm['err'] = 1;
  $vm['msg'] = 'Empresa inválida.';
  return;
}

/* Nome da empresa */
try {
  $stEmp = $pdo->prepare("SELECT nome_fantasia FROM empresas_peca WHERE cnpj = :c LIMIT 1");
  $stEmp->execute([':c' => $empresaCnpj]);
  $vm['empresaNome'] = (string)($stEmp->fetchColumn() ?: $vm['empresaNome']);
} catch (Throwable $e) {
  // sem quebra
}

/* Filtros */
$de  = ymd_or_empty((string)($_GET['de'] ?? ''));
$ate = ymd_or_empty((string)($_GET['ate'] ?? ''));
$cpfFiltro = preg_replace('/\D+/', '', (string)($_GET['cpf'] ?? ''));

$vm['de']  = $de;
$vm['ate'] = $ate;
$vm['cpf'] = $cpfFiltro;

/* padrão: últimos 30 dias */
if ($de === '' && $ate === '') {
  $de  = date('Y-m-d', strtotime('-30 days'));
  $ate = date('Y-m-d');
  $vm['de']  = $de;
  $vm['ate'] = $ate;
}

try {
  $where = "v.empresa_cnpj = :empresa";
  $params = [':empresa' => $empresaCnpj];

  if ($de !== '') {
    $where .= " AND DATE(v.criado_em) >= :de";
    $params[':de'] = $de;
  }
  if ($ate !== '') {
    $where .= " AND DATE(v.criado_em) <= :ate";
    $params[':ate'] = $ate;
  }
  if ($cpfFiltro !== '') {
    // filtra pelo CPF do lavador (tabela lavadores_peca)
    $where .= " AND REPLACE(REPLACE(REPLACE(l.cpf,'.',''),'-',''),' ','') = :cpf";
    $params[':cpf'] = $cpfFiltro;
  }

  /**
   * Agrupa por DIA + lavador_id
   * - Nome: usa v.lavador_nome (snapshot) e se vazio cai no l.nome
   * - CPF: vem de l.cpf
   */
  $sql = "
    SELECT
      DATE(v.criado_em) AS dia,
      COALESCE(NULLIF(v.lavador_nome,''), l.nome, '-') AS lavador_nome,
      l.cpf AS cpf,
      SUM(v.valor) AS total_dia,
      MAX(v.criado_em) AS ultimo_em
    FROM vales_lavadores_peca v
    LEFT JOIN lavadores_peca l
      ON l.id = v.lavador_id
    WHERE $where
    GROUP BY DATE(v.criado_em), v.lavador_id, lavador_nome, l.cpf
    ORDER BY DATE(v.criado_em) DESC, lavador_nome ASC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $porDia = [];
  foreach ($rows as $r) {
    $dia = (string)($r['dia'] ?? '');
    if ($dia === '') continue;

    // formata ultimo_em (para aparecer no vales.php)
    if (!empty($r['ultimo_em'])) {
      $ts = strtotime((string)$r['ultimo_em']);
      $r['ultimo_em'] = $ts ? date('d/m/Y H:i', $ts) : (string)$r['ultimo_em'];
    } else {
      $r['ultimo_em'] = '-';
    }

    $porDia[$dia][] = $r;
  }

  $vm['porDia'] = $porDia;

} catch (Throwable $e) {
  $vm['err'] = 1;
  $vm['msg'] = 'Erro ao carregar: ' . $e->getMessage();
  $vm['porDia'] = [];
}
