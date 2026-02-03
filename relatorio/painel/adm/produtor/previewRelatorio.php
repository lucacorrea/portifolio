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

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ======================
   CONEXÃO
====================== */
require '../../../assets/php/conexao.php';
$pdo = db();

/* ======================
   FEIRA FIXA
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
  'assinatura_nome' => '',
  'assinatura_cargo' => '',
  'mostrar_graficos' => 1,
  'mostrar_por_categoria' => 1,
  'mostrar_por_feirante' => 1,
  'produtos_detalhados' => 1,
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
   SUBSTITUIR VARIÁVEIS
====================== */
function substituirVariaveis(string $texto, array $vars): string {
  foreach ($vars as $key => $value) {
    $texto = str_replace('{' . $key . '}', $value, $texto);
  }
  return $texto;
}

// Período de exemplo (últimos 3 meses)
$mesAtual = date('n');
$anoAtual = date('Y');
$meses = [];
for ($i = 2; $i >= 0; $i--) {
  $mes = $mesAtual - $i;
  $ano = $anoAtual;
  if ($mes <= 0) {
    $mes += 12;
    $ano--;
  }
  $meses[] = sprintf('%02d/%04d', $mes, $ano);
}
$periodoExemplo = implode(', ', array_slice($meses, 0, -1)) . ' e ' . end($meses);

$vars = [
  'titulo_feira' => $config['subtitulo_feira'] ?: $config['titulo_feira'],
  'subtitulo_feira' => $config['subtitulo_feira'],
  'municipio' => $config['municipio'],
  'estado' => $config['estado'],
  'periodo' => $periodoExemplo,
  'total_periodo' => 'R$ 9.930.475,00',
];

