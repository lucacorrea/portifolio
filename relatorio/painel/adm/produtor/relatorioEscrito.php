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

  if (empty($extenso)) {
    $extenso = 'zero';
  }

  $extenso .= ' reais';

  // Centavos
  $centavos = (int)$centavos;
  if ($centavos > 0) {
    $extenso .= ' e ';
    if ($centavos < 10) {
      $extenso .= $unidade[$centavos];
    } elseif ($centavos < 20) {
      $extenso .= $dez[$centavos - 10];
    } else {
      $d = (int)($centavos / 10);
      $u = $centavos % 10;
      $extenso .= $dezena[$d];
      if ($u > 0) {
        $extenso .= ' e ' . $unidade[$u];
      }
    }
    $extenso .= $centavos > 1 ? ' centavos' : ' centavo';
  }

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
  <title>Relatório Oficial - <?= h($config['titulo_feira']) ?></title>

  <style>
    /* ======================
       VARIÁVEIS
    ====================== */
    :root {
      --cor-primaria: #1a365d;
      --cor-secundaria: #2c5282;
      --cor-destaque: #234e52;
      --cor-texto: #1a202c;
      --cor-texto-claro: #4a5568;
      --cor-borda: #cbd5e0;
      --cor-fundo-claro: #f7fafc;
      --cor-branco: #ffffff;
    }

    /* ======================
       RESET
    ====================== */
    *, *::before, *::after {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    /* ======================
       CORPO
    ====================== */
    body {
      font-family: "Times New Roman", Times, Georgia, serif;
      font-size: 12pt;
      line-height: 1.6;
      color: var(--cor-texto);
      background: #e2e8f0;
      padding: 30px 20px;
    }

    /* ======================
       AÇÕES DE IMPRESSÃO
    ====================== */
    .acoes-impressao {
      position: fixed;
      top: 20px;
      right: 20px;
      display: flex;
      gap: 10px;
      z-index: 1000;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      font-family: Arial, sans-serif;
      font-size: 13px;
      font-weight: 600;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
    }

    .btn-imprimir {
      background: var(--cor-primaria);
      color: var(--cor-branco);
      box-shadow: 0 2px 8px rgba(26, 54, 93, 0.3);
    }

    .btn-imprimir:hover {
      background: var(--cor-secundaria);
      transform: translateY(-1px);
    }

    .btn-fechar {
      background: var(--cor-branco);
      color: var(--cor-texto);
      border: 1px solid var(--cor-borda);
    }

    .btn-fechar:hover {
      background: var(--cor-fundo-claro);
    }

    .btn svg {
      width: 16px;
      height: 16px;
    }

    /* ======================
       CONTAINER DO DOCUMENTO
    ====================== */
    .documento-container {
      max-width: 210mm;
      margin: 0 auto;
      background: var(--cor-branco);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .documento {
      padding: 20mm 25mm;
    }

    /* ======================
       CABEÇALHO INSTITUCIONAL
    ====================== */
    .cabecalho {
      text-align: center;
      padding-bottom: 20px;
      margin-bottom: 25px;
      border-bottom: 3px double var(--cor-primaria);
    }

    .logos {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }

    .logo-espaco {
      flex: 0 0 auto;
    }

    .logos img {
      max-height: 70px;
      max-width: 160px;
      object-fit: contain;
    }

    .titulo-prefeitura {
      font-size: 14pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--cor-primaria);
      margin-bottom: 3px;
    }

    .titulo-secretaria {
      font-size: 11pt;
      font-weight: normal;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--cor-texto-claro);
      margin-bottom: 15px;
    }

    .titulo-relatorio {
      font-size: 16pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: var(--cor-primaria);
      margin-bottom: 5px;
      padding-top: 10px;
      border-top: 1px solid var(--cor-borda);
    }

    .subtitulo-relatorio {
      font-size: 12pt;
      font-style: italic;
      font-weight: normal;
      color: var(--cor-destaque);
    }

    /* ======================
       SEÇÕES
    ====================== */
    .secao {
      margin-bottom: 25px;
    }

    .secao-titulo {
      font-size: 12pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--cor-primaria);
      margin-bottom: 12px;
      padding-bottom: 6px;
      border-bottom: 2px solid var(--cor-primaria);
      display: inline-block;
    }

    .secao-conteudo {
      text-align: justify;
      text-indent: 2em;
    }

    .secao-conteudo p {
      margin-bottom: 12px;
    }

    .secao-conteudo p:last-child {
      margin-bottom: 0;
    }

    /* ======================
       PRODUTOS
    ====================== */
    .produtos-intro {
      text-indent: 2em;
      margin-bottom: 15px;
    }

    .categoria-grupo {
      margin-bottom: 12px;
      padding-left: 2em;
    }

    .categoria-nome {
      font-weight: bold;
      color: var(--cor-primaria);
    }

    .categoria-nome::before {
      content: "▸ ";
      color: var(--cor-destaque);
    }

    .categoria-itens {
      display: inline;
    }

    /* ======================
       TABELA FINANCEIRA
    ====================== */
    .tabela-container {
      margin: 20px 0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 11pt;
    }

    table thead th {
      background: var(--cor-primaria);
      color: var(--cor-branco);
      padding: 12px 15px;
      font-weight: bold;
      text-transform: uppercase;
      font-size: 10pt;
      letter-spacing: 0.5px;
      text-align: left;
      border: 1px solid var(--cor-primaria);
    }

    table thead th:last-child {
      text-align: right;
    }

    table tbody td {
      padding: 10px 15px;
      border: 1px solid var(--cor-borda);
      background: var(--cor-branco);
    }

    table tbody tr:nth-child(even) td {
      background: var(--cor-fundo-claro);
    }

    table tbody td:last-child {
      text-align: right;
      font-weight: 600;
      font-family: "Courier New", monospace;
    }

    /* ======================
       TOTAL
    ====================== */
    .total-container {
      margin-top: 20px;
      padding: 15px 20px;
      background: var(--cor-fundo-claro);
      border: 2px solid var(--cor-primaria);
      border-radius: 4px;
    }

    .total-linha {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }

    .total-rotulo {
      font-size: 11pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--cor-primaria);
    }

    .total-valor {
      font-size: 16pt;
      font-weight: bold;
      color: var(--cor-primaria);
      font-family: "Courier New", monospace;
    }

    .total-extenso {
      font-size: 10pt;
      font-style: italic;
      color: var(--cor-texto-claro);
      text-align: right;
      padding-top: 8px;
      border-top: 1px solid var(--cor-borda);
    }

    /* ======================
       CONCLUSÃO
    ====================== */
    .conclusao-box {
      background: var(--cor-fundo-claro);
      padding: 15px 20px;
      border-left: 4px solid var(--cor-destaque);
      margin-top: 10px;
    }

    .conclusao-box p {
      text-indent: 2em;
      margin-bottom: 10px;
    }

    .conclusao-box p:last-child {
      margin-bottom: 0;
    }

    /* ======================
       RODAPÉ
    ====================== */
    .rodape {
      margin-top: 40px;
      padding-top: 20px;
      border-top: 1px solid var(--cor-borda);
    }

    .rodape-data {
      text-align: right;
      font-size: 11pt;
      color: var(--cor-texto);
      margin-bottom: 50px;
    }

    .assinatura-container {
      display: flex;
      justify-content: center;
      margin-top: 30px;
    }

    .assinatura {
      text-align: center;
      min-width: 280px;
    }

    .assinatura-linha {
      border-top: 1px solid var(--cor-texto);
      padding-top: 8px;
      margin-top: 60px;
    }

    .assinatura-nome {
      font-size: 11pt;
      font-weight: bold;
      color: var(--cor-texto);
    }

    .assinatura-cargo {
      font-size: 10pt;
      color: var(--cor-texto-claro);
    }

    /* ======================
       IMPRESSÃO
    ====================== */
    @media print {
      body {
        background: white;
        padding: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .acoes-impressao {
        display: none !important;
      }

      .documento-container {
        box-shadow: none;
        max-width: 100%;
      }

      .documento {
        padding: 15mm 20mm;
      }

      .cabecalho {
        border-bottom-color: var(--cor-primaria) !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      table thead th {
        background: var(--cor-primaria) !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .secao {
        break-inside: avoid;
      }

      .tabela-container {
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

  <!-- AÇÕES -->
  <div class="acoes-impressao">
    <button onclick="window.print()" class="btn btn-imprimir">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
      </svg>
      Imprimir / Salvar PDF
    </button>
    <button onclick="window.close()" class="btn btn-fechar">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
      Fechar
    </button>
  </div>

  <!-- DOCUMENTO -->
  <div class="documento-container">
    <div class="documento">

      <!-- CABEÇALHO INSTITUCIONAL -->
      <header class="cabecalho">
        <?php if ($config['logotipo_prefeitura'] || $config['logotipo_feira']): ?>
          <div class="logos">
            <div class="logo-espaco">
              <?php if ($config['logotipo_prefeitura']): ?>
                <img src="<?= h($config['logotipo_prefeitura']) ?>" alt="Brasão da Prefeitura">
              <?php endif; ?>
            </div>
            <div class="logo-espaco">
              <?php if ($config['logotipo_feira']): ?>
                <img src="<?= h($config['logotipo_feira']) ?>" alt="Logo da Feira">
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="titulo-prefeitura">Prefeitura Municipal de <?= h($config['municipio']) ?> – <?= h($config['estado']) ?></div>
        <?php if ($config['secretaria']): ?>
          <div class="titulo-secretaria"><?= h($config['secretaria']) ?></div>
        <?php endif; ?>
        
        <div class="titulo-relatorio">Relatório da <?= h($config['titulo_feira']) ?></div>
        <?php if ($config['subtitulo_feira']): ?>
          <div class="subtitulo-relatorio"><?= h($config['subtitulo_feira']) ?></div>
        <?php endif; ?>
      </header>

      <!-- 1. INTRODUÇÃO -->
      <?php if ($config['incluir_introducao']): ?>
        <section class="secao">
          <h2 class="secao-titulo">1. Introdução</h2>
          <div class="secao-conteudo">
            <p><?= nl2br(h(substituirVariaveis($config['texto_introducao'], $vars))) ?></p>
            <p>O presente relatório tem por objetivo apresentar os resultados financeiros referentes <?= count($dadosMensais) == 1 ? 'ao mês' : 'aos meses' ?> de <strong><?= h($periodoTextoCapitalizado) ?></strong>, contemplando os principais produtos comercializados e os valores totais arrecadados no período.</p>
          </div>
        </section>
      <?php endif; ?>

      <!-- 2. PRODUTOS COMERCIALIZADOS -->
      <?php if ($config['incluir_produtos_comercializados'] && !empty($produtos)): ?>
        <section class="secao">
          <h2 class="secao-titulo">2. Produtos Comercializados</h2>
          <div class="secao-conteudo">
            <p class="produtos-intro">Durante o período em análise, os produtores rurais locais comercializaram diversos produtos, organizados nas seguintes categorias:</p>
            
            <?php foreach ($produtosAgrupados as $categoria => $prods): ?>
              <?php if (!empty($prods)): ?>
                <div class="categoria-grupo">
                  <span class="categoria-nome"><?= h($categoria) ?>:</span>
                  <span class="categoria-itens"><?= implode(', ', array_map('h', $prods)) ?>.</span>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <!-- 3. RESULTADOS FINANCEIROS -->
      <section class="secao">
        <h2 class="secao-titulo">3. Resultados Financeiros</h2>
        <div class="secao-conteudo" style="text-indent: 0;">
          <p style="text-indent: 2em; margin-bottom: 15px;">A tabela a seguir apresenta o demonstrativo financeiro da arrecadação obtida no período:</p>
          
          <div class="tabela-container">
            <table>
              <thead>
                <tr>
                  <th>Período de Referência</th>
                  <th>Valor Arrecadado (R$)</th>
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

          <div class="total-container">
            <div class="total-linha">
              <span class="total-rotulo">Valor Total do Período:</span>
              <span class="total-valor">R$ <?= number_format($totalPeriodo, 2, ',', '.') ?></span>
            </div>
            <div class="total-extenso">
              <em>(<?= ucfirst(valorPorExtenso($totalPeriodo)) ?>)</em>
            </div>
          </div>
        </div>
      </section>

      <!-- 4. CONCLUSÃO -->
      <?php if ($config['incluir_conclusao']): ?>
        <section class="secao">
          <h2 class="secao-titulo">4. Conclusão</h2>
          <div class="secao-conteudo">
            <div class="conclusao-box">
              <p><?= nl2br(h(substituirVariaveis($config['texto_conclusao'], $vars))) ?></p>
              <?php if (!empty($config['texto_conclusao_extra'])): ?>
                <p><?= nl2br(h(substituirVariaveis($config['texto_conclusao_extra'], $vars))) ?></p>
              <?php endif; ?>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <!-- RODAPÉ -->
      <footer class="rodape">
        <div class="rodape-data">
          <?= h($config['municipio']) ?>-<?= h($config['estado']) ?>, <?= date('d') ?> de <?= $mesesNomes[(int)date('n')] ?> de <?= date('Y') ?>.
        </div>

        <?php if (!empty($config['assinatura_nome'])): ?>
          <div class="assinatura-container">
            <div class="assinatura">
              <div class="assinatura-linha">
                <div class="assinatura-nome"><?= h($config['assinatura_nome']) ?></div>
                <?php if (!empty($config['assinatura_cargo'])): ?>
                  <div class="assinatura-cargo"><?= h($config['assinatura_cargo']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="assinatura-container">
            <div class="assinatura">
              <div class="assinatura-linha">
                <div class="assinatura-nome">Responsável pela Emissão</div>
                <div class="assinatura-cargo"><?= h($config['secretaria']) ?></div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </footer>

    </div>
  </div>

</body>
</html>