<?php

declare(strict_types=1);

require_once __DIR__ . '/../../conexao.php';

$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo "ID inválido.";
  exit;
}

$st = $pdo->prepare("SELECT * FROM vendas WHERE id = ?");
$st->execute([$id]);
$venda = $st->fetch(PDO::FETCH_ASSOC);

if (!$venda) {
  http_response_code(404);
  echo "Venda não encontrada.";
  exit;
}

$st2 = $pdo->prepare("SELECT * FROM venda_itens WHERE venda_id = ? ORDER BY id ASC");
$st2->execute([$id]);
$itens = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* =========================
   CONFIG DO EMITENTE
========================= */
$EMIT = [
  'nome'     => 'DISTRIBUIDORA PLBH',
  'cnpj'     => '00.000.000/0001-00',
  'ie'       => 'ISENTO',
  'endereco' => 'Rua Exemplo, 123 - Centro - Coari/AM',
  'fone'     => '(92) 00000-0000',
  'msg'      => 'Obrigado e volte sempre!',
];

function h(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float $v): string
{
  return number_format($v, 2, ',', '.');
}

function brDateTime(?string $dt): string
{
  $dt = trim((string)$dt);
  if ($dt === '') return '';
  $ts = strtotime($dt);
  if (!$ts) return $dt;
  return date('d/m/Y H:i:s', $ts);
}

function payName(string $m): string
{
  $m = strtoupper(trim($m));
  return match ($m) {
    'DINHEIRO' => 'Dinheiro',
    'PIX'      => 'Pix',
    'CARTAO'   => 'Cartão',
    'FIADO'    => 'À Prazo',
    'MULTI'    => 'Múltiplos',
    default    => $m !== '' ? $m : '—',
  };
}

$auto = (int)($_GET['auto'] ?? 0) === 1;

$canal   = strtoupper((string)($venda['canal'] ?? 'PRESENCIAL'));
$cliente = trim((string)($venda['cliente'] ?? ''));
if ($cliente === '') $cliente = 'CONSUMIDOR FINAL';

$endereco = trim((string)($venda['endereco'] ?? ''));
$obs      = trim((string)($venda['obs'] ?? ''));

$dataHora = brDateTime((string)($venda['created_at'] ?? ''));

$subtotal = (float)($venda['subtotal'] ?? 0);
$desconto = (float)($venda['desconto_valor'] ?? 0);
$taxaEnt  = (float)($venda['taxa_entrega'] ?? 0);
$total    = (float)($venda['total'] ?? 0);

$pagLabel = strtoupper((string)($venda['pagamento'] ?? ''));
$pagMode  = strtoupper((string)($venda['pagamento_mode'] ?? ''));
$pagJson  = (string)($venda['pagamento_json'] ?? '');

$pagData = [];
if ($pagJson !== '') {
  $tmp = json_decode($pagJson, true);
  if (is_array($tmp)) $pagData = $tmp;
}

$troco = (float)($pagData['troco'] ?? 0);
$paid  = (float)($pagData['paid'] ?? 0);
$parts = is_array($pagData['parts'] ?? null) ? $pagData['parts'] : [];