/* ======================
   FUNÇÃO PARA VALOR POR EXTENSO
====================== */
function valorPorExtenso(float $valor): string {
  $valor = number_format($valor, 2, '.', '');
  list($inteiro, $centavos) = explode('.', $valor);
  
  $unidade = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
  $dez = ['dez', 'onze', 'doze', 'treze', 'catorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
  $dezena = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
  $centena = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
  
  $extenso = '';
  $inteiro = str_pad($inteiro, 12, '0', STR_PAD_LEFT);
  
  // Bilhões
  $bilhao = (int)substr($inteiro, 0, 3);
  if ($bilhao > 0) {
    $extenso .= converterGrupo($bilhao, $unidade, $dez, $dezena, $centena);
    $extenso .= $bilhao > 1 ? ' bilhões' : ' bilhão';
  }
  
  // Milhões
  $milhao = (int)substr($inteiro, 3, 3);
  if ($milhao > 0) {
    if ($extenso) $extenso .= $milhao > 0 && $bilhao > 0 ? ', ' : ' e ';
    $extenso .= converterGrupo($milhao, $unidade, $dez, $dezena, $centena);
    $extenso .= $milhao > 1 ? ' milhões' : ' milhão';
  }
  
  // Milhares
  $milhar = (int)substr($inteiro, 6, 3);
  if ($milhar > 0) {
    if ($extenso) $extenso .= ', ';
    if ($milhar == 1) {
      $extenso .= 'mil';
    } else {
      $extenso .= converterGrupo($milhar, $unidade, $dez, $dezena, $centena) . ' mil';
    }
  }
  
  // Centenas
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

function converterGrupo(int $numero, array $unidade, array $dez, array $dezena, array $centena): string {
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

/* ======================
   BUSCAR DADOS REAIS (EXEMPLO)
====================== */
$dadosFinanceiros = [];
$produtosComercializados = [];
$porCategoria = [];
$porFeirante = [];

try {
  // Dados financeiros dos últimos 3 meses
  $st = $pdo->prepare("
    SELECT 
      DATE_FORMAT(v.criado_em, '%m/%Y') as mes_ano,
      SUM(v.total) as total
    FROM vendas v
    WHERE v.feira_id = :feira
      AND v.criado_em >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    GROUP BY mes_ano
    ORDER BY v.criado_em
    LIMIT 5
  ");
  $st->execute([':feira' => $feiraId]);
  $dadosFinanceiros = $st->fetchAll();
  
  // Produtos comercializados (top 15)
  if ($config['incluir_produtos_comercializados']) {
    $st = $pdo->prepare("
      SELECT DISTINCT pr.nome
      FROM venda_itens vi
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN vendas v ON v.id = vi.venda_id
      WHERE v.feira_id = :feira
        AND v.criado_em >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
      ORDER BY pr.nome
      LIMIT 30
    ");
    $st->execute([':feira' => $feiraId]);
    $produtosComercializados = $st->fetchAll(PDO::FETCH_COLUMN);
  }
  
  // Por categoria (se configurado)
  if ($config['mostrar_por_categoria']) {
    $st = $pdo->prepare("
      SELECT 
        COALESCE(c.nome, 'Outros') as categoria,
        COUNT(DISTINCT pr.id) as produtos,
        SUM(vi.subtotal) as total
      FROM venda_itens vi
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN vendas v ON v.id = vi.venda_id
      LEFT JOIN categorias c ON c.id = pr.categoria_id
      WHERE v.feira_id = :feira
        AND v.criado_em >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
      GROUP BY pr.categoria_id
      ORDER BY total DESC
      LIMIT 5
    ");
    $st->execute([':feira' => $feiraId]);
    $porCategoria = $st->fetchAll();
  }
  
  // Por feirante (se configurado)
  if ($config['mostrar_por_feirante']) {
    $st = $pdo->prepare("
      SELECT 
        p.nome,
        SUM(vi.subtotal) as total
      FROM venda_itens vi
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN produtores p ON p.id = pr.produtor_id
      JOIN vendas v ON v.id = vi.venda_id
      WHERE v.feira_id = :feira
        AND v.criado_em >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
      GROUP BY p.id
      ORDER BY total DESC
      LIMIT 5
    ");
    $st->execute([':feira' => $feiraId]);
    $porFeirante = $st->fetchAll();
  }
  
} catch (Exception $e) {
  // Continuar com dados vazios
}

// Calcular total
$totalGeral = array_sum(array_column($dadosFinanceiros, 'total'));

// Organizar produtos por categoria (exemplo)
$produtosPorCategoria = [
  'Frutas' => ['abacaxi', 'laranja', 'tangerina', 'limão', 'mamão', 'maracujá', 'cupuaçu', 'abiu', 'abacate', 'banana', 'melancia', 'melão', 'goiaba'],
  'Derivados da mandioca' => ['macaxeira', 'farinha de mandioca', 'goma'],
  'Legumes e hortaliças' => ['jerimum', 'pimenta doce', 'pepino', 'alface', 'cebola de palha', 'tomate', 'couve', 'repolho'],
  'Grãos' => ['milho verde'],
];

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pré-visualização do Relatório</title>
  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: Arial, sans-serif;
      line-height: 1.5;
      color: #000;
      background: #e0e0e0;
      padding: 20px;
    }
    
    .preview-container {
      max-width: 210mm; /* A4 width */
      margin: 0 auto;
      background: white;
      box-shadow: 0 0 20px rgba(0,0,0,0.2);
      position: relative;
      min-height: 297mm; /* A4 height */
    }
    
    .watermark {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) rotate(-45deg);
      font-size: 100px;
      font-weight: 900;
      color: rgba(255, 0, 0, 0.05);
      pointer-events: none;
      z-index: 9999;
      white-space: nowrap;
      user-select: none;
    }
    
    .page {
      padding: 50px 60px;
      background: white;
      position: relative;
    }
    
    /* Cabeçalho */
    .header {
      text-align: center;
      margin-bottom: 25px;
    }
    
    .logos {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 30px;
      margin-bottom: 15px;
    }
    
    .logos img {
      max-height: 70px;
      max-width: 180px;
    }
    
    .header h1 {
      font-size: 12px;
      color: #000;
      margin: 3px 0;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .header .secretaria {
      font-size: 11px;
      color: #000;
      margin: 2px 0;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .header h2 {
      font-size: 13px;
      color: #000;
      margin: 8px 0 3px;
      font-weight: 700;
      text-transform: uppercase;
    }
    
    .header h3 {
      font-size: 12px;
      color: #000;
      font-weight: 400;
      font-style: italic;
      margin-bottom: 15px;
    }
    
    .header-divider {
      width: 100%;
      height: 1px;
      background: #000;
      margin: 15px 0 20px;
    }
    
    /* Seções */
    .section {
      margin-bottom: 18px;
    }
    
    .section-title {
      font-size: 12px;
      font-weight: 700;
      color: #000;
      margin-bottom: 8px;
    }
    
    .intro, .conclusao {
      text-align: justify;
      line-height: 1.4;
      font-size: 11px;
      margin-bottom: 12px;
    }
    
    /* Tabela - Estilo exato do PDF */
    table {
      width: 60%;
      margin: 15px auto;
      border-collapse: collapse;
      font-size: 11px;
    }
    
    table thead {
      background: #4472C4;
      color: white;
    }
    
    table th {
      padding: 6px 10px;
      text-align: left;
      font-weight: 700;
      border: 1px solid #2F5597;
    }
    
    table td {
      padding: 6px 10px;
      border: 1px solid #8EAADB;
      background: #DEEBF7;
    }
    
    .text-right {
      text-align: right;
    }
    
    .text-center {
      text-align: center;
    }
    
    .total-extenso {
      text-align: center;
      font-size: 11px;
      color: #4472C4;
      font-style: italic;
      margin-top: 10px;
    }
    
    /* Produtos - estilo lista simples */
    .produtos-texto {
      font-size: 11px;
      line-height: 1.4;
      text-align: justify;
      margin-bottom: 10px;
    }
    
    .produtos-lista {
      font-size: 11px;
      line-height: 1.6;
      margin-left: 15px;
    }
    
    .produtos-lista p {
      margin: 4px 0;
      text-align: justify;
    }
    
    .categoria-titulo {
      font-weight: 700;
      display: inline;
    }
    
    /* Assinatura */
    .assinatura {
      margin-top: 40px;
      text-align: left;
      font-size: 11px;
    }
    
    /* Rodapé */
    .footer {
      text-align: left;
      margin-top: 30px;
      font-size: 11px;
      color: #000;
    }
    
    /* Ações de impressão */
    .print-actions {
      position: fixed;
      top: 20px;
      right: 20px;
      background: white;
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 10000;
    }
    
    .btn {
      display: inline-block;
      padding: 10px 20px;
      background: #4472C4;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      margin: 0 5px;
    }
    
    .btn:hover {
      background: #2F5597;
    }
    
    .btn-secondary {
      background: #6c757d;
    }
    
    .btn-secondary:hover {
      background: #5a6268;
    }
    
    @media print {
      body {
        background: white;
        padding: 0;
      }
      
      .preview-container {
        box-shadow: none;
        max-width: 100%;
      }
      
      .print-actions {
        display: none;
      }
      
      .watermark {
        display: none;
      }
      
      .page {
        padding: 50px 60px;
      }
    }
    
    @page {
      size: A4;
      margin: 0;
    }
  </style>
</head>
<body>
  
  <div class="watermark">PRÉ-VISUALIZAÇÃO</div>
  
  <div class="print-actions">
    <button onclick="window.print()" class="btn">
      🖨️ Imprimir / PDF
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
            <?php if ($config['logotipo_prefeitura']): ?>
              <img src="<?= h($config['logotipo_prefeitura']) ?>" alt="Logo Prefeitura">
            <?php endif; ?>
            <?php if ($config['logotipo_feira']): ?>
              <img src="<?= h($config['logotipo_feira']) ?>" alt="Logo Feira">
            <?php endif; ?>
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
          </div>
        </div>
      <?php endif; ?>
      
      <!-- PRODUTOS COMERCIALIZADOS -->
      <?php if ($config['incluir_produtos_comercializados']): ?>
        <div class="section">
          <div class="section-title">Produtos Comercializados</div>
          <p class="produtos-texto">Os agricultores locais comercializaram uma grande variedade de itens durante o período, tais como:</p>
          
          <div class="produtos-lista">
            <p><span class="categoria-titulo">- Frutas:</span> abacaxi, laranja, tangerina, limão, mamão, maracujá, cupuaçu, abiu, abacate, banana, melancia, melão, goiaba.</p>
            <p><span class="categoria-titulo">- Produtos derivados da mandioca:</span> macaxeira, farinha de mandioca, goma.</p>
            <p><span class="categoria-titulo">- Legumes e hortaliças:</span> jerimum, pimenta doce, pepino, alface, cebola de palha, tomate, couve, repolho.</p>
            <p><span class="categoria-titulo">- Grãos e outros:</span> milho verde.</p>
            <p><span class="categoria-titulo">- Diversos:</span> produtos regionais variados.</p>
          </div>
        </div>
      <?php endif; ?>
      
      <!-- RESULTADOS FINANCEIROS -->
      <div class="section">
        <div class="section-title">Resultados Financeiros</div>
        
        <?php if (!empty($dadosFinanceiros)): ?>
          <table>
            <thead>
              <tr>
                <th>Mês</th>
                <th class="text-right">Valor (R$)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dadosFinanceiros as $dado): ?>
                <tr>
                  <td><?= h($dado['mes_ano']) ?></td>
                  <td class="text-right"><?= number_format((float)$dado['total'], 2, ',', '.') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          
          <p class="total-extenso">
            <strong>Soma Total do Período: R$ <?= number_format($totalGeral, 2, ',', '.') ?></strong> 
            <em>por extenso: <?= valorPorExtenso($totalGeral) ?>.</em>
          </p>
        <?php else: ?>
          <p style="text-align: center; color: #999; padding: 40px 0;">
            <em>Dados financeiros não disponíveis para o período</em>
          </p>
        <?php endif; ?>
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
  
  <script>
    // Converter primeira letra para maiúscula no mês
    document.addEventListener('DOMContentLoaded', function() {
      const footer = document.querySelector('.footer');
      if (footer) {
        footer.textContent = footer.textContent.replace(/^\w/, c => c.toUpperCase());
      }
    });
  </script>
  
</body>
</html>