<?php

declare(strict_types=1);

/**
 * cupom.php (layout NFC-e / cupom 58mm)
 * ATENÇÃO: é um COMPROVANTE com aparência de NFC-e.
 * Não é NFC-e oficial SEFAZ.
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
$EMIT = [
  'nome'     => 'DISTRIBUIDORA (NOME FANTASIA)',
  'razao'    => 'DISTRIBUIDORA EXEMPLO LTDA',
  'cnpj'     => '00.000.000/0001-00',
  'ie'       => 'ISENTO',
  'im'       => '',
  'endereco' => 'Rua Exemplo, 123 - Centro - Coari/AM',
  'fone'     => '(92) 00000-0000',
  'logo'     => '', // ex: 'assets/images/logo/logo.svg' (opcional)
];

/* =========================
   Helpers
========================= */
function h(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function money(float $v): string
{
  return number_format($v, 2, ',', '.');
}
function onlyDigits(string $s): string
{
  return preg_replace('/\D+/', '', $s) ?? '';
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
    'BOLETO'   => 'Boleto',
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

$created  = (string)($venda['created_at'] ?? '');
$dataHora = brDateTime($created);

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

/**
 * “Chave/QRCode” FAKE (visual)
 * - aqui é só estética tipo NFC-e
 * - se quiser, você pode gerar um link real pro seu sistema
 */
$fakeKey = str_pad((string)$id, 44, '0', STR_PAD_LEFT); // 44 dígitos só pra parecer
$fakeUrl = "https://{$_SERVER['HTTP_HOST']}/distribuidora/assets/dados/vendas/cupom.php?id={$id}";
$fakeQr  = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=" . urlencode($fakeUrl); // usa serviço público

?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>NFC-e (Cupom) #<?= (int)$venda['id'] ?></title>
  <style>
    /* ===== NFC-e 58mm ===== */
    @page {
      size: 58mm auto;
      margin: 2mm;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: Arial, Helvetica, sans-serif;
      margin: 0;
      color: #111;
      background: #fff;
    }

    .paper {
      width: 58mm;
      padding: 2mm;
      margin: 0 auto;
    }

    .center {
      text-align: center;
    }

    .right {
      text-align: right;
    }

    .bold {
      font-weight: 800;
    }

    .tiny {
      font-size: 10px;
    }

    .small {
      font-size: 11px;
    }

    .line {
      border-top: 1px dashed #000;
      margin: 6px 0;
    }

    .line2 {
      border-top: 1px solid #000;
      margin: 6px 0;
    }

    .logo {
      max-width: 46mm;
      max-height: 20mm;
      margin: 0 auto 4px auto;
      display: block;
    }

    .h1 {
      font-size: 12px;
      font-weight: 900;
      letter-spacing: .2px;
      text-transform: uppercase;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      font-size: 10px;
      padding: 2px 0;
      vertical-align: top;
    }

    th {
      font-weight: 900;
      border-bottom: 1px solid #000;
      padding-bottom: 3px;
    }

    td.r,
    th.r {
      text-align: right;
    }

    td.c,
    th.c {
      text-align: center;
    }

    .tot-row {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      font-size: 11px;
    }

    .tot-row .lbl {
      font-weight: 700;
    }

    .tot-row .val {
      font-weight: 900;
    }

    .grand {
      font-size: 13px;
    }

    .qr {
      display: block;
      width: 40mm;
      height: 40mm;
      margin: 6px auto 2px auto;
    }

    .key {
      word-break: break-all;
      font-size: 9px;
      line-height: 1.2;
    }

    .btns {
      display: flex;
      gap: 6px;
      justify-content: center;
      margin-top: 8px;
    }

    button {
      padding: 8px 10px;
      border: 1px solid #000;
      border-radius: 6px;
      background: #fff;
      cursor: pointer;
      font-weight: 800;
      font-size: 12px;
    }

    @media print {
      .btns {
        display: none;
      }

      .paper {
        margin: 0;
      }
    }
  </style>
</head>

<body>
  <div class="paper">

    <?php if ($EMIT['logo']): ?>
      <img class="logo" src="<?= h($EMIT['logo']) ?>" alt="Logo">
    <?php endif; ?>

    <div class="center">
      <div class="h1"><?= h($EMIT['nome']) ?></div>
      <div class="tiny"><?= h($EMIT['razao']) ?></div>
      <div class="tiny">CNPJ: <?= h($EMIT['cnpj']) ?> <?= $EMIT['ie'] ? ' • IE: ' . h($EMIT['ie']) : '' ?></div>
      <?php if (!empty($EMIT['im'])): ?><div class="tiny">IM: <?= h($EMIT['im']) ?></div><?php endif; ?>
      <div class="tiny"><?= h($EMIT['endereco']) ?></div>
      <div class="tiny"><?= h($EMIT['fone']) ?></div>
    </div>

    <div class="line"></div>

    <div class="center bold small">DOCUMENTO AUXILIAR DA NFC-e</div>
    <div class="center tiny">(NÃO É DOCUMENTO FISCAL OFICIAL SEFAZ)</div>

    <div class="line"></div>

    <div class="small">
      <div><span class="bold">NFC-e Nº:</span> <?= (int)$venda['id'] ?> <span class="bold">Série:</span> 001</div>
      <div><span class="bold">Emissão:</span> <?= h($dataHora) ?></div>
      <div><span class="bold">Canal:</span> <?= h($canal) ?></div>
    </div>

    <div class="line"></div>

    <div class="small">
      <div><span class="bold">Consumidor:</span> <?= h($cliente) ?></div>
      <?php if ($canal === 'DELIVERY' && $endereco !== ''): ?>
        <div><span class="bold">Endereço:</span> <?= h($endereco) ?></div>
      <?php endif; ?>
      <?php if ($canal === 'DELIVERY' && $obs !== ''): ?>
        <div><span class="bold">Obs:</span> <?= h($obs) ?></div>
      <?php endif; ?>
    </div>

    <div class="line"></div>

    <table>
      <thead>
        <tr>
          <th style="width:6mm;">#</th>
          <th>Descrição</th>
          <th class="r" style="width:13mm;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$itens): ?>
          <tr>
            <td colspan="3" class="c">Sem itens.</td>
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
              <td class="tiny"><?= $i + 1 ?></td>
              <td class="tiny">
                <div class="bold"><?= h($nome) ?></div>
                <div><?= h($cod) ?> • <?= $qtd ?> <?= h($un) ?> x <?= money($vu) ?></div>
              </td>
              <td class="r tiny bold"><?= money($vt) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="line2"></div>

    <div class="tot-row"><span class="lbl">Subtotal</span><span class="val"><?= money($subtotal) ?></span></div>
    <div class="tot-row"><span class="lbl">Desconto</span><span class="val">- <?= money($desconto) ?></span></div>
    <div class="tot-row"><span class="lbl">Taxa Entrega</span><span class="val"><?= money($taxaEnt) ?></span></div>
    <div class="line2"></div>
    <div class="tot-row grand"><span class="lbl">TOTAL</span><span class="val"><?= money($total) ?></span></div>

    <div class="line"></div>

    <div class="small">
      <div><span class="bold">Forma de Pagamento:</span> <?= h(payName($pagLabel)) ?></div>

      <?php if ($pagMode === 'UNICO'): ?>
        <?php if ($paid > 0): ?><div>Valor Pago: <span class="bold"><?= money($paid) ?></span></div><?php endif; ?>
        <?php if ($troco > 0): ?><div>Troco: <span class="bold"><?= money($troco) ?></span></div><?php endif; ?>
      <?php else: ?>
        <?php if ($parts): ?>
          <div class="line"></div>
          <div class="tiny bold">Detalhamento (Múltiplos):</div>
          <?php foreach ($parts as $p): ?>
            <?php
            $m = payName((string)($p['method'] ?? ''));
            $v = (float)($p['value'] ?? 0);
            ?>
            <div class="tiny"><?= h($m) ?>: <span class="bold"><?= money($v) ?></span></div>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($troco > 0): ?><div class="tiny">Troco: <span class="bold"><?= money($troco) ?></span></div><?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="line"></div>

    <div class="center tiny bold">CONSULTE PELA CHAVE DE ACESSO</div>
    <div class="key center"><?= h($fakeKey) ?></div>

    <img class="qr" src="<?= h($fakeQr) ?>" alt="QR Code">

    <div class="center tiny">
      Consulta (sistema):<br>
      <?= h($fakeUrl) ?>
    </div>

    <div class="line"></div>

    <div class="center tiny">
      Obrigado pela preferência!<br>
      Volte sempre.
    </div>

    <div class="btns">
      <button onclick="window.print()">Imprimir</button>
      <button onclick="window.close()">Fechar</button>
    </div>

  </div>

  <script>
    <?php if ($auto): ?>
      window.addEventListener('load', () => setTimeout(() => window.print(), 250));
    <?php endif; ?>
  </script>
</body>

</html>