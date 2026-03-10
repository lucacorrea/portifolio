<?php
// recibo_venda.php — Recibo Não Fiscal (estilo bobina 80mm)
// Uso: recibo_venda.php?id=<venda_id>
session_start();
if (empty($_SESSION['usuario_id'])) { http_response_code(403); exit('Acesso negado.'); }

require_once __DIR__ . '/src/autoload.php';

$vendaId = (int)($_GET['id'] ?? 0);
if (!$vendaId) { exit('ID inválido.'); }

$db = \App\Config\Database::getInstance()->getConnection();

// Fetch sale
$stmt = $db->prepare("
    SELECT v.*, 
           COALESCE(c.nome, v.nome_cliente_avulso, 'Consumidor Final') as cliente_nome,
           c.cpf_cnpj,
           u.nome as vendedor_nome,
           f.nome as filial_nome, f.cnpj as filial_cnpj, f.endereco as filial_endereco,
           f.telefone as filial_telefone, f.cidade as filial_cidade, f.uf as filial_uf
    FROM vendas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    LEFT JOIN filiais  f ON v.filial_id = f.id
    WHERE v.id = ?
");
$stmt->execute([$vendaId]);
$venda = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$venda) { exit('Venda não encontrada.'); }

// Fetch items
$stmtI = $db->prepare("
    SELECT vi.quantidade, vi.preco_unitario, p.nome, p.codigo, p.unidade
    FROM vendas_itens vi
    JOIN produtos p ON vi.produto_id = p.id
    WHERE vi.venda_id = ?
");
$stmtI->execute([$vendaId]);
$itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

$paymentMap = [
    'dinheiro'       => 'Dinheiro',
    'pix'            => 'PIX',
    'cartao_credito' => 'Cartão de Crédito',
    'cartao_debito'  => 'Cartão de Débito',
    'boleto'         => 'Boleto',
    'fiado'          => 'A Prazo (Fiado)',
];
$formaPag = $paymentMap[$venda['forma_pagamento']] ?? strtoupper($venda['forma_pagamento']);
$dataVenda = date('d/m/Y H:i', strtotime($venda['data_venda'] ?? $venda['created_at'] ?? 'now'));
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Recibo #<?= $vendaId ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --ticket-max: 384px; --pad: 12px; --ink: #111; --paper: #fff; --bg: #f5f7fb; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: var(--bg); color: var(--ink); -webkit-text-size-adjust: 100%; }
        body { font: 13px/1.45 monospace; }
        .wrapper { width: 100%; max-width: var(--ticket-max); margin: 10px auto 90px; background: var(--paper); border-radius: 12px; box-shadow: 0 10px 28px rgba(0,0,0,.08); padding: var(--pad); }
        .center { text-align: center; }
        .right  { text-align: right; }
        .left   { text-align: left; }
        .small  { font-size: 11px; }
        .hr     { border-top: 1px dashed #000; margin: 8px 0; }
        .tbl    { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .tbl thead th { border-bottom: 1px dashed #000; font-weight: 700; padding: 4px 0; }
        .tbl td { padding: 3px 0; vertical-align: top; }
        .badge-nf { display:inline-block; background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:6px; font-size:10px; border:1px solid #fca5a5; }
        .actions { position:fixed; left:0; right:0; bottom:0; z-index:50; padding:10px; background:#fff; border-top:1px solid #e5e7eb; display:flex; gap:10px; justify-content:center; }
        .btn { appearance:none; border:0; border-radius:10px; padding:11px 20px; font-family:system-ui,sans-serif; font-weight:600; cursor:pointer; transition:.2s; white-space:nowrap; font-size:14px; }
        .btn-primary   { background:#2563eb; color:#fff; }
        .btn-secondary { background:#6b7280; color:#fff; }
        @page { size: 80mm auto; margin: 3mm; }
        @media print {
            html, body { background:#fff; }
            .wrapper { box-shadow:none; border-radius:0; margin:0; max-width:unset; width:75mm; padding:0; }
            .actions { display:none; }
        }
    </style>
</head>
<body>
<div class="wrapper" role="document">
    <header class="center">
        <div style="font-size:16px;font-weight:700;text-transform:uppercase;"><?= htmlspecialchars($venda['filial_nome'] ?? 'ERP Elétrica') ?></div>
        <?php if ($venda['filial_cnpj']): ?>
        <div class="small">CNPJ: <?= htmlspecialchars($venda['filial_cnpj']) ?></div>
        <?php endif; ?>
        <?php if ($venda['filial_endereco']): ?>
        <div class="small"><?= htmlspecialchars($venda['filial_endereco']) ?><?php if($venda['filial_cidade']): ?>, <?= htmlspecialchars($venda['filial_cidade']) ?>/<?= htmlspecialchars($venda['filial_uf']) ?><?php endif; ?></div>
        <?php endif; ?>
        <?php if ($venda['filial_telefone']): ?>
        <div class="small">Tel: <?= htmlspecialchars($venda['filial_telefone']) ?></div>
        <?php endif; ?>
    </header>

    <div class="hr"></div>
    <div class="center"><span class="badge-nf">RECIBO NÃO FISCAL</span></div>
    <div class="hr"></div>

    <div class="small">Recibo Nº: <b>#<?= $vendaId ?></b></div>
    <div class="small">Data: <b><?= $dataVenda ?></b></div>
    <div class="small">Vendedor: <?= htmlspecialchars($venda['vendedor_nome'] ?? '—') ?></div>

    <div class="hr"></div>

    <table class="tbl small">
        <colgroup>
            <col style="width:40%">
            <col style="width:10%">
            <col style="width:10%">
            <col style="width:18%">
            <col style="width:22%">
        </colgroup>
        <thead>
            <tr>
                <th class="left">Produto</th>
                <th class="right">Qtd</th>
                <th class="left">Un</th>
                <th class="right">V.Unit</th>
                <th class="right">V.Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $it):
                $subtotal = $it['quantidade'] * $it['preco_unitario'];
            ?>
            <tr>
                <td class="left"><?= htmlspecialchars(mb_strimwidth($it['nome'], 0, 22, '..')) ?></td>
                <td class="right"><?= number_format($it['quantidade'],2,',','.') ?></td>
                <td class="left"><?= htmlspecialchars($it['unidade'] ?? 'UN') ?></td>
                <td class="right"><?= number_format($it['preco_unitario'],2,',','.') ?></td>
                <td class="right"><?= number_format($subtotal,2,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="hr"></div>

    <table class="tbl small">
        <tbody>
            <?php if ($venda['desconto_total'] > 0): ?>
            <tr><td class="left"><b>DESCONTO</b></td><td class="right">- R$ <?= number_format($venda['desconto_total'],2,',','.') ?></td></tr>
            <?php endif; ?>
            <tr><td class="left"><b>QTDE DE ITENS</b></td><td class="right"><?= count($itens) ?></td></tr>
            <tr><td class="left" style="font-size:14px;"><b>TOTAL R$</b></td><td class="right" style="font-size:14px;"><b><?= number_format($venda['valor_total'],2,',','.') ?></b></td></tr>
            <tr><td class="left"><b>PAGAMENTO</b></td><td class="right"><?= htmlspecialchars($formaPag) ?></td></tr>
        </tbody>
    </table>

    <div class="hr"></div>

    <div class="small center">Cliente: <b><?= htmlspecialchars($venda['cliente_nome']) ?></b></div>
    <?php if (!empty($venda['cpf_cnpj'])): ?>
    <div class="small center">CPF/CNPJ: <?= htmlspecialchars($venda['cpf_cnpj']) ?></div>
    <?php endif; ?>

    <div class="hr"></div>

    <div class="small center" style="color:#888;">
        Este documento não tem validade fiscal.<br>
        Obrigado pela preferência!
    </div>
</div>

<div class="actions">
    <button class="btn btn-secondary" onclick="window.close()">← Fechar</button>
    <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimir</button>
</div>

<script>
    // Auto open print dialog after render
    window.addEventListener('load', function() {
        setTimeout(() => window.print(), 600);
    });
</script>
</body>
</html>
