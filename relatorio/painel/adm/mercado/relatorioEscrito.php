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
$feiraId = 3;

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
} catch (Exception $e) {}

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

  if (empty($extenso)) $extenso = 'zero';
  $extenso .= ' reais';

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
      if ($u > 0) $extenso .= ' e ' . $unidade[$u];
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
    $texto .= $numero == 100 ? 'cem' : $centena[$c];
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
$params = [':ini' => $dataInicio, ':fim' => $dataFim, ':f' => $feiraId];

$inicio = new DateTime($dataInicio);
$fim = new DateTime($dataFim);
$interval = new DateInterval('P1M');
$period = new DatePeriod($inicio, $interval, $fim->modify('+1 day'));

$mesesNomes = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$mesesNomesCapitalizados = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

$periodoTextoCapitalizado = '';
if ($inicio->format('Y-m') == $fim->format('Y-m')) {
  $periodoTextoCapitalizado = $mesesNomesCapitalizados[(int)$inicio->format('n')] . ' de ' . $inicio->format('Y');
} else {
  $mesesPeriodoCapitalizados = [];
  foreach ($period as $dt) {
    $mesCapitalizado = $mesesNomesCapitalizados[(int)$dt->format('n')];
    if (!in_array($mesCapitalizado, $mesesPeriodoCapitalizados)) {
      $mesesPeriodoCapitalizados[] = $mesCapitalizado;
    }
  }
  if (count($mesesPeriodoCapitalizados) > 1) {
    $ultimoCapitalizado = array_pop($mesesPeriodoCapitalizados);
    $periodoTextoCapitalizado = $mesesPeriodoCapitalizados[0] . ' a ' . $ultimoCapitalizado . ' de ' . $inicio->format('Y');
  } else {
    $periodoTextoCapitalizado = ($mesesPeriodoCapitalizados[0] ?? '') . ' de ' . $inicio->format('Y');
  }
}

