<?php
require_once 'config.php';
require_once 'autoloader.php';

use App\Models\Cashier;
use App\Services\AuthService;

AuthService::checkPermission('caixa', 'visualizar');

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID da sessão não fornecido.");
}

$cashierModel = new Cashier();
$details = $cashierModel->getSessionDetails($id);

if (!$details) {
    die("Sessão não encontrada.");
}

$caixa = $details['caixa'];
$summary = $details['summary'];
$isFechado = $caixa['status'] === 'fechado';
$resumoDeth = !empty($caixa['resumo_fechamento']) ? json_decode($caixa['resumo_fechamento'], true) : null;

// Totais para o rodapé da nota
$totalSistema = $caixa['valor_abertura'] + ($summary['saldo'] ?? 0);
$totalInformado = $caixa['valor_fechamento'] ?? 0;
$diferencaTotal = $totalInformado - $totalSistema;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Resumo de Caixa #<?= $id ?></title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { 
            font-family: Arial, sans-serif; 
            width: 72mm; 
            margin: 0 auto; 
            padding: 5mm 0; 
            font-size: 11px; 
            line-height: 1.4;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .hr { border-top: 1px solid #000; margin: 4px 0; }
        .mb-1 { margin-bottom: 2px; }
        .flex { display: flex; justify-content: space-between; align-items: baseline; }
        .table { width: 100%; border-collapse: collapse; }
        .table td { padding: 1px 0; vertical-align: top; }
        .fs-small { font-size: 10px; }
        .fs-large { font-size: 13px; }
        .col-3 { display: inline-block; width: 32%; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">IMPRIMIR AGORA</button>
    </div>

    <div class="flex fs-small">
        <span>Emissão: <?= date('d/m/Y') ?></span>
        <span>@arcasistema</span>
    </div>

    <div class="fw-bold fs-large mb-1"><?= strtoupper(APP_NAME) ?></div>
    
    <div class="fw-bold mb-1">CAIXA: <?= $id ?> &nbsp; OPERADOR: <?= strtoupper(htmlspecialchars($caixa['operador_nome'] ?? 'ADM')) ?></div>
    
    <div class="mb-1 fw-bold">Data abertura: <?= date('d/m/Y H:i:s', strtotime($caixa['data_abertura'])) ?></div>
    <?php if ($isFechado): ?>
        <div class="mb-1 fw-bold">Data fechamento: <?= date('d/m/Y H:i:s', strtotime($caixa['data_fechamento'])) ?></div>
    <?php endif; ?>

    <div class="hr" style="border-top: 2px solid #000;"></div>

    <div class="fw-bold">RESUMO VENDAS</div>
    <div class="flex fw-bold">
        <span>FORMA PAGTO</span>
        <span>VALOR-R$</span>
    </div>

    <div class="hr"></div>

    <?php if ($resumoDeth): ?>
        <?php foreach ($resumoDeth as $metodo => $vals): ?>
        <div class="fw-bold fs-small mt-1"><?= $metodo ?></div>
        <div class="flex fw-bold" style="padding-left: 20mm;">
            <span style="width: 33%; text-align: right;">R$<?= number_format($vals['calculado'], 2, ',', '.') ?></span>
            <span style="width: 33%; text-align: right;">R$<?= number_format($vals['informado'], 2, ',', '.') ?></span>
            <span style="width: 33%; text-align: right;">R$<?= number_format($vals['diferenca'], 2, ',', '.') ?></span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="hr"></div>

    <div class="flex fw-bold">
        <span>TOTAIS:</span>
        <div style="display: flex; gap: 8px; justify-content: flex-end; width: 70%;">
            <span style="width: 33%; text-align: right;">R$<?= number_format($summary['total_vendas'], 2, ',', '.') ?></span>
            <span style="width: 33%; text-align: right;">R$<?= number_format($totalInformado - $caixa['valor_abertura'] - ($summary['suprimento'] ?? 0) + ($summary['sangria'] ?? 0), 2, ',', '.') ?></span>
            <span style="width: 33%; text-align: right;">R$<?= number_format($diferencaTotal, 2, ',', '.') ?></span>
        </div>
    </div>

    <div class="hr"></div>
    <div class="fw-bold mb-1">TOTAIS</div>
    
    <div class="flex">
        <span class="fw-bold">DINHEIRO</span>
        <span class="fw-bold">R$<?= number_format($summary['breakdown']['DINHEIRO'] ?? 0, 2, ',', '.') ?></span>
    </div>
    <div class="flex">
        <span class="fw-bold">SUPRIMENTO</span>
        <span class="fw-bold">R$<?= number_format($summary['suprimento'] ?? 0, 2, ',', '.') ?></span>
    </div>
    <div class="flex">
        <span class="fw-bold">SANGRIA</span>
        <span class="fw-bold">R$<?= number_format($summary['sangria'] ?? 0, 2, ',', '.') ?></span>
    </div>
    <div class="flex">
        <span class="fw-bold">SALDO</span>
        <span class="fw-bold">R$<?= number_format($caixa['valor_abertura'] + $summary['saldo'], 2, ',', '.') ?></span>
    </div>

    <div class="hr"></div>
    <div class="fw-bold">RECEBIMENTOS</div>
    <div class="hr"></div>

    <div class="fs-small" style="margin-top: 10px;">Arca Sistema</div>


    <script>
        window.onload = function() {
            // window.print();
        }
    </script>
</body>
</html>
