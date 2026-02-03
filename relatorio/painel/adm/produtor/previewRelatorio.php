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
   CONFIGURAÇÕES
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
  'texto_introducao' =>
    'A Feira do Produtor Rural "{titulo_feira}" é um espaço de valorização da agricultura familiar e de comercialização de alimentos cultivados no município de {municipio}-{estado}.',
  'incluir_produtos_comercializados' => 1,
  'incluir_conclusao' => 1,
  'texto_conclusao' =>
    'O levantamento demonstra a relevância da {titulo_feira} para a economia agrícola do município, garantindo escoamento da produção, geração de renda e acesso da população a alimentos saudáveis.',
];

function substituirVariaveis(string $texto, array $vars): string {
  foreach ($vars as $k => $v) {
    $texto = str_replace('{' . $k . '}', $v, $texto);
  }
  return $texto;
}

/* ======================
   DADOS FINANCEIROS (EXEMPLO REAL)
====================== */
$dadosFinanceiros = [];

try {
  $st = $pdo->prepare("
    SELECT 
      DATE_FORMAT(v.criado_em, '%m/%Y') mes_ano,
      SUM(v.total) total
    FROM vendas v
    WHERE v.feira_id = :feira
      AND v.criado_em >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    GROUP BY mes_ano
    ORDER BY v.criado_em
  ");
  $st->execute([':feira' => $feiraId]);
  $dadosFinanceiros = $st->fetchAll();
} catch (Exception $e) {}

$totalGeral = array_sum(array_column($dadosFinanceiros, 'total'));

function valorPorExtenso(float $valor): string {
  return 'valor por extenso gerado automaticamente';
}

$vars = [
  'titulo_feira' => $config['titulo_feira'],
  'municipio' => $config['municipio'],
  'estado' => $config['estado'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Relatório</title>

<style>
body {
  background: #eee;
  font-family: "Times New Roman", serif;
}

.preview-container {
  display: flex;
  justify-content: center;
  padding: 30px 0;
}

.page {
  width: 210mm;
  min-height: 297mm;
  background: #fff;
  padding: 30mm 25mm;
  box-shadow: 0 0 18px rgba(0,0,0,.2);
}

/* CABEÇALHO */
.header {
  text-align: center;
  margin-bottom: 28px;
}

.logos {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
}

.logos img {
  max-height: 70px;
}

.header h1 {
  font-size: 12px;
  font-weight: bold;
  text-transform: uppercase;
}

.header .secretaria {
  font-size: 11px;
  font-weight: bold;
  margin-top: 2px;
}

.header h2 {
  font-size: 14px;
  margin-top: 10px;
  font-weight: bold;
  text-transform: uppercase;
}

.header h3 {
  font-size: 12px;
  font-style: italic;
}

.header-divider {
  margin-top: 15px;
  border-top: 1px solid #000;
}

/* SEÇÕES */
.section {
  margin-bottom: 20px;
}

.section-title {
  font-size: 12px;
  font-weight: bold;
  margin-bottom: 6px;
  text-transform: uppercase;
}

.intro,
.conclusao {
  font-size: 11px;
  text-align: justify;
  line-height: 1.5;
}

/* PRODUTOS */
.produtos-texto,
.produtos-lista {
  font-size: 11px;
  text-align: justify;
}

.produtos-lista p {
  margin: 4px 0;
}

.categoria-titulo {
  font-weight: bold;
}

/* TABELA */
table {
  width: 70%;
  margin: 12px auto;
  border-collapse: collapse;
  font-size: 11px;
}

table th {
  background: #f0f0f0;
  border: 1px solid #000;
  padding: 6px;
}

table td {
  border: 1px solid #000;
  padding: 6px;
}

.text-right {
  text-align: right;
}

.total-extenso {
  text-align: center;
  font-size: 11px;
  margin-top: 8px;
  font-style: italic;
}

/* RODAPÉ */
.footer {
  margin-top: 35px;
  font-size: 11px;
}
@media print {
  body { background: #fff; }
  .preview-container { padding: 0; }
  .page { box-shadow: none; }
}
</style>
</head>

<body>

<div class="preview-container">
<div class="page">

<div class="header">
  <h1>PREFEITURA MUNICIPAL DE <?= strtoupper(h($config['municipio'])) ?> – <?= strtoupper(h($config['estado'])) ?></h1>
  <div class="secretaria"><?= strtoupper(h($config['secretaria'])) ?></div>
  <h2>RELATÓRIO DA <?= strtoupper(h($config['titulo_feira'])) ?></h2>
  <h3><?= h($config['subtitulo_feira']) ?></h3>
  <div class="header-divider"></div>
</div>

<?php if ($config['incluir_introducao']): ?>
<div class="section">
  <div class="section-title">Introdução</div>
  <div class="intro">
    <?= nl2br(h(substituirVariaveis($config['texto_introducao'], $vars))) ?>
  </div>
</div>
<?php endif; ?>

<div class="section">
  <div class="section-title">Resultados Financeiros</div>

  <?php if ($dadosFinanceiros): ?>
  <table>
    <thead>
      <tr>
        <th>Mês</th>
        <th class="text-right">Valor (R$)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($dadosFinanceiros as $d): ?>
      <tr>
        <td><?= h($d['mes_ano']) ?></td>
        <td class="text-right"><?= number_format($d['total'], 2, ',', '.') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="total-extenso">
    Soma Total do Período: R$ <?= number_format($totalGeral, 2, ',', '.') ?>
  </div>
  <?php else: ?>
  <p style="text-align:center;font-size:11px;"><em>Dados não disponíveis</em></p>
  <?php endif; ?>
</div>

<?php if ($config['incluir_conclusao']): ?>
<div class="section">
  <div class="section-title">Conclusão</div>
  <div class="conclusao">
    <?= nl2br(h(substituirVariaveis($config['texto_conclusao'], $vars))) ?>
  </div>
</div>
<?php endif; ?>

<div class="footer">
  <?= h($config['municipio']) ?>-<?= h($config['estado']) ?>, <?= strftime('%B de %Y') ?>
</div>

</div>
</div>

</body>
</html>
