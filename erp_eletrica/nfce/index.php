<?php
declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Carrega a configuração unificada (que resolve tanto Empresa quanto Vendas no banco u784961086_pdv)
require_once __DIR__ . '/config.php';

// ===== AJAX: Busca de cliente para autocomplete =====
if (isset($_GET['busca_cliente']) && isset($_GET['q'])) {
    header('Content-Type: application/json; charset=utf-8');
    $q        = trim($_GET['q']);
    $qLike    = '%' . $q . '%';
    // Remove non-digits to also search by raw CPF digits
    $qDigits  = preg_replace('/\D/', '', $q);
    try {
        $stBusca = $pdo->prepare("SELECT id, nome, cpf_cnpj FROM clientes 
                                   WHERE nome LIKE :q 
                                      OR cpf_cnpj LIKE :q
                                      OR REGEXP_REPLACE(cpf_cnpj, '[^0-9]', '') LIKE :qd 
                                   ORDER BY nome LIMIT 8");
        $stBusca->execute([':q' => $qLike, ':qd' => '%'.$qDigits.'%']);
        echo json_encode($stBusca->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        // Fallback sem REGEXP_REPLACE (MySQL < 8)
        try {
            $stBusca2 = $pdo->prepare("SELECT id, nome, cpf_cnpj FROM clientes 
                                        WHERE nome LIKE :q OR cpf_cnpj LIKE :q 
                                        ORDER BY nome LIMIT 8");
            $stBusca2->execute([':q' => $qLike]);
            echo json_encode($stBusca2->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e2) {
            echo json_encode([]);
        }
    }
    exit;
}

/* ===== Entrada ===== */
$vendaId  = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 0;
$autoEmit = isset($_GET['auto_emit']) ? (int)$_GET['auto_emit'] : 0;

$venda = null;
$itens = [];
$total = 0.0;
$cpfConsumidor = '';

if ($vendaId > 0) {
  // Cabeçalho da venda (Usa pdo que está em u784961086_pdv)
  try {
    $st = $pdo->prepare("SELECT v.*, c.nome as cliente_nome, c.cpf_cnpj as cliente_doc
                         FROM vendas v
                         LEFT JOIN clientes c ON v.cliente_id = c.id
                         WHERE v.id = :id 
                         LIMIT 1");
    $st->execute([':id'=>$vendaId]);
    $venda = $st->fetch();

    if ($venda) {
        $cpfConsumidor = preg_replace('/\D+/', '', (string)($venda['cliente_doc'] ?? ''));
        $nomeConsumidor = (string)($venda['cliente_nome'] ?? $venda['nome_cliente_avulso'] ?? '');
        
        // Itens (Busca em VENDAS_ITENS JOIN PRODUTOS, seguindo Sale.php)
        $sti = $pdo->prepare("SELECT i.*, p.nome as produto_nome, p.unidade as p_unidade, p.ncm as p_ncm, p.origem as p_origem, p.cest as p_cest
                                FROM vendas_itens i 
                                JOIN produtos p ON i.produto_id = p.id 
                               WHERE i.venda_id = :id ORDER BY i.id");
        $sti->execute([':id'=>$vendaId]);
        
        while ($r = $sti->fetch()) {
          $qtd = (float)$r['quantidade'];
          $vun = (float)$r['preco_unitario'];
          $linTotal = $qtd * $vun;
          $total += $linTotal;

          $itens[] = [
            'desc' => (string)($r['produto_nome'] ?? $r['produto_id']),
            'qtd'  => $qtd,
            'vun'  => $vun,
            'ncm'   => (string)($r['ncm'] ?: $r['p_ncm'] ?: '21069090'),
            'cfop'  => (string)($r['cfop'] ?: '5102'),
            'unid'  => (string)($r['unidade'] ?: $r['p_unidade'] ?: 'UN'),
            'origem'=> (string)($r['origem'] ?: $r['p_origem'] ?: '0'),
            'cest'  => (string)($r['cest'] ?: $r['p_cest'] ?: ''),
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
<title>Revisão NFC-e • Venda #<?= htmlspecialchars((string)$vendaId) ?></title>
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
    <p><strong>Venda #<?= $vendaId ?> não encontrada.</strong></p>
    <p class="mut">Verifique se esta venda existe na tabela <code>vendas</code> do banco <code>u784961086_pdv</code>.</p>
  </div>
<?php else: ?>

  <div class="card" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:20px">
    <div>
      <h3 style="margin-top:0">Dados da Venda</h3>
      <div class="mut">Data: <?= date('d/m/Y H:i', strtotime((string)$venda['data_venda'])) ?></div>
      <div class="mut">Responsável: <?= htmlspecialchars((string)$venda['responsavel']) ?></div>
      <div class="mut">Pagamento: <?= htmlspecialchars((string)$venda['forma_pagamento']) ?></div>
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
          <tr><td colspan="4" class="mut center">Nenhum item encontrado na tabela <code>vendas_itens</code> para esta venda.</td></tr>
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
    <input type="hidden" name="venda_id" value="<?= (int)$vendaId ?>">
    <input type="hidden" name="itens" value="<?= htmlspecialchars(json_encode($itens, JSON_UNESCAPED_UNICODE)) ?>">
    
    <h3 style="margin-top:0">Identificar Cliente (opcional)</h3>
    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:12px; position:relative">
      <div style="flex:1; min-width:200px">
        <label for="campo_cpf" class="mut" style="display:block; margin-bottom:4px">CPF/CNPJ (somente dígitos)</label>
        <!-- Açaidinhos pattern: campo direto name="cpf", valor é dígitos puros -->
        <input id="campo_cpf" name="cpf" type="text" inputmode="numeric"
               placeholder="000.000.000-00 ou só dígitos"
               value="<?= htmlspecialchars($cpfConsumidor) ?>"
               style="padding:10px; border:1px solid var(--b); border-radius:6px; width:100%; box-sizing:border-box"
               autocomplete="off">
      </div>
      <div style="flex:2; min-width:220px">
        <label for="campo_nome" class="mut" style="display:block; margin-bottom:4px">Nome do Cliente (busca ou digita)</label>
        <input id="campo_nome" name="nome_dest" type="text"
               placeholder="Buscar cliente por nome..."
               value="<?= htmlspecialchars($nomeConsumidor ?? '') ?>"
               style="padding:10px; border:1px solid var(--b); border-radius:6px; width:100%; box-sizing:border-box"
               autocomplete="off">
        <ul id="sugestoes" style="display:none; position:absolute; background:#fff; border:1px solid var(--b);
            border-radius:6px; list-style:none; margin:0; padding:0; min-width:280px; z-index:10;
            box-shadow:0 4px 12px rgba(0,0,0,0.15); max-height:220px; overflow-y:auto"></ul>
      </div>
    </div>
    <div id="info_cliente" class="mut" style="font-size:13px; margin-bottom:12px"><?php
      if ($cpfConsumidor) echo '✅ <b>Venda vinculada:</b> ' . htmlspecialchars($nomeConsumidor) . ' (CPF: ' . $cpfConsumidor . ')';
    ?></div>
    <button class="btn" type="submit" <?= empty($itens) ? 'disabled' : '' ?>>EMITIR NOTA FISCAL</button>
  </form>

  <script>
  (function(){
    const fCpf   = document.getElementById('campo_cpf');
    const fNome  = document.getElementById('campo_nome');
    const list   = document.getElementById('sugestoes');
    const info   = document.getElementById('info_cliente');
    const form   = document.getElementById('fEmit');
    let debounce;

    // Antes de submeter: limpa dígitos do campo CPF
    form.addEventListener('submit', function(){
      fCpf.value = fCpf.value.replace(/\D/g, '');
    });

    // Autocomplete pelo nome
    fNome.addEventListener('input', function(){
      clearTimeout(debounce);
      const q = this.value.trim();
      if (q.length < 2) { list.style.display = 'none'; return; }

      debounce = setTimeout(() => {
        fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'] ?? 'index.php') ?>?busca_cliente=1&q=' + encodeURIComponent(q))
          .then(r => r.json())
          .then(data => {
            list.innerHTML = '';
            if (!data.length) { list.style.display = 'none'; return; }
            data.forEach(c => {
              const li = document.createElement('li');
              li.style.cssText = 'padding:10px 12px; cursor:pointer; border-bottom:1px solid #f0f0f0; font-size:13px';
              const hasCpf = c.cpf_cnpj && c.cpf_cnpj.replace(/\D/g,'').length >= 11;
              li.innerHTML = '<b>' + c.nome + '</b>'
                + (c.cpf_cnpj ? ' <span style="color:#555">— ' + c.cpf_cnpj + '</span>' : '<span style="color:#e00"> (sem CPF)</span>');
              li.addEventListener('mouseenter', () => li.style.background = '#f0f7ff');
              li.addEventListener('mouseleave', () => li.style.background = '');
              li.addEventListener('click', () => {
                fNome.value = c.nome;
                fCpf.value  = c.cpf_cnpj ? c.cpf_cnpj.replace(/\D/g,'') : '';
                list.style.display = 'none';
                if (hasCpf) {
                  info.innerHTML = '✅ <b>' + c.nome + '</b> (CPF: ' + c.cpf_cnpj + ') — será impresso na nota';
                } else {
                  info.innerHTML = '⚠️ <b>' + c.nome + '</b> não tem CPF — sem identificação na nota';
                }
              });
              list.appendChild(li);
            });
            list.style.display = 'block';
          }).catch(() => list.style.display = 'none');
      }, 300);
    });

    // CPF digitado manualmente: valida e mostra feedback
    fCpf.addEventListener('input', function(){
      const d = this.value.replace(/\D/g,'');
      if (d.length === 11) {
        info.innerHTML = '✅ CPF registrado — será impresso na nota';
      } else if (d.length === 14) {
        info.innerHTML = '✅ CNPJ registrado — será impresso na nota';
      } else if (d.length > 0) {
        info.innerHTML = '⌛ Continue digitando... (' + d.length + '/11 dígitos)';
      } else {
        info.innerHTML = '';
      }
    });

    document.addEventListener('click', e => {
      if (e.target !== fNome) list.style.display = 'none';
    });
  })();
  </script>

<?php endif; ?>

<script>
<?php if ($autoEmit && !empty($itens)): ?>
  setTimeout(() => { document.getElementById('fEmit').submit(); }, 500);
<?php endif; ?>
</script>

</body>
</html>
