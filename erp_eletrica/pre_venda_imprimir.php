<?php
require_once 'config.php';
checkAuth();

$code = $_GET['code'] ?? '';
$model = new \App\Models\PreSale();
$pv = $model->findByCode($code);

if (!$pv) {
    die("Pré-venda não encontrada ou já finalizada.");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Pré-Venda - <?= $pv['codigo'] ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 80mm; margin: 0; padding: 10px; font-size: 12px; }
        .text-center { text-align: center; }
        .header { border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 10px; }
        .code { font-size: 24px; font-weight: bold; border: 1px solid #000; padding: 10px; display: inline-block; margin: 10px 0; }
        .items { border-bottom: 1px dashed #000; margin-bottom: 10px; padding-bottom: 10px; }
        .footer { font-size: 10px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="text-center no-print">
        <button onclick="window.print()">Imprimir Agora</button>
        <button onclick="window.close()">Fechar</button>
        <hr>
    </div>

    <div class="header text-center">
        <h2>ERP ELÉTRICA</h2>
        <p>FICHA DE PRÉ-VENDA / ORÇAMENTO</p>
        <p>Data: <?= date('d/m/Y H:i', strtotime($pv['created_at'])) ?></p>
    </div>

    <div class="text-center">
        <p>APRESENTE ESTE CÓDIGO NO CAIXA:</p>
        <div class="code"><?= $pv['codigo'] ?></div>
    </div>

    <div class="items">
        <strong>ITENS:</strong><br>
        <?php foreach ($pv['itens'] as $item): ?>
            <?= str_pad($item['quantidade'], 5) ?> x <?= substr($item['produto_nome'], 0, 20) ?>...<br>
        <?php endforeach; ?>
    </div>

    <div class="text-center">
        <h3>TOTAL: R$ <?= number_format($pv['valor_total'], 2, ',', '.') ?></h3>
    </div>

    <div class="footer text-center">
        <p>Esta ficha não é um documento fiscal.<br>Válido apenas para consulta no balcão.</p>
    </div>
</body>
</html>