$st = $pdo->prepare("
  SELECT DATE_FORMAT({$dateExpr}, '%m/%Y') as mes_ano, SUM(v.total) as total
  FROM vendas v
  WHERE {$dateExpr} BETWEEN :ini AND :fim AND v.feira_id = :f
  GROUP BY DATE_FORMAT({$dateExpr}, '%Y-%m')
  ORDER BY DATE_FORMAT({$dateExpr}, '%Y-%m')
");
$st->execute($params);
$dadosMensais = $st->fetchAll();

foreach ($dadosMensais as &$dado) {
  list($mes, $ano) = explode('/', $dado['mes_ano']);
  $dado['mes_ano_formatado'] = $mesesNomesCapitalizados[(int)$mes] . '/' . $ano;
}

$totalPeriodo = array_sum(array_column($dadosMensais, 'total'));

$st = $pdo->prepare("
  SELECT COALESCE(c.nome, 'Diversos') AS categoria,
    GROUP_CONCAT(DISTINCT pr.nome ORDER BY pr.nome SEPARATOR ', ') AS produtos
  FROM venda_itens vi
  JOIN produtos pr ON pr.id = vi.produto_id
  LEFT JOIN categorias c ON c.id = pr.categoria_id AND c.feira_id = pr.feira_id
  JOIN vendas v ON v.id = vi.venda_id
  WHERE {$dateExpr} BETWEEN :ini AND :fim AND v.feira_id = :f
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
];

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Relatório - <?= h($config['titulo_feira']) ?></title>
  <style>
    :root {
      --cor-primaria: #1a365d;
      --cor-texto: #1a202c;
      --cor-texto-claro: #4a5568;
      --cor-borda: #cbd5e0;
      --cor-fundo: #f7fafc;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: "Times New Roman", Times, serif;
      font-size: 11pt;
      line-height: 1.4;
      color: var(--cor-texto);
      background: #e2e8f0;
      padding: 20px;
    }

    .acoes {
      position: fixed;
      top: 15px;
      right: 15px;
      display: flex;
      gap: 8px;
      z-index: 1000;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      font-family: Arial, sans-serif;
      font-size: 12px;
      font-weight: 600;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-imprimir {
      background: var(--cor-primaria);
      color: #fff;
      box-shadow: 0 2px 6px rgba(26, 54, 93, 0.3);
    }

    .btn-imprimir:hover { background: #2c5282; }

    .btn-fechar {
      background: #fff;
      color: var(--cor-texto);
      border: 1px solid var(--cor-borda);
    }

    .btn svg { width: 14px; height: 14px; }

    .documento-container {
      width: 210mm;
      min-height: 297mm;
      margin: 0 auto;
      background: #fff;
      box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    }

    .documento {
      padding: 12mm 18mm;
      height: 297mm;
      display: flex;
      flex-direction: column;
    }

    /* CABEÇALHO */
    .cabecalho {
      text-align: center;
      padding-bottom: 10px;
      margin-bottom: 12px;
      border-bottom: 2px double var(--cor-primaria);
    }

    .logos {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }

    .logos img {
      max-height: 55px;
      max-width: 130px;
      object-fit: contain;
    }

    .titulo-prefeitura {
      font-size: 12pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--cor-primaria);
      margin-bottom: 2px;
    }

    .titulo-secretaria {
      font-size: 9pt;
      text-transform: uppercase;
      color: var(--cor-texto-claro);
      margin-bottom: 8px;
    }

    .titulo-relatorio {
      font-size: 13pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--cor-primaria);
      margin-bottom: 2px;
      padding-top: 6px;
      border-top: 1px solid var(--cor-borda);
    }

    .subtitulo-relatorio {
      font-size: 10pt;
      font-style: italic;
      color: #234e52;
    }

    /* CONTEÚDO */
    .conteudo {
      flex: 1;
    }

    .secao {
      margin-bottom: 12px;
    }

    .secao-titulo {
      font-size: 10pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--cor-primaria);
      margin-bottom: 6px;
      padding-bottom: 3px;
      border-bottom: 1.5px solid var(--cor-primaria);
      display: inline-block;
    }

    .secao-texto {
      text-align: justify;
      text-indent: 1.5em;
      font-size: 10.5pt;
      line-height: 1.5;
    }

    /* PRODUTOS */
    .produtos-lista {
      font-size: 10pt;
      padding-left: 1.5em;
      line-height: 1.5;
    }

    .categoria-item {
      margin-bottom: 4px;
    }

    .categoria-nome {
      font-weight: bold;
      color: var(--cor-primaria);
    }

    /* TABELA */
    .tabela-container {
      margin: 10px 0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 10pt;
    }

    table th {
      background: var(--cor-primaria);
      color: #fff;
      padding: 8px 12px;
      font-weight: bold;
      text-transform: uppercase;
      font-size: 9pt;
      letter-spacing: 0.3px;
      text-align: left;
      border: 1px solid var(--cor-primaria);
    }

    table th:last-child { text-align: right; }

    table td {
      padding: 7px 12px;
      border: 1px solid var(--cor-borda);
    }

    table tr:nth-child(even) td { background: var(--cor-fundo); }

    table td:last-child {
      text-align: right;
      font-weight: 600;
      font-family: "Courier New", monospace;
      font-size: 10pt;
    }

    /* TOTAL */
    .total-box {
      margin-top: 10px;
      padding: 10px 14px;
      background: var(--cor-fundo);
      border: 1.5px solid var(--cor-primaria);
      border-radius: 3px;
    }

    .total-linha {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .total-rotulo {
      font-size: 10pt;
      font-weight: bold;
      text-transform: uppercase;
      color: var(--cor-primaria);
    }

    .total-valor {
      font-size: 14pt;
      font-weight: bold;
      color: var(--cor-primaria);
      font-family: "Courier New", monospace;
    }

    .total-extenso {
      font-size: 9pt;
      font-style: italic;
      color: var(--cor-texto-claro);
      text-align: right;
      margin-top: 4px;
      padding-top: 4px;
      border-top: 1px solid var(--cor-borda);
    }

    /* CONCLUSÃO */
    .conclusao-box {
      background: var(--cor-fundo);
      padding: 10px 14px;
      border-left: 3px solid #234e52;
      font-size: 10pt;
      line-height: 1.5;
    }

    .conclusao-box p {
      text-indent: 1.5em;
      text-align: justify;
      margin-bottom: 6px;
    }

    .conclusao-box p:last-child { margin-bottom: 0; }

    /* RODAPÉ */
    .rodape {
      margin-top: auto;
      padding-top: 12px;
      border-top: 1px solid var(--cor-borda);
    }

    .rodape-data {
      text-align: right;
      font-size: 10pt;
      margin-bottom: 25px;
    }

    .assinatura {
      text-align: center;
      width: 250px;
      margin: 0 auto;
    }

    .assinatura-linha {
      border-top: 1px solid var(--cor-texto);
      padding-top: 5px;
    }

    .assinatura-nome {
      font-size: 10pt;
      font-weight: bold;
    }

    .assinatura-cargo {
      font-size: 9pt;
      color: var(--cor-texto-claro);
    }

    /* IMPRESSÃO */
    @media print {
      body {
        background: white;
        padding: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .acoes { display: none !important; }

      .documento-container {
        box-shadow: none;
        width: 100%;
      }

      .documento {
        padding: 10mm 15mm;
        height: auto;
        min-height: 277mm;
      }

      table th {
        background: var(--cor-primaria) !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
    }

    @page {
      size: A4;
      margin: 0;
    }
  </style>
</head>
<body>

  <div class="acoes">
    <button onclick="window.print()" class="btn btn-imprimir">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
      </svg>
      Imprimir / PDF
    </button>
    <button onclick="window.close()" class="btn btn-fechar">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
      Fechar
    </button>
  </div>

  <div class="documento-container">
    <div class="documento">

      <!-- CABEÇALHO -->
      <header class="cabecalho">
        <?php if ($config['logotipo_prefeitura'] || $config['logotipo_feira']): ?>
          <div class="logos">
            <div><?php if ($config['logotipo_prefeitura']): ?><img src="<?= h($config['logotipo_prefeitura']) ?>" alt="Brasão"><?php endif; ?></div>
            <div><?php if ($config['logotipo_feira']): ?><img src="<?= h($config['logotipo_feira']) ?>" alt="Logo Feira"><?php endif; ?></div>
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

      <!-- CONTEÚDO -->
      <main class="conteudo">

        <!-- INTRODUÇÃO -->
        <?php if ($config['incluir_introducao']): ?>
          <section class="secao">
            <h2 class="secao-titulo">1. Introdução</h2>
            <p class="secao-texto"><?= h(substituirVariaveis($config['texto_introducao'], $vars)) ?> Este relatório apresenta os resultados financeiros referentes <?= count($dadosMensais) == 1 ? 'ao mês' : 'aos meses' ?> de <strong><?= h($periodoTextoCapitalizado) ?></strong>, com os principais produtos comercializados e valores arrecadados.</p>
          </section>
        <?php endif; ?>

        <!-- PRODUTOS -->
        <?php if ($config['incluir_produtos_comercializados'] && !empty($produtos)): ?>
          <section class="secao">
            <h2 class="secao-titulo">2. Produtos Comercializados</h2>
            <div class="produtos-lista">
              <?php foreach ($produtosAgrupados as $categoria => $prods): ?>
                <?php if (!empty($prods)): ?>
                  <div class="categoria-item">
                    <span class="categoria-nome">▸ <?= h($categoria) ?>:</span>
                    <?= implode(', ', array_map('h', $prods)) ?>.
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- RESULTADOS FINANCEIROS -->
        <section class="secao">
          <h2 class="secao-titulo">3. Resultados Financeiros</h2>
          <div class="tabela-container">
            <table>
              <thead>
                <tr>
                  <th>Período</th>
                  <th>Valor (R$)</th>
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
            <div class="total-linha">
              <span class="total-rotulo">Total do Período:</span>
              <span class="total-valor">R$ <?= number_format($totalPeriodo, 2, ',', '.') ?></span>
            </div>
            <div class="total-extenso">(<?= ucfirst(valorPorExtenso($totalPeriodo)) ?>)</div>
          </div>
        </section>

        <!-- CONCLUSÃO -->
        <?php if ($config['incluir_conclusao']): ?>
          <section class="secao">
            <h2 class="secao-titulo">4. Conclusão</h2>
            <div class="conclusao-box">
              <p><?= h(substituirVariaveis($config['texto_conclusao'], $vars)) ?></p>
              <?php if (!empty($config['texto_conclusao_extra'])): ?>
                <p><?= h(substituirVariaveis($config['texto_conclusao_extra'], $vars)) ?></p>
              <?php endif; ?>
            </div>
          </section>
        <?php endif; ?>

      </main>

      <!-- RODAPÉ -->
      <footer class="rodape">
        <div class="rodape-data"><?= h($config['municipio']) ?>-<?= h($config['estado']) ?>, <?= date('d') ?> de <?= $mesesNomes[(int)date('n')] ?> de <?= date('Y') ?>.</div>
        <div class="assinatura">
          <div class="assinatura-linha">
            <div class="assinatura-nome"><?= !empty($config['assinatura_nome']) ? h($config['assinatura_nome']) : 'Responsável pela Emissão' ?></div>
            <div class="assinatura-cargo"><?= !empty($config['assinatura_cargo']) ? h($config['assinatura_cargo']) : h($config['secretaria']) ?></div>
          </div>
        </div>
      </footer>

    </div>
  </div>

</body>
</html>