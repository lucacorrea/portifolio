<?php

declare(strict_types=1);
session_start();

/* ======================
   SEGURANÇA
====================== */
if (empty($_SESSION['usuario_logado'])) {
  header('Location: ../../../index.php');
  exit;
}

$perfis = $_SESSION['perfis'] ?? [];
if (!is_array($perfis)) $perfis = [$perfis];
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../../operador/index.php');
  exit;
}

function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ======================
   CONEXÃO
====================== */
require '../../../assets/php/conexao.php';
$pdo = db();

/* ======================
   HELPERS
====================== */
function hasTable(PDO $pdo, string $table): bool
{
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = :t
  ");
  $st->execute([':t' => $table]);
  return (int)$st->fetchColumn() > 0;
}

function hasColumn(PDO $pdo, string $table, string $column): bool
{
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = :t
      AND column_name = :c
  ");
  $st->execute([':t' => $table, ':c' => $column]);
  return (int)$st->fetchColumn() > 0;
}

/* ======================
   FEIRA ID
====================== */
$feiraId = 1;

/* ======================
   CARREGAR CONFIGURAÇÕES
====================== */
$config = [
  'titulo_feira' => 'Feira do Produtor Rural',
  'subtitulo_feira' => 'Francisco Lopes da Silva – "Folha"',
  'municipio' => 'Coari',
  'estado' => 'AM',
  'secretaria' => 'Secretaria de Desenvolvimento Rural e Econômico',
  'logotipo_prefeitura' => '',
  'logotipo_feira' => '',
  'incluir_introducao' => 1,
  'texto_introducao' => 'A Feira do Produtor Rural "{titulo_feira}" é um espaço de valorização da agricultura familiar e de comercialização de alimentos cultivados no município de {municipio}-{estado}.',
  'incluir_produtos_comercializados' => 1,
  'incluir_conclusao' => 1,
  'texto_conclusao' => 'O levantamento demonstra a relevância da {titulo_feira} para a economia agrícola do município, garantindo escoamento da produção, geração de renda e acesso da população a alimentos saudáveis.',
  'texto_conclusao_extra' => 'O investimento na agricultura familiar e no fortalecimento dessa feira é essencial para o desenvolvimento sustentável de {municipio}-{estado}.',
  'assinatura_nome' => '',
  'assinatura_cargo' => '',
];

