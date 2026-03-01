<?php
declare(strict_types=1);

/**
 * cupom.php (Cupom Fiscal BÁSICO - 58mm)
 * - Largura fixa 58mm
 * - Layout simples (sem “SEFAZ”, sem “DANFE”, sem chave/QR)
 */

require_once __DIR__ . '/../../conexao.php';

$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo "ID inválido."; exit; }

$st = $pdo->prepare("SELECT * FROM vendas WHERE id = ?");
$st->execute([$id]);
$venda = $st->fetch(PDO::FETCH_ASSOC);
if (!$venda) { http_response_code(404); echo "Venda não encontrada."; exit; }

$st2 = $pdo->prepare("SELECT * FROM venda_itens WHERE venda_id = ? ORDER BY id ASC");
$st2->execute([$id]);
$itens = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* =========================
   CONFIG DO EMITENTE (edite aqui)
========================= */
$EMIT = [
  'nome'     => 'DISTRIBUIDORA (NOME FANTASIA)',
  'cnpj'     => '00.000.000/0001-00',
  'ie'       => 'ISENTO',
  'endereco' => 'Rua Exemplo, 123 - Centro - Coari/AM',
  'fone'     => '(92) 00000-0000',
  'msg'      => 'Obrigado e volte sempre!',
];

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function money(float $v): string {
  return number_format($v, 2, ',', '.');
}
function brDateTime(?string $dt): string {
  $dt = trim((string)$dt);
  if ($dt === '') return '';
  $ts = strtotime($dt);
  if (!$ts) return $dt;
  return date('d/m/Y H:i:s', $ts);
}
function payName(string $m): string {
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
    /* ===== CUPOM 58mm BÁSICO ===== */
    @page { size: 58mm auto; margin: 2mm; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; }
    .paper { width: 58mm; padding: 2mm; margin: 0 auto; }

    .c { text-align: center; }
    .r { text-align: right; }
    .b { font-weight: 800; }
    .t10 { font-size: 10px; }
    .t11 { font-size: 11px; }
    .t12 { font-size: 12px; }

    .line { border-top: 1px dashed #000; margin: 6px 0; }
    .line2 { border-top: 1px solid #000; margin: 6px 0; }

    table { width: 100%; border-collapse: collapse; }
    th, td { font-size: 10px; padding: 2px 0; vertical-align: top; }
    th { border-bottom: 1px solid #000; padding-bottom: 3px; font-weight: 800; }

    .itemname { font-size: 10px; font-weight: 800; }
    .subline { font-size: 10px; }

    .totrow { display: flex; justify-content: space-between; gap: 8px; font-size: 11px; }
    .totrow .lbl { font-weight: 700; }
    .totrow .val { font-weight: 800; }
    .grand { font-size: 13px; }

    .btns { display: flex; gap: 6px; justify-content: center; margin-top: 8px; }
    button { padding: 8px 10px; border: 1px solid #000; border-radius: 6px; background: #fff; cursor: pointer; font-weight: 800; font-size: 12px; }

    @media print {
      .btns { display: none; }
      .paper { margin: 0; }
    }
  </style>
</head>
<body>
  <div class="paper">

    <div class="c">
      <div class="b t12"><?= h($EMIT['nome']) ?></div>
      <div class="t10">CNPJ: <?= h($EMIT['cnpj']) ?> <?= $EMIT['ie'] ? ' • IE: ' . h($EMIT['ie']) : '' ?></div>
      <div class="t10"><?= h($EMIT['endereco']) ?></div>
      <div class="t10"><?= h($EMIT['fone']) ?></div>
    </div>

    <div class="line"></div>

    <div class="c b t11">CUPOM FISCAL</div>
    <div class="t10">
      <div><span class="b">Venda:</span> #<?= (int)$venda['id'] ?></div>
      <div><span class="b">Data/Hora:</span> <?= h($dataHora) ?></div>
      <div><span class="b">Canal:</span> <?= h($canal) ?></div>
    </div>

    <div class="line"></div>

    <div class="t10">
      <div><span class="b">Cliente:</span> <?= h($cliente) ?></div>
      <?php if ($canal === 'DELIVERY' && $endereco !== ''): ?>
        <div><span class="b">Endereço:</span> <?= h($endereco) ?></div>
      <?php endif; ?>
      <?php if ($canal === 'DELIVERY' && $obs !== ''): ?>
        <div><span class="b">Obs:</span> <?= h($obs) ?></div>
      <?php endif; ?>
    </div>

    <div class="line"></div>

    <table>
      <thead>
        <tr>
          <th style="width:6mm;">#</th>
          <th>Item</th>
          <th class="r" style="width:14mm;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$itens): ?>
          <tr><td colspan="3" class="c t10">Sem itens.</td></tr>
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
              <td class="t10"><?= $i + 1 ?></td>
              <td>
                <div class="itemname"><?= h($nome) ?></div>
                <div class="subline"><?= h($cod) ?> • <?= $qtd ?> <?= h($un) ?> x <?= money($vu) ?></div>
              </td>
              <td class="r b t10"><?= money($vt) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="line2"></div>

    <div class="totrow"><span class="lbl">Subtotal</span><span class="val"><?= money($subtotal) ?></span></div>
    <div class="totrow"><span class="lbl">Desconto</span><span class="val">- <?= money($desconto) ?></span></div>
    <div class="totrow"><span class="lbl">Taxa Entrega</span><span class="val"><?= money($taxaEnt) ?></span></div>

    <div class="line2"></div>

    <div class="totrow grand"><span class="lbl">TOTAL</span><span class="val"><?= money($total) ?></span></div>

    <div class="line"></div>

    <div class="t10">
      <div><span class="b">Pagamento:</span> <?= h(payName($pagLabel)) ?></div>

      <?php if ($pagMode === 'UNICO'): ?>
        <?php if ($paid > 0): ?><div>Valor Pago: <span class="b"><?= money($paid) ?></span></div><?php endif; ?>
        <?php if ($troco > 0): ?><div>Troco: <span class="b"><?= money($troco) ?></span></div><?php endif; ?>
      <?php else: ?>
        <?php if ($parts): ?>
          <div class="line"></div>
          <div class="b t10">Múltiplos:</div>
          <?php foreach ($parts as $p): ?>
            <?php
              $m = payName((string)($p['method'] ?? ''));
              $v = (float)($p['value'] ?? 0);
            ?>
            <div class="t10"><?= h($m) ?>: <span class="b"><?= money($v) ?></span></div>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($troco > 0): ?><div class="t10">Troco: <span class="b"><?= money($troco) ?></span></div><?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="line"></div>

    <div class="c t10"><?= h($EMIT['msg']) ?></div>

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