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
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: #333;
      background: #f5f5f5;
      padding: 20px;
    }
    
    .preview-container {
      max-width: 1000px;
      margin: 0 auto;
      background: white;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
      position: relative;
    }
    
    .watermark {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) rotate(-45deg);
      font-size: 80px;
      font-weight: 900;
      color: rgba(255, 0, 0, 0.08);
      pointer-events: none;
      z-index: 9999;
      white-space: nowrap;
      user-select: none;
    }
    
    .page {
      padding: 60px 80px;
      background: white;
      position: relative;
    }
    
    /* Cabeçalho */
    .header {
      text-align: center;
      border-bottom: 3px solid #231475;
      padding-bottom: 30px;
      margin-bottom: 40px;
    }
    
    .logos {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 40px;
      margin-bottom: 20px;
    }
    
    .logos img {
      max-height: 80px;
      max-width: 200px;
    }
    
    .header h1 {
      font-size: 20px;
      color: #231475;
      margin: 10px 0 5px;
      font-weight: 700;
      text-transform: uppercase;
    }
    
    .header h2 {
      font-size: 24px;
      color: #000;
      margin: 5px 0;
      font-weight: 800;
    }
    
    .header h3 {
      font-size: 16px;
      color: #666;
      font-weight: 400;
      font-style: italic;
    }
    
    .header .secretaria {
      font-size: 13px;
      color: #666;
      margin-top: 10px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    /* Seções */
    .section {
      margin-bottom: 40px;
    }
    
    .section-title {
      font-size: 18px;
      font-weight: 700;
      color: #231475;
      margin-bottom: 15px;
      padding-bottom: 8px;
      border-bottom: 2px solid #231475;
    }
    
    .intro, .conclusao {
      text-align: justify;
      line-height: 1.8;
      font-size: 14px;
    }
    
    /* Tabela */
    table {
      width: 100%;
      border-collapse: collapse;
      margin: 20px 0;
      font-size: 14px;
    }
    
    table thead {
      background: #231475;
      color: white;
    }
    
    table th {
      padding: 12px;
      text-align: left;
      font-weight: 600;
    }
    
    table td {
      padding: 10px 12px;
      border-bottom: 1px solid #e0e0e0;
    }
    
    table tbody tr:hover {
      background: #f8f9fa;
    }
    
    .text-right {
      text-align: right;
    }
    
    .text-center {
      text-align: center;
    }
    
    .total-row {
      background: #f0f0f0;
      font-weight: 700;
    }
    
    /* Produtos */
    .produtos-lista {
      column-count: 2;
      column-gap: 40px;
      margin: 20px 0;
    }
    
    .categoria-grupo {
      break-inside: avoid;
      margin-bottom: 25px;
    }
    
    .categoria-titulo {
      font-weight: 700;
      color: #231475;
      margin-bottom: 8px;
      font-size: 15px;
    }
    
    .produtos-lista ul {
      list-style: none;
      padding-left: 0;
    }
    
    .produtos-lista li {
      padding: 4px 0 4px 20px;
      position: relative;
      font-size: 13px;
    }
    
    .produtos-lista li:before {
      content: "•";
      position: absolute;
      left: 0;
      color: #231475;
      font-weight: 700;
    }
    
    /* Gráfico simulado */
    .chart {
      margin: 30px 0;
    }
    
    .chart-bar {
      display: flex;
      align-items: center;
      margin: 10px 0;
    }
    
    .chart-label {
      width: 120px;
      font-size: 13px;
      font-weight: 600;
    }
    
    .chart-bar-fill {
      height: 30px;
      background: linear-gradient(90deg, #231475, #4a3ba5);
      border-radius: 4px;
      display: flex;
      align-items: center;
      padding: 0 10px;
      color: white;
      font-size: 12px;
      font-weight: 700;
    }
    
    /* Assinatura */
    .assinatura {
      margin-top: 60px;
      text-align: center;
    }
    
    .assinatura-linha {
      width: 300px;
      margin: 0 auto 10px;
      border-top: 2px solid #000;
    }
    
    .assinatura-nome {
      font-weight: 700;
      font-size: 14px;
    }
    
    .assinatura-cargo {
      font-size: 13px;
      color: #666;
    }
    
    /* Rodapé */
    .footer {
      text-align: center;
      margin-top: 40px;
      padding-top: 20px;
      border-top: 2px solid #e0e0e0;
      font-size: 12px;
      color: #666;
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
      background: #231475;
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
      background: #1a0f5a;
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
      }
      
      .print-actions {
        display: none;
      }
      
      .watermark {
        display: none;
      }
      
      .page {
        page-break-after: always;
      }
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
        
        <h1>Prefeitura Municipal de <?= h($config['municipio']) ?> – <?= h($config['estado']) ?></h1>
        <?php if ($config['secretaria']): ?>
          <div class="secretaria"><?= h($config['secretaria']) ?></div>
        <?php endif; ?>
        <h2>Relatório da <?= h($config['titulo_feira']) ?></h2>
        <?php if ($config['subtitulo_feira']): ?>
          <h3><?= h($config['subtitulo_feira']) ?></h3>
        <?php endif; ?>
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
      <?php if ($config['incluir_produtos_comercializados'] && !empty($produtosComercializados)): ?>
        <div class="section">
          <div class="section-title">Produtos Comercializados</div>
          <p style="margin-bottom: 20px;">Os agricultores locais comercializaram uma grande variedade de itens durante o período:</p>
          
          <?php if ($config['produtos_detalhados']): ?>
            <div class="produtos-lista">
              <?php foreach ($produtosPorCategoria as $categoria => $produtos): ?>
                <div class="categoria-grupo">
                  <div class="categoria-titulo"><?= h($categoria) ?>:</div>
                  <ul>
                    <?php foreach ($produtos as $produto): ?>
                      <li><?= h($produto) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <ul style="column-count: 3; column-gap: 30px;">
              <?php foreach ($produtosComercializados as $produto): ?>
                <li><?= h($produto) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
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
              <tr class="total-row">
                <td><strong>Soma Total do Período:</strong></td>
                <td class="text-right"><strong>R$ <?= number_format($totalGeral, 2, ',', '.') ?></strong></td>
              </tr>
            </tbody>
          </table>
          
          <p style="text-align: center; font-size: 13px; margin-top: 10px;">
            <strong>por extenso:</strong> 
            <em><?= number_format($totalGeral, 2, ',', '.') ?> reais</em>
          </p>
        <?php else: ?>
          <p style="text-align: center; color: #999; padding: 40px 0;">
            <em>Dados financeiros não disponíveis para o período</em>
          </p>
        <?php endif; ?>
      </div>
      
      <!-- GRÁFICOS / VISUALIZAÇÕES -->
      <?php if ($config['mostrar_graficos']): ?>
        
        <!-- Por Categoria -->
        <?php if ($config['mostrar_por_categoria'] && !empty($porCategoria)): ?>
          <div class="section">
            <div class="section-title">Vendas por Categoria</div>
            <div class="chart">
              <?php 
              $maxValor = max(array_column($porCategoria, 'total'));
              foreach ($porCategoria as $cat): 
                $percentual = $maxValor > 0 ? ($cat['total'] / $maxValor) * 100 : 0;
              ?>
                <div class="chart-bar">
                  <div class="chart-label"><?= h($cat['categoria']) ?></div>
                  <div class="chart-bar-fill" style="width: <?= $percentual ?>%;">
                    R$ <?= number_format((float)$cat['total'], 2, ',', '.') ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        
        <!-- Por Feirante -->
        <?php if ($config['mostrar_por_feirante'] && !empty($porFeirante)): ?>
          <div class="section">
            <div class="section-title">Top 5 Feirantes</div>
            <table>
              <thead>
                <tr>
                  <th>Feirante</th>
                  <th class="text-right">Total (R$)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($porFeirante as $feirante): ?>
                  <tr>
                    <td><?= h($feirante['nome']) ?></td>
                    <td class="text-right"><?= number_format((float)$feirante['total'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        
      <?php endif; ?>
      
      <!-- CONCLUSÃO -->
      <?php if ($config['incluir_conclusao']): ?>
        <div class="section">
          <div class="section-title">Conclusão</div>
          <div class="conclusao">
            <?= nl2br(h(substituirVariaveis($config['texto_conclusao'], $vars))) ?>
          </div>
        </div>
      <?php endif; ?>
      
      <!-- ASSINATURA -->
      <?php if ($config['assinatura_nome']): ?>
        <div class="assinatura">
          <div class="assinatura-linha"></div>
          <div class="assinatura-nome"><?= h($config['assinatura_nome']) ?></div>
          <?php if ($config['assinatura_cargo']): ?>
            <div class="assinatura-cargo"><?= h($config['assinatura_cargo']) ?></div>
          <?php endif; ?>
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