?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Cupom #<?= (int)$venda['id'] ?></title>

  <style>
    :root {
      --page-w: 58mm;
      --content-w: 48mm;

      --pad-top: 2.8mm;
      --pad-bottom: 3.5mm;

      --fs-8: 8px;
      --fs-9: 9px;
      --fs-10: 10px;
      --fs-11: 11px;
      --fs-12: 12px;
    }

    @page {
      size: 58mm auto;
      margin: 0;
    }

    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    html,
    body {
      margin: 0;
      padding: 0;
      width: var(--page-w);
      min-width: var(--page-w);
      max-width: var(--page-w);
      background: #fff;
      color: #000;
      font-family: Arial, Helvetica, sans-serif;
      overflow-x: hidden;
    }

    body {
      display: block;
    }

    .paper {
      width: var(--content-w);
      max-width: var(--content-w);
      margin: 0 auto;
      padding-top: var(--pad-top);
      padding-bottom: var(--pad-bottom);
    }

    .c {
      text-align: center;
    }

    .r {
      text-align: right;
    }

    .l {
      text-align: left;
    }

    .b {
      font-weight: 700;
    }

    .t8 {
      font-size: var(--fs-8);
    }

    .t9 {
      font-size: var(--fs-9);
    }

    .t10 {
      font-size: var(--fs-10);
    }

    .t11 {
      font-size: var(--fs-11);
    }

    .t12 {
      font-size: var(--fs-12);
    }

    .line {
      border-top: 1px dashed #000;
      margin: 5px 0;
      height: 0;
      width: 100%;
    }

    .line2 {
      border-top: 1px solid #000;
      margin: 5px 0;
      height: 0;
      width: 100%;
    }

    .wrap {
      word-break: break-word;
      overflow-wrap: break-word;
      white-space: normal;
    }

    .head-emit .nome {
      font-size: 10.8px;
      font-weight: 700;
      line-height: 1.2;
      text-transform: uppercase;
    }

    .head-emit .sub {
      font-size: 8.6px;
      line-height: 1.2;
      margin-top: 1px;
    }

    .sec-title {
      text-align: center;
      font-size: 9.8px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .2px;
    }

    .meta div,
    .client div,
    .pay div {
      font-size: 8.8px;
      line-height: 1.25;
      margin-bottom: 2px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    th,
    td {
      padding: 2px 0;
      vertical-align: top;
      font-size: 8.8px;
      line-height: 1.2;
    }

    thead th {
      border-bottom: 1px solid #000;
      padding-bottom: 3px;
      font-weight: 700;
    }

    .col-n {
      width: 4mm;
    }

    .col-total {
      width: 14mm;
    }

    .itemname {
      font-size: 8.8px;
      font-weight: 700;
      line-height: 1.15;
      word-break: break-word;
      overflow-wrap: break-word;
      padding-right: 2px;
    }

    .subline {
      font-size: 8.2px;
      line-height: 1.15;
      color: #111;
      word-break: break-word;
      overflow-wrap: break-word;
      padding-right: 2px;
    }

    .totrow {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 6px;
      font-size: 9.2px;
      line-height: 1.25;
      margin: 2px 0;
      width: 100%;
    }

    .totrow .lbl {
      font-weight: 700;
      flex: 1 1 auto;
      min-width: 0;
    }

    .totrow .val {
      font-weight: 700;
      white-space: nowrap;
      text-align: right;
      flex: 0 0 auto;
    }

    .grand {
      font-size: 10.6px;
      font-weight: 700;
    }

    .footer-msg {
      text-align: center;
      font-size: 8.8px;
      line-height: 1.25;
      margin-top: 4px;
    }

    .btns {
      display: flex;
      gap: 6px;
      justify-content: center;
      margin-top: 10px;
    }

    button {
      padding: 8px 10px;
      border: 1px solid #000;
      border-radius: 6px;
      background: #fff;
      cursor: pointer;
      font-weight: 700;
      font-size: 12px;
    }

    @media print {

      html,
      body {
        width: 58mm !important;
        min-width: 58mm !important;
        max-width: 58mm !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      .paper {
        width: 48mm !important;
        max-width: 48mm !important;
        margin: 0 auto !important;
        padding-top: 2.8mm !important;
        padding-bottom: 3.5mm !important;
      }

      .btns {
        display: none !important;
      }
    }
  </style>
</head>

<body>
  <div class="paper">

    <div class="head-emit c">
      <div class="nome"><?= h($EMIT['nome']) ?></div>
      <div class="sub">CNPJ: <?= h($EMIT['cnpj']) ?></div>
      <div class="sub wrap"><?= h($EMIT['endereco']) ?></div>
      <div class="sub"><?= h($EMIT['fone']) ?></div>
    </div>

    <div class="line"></div>

    <div class="sec-title">Cupom Fiscal</div>

    <div class="meta t9">
      <div><span class="b">Venda:</span> #<?= (int)$venda['id'] ?></div>
      <div><span class="b">Data/Hora:</span> <?= h($dataHora) ?></div>
      <div><span class="b">Canal:</span> <?= h($canal) ?></div>
    </div>

    <div class="line"></div>

    <div class="client t9">
      <div class="wrap"><span class="b">Cliente:</span> <?= h($cliente) ?></div>

      <?php if ($canal === 'DELIVERY' && $endereco !== ''): ?>
        <div class="wrap"><span class="b">Endereço:</span> <?= h($endereco) ?></div>
      <?php endif; ?>

      <?php if ($canal === 'DELIVERY' && $obs !== ''): ?>
        <div class="wrap"><span class="b">Obs:</span> <?= h($obs) ?></div>
      <?php endif; ?>
    </div>

    <div class="line"></div>

    <table>
      <thead>
        <tr>
          <th class="col-n l">#</th>
          <th class="l">Item</th>
          <th class="col-total r">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$itens): ?>
          <tr>
            <td colspan="3" class="c t9">Sem itens.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($itens as $i => $it): ?>
            <?php
            $nome = (string)($it['nome'] ?? '');
            $cod  = (string)($it['codigo'] ?? '');
            $qtd  = (int)($it['qtd'] ?? 0);
            $un   = (string)($it['unidade'] ?? '');
            $vu   = (float)($it['preco_unit'] ?? 0);
            $vt   = (float)($it['subtotal'] ?? 0);
            ?>
            <tr>
              <td class="t9"><?= $i + 1 ?></td>
              <td>
                <div class="itemname"><?= h($nome) ?></div>
                <div class="subline"><?= h($cod) ?> • <?= $qtd ?> <?= h($un) ?> x <?= money($vu) ?></div>
              </td>
              <td class="r b t9"><?= money($vt) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="line2"></div>

    <div class="totrow">
      <span class="lbl">Subtotal</span>
      <span class="val"><?= money($subtotal) ?></span>
    </div>

    <div class="totrow">
      <span class="lbl">Desconto</span>
      <span class="val">- <?= money($desconto) ?></span>
    </div>

    <div class="totrow">
      <span class="lbl">Taxa Entrega</span>
      <span class="val"><?= money($taxaEnt) ?></span>
    </div>

    <div class="line2"></div>

    <div class="totrow grand">
      <span class="lbl">TOTAL</span>
      <span class="val"><?= money($total) ?></span>
    </div>

    <div class="line"></div>

    <div class="pay t9">
      <div><span class="b">Pagamento:</span> <?= h(payName($pagLabel)) ?></div>

      <?php if ($pagMode === 'UNICO'): ?>
        <?php if ($paid > 0): ?>
          <div>Valor Pago: <span class="b"><?= money($paid) ?></span></div>
        <?php endif; ?>
        <?php if ($troco > 0): ?>
          <div>Troco: <span class="b"><?= money($troco) ?></span></div>
        <?php endif; ?>
      <?php else: ?>
        <?php if ($parts): ?>
          <div class="line"></div>
          <div class="b">Múltiplos:</div>
          <?php foreach ($parts as $p): ?>
            <?php
            $m = payName((string)($p['method'] ?? ''));
            $v = (float)($p['value'] ?? 0);
            ?>
            <div><?= h($m) ?>: <span class="b"><?= money($v) ?></span></div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($troco > 0): ?>
          <div>Troco: <span class="b"><?= money($troco) ?></span></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="line"></div>

    <div class="footer-msg"><?= h($EMIT['msg']) ?></div>

    <div class="btns">
      <button onclick="window.print()">Imprimir</button>
      <button onclick="window.close()">Fechar</button>
    </div>
  </div>

  <script>
    <?php if ($auto): ?>
      window.addEventListener('load', () => {
        setTimeout(() => window.print(), 250);
      });
    <?php endif; ?>
  </script>
</body>

</html>