try {
  $st = $pdo->query("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'config_relatorio'
  ");

  if ((int)$st->fetchColumn() > 0) {
    $st = $pdo->prepare("SELECT * FROM config_relatorio WHERE feira_id = :feira_id");
    $st->execute([':feira_id' => $feiraId]);
    $savedConfig = $st->fetch(PDO::FETCH_ASSOC);

    if ($savedConfig) {
      foreach ($config as $key => $defaultValue) {
        if (isset($savedConfig[$key])) {
          $config[$key] = $savedConfig[$key];
        }
      }
    }
  }
} catch (Exception $e) {
  // Usar configurações padrão
}

/* ======================
   FUNÇÃO VALOR POR EXTENSO
====================== */
function valorPorExtenso(float $valor): string
{
  $valor = number_format($valor, 2, '.', '');
  list($inteiro, $centavos) = explode('.', $valor);

  $unidade = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
  $dez = ['dez', 'onze', 'doze', 'treze', 'catorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
  $dezena = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
  $centena = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

  $extenso = '';
  $inteiro = str_pad($inteiro, 12, '0', STR_PAD_LEFT);

  $bilhao = (int)substr($inteiro, 0, 3);
  if ($bilhao > 0) {
    $extenso .= converterGrupo($bilhao, $unidade, $dez, $dezena, $centena);
    $extenso .= $bilhao > 1 ? ' bilhões' : ' bilhão';
  }

  $milhao = (int)substr($inteiro, 3, 3);
  if ($milhao > 0) {
    if ($extenso) $extenso .= $milhao > 0 && $bilhao > 0 ? ', ' : ' e ';
    $extenso .= converterGrupo($milhao, $unidade, $dez, $dezena, $centena);
    $extenso .= $milhao > 1 ? ' milhões' : ' milhão';
  }

  $milhar = (int)substr($inteiro, 6, 3);
  if ($milhar > 0) {
    if ($extenso) $extenso .= ', ';
    if ($milhar == 1) {
      $extenso .= 'mil';
    } else {
      $extenso .= converterGrupo($milhar, $unidade, $dez, $dezena, $centena) . ' mil';
    }
  }

  $cent = (int)substr($inteiro, 9, 3);
  if ($cent > 0) {
    if ($extenso && $milhar == 0 && $milhao == 0 && $bilhao == 0) {
      $extenso .= ' e ';
    } elseif ($extenso) {
      $extenso .= $milhar > 0 || $milhao > 0 || $bilhao > 0 ? ', ' : ' e ';
    }
    $extenso .= converterGrupo($cent, $unidade, $dez, $dezena, $centena);
  }

  $extenso .= ' reais';

  return $extenso;
}

function converterGrupo(int $numero, array $unidade, array $dez, array $dezena, array $centena): string
{
  $texto = '';

  $c = (int)($numero / 100);
  $d = (int)(($numero % 100) / 10);
  $u = $numero % 10;

  if ($c > 0) {
    if ($numero == 100) {
      $texto .= 'cem';
    } else {
      $texto .= $centena[$c];
    }
  }

  if ($d > 0) {
    if ($texto) $texto .= ' e ';
    if ($d == 1) {
      $texto .= $dez[$u];
      return $texto;
    } else {
      $texto .= $dezena[$d];
    }
  }

  if ($u > 0) {
    if ($texto) $texto .= ' e ';
    $texto .= $unidade[$u];
  }

  return $texto;
}

function substituirVariaveis(string $texto, array $vars): string
{
  foreach ($vars as $key => $value) {
    $texto = str_replace('{' . $key . '}', $value, $texto);
  }
  return $texto;
}

/* ======================
   PARÂMETROS
====================== */
$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim = $_GET['data_fim'] ?? '';

if (!$dataInicio || !$dataFim) {
  die('Período não especificado');
}

/* ======================
   CAMPOS
====================== */
$colDataVenda = hasColumn($pdo, 'vendas', 'data_venda');
$colDataHora  = hasColumn($pdo, 'vendas', 'data_hora');
$colCategoria = hasColumn($pdo, 'produtos', 'categoria_id');

if ($colDataVenda) {
  $dateExpr = "v.data_venda";
} elseif ($colDataHora) {
  $dateExpr = "DATE(v.data_hora)";
} else {
  $dateExpr = "DATE(v.criado_em)";
}

/* ======================
   BUSCAR DADOS
====================== */
$params = [
  ':ini' => $dataInicio,
  ':fim' => $dataFim,
  ':f'   => $feiraId,
];

// Período formatado
$meses = [];
$inicio = new DateTime($dataInicio);
$fim = new DateTime($dataFim);
$interval = new DateInterval('P1M');
$period = new DatePeriod($inicio, $interval, $fim->modify('+1 day'));

$mesesNomes = [
  '', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'
];

$mesesNomesCapitalizados = [
  '', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
];

$periodoTexto = '';
$periodoTextoCapitalizado = '';
if ($inicio->format('Y-m') == $fim->format('Y-m')) {
  $periodoTexto = $mesesNomes[(int)$inicio->format('n')] . ' de ' . $inicio->format('Y');
  $periodoTextoCapitalizado = $mesesNomesCapitalizados[(int)$inicio->format('n')] . ' de ' . $inicio->format('Y');
} else {
  $mesesPeriodo = [];
  $mesesPeriodoCapitalizados = [];
  foreach ($period as $dt) {
    $mes = $mesesNomes[(int)$dt->format('n')];
    $mesCapitalizado = $mesesNomesCapitalizados[(int)$dt->format('n')];
    if (!in_array($mes, $mesesPeriodo)) {
      $mesesPeriodo[] = $mes;
      $mesesPeriodoCapitalizados[] = $mesCapitalizado;
    }
  }

  if (count($mesesPeriodo) > 1) {
    $ultimo = array_pop($mesesPeriodo);
    $periodoTexto = implode(', ', $mesesPeriodo) . ' e ' . $ultimo;
    
    $ultimoCapitalizado = array_pop($mesesPeriodoCapitalizados);
    $periodoTextoCapitalizado = $mesesPeriodoCapitalizados[0] . ' a ' . $ultimoCapitalizado . ' de ' . $inicio->format('Y');
  } else {
    $periodoTexto = $mesesPeriodo[0] ?? '';
    $periodoTextoCapitalizado = $mesesPeriodoCapitalizados[0] ?? '';
  }
  
  if (count($mesesPeriodo) <= 1) {
    $periodoTexto .= ' de ' . $inicio->format('Y');
    $periodoTextoCapitalizado .= ' de ' . $inicio->format('Y');
  }
}

// Dados por mês
$st = $pdo->prepare("
  SELECT 
    DATE_FORMAT({$dateExpr}, '%m/%Y') as mes_ano,
    SUM(v.total) as total
  FROM vendas v
  WHERE {$dateExpr} BETWEEN :ini AND :fim
    AND v.feira_id = :f
  GROUP BY DATE_FORMAT({$dateExpr}, '%Y-%m')
  ORDER BY DATE_FORMAT({$dateExpr}, '%Y-%m')
");
$st->execute($params);
$dadosMensais = $st->fetchAll();

foreach ($dadosMensais as &$dado) {
  list($mes, $ano) = explode('/', $dado['mes_ano']);
  $mesNome = $mesesNomesCapitalizados[(int)$mes];
  $dado['mes_ano_formatado'] = $mesNome . '/' . $ano;
}

$totalPeriodo = array_sum(array_column($dadosMensais, 'total'));

// Produtos comercializados organizados por categoria
$st = $pdo->prepare("
  SELECT 
    COALESCE(c.nome, 'Diversos') AS categoria,
    GROUP_CONCAT(DISTINCT pr.nome ORDER BY pr.nome SEPARATOR ', ') AS produtos
  FROM venda_itens vi
  JOIN produtos pr ON pr.id = vi.produto_id
  LEFT JOIN categorias c ON c.id = pr.categoria_id AND c.feira_id = pr.feira_id
  JOIN vendas v ON v.id = vi.venda_id
  WHERE {$dateExpr} BETWEEN :ini AND :fim
    AND v.feira_id = :f
  GROUP BY COALESCE(c.id, 999), COALESCE(c.nome, 'Diversos')
  ORDER BY c.nome ASC
");
$st->execute($params);
$produtosPorCategoria = $st->fetchAll(PDO::FETCH_ASSOC);

$produtosAgrupados = [];
foreach ($produtosPorCategoria as $row) {
  if (!empty($row['produtos'])) {
    $produtosAgrupados[$row['categoria']] = explode(', ', $row['produtos']);
  }
}

$produtos = [];
foreach ($produtosAgrupados as $prods) {
  $produtos = array_merge($produtos, $prods);
}

$vars = [
  'titulo_feira' => $config['subtitulo_feira'] ?: $config['titulo_feira'],
  'subtitulo_feira' => $config['subtitulo_feira'],
  'municipio' => $config['municipio'],
  'estado' => $config['estado'],
  'periodo' => $periodoTexto,
  'periodo_capitalizado' => $periodoTextoCapitalizado,
  'total_periodo' => 'R$ ' . number_format($totalPeriodo, 2, ',', '.'),
];

setlocale(LC_TIME, 'pt_BR.UTF-8', 'pt_BR', 'portuguese');

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Relatório - <?= h($config['titulo_feira']) ?></title>
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">

  <style>
    /* ======================
       CSS VARIABLES
    ====================== */
    :root {
      --color-primary: #1e3a5f;
      --color-primary-light: #2d5a8a;
      --color-primary-dark: #0f1f33;
      --color-accent: #2e7d32;
      --color-accent-light: #4caf50;
      --color-gold: #c9a227;
      --color-text: #1a1a1a;
      --color-text-muted: #555;
      --color-border: #ddd;
      --color-bg-light: #f8f9fa;
      --color-white: #fff;
      --font-serif: 'Merriweather', Georgia, serif;
      --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
      --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
      --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
      --radius-sm: 4px;
      --radius-md: 8px;
      --radius-lg: 12px;
    }

    /* ======================
       RESET & BASE
    ====================== */
    *, *::before, *::after {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: var(--font-serif);
      font-size: 11pt;
      line-height: 1.7;
      color: var(--color-text);
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 40px 20px;
    }

    /* ======================
       FLOATING ACTIONS
    ====================== */
    .print-actions {
      position: fixed;
      top: 24px;
      right: 24px;
      display: flex;
      gap: 12px;
      z-index: 1000;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 24px;
      font-family: var(--font-sans);
      font-size: 14px;
      font-weight: 600;
      border: none;
      border-radius: var(--radius-lg);
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
      color: var(--color-white);
      box-shadow: var(--shadow-md), 0 4px 15px rgba(30, 58, 95, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg), 0 6px 20px rgba(30, 58, 95, 0.4);
    }

    .btn-secondary {
      background: var(--color-white);
      color: var(--color-text);
      box-shadow: var(--shadow-md);
    }

    .btn-secondary:hover {
      background: var(--color-bg-light);
      transform: translateY(-2px);
    }

    .btn svg {
      width: 18px;
      height: 18px;
    }

    /* ======================
       DOCUMENT CONTAINER
    ====================== */
    .document-container {
      max-width: 210mm;
      margin: 0 auto;
      background: var(--color-white);
      box-shadow: var(--shadow-lg);
      border-radius: var(--radius-lg);
      overflow: hidden;
    }

    .document {
      padding: 25mm 28mm;
      position: relative;
    }

    /* ======================
       HEADER
    ====================== */
    .header {
      text-align: center;
      margin-bottom: 32px;
      padding-bottom: 24px;
      border-bottom: 2px solid var(--color-primary);
      position: relative;
    }

    .header::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 4px;
      background: var(--color-gold);
      border-radius: 2px;
    }

    .logos {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding: 0 10px;
    }

    .logo-wrapper {
      flex: 0 0 auto;
    }

    .logos img {
      max-height: 75px;
      max-width: 180px;
      object-fit: contain;
    }

    .header-content {
      padding: 0 20px;
    }

    .header h1 {
      font-family: var(--font-sans);
      font-size: 13pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--color-primary);
      margin-bottom: 4px;
    }

    .header .secretaria {
      font-family: var(--font-sans);
      font-size: 10pt;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--color-text-muted);
      margin-bottom: 16px;
    }

    .header h2 {
      font-family: var(--font-sans);
      font-size: 15pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: var(--color-primary-dark);
      margin-bottom: 6px;
      position: relative;
      display: inline-block;
    }

    .header h3 {
      font-family: var(--font-serif);
      font-size: 12pt;
      font-style: italic;
      font-weight: 400;
      color: var(--color-accent);
    }

    /* ======================
       SECTIONS
    ====================== */
    .section {
      margin-bottom: 28px;
    }

    .section-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 14px;
    }

    .section-icon {
      width: 32px;
      height: 32px;
      background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
      border-radius: var(--radius-sm);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--color-white);
      flex-shrink: 0;
    }

    .section-icon svg {
      width: 18px;
      height: 18px;
    }

    .section-title {
      font-family: var(--font-sans);
      font-size: 13pt;
      font-weight: 700;
      color: var(--color-primary-dark);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .section-content {
      padding-left: 44px;
    }

    .section-content p {
      text-align: justify;
      margin-bottom: 10px;
      line-height: 1.8;
    }

    .section-content p:last-child {
      margin-bottom: 0;
    }

    /* ======================
       PRODUCTS LIST
    ====================== */
    .produtos-intro {
      margin-bottom: 16px;
      color: var(--color-text);
    }

    .produtos-grid {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .categoria-card {
      background: var(--color-bg-light);
      border-radius: var(--radius-md);
      padding: 14px 18px;
      border-left: 4px solid var(--color-accent);
    }

    .categoria-nome {
      font-family: var(--font-sans);
      font-size: 10pt;
      font-weight: 600;
      color: var(--color-accent);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 6px;
    }

    .categoria-produtos {
      font-size: 10.5pt;
      color: var(--color-text);
      line-height: 1.6;
    }

    /* ======================
       FINANCIAL TABLE
    ====================== */
    .table-wrapper {
      margin: 20px 0;
      border-radius: var(--radius-md);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      border: 1px solid var(--color-border);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 11pt;
    }

    thead {
      background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
    }

    thead th {
      padding: 14px 20px;
      font-family: var(--font-sans);
      font-weight: 600;
      font-size: 10pt;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--color-white);
      text-align: left;
    }

    thead th:last-child {
      text-align: right;
    }

    tbody tr {
      border-bottom: 1px solid var(--color-border);
      transition: background 0.15s ease;
    }

    tbody tr:last-child {
      border-bottom: none;
    }

    tbody tr:nth-child(even) {
      background: var(--color-bg-light);
    }

    tbody tr:hover {
      background: #e8f4e8;
    }

    tbody td {
      padding: 14px 20px;
      vertical-align: middle;
    }

    tbody td:last-child {
      text-align: right;
      font-family: var(--font-sans);
      font-weight: 600;
      color: var(--color-primary);
    }

    /* ======================
       TOTAL BOX
    ====================== */
    .total-box {
      background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
      border-radius: var(--radius-md);
      padding: 20px 24px;
      margin-top: 20px;
      color: var(--color-white);
    }

    .total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }

    .total-label {
      font-family: var(--font-sans);
      font-size: 11pt;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      opacity: 0.9;
    }

    .total-value {
      font-family: var(--font-sans);
      font-size: 20pt;
      font-weight: 700;
      color: var(--color-gold);
    }

    .total-extenso {
      font-size: 10pt;
      font-style: italic;
      opacity: 0.85;
      padding-top: 8px;
      border-top: 1px solid rgba(255,255,255,0.2);
    }

    /* ======================
       CONCLUSION
    ====================== */
    .conclusao-content {
      background: linear-gradient(135deg, #f0f7f0 0%, #e8f5e9 100%);
      border-radius: var(--radius-md);
      padding: 20px 24px;
      border-left: 4px solid var(--color-accent);
    }

    .conclusao-content p {
      margin-bottom: 12px;
    }

    .conclusao-content p:last-child {
      margin-bottom: 0;
    }

    /* ======================
       FOOTER
    ====================== */
    .footer {
      margin-top: 40px;
      padding-top: 20px;
      border-top: 1px solid var(--color-border);
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
    }

    .footer-location {
      font-family: var(--font-sans);
      font-size: 10pt;
      color: var(--color-text-muted);
    }

    .footer-signature {
      text-align: center;
      min-width: 200px;
    }

    .signature-line {
      width: 100%;
      height: 1px;
      background: var(--color-text);
      margin-bottom: 8px;
    }

    .signature-name {
      font-family: var(--font-sans);
      font-size: 10pt;
      font-weight: 600;
      color: var(--color-text);
    }

    .signature-cargo {
      font-size: 9pt;
      color: var(--color-text-muted);
    }

    /* ======================
       PRINT STYLES
    ====================== */
    @media print {
      body {
        background: white;
        padding: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .print-actions {
        display: none !important;
      }

      .document-container {
        box-shadow: none;
        border-radius: 0;
        max-width: 100%;
      }

      .document {
        padding: 15mm 20mm;
      }

      .section-icon {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      thead {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .total-box {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .categoria-card {
        break-inside: avoid;
      }

      .table-wrapper {
        break-inside: avoid;
      }
    }

    @page {
      size: A4;
      margin: 0;
    }
  </style>
</head>

<body>

  <!-- FLOATING ACTIONS -->
  <div class="print-actions">
    <button onclick="window.print()" class="btn btn-primary">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
      </svg>
      Imprimir / PDF
    </button>
    <button onclick="window.close()" class="btn btn-secondary">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
      Fechar
    </button>
  </div>

  <!-- DOCUMENT -->
  <div class="document-container">
    <div class="document">

      <!-- HEADER -->
      <header class="header">
        <?php if ($config['logotipo_prefeitura'] || $config['logotipo_feira']): ?>
          <div class="logos">
            <div class="logo-wrapper">
              <?php if ($config['logotipo_prefeitura']): ?>
                <img src="<?= h($config['logotipo_prefeitura']) ?>" alt="Logo Prefeitura">
              <?php endif; ?>
            </div>
            <div class="logo-wrapper">
              <?php if ($config['logotipo_feira']): ?>
                <img src="<?= h($config['logotipo_feira']) ?>" alt="Logo Feira">
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="header-content">
          <h1>Prefeitura Municipal de <?= h($config['municipio']) ?> – <?= h($config['estado']) ?></h1>
          <?php if ($config['secretaria']): ?>
            <div class="secretaria"><?= h($config['secretaria']) ?></div>
          <?php endif; ?>
          <h2>Relatório da <?= h($config['titulo_feira']) ?></h2>
          <?php if ($config['subtitulo_feira']): ?>
            <h3><?= h($config['subtitulo_feira']) ?></h3>
          <?php endif; ?>
        </div>
      </header>

      <!-- INTRODUÇÃO -->
      <?php if ($config['incluir_introducao']): ?>
        <section class="section">
          <div class="section-header">
            <div class="section-icon">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h2 class="section-title">Introdução</h2>
          </div>
          <div class="section-content">
            <p><?= nl2br(h(substituirVariaveis($config['texto_introducao'], $vars))) ?> Este relatório apresenta os resultados financeiros referentes <?= count($dadosMensais) == 1 ? 'ao mês' : 'aos meses' ?> de <?= h($periodoTextoCapitalizado) ?>, com destaque para os principais produtos e valores arrecadados.</p>
          </div>
        </section>
      <?php endif; ?>

      <!-- PRODUTOS COMERCIALIZADOS -->
      <?php if ($config['incluir_produtos_comercializados'] && !empty($produtos)): ?>
        <section class="section">
          <div class="section-header">
            <div class="section-icon">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <h2 class="section-title">Produtos Comercializados</h2>
          </div>
          <div class="section-content">
            <p class="produtos-intro">Os agricultores locais comercializaram uma grande variedade de itens durante o período, organizados nas seguintes categorias:</p>
            
            <div class="produtos-grid">
              <?php foreach ($produtosAgrupados as $categoria => $prods): ?>
                <?php if (!empty($prods)): ?>
                  <div class="categoria-card">
                    <div class="categoria-nome"><?= h($categoria) ?></div>
                    <div class="categoria-produtos"><?= implode(', ', array_map('h', $prods)) ?>.</div>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <!-- RESULTADOS FINANCEIROS -->
      <section class="section">
        <div class="section-header">
          <div class="section-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h2 class="section-title">Resultados Financeiros</h2>
        </div>
        <div class="section-content">
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Período</th>
                  <th>Valor Arrecadado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dadosMensais as $dado): ?>
                  <tr>
                    <td><?= h($dado['mes_ano_formatado']) ?></td>
                    <td>R$ <?= number_format((float)$dado['total'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="total-box">
            <div class="total-row">
              <span class="total-label">Total do Período</span>
              <span class="total-value">R$ <?= number_format($totalPeriodo, 2, ',', '.') ?></span>
            </div>
            <div class="total-extenso">
              Por extenso: <?= valorPorExtenso($totalPeriodo) ?>.
            </div>
          </div>
        </div>
      </section>

      <!-- CONCLUSÃO -->
      <?php if ($config['incluir_conclusao']): ?>
        <section class="section">
          <div class="section-header">
            <div class="section-icon">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h2 class="section-title">Conclusão</h2>
          </div>
          <div class="section-content">
            <div class="conclusao-content">
              <p><?= nl2br(h(substituirVariaveis($config['texto_conclusao'], $vars))) ?></p>
              <?php if (!empty($config['texto_conclusao_extra'])): ?>
                <p><?= nl2br(h(substituirVariaveis($config['texto_conclusao_extra'], $vars))) ?></p>
              <?php endif; ?>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <!-- FOOTER -->
      <footer class="footer">
        <div class="footer-location">
          <?= h($config['municipio']) ?>-<?= h($config['estado']) ?>, <?= strftime('%d de %B de %Y', time()) ?>
        </div>
        
        <?php if (!empty($config['assinatura_nome'])): ?>
          <div class="footer-signature">
            <div class="signature-line"></div>
            <div class="signature-name"><?= h($config['assinatura_nome']) ?></div>
            <?php if (!empty($config['assinatura_cargo'])): ?>
              <div class="signature-cargo"><?= h($config['assinatura_cargo']) ?></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </footer>

    </div>
  </div>

</body>
</html>