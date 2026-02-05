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
   CARREGAR CONFIGURAÇÕES
====================== */
$config = [
  'titulo_feira' => 'Feira Alternativa',
  'subtitulo_feira' => 'Francisco Lopes da Silva – "Folha"',
  'municipio' => 'Coari',
  'estado' => 'AM',
  'secretaria' => 'Secretaria de Desenvolvimento Rural e Econômico',
  'logotipo_prefeitura' => '',
  'logotipo_feira' => '',
  'incluir_introducao' => 1,
  'texto_introducao' => 'A Feira Alternativa "{titulo_feira}" é um espaço de valorização da agricultura familiar e de comercialização de alimentos cultivados no município de {municipio}-{estado}.',
  'incluir_produtos_comercializados' => 1,
  'incluir_conclusao' => 1,
  'texto_conclusao' => 'O levantamento demonstra a relevância da {titulo_feira} para a economia agrícola do município, garantindo escoamento da produção, geração de renda e acesso da população a alimentos saudáveis.',
  'assinatura_nome' => '',
  'assinatura_cargo' => '',
];

try {
  $st = $pdo->query("
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
      AND table_name = 'relatorio_config'
  ");

  if ((int)$st->fetchColumn() > 0) {
    $st = $pdo->query("SELECT * FROM relatorio_config WHERE id = 1");
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
   FEIRA
====================== */
$feiraId = 2;

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
  '',
  'janeiro',
  'fevereiro',
  'março',
  'abril',
  'maio',
  'junho',
  'julho',
  'agosto',
  'setembro',
  'outubro',
  'novembro',
  'dezembro'
];

$periodoTexto = '';
if ($inicio->format('Y-m') == $fim->format('Y-m')) {
  $periodoTexto = $mesesNomes[(int)$inicio->format('n')] . ' de ' . $inicio->format('Y');
} else {
  $mesesPeriodo = [];
  foreach ($period as $dt) {
    $mes = $mesesNomes[(int)$dt->format('n')];
    if (!in_array($mes, $mesesPeriodo)) {
      $mesesPeriodo[] = $mes;
    }
  }

  if (count($mesesPeriodo) > 1) {
    $ultimo = array_pop($mesesPeriodo);
    $periodoTexto = implode(', ', $mesesPeriodo) . ' e ' . $ultimo;
  } else {
    $periodoTexto = $mesesPeriodo[0];
  }
  $periodoTexto .= ' de ' . $inicio->format('Y');
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

// Total
$totalPeriodo = array_sum(array_column($dadosMensais, 'total'));

// Produtos comercializados
$st = $pdo->prepare("
  SELECT DISTINCT pr.nome
  FROM venda_itens vi
  JOIN produtos pr ON pr.id = vi.produto_id
  JOIN vendas v ON v.id = vi.venda_id
  WHERE {$dateExpr} BETWEEN :ini AND :fim
    AND v.feira_id = :f
  ORDER BY pr.nome
");
$st->execute($params);
$produtos = $st->fetchAll(PDO::FETCH_COLUMN);

// Organizar produtos (simulação simplificada)
$produtosAgrupados = [
  'Frutas' => [],
  'Produtos derivados da mandioca' => [],
  'Legumes e hortaliças' => [],
  'Grãos e outros' => [],
  'Diversos' => [],
];

$categoriasFrutas = ['abacaxi', 'laranja', 'tangerina', 'limão', 'mamão', 'maracujá', 'cupuaçu', 'abiu', 'abacate', 'banana', 'melancia', 'melão', 'goiaba', 'açaí', 'caju'];
$categoriasMandioca = ['macaxeira', 'farinha', 'goma', 'tapioca', 'beiju'];
$categoriasLegumes = ['jerimum', 'pimenta', 'pepino', 'alface', 'cebola', 'tomate', 'couve', 'repolho', 'cheiro-verde', 'chicória', 'maxixe'];
$categoriasGraos = ['milho'];

foreach ($produtos as $prod) {
  $prodLower = strtolower($prod);
  $encontrou = false;

  foreach ($categoriasFrutas as $fruta) {
    if (strpos($prodLower, $fruta) !== false) {
      $produtosAgrupados['Frutas'][] = $prod;
      $encontrou = true;
      break;
    }
  }

  if (!$encontrou) {
    foreach ($categoriasMandioca as $man) {
      if (strpos($prodLower, $man) !== false) {
        $produtosAgrupados['Produtos derivados da mandioca'][] = $prod;
        $encontrou = true;
        break;
      }
    }
  }

  if (!$encontrou) {
    foreach ($categoriasLegumes as $leg) {
      if (strpos($prodLower, $leg) !== false) {
        $produtosAgrupados['Legumes e hortaliças'][] = $prod;
        $encontrou = true;
        break;
      }
    }
  }

  if (!$encontrou) {
    foreach ($categoriasGraos as $grao) {
      if (strpos($prodLower, $grao) !== false) {
        $produtosAgrupados['Grãos e outros'][] = $prod;
        $encontrou = true;
        break;
      }
    }
  }

  if (!$encontrou) {
    $produtosAgrupados['Diversos'][] = $prod;
  }
}

// Remover categorias vazias
$produtosAgrupados = array_filter($produtosAgrupados);

// Variáveis para substituição
$vars = [
  'titulo_feira' => $config['subtitulo_feira'] ?: $config['titulo_feira'],
  'subtitulo_feira' => $config['subtitulo_feira'],
  'municipio' => $config['municipio'],
  'estado' => $config['estado'],
  'periodo' => $periodoTexto,
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

  <style>
    /* ======================
   RESET
====================== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    /* ======================
   BODY
====================== */
    body {
      font-family: "Times New Roman", Georgia, serif;
      line-height: 1.6;
      color: #000;
      background: #dcdcdc;
    }

    /* ======================
   ÁREA DE PRÉ-VISUALIZAÇÃO
====================== */
    .preview-container {
      max-width: 210mm;
      min-height: 297mm;
      margin: 0 auto;
      background: #fff;
      box-shadow: 0 0 22px rgba(0, 0, 0, 0.25);
    }

    .page {
      padding: 10mm 30mm 35mm;
      position: relative;
    }

    /* ======================
   CABEÇALHO
====================== */
    .header {
      text-align: center;
      margin-bottom: 26px;
    }

    .logos {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      margin-bottom: 14px;
    }

    .logo-left {
      text-align: left;
    }

    .logo-right {
      text-align: right;
    }

    .logos img {
      max-height: 80px;
      max-width: 180px;
    }

    .header h1 {
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      margin: 4px 0;
    }

    .header .secretaria {
      font-size: 11px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      margin-bottom: 4px;
    }

    .header h2 {
      font-size: 14px;
      font-weight: bold;
      text-transform: uppercase;
      margin-top: 10px;
    }

    .header h3 {
      font-size: 12px;
      font-style: italic;
      font-weight: normal;
      margin-top: 4px;
    }

    /* Linha institucional */
    .header-divider {
      margin-top: 14px;
      border-top: 2px solid #2f5597;
    }

    /* ======================
   SEÇÕES
====================== */
    .section {
      margin-bottom: 22px;
    }

    .section-title {
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
      margin-bottom: 6px;
      color: #2f5597;
    }

    /* Textos */
    .intro,
    .conclusao,
    .produtos-texto,
    .produtos-lista p {
      font-size: 11px;
      text-align: justify;
      line-height: 1.55;
    }

    /* ======================
   TABELA (MELHORADA)
====================== */
    table {
      width: 65%;
      margin: 18px auto 14px;
      border-collapse: collapse;
      font-size: 11px;
      background: #ffffff;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
    }

    /* Cabeçalho */
    table thead th {
      background: #2f5597;
      color: #ffffff;
      border: 1px solid #1f3f73;
      padding: 8px 10px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }

    /* Corpo */
    table tbody td {
      border: 1px solid #c3c9d4;
      padding: 7px 10px;
      vertical-align: middle;
    }

    /* Linhas zebrada */
    table tbody tr:nth-child(even) {
      background: #f4f6fa;
    }

    table tbody tr:nth-child(odd) {
      background: #ffffff;
    }

    /* Hover (somente na tela) */
    @media screen {
      table tbody tr:hover {
        background: #e9effb;
      }
    }

    /* Alinhamento */
    .text-right {
      text-align: right;
    }

    /* Linha de total */
    tr.total-row td {
      background: #e1e7f5;
      font-weight: bold;
      border-top: 2px solid #2f5597;
    }

    /* Total por extenso */
    .total-extenso {
      text-align: right;
      font-size: 11px;
      font-style: italic;
      margin-top: 6px;
      color: #333;
    }

    /* ======================
   LISTAS
====================== */
    .produtos-lista {
      margin-left: 14px;
    }

    .categoria-titulo {
      font-weight: bold;
    }

    /* ======================
   RODAPÉ
====================== */
    .footer {
      margin-top: 30px;
      font-size: 11px;
      text-align: left;
    }

    /* ======================
   BOTÕES DE AÇÃO
====================== */
    .print-actions {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #fff;
      padding: 14px;
      border-radius: 8px;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
      z-index: 10000;
    }

    .btn {
      padding: 10px 18px;
      background: #2f5597;
      color: #fff;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      border: none;
      cursor: pointer;
    }

    .btn:hover {
      background: #1f3f73;
    }

    .btn-secondary {
      background: #6c757d;
    }

    .btn-secondary:hover {
      background: #545b62;
    }

    /* ======================
   PRINT
====================== */
    @media print {
      body {
        background: #fff;
        padding: 0;
      }

      .preview-container {
        box-shadow: none;
      }

      .print-actions {
        display: none;
      }

      .page {
        padding: 5mm 30mm 35mm;
      }

      table {
        box-shadow: none;
      }
    }

    @page {
      size: A4;
      margin: 0;
    }
  </style>

</head>

<body>

  <div class="print-actions">
    <button onclick="window.print()" class="btn">
      🖨️ Imprimir / Salvar PDF
    </button>
    <button onclick="window.close()" class="btn btn-secondary">
      ✕ Fechar
    </button>
  </div>

  <div class="preview-container">
    <div class="page">

      <!-- CABEÇALHO -->
      <div class="header">
        <?php if ($config['logotipo_prefeitura'] || $config['logotipo_feira']): ?>
          <div class="logos">

            <!-- Logo esquerda -->
            <div class="logo-left">
              <?php if ($config['logotipo_prefeitura']): ?>
                <img src="<?= h($config['logotipo_prefeitura']) ?>" alt="Logo Prefeitura">
              <?php endif; ?>
            </div>

            <!-- Espaço central (títulos ficam abaixo, não aqui) -->
            <div class="logo-center"></div>

            <!-- Logo direita -->
            <div class="logo-right">
              <?php if ($config['logotipo_feira']): ?>
                <img src="<?= h($config['logotipo_feira']) ?>" alt="Logo Feira">
              <?php endif; ?>
            </div>

          </div>
        <?php endif; ?>


        <h1>PREFEITURA MUNICIPAL DE <?= strtoupper(h($config['municipio'])) ?> – <?= strtoupper(h($config['estado'])) ?></h1>
        <?php if ($config['secretaria']): ?>
          <div class="secretaria"><?= strtoupper(h($config['secretaria'])) ?></div>
        <?php endif; ?>
        <h2>RELATÓRIO DA <?= strtoupper(h($config['titulo_feira'])) ?></h2>
        <?php if ($config['subtitulo_feira']): ?>
          <h3><?= h($config['subtitulo_feira']) ?></h3>
        <?php endif; ?>
        <div class="header-divider"></div>
      </div>

      <!-- INTRODUÇÃO -->
      <?php if ($config['incluir_introducao']): ?>
        <div class="section">
          <div class="section-title">Introdução</div>
          <div class="intro">
            <?= nl2br(h(substituirVariaveis($config['texto_introducao'], $vars))) ?>
            Este relatório apresenta os resultados financeiros referentes <?= count($dadosMensais) == 1 ? 'ao mês' : 'aos meses' ?> de <?= ucfirst($periodoTexto) ?>, com destaque para os principais produtos e valores arrecadados.
          </div>
        </div>
      <?php endif; ?>

      <!-- PRODUTOS COMERCIALIZADOS -->
      <?php if ($config['incluir_produtos_comercializados'] && !empty($produtos)): ?>
        <div class="section">
          <div class="section-title">Produtos Comercializados</div>
          <p class="produtos-texto">Os agricultores locais comercializaram uma grande variedade de itens durante o período, tais como:</p>

          <div class="produtos-lista">
            <?php foreach ($produtosAgrupados as $categoria => $prods): ?>
              <?php if (!empty($prods)): ?>
                <p>
                  <span class="categoria-titulo">- <?= h($categoria) ?>:</span>
                  <?= implode(', ', array_map('h', $prods)) ?>.
                </p>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- RESULTADOS FINANCEIROS -->
      <div class="section">
        <div class="section-title">Resultados Financeiros</div>

        <table>
          <thead>
            <tr>
              <th>Mês</th>
              <th class="text-right">Valor (R$)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dadosMensais as $dado): ?>
              <tr>
                <td><?= h($dado['mes_ano']) ?></td>
                <td class="text-right"><?= number_format((float)$dado['total'], 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <p class="total-extenso">
          <strong>Soma Total do Período: R$ <?= number_format($totalPeriodo, 2, ',', '.') ?></strong>
          <em>por extenso: <?= valorPorExtenso($totalPeriodo) ?>.</em>
        </p>
      </div>

      <!-- CONCLUSÃO -->
      <?php if ($config['incluir_conclusao']): ?>
        <div class="section">
          <div class="section-title">Conclusão</div>
          <div class="conclusao">
            <?= nl2br(h(substituirVariaveis($config['texto_conclusao'], $vars))) ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- RODAPÉ -->
      <div class="footer">
        <?= h($config['municipio']) ?>-<?= h($config['estado']) ?>, <?= strftime('%B de %Y', time()) ?>
      </div>

    </div>
  </div>

</body>

</html>