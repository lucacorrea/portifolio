<?php

declare(strict_types=1);

date_default_timezone_set('America/Manaus');

if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== CONEXÃO ===== */
$pdo = null;
$path = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($path && file_exists($path)) require_once $path;
if (!$pdo instanceof PDO) die('Erro de conexão');

/* ===== ID ===== */
$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die('ID inválido');

/* ===== BUSCA PRINCIPAL ===== */
$sql = "
SELECT
  l.id, l.placa, l.modelo, l.cor, l.valor AS valor_base, l.forma_pagamento,
  l.criado_em, l.categoria_nome,
  l.observacoes AS obs,
  e.nome_fantasia, e.telefone, e.endereco, e.cidade, e.estado,
  lv.nome AS lavador_nome
FROM lavagens_peca l
JOIN empresas_peca e
  ON REPLACE(REPLACE(REPLACE(e.cnpj,'.',''),'-',''),'/','')
   = REPLACE(REPLACE(REPLACE(l.empresa_cnpj,'.',''),'-',''),'/','')
LEFT JOIN lavadores_peca lv
  ON REPLACE(REPLACE(REPLACE(lv.cpf,'.',''),'-',''),'/','')
   = REPLACE(REPLACE(REPLACE(l.lavador_cpf,'.',''),'-',''),'/','')
WHERE l.id = :id
LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([':id' => $id]);
$d = $st->fetch(PDO::FETCH_ASSOC);
if (!$d) die('Comprovante não encontrado');

/* ===== BUSCA ADICIONAIS ===== */
$stAdd = $pdo->prepare("
    SELECT nome, valor
    FROM lavagem_adicionais_peca
    WHERE lavagem_id = :id
    ORDER BY id
");
$stAdd->execute([':id' => $id]);
$adicionais = $stAdd->fetchAll(PDO::FETCH_ASSOC);

/* ===== FORMATA DATA ===== */
$dt = new DateTime($d['criado_em'], new DateTimeZone('UTC'));
$dt->setTimezone(new DateTimeZone('America/Manaus'));
$dataHora = $dt->format('d/m/Y H:i');

/* ===== VALORES ===== */
$valorBase = (float)$d['valor_base'];
$valorAdicionais = 0.0;
foreach ($adicionais as $a) {
  $valorAdicionais += (float)$a['valor'];
}
$valorTotal = $valorBase + $valorAdicionais;

$valorBaseFmt = number_format($valorBase, 2, ',', '.');
$valorTotalFmt = number_format($valorTotal, 2, ',', '.');

/* ===== VEÍCULO SEPARADO ===== */
$placa  = trim((string)($d['placa'] ?? ''));
$modelo = trim((string)($d['modelo'] ?? ''));
$cor    = trim((string)($d['cor'] ?? ''));

$veiculo = trim(
  $modelo . ($cor !== '' ? ' ' . $cor : '')
);

/* ===== OBS ===== */
$obsRaw = trim((string)($d['obs'] ?? ''));
$obsRaw = trim(preg_replace('/\s+/', ' ', $obsRaw) ?? $obsRaw);

/* ===== ESCAPE ===== */
function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <title>Comprovante</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

  <style>
    @page {
      size: 58mm auto;
      margin: 0;
    }

    html,
    body {
      margin: 0;
      padding: 0;
      width: 78mm;
      font-family: Courier, monospace;
      font-size: 10.5px;
      color: #000;
      font-weight: bold;
    }

    .cupom {
      width: 78mm;
      padding: 3mm;
      box-sizing: border-box;
    }

    .center {
      text-align: center;
    }

    .small {
      font-size: 9px;
    }

    .line {
      margin: 3px 0;
    }

    .sep {
      margin: 4px 0;
      text-align: center;
      font-size: 9px;
    }

    .total {
      font-size: 13px;
      text-align: center;
    }

    .wrap {
      white-space: normal;
      word-break: break-word;
    }

    .placa {
      font-size: 15px;
      text-align: center;
      letter-spacing: 2px;
      margin: 5px 0;
    }
  </style>
</head>

<body>

  <div class="cupom">

    <div class="center">
      <?= strtoupper(e((string)$d['nome_fantasia'])) ?><br>
      <span class="small">
        <?= e((string)$d['cidade']) ?> - <?= e((string)$d['estado']) ?> /
        <i class="bi bi-whatsapp"></i> <?= e((string)($d['telefone'] ?: '')) ?>
      </span>
    </div>

    <div class="sep">--------------------------------------------------</div>

    <div class="line">
      COMPROVANTE: <?= (int)$d['id'] ?> / DATA: <?= e($dataHora) ?>
    </div>

    <div class="sep">--------------------------------------------------</div>

    <div class="line">
      SERVIÇO: <?= strtoupper(e((string)($d['categoria_nome'] ?: 'LAVAGEM'))) ?>
      <span style="float:right;">
        R$ <?= e($valorBaseFmt) ?>
      </span>
    </div>

    <!-- ADICIONAIS -->
    <div class="center small">ADICIONAIS</div>
    <?php if ($adicionais): ?>
      <?php foreach ($adicionais as $a): ?>
        <div class="line small">
          - <?= strtoupper(e((string)$a['nome'])) ?>
          <span style="float:right;">R$ <?= e(number_format((float)$a['valor'], 2, ',', '.')) ?></span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="line small">
        - Nenhum adicional
      </div>
    <?php endif; ?>

    <div class="sep">--------------------------------------------------</div>

    <!-- PLACA DESTACADA -->
    <div class="placa">
      <?= strtoupper(e($placa !== '' ? $placa : 'NAO INFORMADO')) ?>
    </div>

    <div class="line small center">
      <?= strtoupper(e($veiculo !== '' ? $veiculo : 'VEICULO NAO INFORMADO')) ?>
    </div>

    <div class="sep">--------------------------------------------------</div>

    <div class="line">
      LAVADOR: <?= strtoupper(e((string)($d['lavador_nome'] ?: '---'))) ?>
    </div>

    <div class="line">
      PAGAMENTO: <?= strtoupper(e((string)($d['forma_pagamento'] ?: '---'))) ?>
    </div>

    <?php if ($obsRaw !== ''): ?>
      <div class="sep">--------------------------------------------------</div>
      <div class="line wrap">
        OBS: <?= strtoupper(e($obsRaw)) ?>
      </div>
    <?php endif; ?>

    <div class="sep">--------------------------------------------------</div>

    <div class="total">
      TOTAL R$ <?= e($valorTotalFmt) ?>
    </div>

    <div class="sep">--------------------------------------------------</div>

    <div class="center small">
      OBRIGADO PELA PREFERENCIA
    </div>

  </div>

  <script>
    (function() {
      if (window.__printed) return;
      window.__printed = true;

      window.print();

      setTimeout(function() {
        window.location.href = 'lavagemRapida.php';
      }, 600);
    })();
  </script>

</body>

</html>
