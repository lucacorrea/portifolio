<?php
declare(strict_types=1);

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

function money(float $v): string {
  return 'R$ ' . number_format($v, 2, ',', '.');
}

$auto = (int)($_GET['auto'] ?? 0) === 1;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Cupom Venda #<?= (int)$venda['id'] ?></title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 16px; color:#0f172a; }
    .box { max-width: 520px; margin: 0 auto; }
    .top { text-align: center; margin-bottom: 10px; }
    .top h2 { margin: 0; font-size: 18px; }
    .muted { color:#64748b; font-size: 12px; }
    hr { border:0; border-top:1px dashed #cbd5e1; margin: 10px 0; }
    table { width:100%; border-collapse: collapse; }
    th, td { padding: 6px 0; font-size: 12px; }
    th { text-align:left; border-bottom: 1px solid #e2e8f0; }
    td.r, th.r { text-align: right; }
    .tot { font-size: 14px; font-weight: 800; }
    .btns { display:flex; gap:8px; justify-content:center; margin-top:12px; }
    button { padding: 10px 12px; border:1px solid #cbd5e1; border-radius:10px; background:#fff; cursor:pointer; font-weight:700; }
    @media print {
      .btns { display:none; }
      body { padding: 0; }
      .box { max-width: 100%; }
    }
  </style>
</head>
<body>
  <div class="box">
    <div class="top">
      <h2>COMPROVANTE DE VENDA</h2>
      <div class="muted">Venda #<?= (int)$venda['id'] ?> • <?= htmlspecialchars((string)$venda['created_at']) ?></div>
      <div class="muted">Canal: <?= htmlspecialchars((string)$venda['canal']) ?></div>
    </div>

    <hr>

    <div class="muted">
      Cliente: <b><?= htmlspecialchars((string)($venda['cliente'] ?? 'Consumidor Final')) ?></b><br>
      <?php if (($venda['canal'] ?? '') === 'DELIVERY'): ?>
        Endereço: <b><?= htmlspecialchars((string)($venda['endereco'] ?? '')) ?></b><br>
        Obs: <b><?= htmlspecialchars((string)($venda['obs'] ?? '')) ?></b><br>
      <?php endif; ?>
    </div>

    <hr>

    <table>
      <thead>
        <tr>
          <th>Item</th>
          <th class="r">Qtd</th>
          <th class="r">Unit</th>
          <th class="r">Sub</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($itens as $it): ?>
          <tr>
            <td>
              <b><?= htmlspecialchars((string)$it['nome']) ?></b><br>
              <span class="muted"><?= htmlspecialchars((string)$it['codigo']) ?></span>
            </td>
            <td class="r"><?= (int)$it['qtd'] ?></td>
            <td class="r"><?= money((float)$it['preco_unit']) ?></td>
            <td class="r"><?= money((float)$it['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <hr>

    <table>
      <tr><td class="muted">Subtotal</td><td class="r"><?= money((float)$venda['subtotal']) ?></td></tr>
      <tr><td class="muted">Desconto</td><td class="r">- <?= money((float)$venda['desconto_valor']) ?></td></tr>
      <tr><td class="muted">Taxa entrega</td><td class="r"><?= money((float)$venda['taxa_entrega']) ?></td></tr>
      <tr><td class="tot">TOTAL</td><td class="r tot"><?= money((float)$venda['total']) ?></td></tr>
    </table>

    <hr>

    <div class="muted">
      Pagamento: <b><?= htmlspecialchars((string)$venda['pagamento']) ?></b>
    </div>

    <div class="btns">
      <button onclick="window.print()">Imprimir</button>
      <button onclick="window.close()">Fechar</button>
    </div>
  </div>

  <script>
    <?php if ($auto): ?>
      window.addEventListener('load', () => setTimeout(() => window.print(), 350));
    <?php endif; ?>
  </script>
</body>
</html>