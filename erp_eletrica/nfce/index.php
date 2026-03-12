<?php
declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Carrega a configuração unificada (que resolve tanto Empresa quanto Vendas)
require_once __DIR__ . '/config.php';
// Nota: config.php já fornece $pdo (Config) e $pdoSales (Vendas) e define data_timezone

/* ===== Entrada ===== */
$vendaId  = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 0;
$autoEmit = isset($_GET['auto_emit']) ? (int)$_GET['auto_emit'] : 0;

$venda = null;
$itens = [];
$total = 0.0;
$cpfConsumidor = '';

if ($vendaId > 0) {
  // Cabeçalho da venda (Usa pdoSales)
  try {
    $st = $pdoSales->prepare("SELECT id, responsavel, cpf_responsavel, cpf_cliente, forma_pagamento, valor_total, valor_recebido, troco, empresa_id, id_caixa, data_venda
                         FROM vendas WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$vendaId]);
    $venda = $st->fetch();

    if ($venda) {
        $cpfConsumidor = preg_replace('/\D+/', '', (string)($venda['cpf_cliente'] ?? ''));
        // Itens
        $sti = $pdoSales->prepare("SELECT produto_id, produto_nome, quantidade, preco_unitario, ncm, cfop, unidade, informacoes_adicionais, cest, origem, tributacao
                              FROM itens_venda WHERE venda_id=:id ORDER BY id");
        $sti->execute([':id'=>$vendaId]);
        while ($r = $sti->fetch()) {
          $qtd = (float)$r['quantidade'];
          $vun = (float)$r['preco_unitario'];
          $linTotal = $qtd * $vun;
          $total += $linTotal;

          $itens[] = [
            'desc' => (string)$r['produto_nome'],
            'qtd'  => $qtd,
            'vun'  => $vun,
            'ncm'   => $r['ncm'] ?: '21069090',
            'cfop'  => $r['cfop'] ?: '5102',
            'unid'  => $r['unidade'] ?: 'UN',
            'info'  => $r['informacoes_adicionais'] ?: null,
            'cest'  => $r['cest'] ?: null,
            'origem'=> $r['origem'] ?: null,
            'trib'  => $r['tributacao'] ?: null,
          ];
        }
    }
  } catch (Throwable $e) { $venda = null; }
}

function brl($v){ return number_format((float)$v, 2, ',', '.'); }
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Revisão da Venda #<?= htmlspecialchars((string)$vendaId) ?> • NFC-e</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { --b:#e8e8e8; --tx:#222; --mut:#666; --accent:#1a73e8; }
  body{font-family:system-ui,Arial; color:var(--tx); margin:20px; max-width:1000px; background:#f9fafb}
  h2{margin:.2rem 0}
  .head{display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px}
  .card{border:1px solid var(--b); border-radius:12px; padding:20px; margin-bottom:16px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.1)}
  table{width:100%; border-collapse:collapse}
  th,td{border-bottom:1px solid var(--b); padding:12px 8px; text-align:left}
  th{background:#f9fafb; font-size:12px; text-transform:uppercase; color:var(--mut)}
  .right{text-align:right}
  .mut{color:var(--mut); font-size:13px}
  .total{font-size:20px; font-weight:700; color:var(--accent)}
  .btn{padding:12px 24px; border:0; background:var(--accent); color:#fff; border-radius:8px; cursor:pointer; font-weight:600; font-size:14px}
  .btn:hover{filter:brightness(0.9)}
</style>
</head>
<body>

<div class="head">
  <div>
    <h2>Revisão NFC-e • Venda #<?= htmlspecialchars((string)$vendaId) ?></h2>
    <div class="mut">Confira os itens antes de emitir a nota fiscal eletrônica.</div>
  </div>
</div>

<?php if (!$vendaId || !$venda): ?>
  <div class="card">
    <p><strong>Venda não encontrada (ID: <?= $vendaId ?>).</strong></p>
    <p class="mut">Verifique se a venda existe no banco de dados.</p>
  </div>
<?php else: ?>

  <div class="card" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:20px">
    <div>
      <h3 style="margin-top:0">Dados da Venda</h3>
      <div class="mut">Data: <?= date('d/m/Y H:i', strtotime((string)$venda['data_venda'])) ?></div>
      <div class="mut">Responsável: <?= htmlspecialchars((string)$venda['responsavel']) ?></div>
    </div>
    <div class="right">
      <div class="mut">Total da Venda</div>
      <div class="total">R$ <?= brl($total ?: $venda['valor_total']) ?></div>
    </div>
  </div>

  <div class="card">
    <h3 style="margin-top:0">Itens</h3>
    <table>
      <thead>
        <tr>
          <th>Descrição</th>
          <th class="right">Qtd</th>
          <th class="right">V. Unit</th>
          <th class="right">V. Total</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($itens)): ?>
          <tr><td colspan="4" class="mut center">Nenhum item encontrado nesta venda.</td></tr>
      <?php else: ?>
          <?php foreach ($itens as $it): ?>
            <tr>
              <td><?= htmlspecialchars($it['desc']) ?></td>
              <td class="right"><?= number_format($it['qtd'], 2) ?> <?= htmlspecialchars($it['unid']) ?></td>
              <td class="right">R$ <?= brl($it['vun']) ?></td>
              <td class="right">R$ <?= brl($it['qtd'] * $it['vun']) ?></td>
            </tr>
          <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <form id="fEmit" class="card" method="post" action="emitir.php">
    <input type="hidden" name="id" value="<?= htmlspecialchars((string)NFCE_EMPRESA_ID) ?>">
    <input type="hidden" name="venda_id" value="<?= (int)$vendaId ?>">
    <input type="hidden" name="itens" value="<?= htmlspecialchars(json_encode($itens, JSON_UNESCAPED_UNICODE)) ?>">
    
    <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap">
      <div style="flex:1">
        <label for="cpf" class="mut" style="display:block; margin-bottom:4px">CPF do Consumidor (opcional)</label>
        <input id="cpf" name="cpf" type="text" placeholder="000.000.000-00" value="<?= htmlspecialchars($cpfConsumidor) ?>" style="padding:10px; border:1px solid var(--b); border-radius:6px; width:100%">
      </div>
      <button class="btn" type="submit">EMITIR NOTA FISCAL</button>
    </div>
  </form>

<?php endif; ?>

<script>
<?php if ($autoEmit && !empty($itens)): ?>
  setTimeout(() => { document.getElementById('fEmit').submit(); }, 500);
<?php endif; ?>
</script>

</body>
</html>
