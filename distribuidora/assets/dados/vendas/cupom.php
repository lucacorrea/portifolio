<?php

declare(strict_types=1);

/**
 * cupom.php (modelo "Nota Fiscal" / DANFE-like)
 * ATENÇÃO: este é um COMPROVANTE/RECIBO com layout parecido com nota fiscal.
 * Não substitui NF-e/NFC-e oficial (SEFAZ).
 */

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
   CONFIG DO EMITENTE (edite aqui)
========================= */
$EMITENTE = [
  'nome'     => 'DISTRIBUIDORA EXEMPLO LTDA',
  'cnpj'     => '00.000.000/0001-00',
  'ie'       => 'ISENTO',
  'endereco' => 'Rua Exemplo, 123 - Centro - Coari/AM',
  'fone'     => '(92) 00000-0000',
];

/* =========================
   Helpers
========================= */
function money(float $v): string
{
  return 'R$ ' . number_format($v, 2, ',', '.');
}
function h(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function brDateTime(?string $dt): string
{
  $dt = trim((string)$dt);
  if ($dt === '') return '';
  $ts = strtotime($dt);
  if (!$ts) return $dt;
  return date('d/m/Y H:i:s', $ts);
}
function brDate(?string $dt): string
{
  $dt = trim((string)$dt);
  if ($dt === '') return '';
  $ts = strtotime($dt);
  if (!$ts) return $dt;
  return date('d/m/Y', $ts);
}

$auto = (int)($_GET['auto'] ?? 0) === 1;

/* =========================
   Pagamento (detalhes)
========================= */
$pagLabel = strtoupper((string)($venda['pagamento'] ?? ''));
$pagMode  = strtoupper((string)($venda['pagamento_mode'] ?? ''));
$pagJson  = (string)($venda['pagamento_json'] ?? '');
$pagData  = [];
if ($pagJson !== '') {
  $tmp = json_decode($pagJson, true);
  if (is_array($tmp)) $pagData = $tmp;
}

function payName(string $m): string
{
  $m = strtoupper(trim($m));
  return match ($m) {
    'DINHEIRO' => 'Dinheiro',
    'PIX'      => 'Pix',
    'CARTAO'   => 'Cartão',
    'BOLETO'   => 'Boleto',
    'MULTI'    => 'Múltiplos',
    default    => $m !== '' ? $m : '—',
  };
}

$troco = 0.0;
$paid  = 0.0;
$parts = [];

if ($pagMode === 'UNICO') {
  $troco = (float)($pagData['troco'] ?? 0);
  $paid  = (float)($pagData['paid'] ?? 0);
} else {
  $troco = (float)($pagData['troco'] ?? 0);
  $parts = is_array($pagData['parts'] ?? null) ? $pagData['parts'] : [];
}

/* =========================
   “Chave/Referência” (não é chave SEFAZ)
========================= */
$ref = 'VENDA-' . (int)$venda['id'] . '-' . preg_replace('/\D+/', '', (string)($venda['created_at'] ?? ''));
$ref = substr($ref, 0, 44); // tamanho “parecido”, só por layout

$canal = strtoupper((string)($venda['canal'] ?? 'PRESENCIAL'));
$cliente = trim((string)($venda['cliente'] ?? ''));
if ($cliente === '') $cliente = 'Consumidor Final';

$endereco = trim((string)($venda['endereco'] ?? ''));
$obs = trim((string)($venda['obs'] ?? ''));

$created = (string)($venda['created_at'] ?? '');
$dataEmissao = brDateTime($created);
$dataDia = brDate($created);

$subtotal = (float)($venda['subtotal'] ?? 0);
$desconto = (float)($venda['desconto_valor'] ?? 0);
$taxaEnt = (float)($venda['taxa_entrega'] ?? 0);
$total = (float)($venda['total'] ?? 0);

?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Documento - Venda #<?= (int)$venda['id'] ?></title>
  <style>
    /* ====== Base “nota fiscal” ====== */
    @page {
      size: A4;
      margin: 10mm;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: Arial, Helvetica, sans-serif;
      margin: 0;
      color: #111827;
      background: #fff;
    }

    .wrap {
      padding: 10mm;
    }

    .nf {
      border: 1px solid #111827;
      padding: 10px;
    }

    .row {
      display: flex;
      gap: 10px;
    }

    .col {
      flex: 1;
    }

    .box {
      border: 1px solid #111827;
      padding: 8px;
    }

    .box+.box {
      margin-top: 8px;
    }

    .title {
      font-weight: 900;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: .3px;
    }

    .small {
      font-size: 11px;
      line-height: 1.25;
    }

    .muted {
      color: #374151;
    }

    .k {
      font-weight: 700;
    }

    .hr {
      border-top: 1px solid #111827;
      margin: 8px 0;
    }

    .nf-head {
      align-items: stretch;
    }

    .emit .title {
      margin-bottom: 4px;
    }

    .doc {
      text-align: center;
    }

    .doc .big {
      font-size: 16px;
      font-weight: 900;
    }

    .doc .serie {
      font-size: 12px;
      font-weight: 800;
      margin-top: 2px;
    }

    .doc .num {
      font-size: 12px;
      margin-top: 4px;
    }

    .doc .warn {
      font-size: 10px;
      margin-top: 6px;
      color: #b91c1c;
      font-weight: 900;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      border: 1px solid #111827;
      padding: 6px 6px;
      font-size: 11px;
    }

    th {
      background: #f3f4f6;
      text-align: left;
      font-weight: 900;
    }

    td.r,
    th.r {
      text-align: right;
    }

    td.c,
    th.c {
      text-align: center;
    }

    .desc {
      max-width: 360px;
    }

    .totals {
      width: 100%;
    }

    .totals td {
      border: none;
      padding: 4px 0;
      font-size: 12px;
    }

    .totals .lbl {
      color: #111827;
      font-weight: 700;
    }

    .totals .val {
      text-align: right;
      font-weight: 900;
    }

    .grand {
      font-size: 14px;
    }

    .foot {
      margin-top: 8px;
    }

    .foot .small {
      font-size: 10.5px;
    }

    /* ====== Botões ====== */
    .btns {
      display: flex;
      gap: 8px;
      justify-content: center;
      margin-top: 10px;
    }

    button {
      padding: 10px 12px;
      border: 1px solid #111827;
      border-radius: 8px;
      background: #fff;
      cursor: pointer;
      font-weight: 800;
    }

    @media print {
      .wrap {
        padding: 0;
      }

      .btns {
        display: none;
      }
    }
  </style>
</head>

<body>
  <div class="wrap">
    <div class="nf">

      <!-- CABEÇALHO -->
      <div class="row nf-head">
        <div class="col box emit">
          <div class="title"><?= h($EMITENTE['nome']) ?></div>
          <div class="small">
            <span class="k">CNPJ:</span> <?= h($EMITENTE['cnpj']) ?> &nbsp;&nbsp; <span class="k">IE:</span> <?= h($EMITENTE['ie']) ?><br>
            <span class="k">Endereço:</span> <?= h($EMITENTE['endereco']) ?><br>
            <span class="k">Fone:</span> <?= h($EMITENTE['fone']) ?>
          </div>
        </div>

        <div class="col box doc">
          <div class="big">DOCUMENTO AUXILIAR</div>
          <div class="serie">COMPROVANTE DE VENDA (MODELO “NOTA FISCAL”)</div>
          <div class="num">
            <span class="k">Nº:</span> <?= (int)$venda['id'] ?>
            &nbsp;&nbsp; <span class="k">Série:</span> 001
            &nbsp;&nbsp; <span class="k">Emissão:</span> <?= h($dataDia) ?>
          </div>
          <div class="warn">NÃO É DOCUMENTO FISCAL (NÃO SUBSTITUI NF-e/NFC-e)</div>
        </div>
      </div>

      <!-- REFERÊNCIA / CHAVE (apenas interna) -->
      <div class="box">
        <div class="small">
          <span class="k">Referência Interna:</span> <?= h($ref) ?><br>
          <span class="k">Data/Hora:</span> <?= h($dataEmissao) ?> &nbsp;&nbsp;
          <span class="k">Canal:</span> <?= h($canal) ?>
        </div>
      </div>

      <!-- DESTINATÁRIO -->
      <div class="box">
        <div class="title">Destinatário / Consumidor</div>
        <div class="small">
          <span class="k">Nome/CPF:</span> <?= h($cliente) ?><br>
          <?php if ($canal === 'DELIVERY' && $endereco !== ''): ?>
            <span class="k">Endereço:</span> <?= h($endereco) ?><br>
          <?php endif; ?>
          <?php if ($canal === 'DELIVERY' && $obs !== ''): ?>
            <span class="k">Observação:</span> <?= h($obs) ?><br>
          <?php endif; ?>
        </div>
      </div>

      <!-- ITENS -->
      <div class="box">
        <div class="title">Discriminação dos Produtos</div>
        <table>
          <thead>
            <tr>
              <th class="c" style="width:40px;">Item</th>
              <th style="width:90px;">Código</th>
              <th class="desc">Descrição</th>
              <th class="c" style="width:55px;">Qtd</th>
              <th class="c" style="width:55px;">UN</th>
              <th class="r" style="width:90px;">V. Unit</th>
              <th class="r" style="width:95px;">V. Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$itens): ?>
              <tr>
                <td colspan="7" class="c">Sem itens.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($itens as $i => $it): ?>
                <tr>
                  <td class="c"><?= $i + 1 ?></td>
                  <td><?= h((string)$it['codigo']) ?></td>
                  <td class="desc"><?= h((string)$it['nome']) ?></td>
                  <td class="c"><?= (int)$it['qtd'] ?></td>
                  <td class="c"><?= h((string)($it['unidade'] ?? '')) ?></td>
                  <td class="r"><?= money((float)$it['preco_unit']) ?></td>
                  <td class="r"><?= money((float)$it['subtotal']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- TOTAIS / PAGAMENTO -->
      <div class="row">
        <div class="col box">
          <div class="title">Pagamento</div>
          <div class="small">
            <span class="k">Forma:</span> <?= h(payName($pagLabel)) ?><br>

            <?php if ($pagMode === 'UNICO'): ?>
              <?php if ($paid > 0): ?>
                <span class="k">Valor Pago:</span> <?= h(money($paid)) ?><br>
              <?php endif; ?>
              <?php if ($troco > 0): ?>
                <span class="k">Troco:</span> <?= h(money($troco)) ?><br>
              <?php endif; ?>
            <?php else: ?>
              <div class="hr"></div>
              <div class="small k">Detalhamento (Múltiplos)</div>
              <?php if (!$parts): ?>
                <div class="small muted">—</div>
              <?php else: ?>
                <?php foreach ($parts as $p): ?>
                  <?php
                  $m = payName((string)($p['method'] ?? ''));
                  $v = (float)($p['value'] ?? 0);
                  ?>
                  <div class="small"><?= h($m) ?>: <b><?= h(money($v)) ?></b></div>
                <?php endforeach; ?>
              <?php endif; ?>
              <?php if ($troco > 0): ?>
                <div class="hr"></div>
                <div class="small"><span class="k">Troco:</span> <?= h(money($troco)) ?></div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="col box">
          <div class="title">Totais</div>
          <table class="totals">
            <tr>
              <td class="lbl">Subtotal</td>
              <td class="val"><?= h(money($subtotal)) ?></td>
            </tr>
            <tr>
              <td class="lbl">Desconto</td>
              <td class="val">- <?= h(money($desconto)) ?></td>
            </tr>
            <tr>
              <td class="lbl">Taxa de Entrega</td>
              <td class="val"><?= h(money($taxaEnt)) ?></td>
            </tr>
            <tr>
              <td colspan="2">
                <div class="hr"></div>
              </td>
            </tr>
            <tr>
              <td class="lbl grand">TOTAL</td>
              <td class="val grand"><?= h(money($total)) ?></td>
            </tr>
          </table>
        </div>
      </div>

      <!-- INFORMAÇÕES ADICIONAIS -->
      <div class="box foot">
        <div class="title">Informações Adicionais</div>
        <div class="small muted">
          Documento gerado pelo sistema (PDV). Para emissão de NF-e/NFC-e oficial é necessário integração e autorização SEFAZ.
        </div>
      </div>

      <div class="btns">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Fechar</button>
      </div>

    </div>
  </div>

  <script>
    <?php if ($auto): ?>
      window.addEventListener('load', () => setTimeout(() => window.print(), 350));
    <?php endif; ?>
  </script>
</body>

</html>