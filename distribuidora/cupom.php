<?php
declare(strict_types=1);

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

$pdo = db();

$id = to_int($_GET['id'] ?? 0);
if ($id <= 0) {
  echo "ID inválido.";
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM vendas WHERE id = :id");
$stmt->execute([':id' => $id]);
$v = $stmt->fetch();

if (!$v) {
  echo "Venda não encontrada.";
  exit;
}

$stmtItens = $pdo->prepare("
  SELECT s.id, s.produto_id, s.qtd, s.preco, s.total,
         p.codigo, p.nome
  FROM saidas s
  JOIN produtos p ON p.id = s.produto_id
  WHERE s.pedido = :pedido
  ORDER BY s.id ASC
");
$stmtItens->execute([':pedido' => (string)$id]);
$itens = $stmtItens->fetchAll();

function fmtMoney($v): string { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function brDate(string $ymd): string {
  $ymd = trim($ymd);
  if (!$ymd) return '';
  $p = explode('-', $ymd);
  if (count($p) !== 3) return $ymd;
  return $p[2] . '/' . $p[1] . '/' . $p[0];
}

$pay = [];
if (!empty($v['pagamento_json'])) {
  $pay = json_decode((string)$v['pagamento_json'], true);
  if (!is_array($pay)) $pay = [];
}

$cliente = (string)($v['cliente'] ?? 'CONSUMIDOR FINAL');
$canal = (string)($v['canal'] ?? 'PRESENCIAL');
$endereco = (string)($v['endereco'] ?? '');
$obs = (string)($v['obs'] ?? '');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Cupom #<?= e((string)$id) ?></title>
  <style>
    @page { size: 80mm auto; margin: 6mm; }
    body { margin:0; padding:0; font-family: "Courier New", monospace; color:#000; }
    .wrap { width: 72mm; margin: 0 auto; font-size: 11px; }
    .center { text-align:center; }
    .bold { font-weight:800; }
    .small { font-size:10px; }
    .hr { border-top: 1px dashed #000; margin: 6px 0; }
    .row { display:flex; justify-content:space-between; gap:10px; }
    .row span:last-child { text-align:right; white-space:nowrap; }
    .item { margin: 6px 0; }
    .top { margin-top: 4px; }
    .mono { letter-spacing: .2px; }
    .printbtn { display:none; }
    @media screen {
      .printbtn { display:block; margin:10px auto; width:72mm; }
      button { width:100%; padding:10px; font-weight:800; }
    }
  </style>
</head>
<body>
  <div class="printbtn"><button onclick="window.print()">IMPRIMIR</button></div>

  <div class="wrap mono">
    <div class="center bold">PAINEL DA DISTRIBUIDORA</div>
    <div class="center small">CUPOM (MODELO)</div>
    <div class="hr"></div>

    <div class="row"><span class="bold">VENDA</span><span>#<?= e((string)$id) ?></span></div>
    <div class="row"><span class="bold">DATA</span><span><?= e(brDate((string)$v['data'])) ?></span></div>
    <div class="row"><span class="bold">CLIENTE</span><span><?= e($cliente) ?></span></div>
    <div class="row"><span class="bold">ENTREGA</span><span><?= e($canal) ?></span></div>

    <?php if ($canal === 'DELIVERY'): ?>
      <div class="top small">END: <?= e($endereco ?: '-') ?></div>
      <?php if ($obs !== ''): ?>
        <div class="top small">OBS: <?= e($obs) ?></div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="hr"></div>
    <div class="row bold"><span>ITENS</span><span></span></div>

    <?php if (!$itens): ?>
      <div class="small">—</div>
    <?php else: ?>
      <?php foreach ($itens as $idx => $it): ?>
        <div class="item">
          <div class="row">
            <span><?= e(str_pad((string)($idx+1), 3, '0', STR_PAD_LEFT) . ' ' . mb_strtoupper((string)$it['nome'])) ?></span>
          </div>
          <div class="row small">
            <span><?= e((string)$it['codigo']) ?></span>
            <span><?= e((string)$it['qtd']) ?> x <?= e(fmtMoney((float)$it['preco'])) ?> = <?= e(fmtMoney((float)$it['total'])) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="hr"></div>

    <div class="row"><span>SUBTOTAL</span><span><?= e(fmtMoney((float)$v['subtotal'])) ?></span></div>
    <div class="row"><span>DESCONTO</span><span>- <?= e(fmtMoney((float)$v['desconto_valor'])) ?></span></div>
    <div class="row"><span>TAXA ENTREGA</span><span><?= e(fmtMoney((float)$v['taxa_entrega'])) ?></span></div>

    <div class="hr"></div>
    <div class="row bold"><span>TOTAL</span><span><?= e(fmtMoney((float)$v['total'])) ?></span></div>

    <div class="hr"></div>
    <div class="row bold"><span>PAGAMENTO</span><span></span></div>

    <?php if (($pay['mode'] ?? '') === 'MULTI'): ?>
      <?php foreach (($pay['parts'] ?? []) as $p): ?>
        <div class="row">
          <span><?= e((string)($p['method'] ?? '')) ?></span>
          <span><?= e(fmtMoney((float)($p['value'] ?? 0))) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="row"><span>TROCO</span><span><?= e(fmtMoney((float)($pay['troco'] ?? 0))) ?></span></div>
    <?php else: ?>
      <div class="row">
        <span><?= e((string)($pay['method'] ?? $v['pagamento'])) ?></span>
        <span><?= e(fmtMoney((float)($pay['paid'] ?? $v['total']))) ?></span>
      </div>
      <div class="row"><span>TROCO</span><span><?= e(fmtMoney((float)($pay['troco'] ?? 0))) ?></span></div>
    <?php endif; ?>

    <div class="hr"></div>
    <div class="center small">OBRIGADO PELA PREFERÊNCIA!</div>
  </div>

  <script>
    // auto-print se abrir em nova aba
    // window.print();
  </script>
</body>
</html>