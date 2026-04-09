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
            font-family: 'Courier New', Courier, monospace; 
            width: 80mm; 
            margin: 0; 
            padding: 5mm; 
            font-size: 12px; 
            line-height: 1.2;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .hr { border-top: 1px dashed #000; margin: 5px 0; }
        .mb-1 { margin-bottom: 2px; }
        .mb-2 { margin-bottom: 5px; }
        .flex { display: flex; justify-content: space-between; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { text-align: left; padding: 2px 0; }
        .fs-small { font-size: 10px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">IMPRIMIR AGORA</button>
    </div>

    <div class="text-center">
        <div class="fw-bold" style="font-size: 14px;"><?= strtoupper(APP_NAME) ?></div>
        <div class="fs-small">Emissão: <?= date('d/m/Y H:i:s') ?></div>
        <div class="fs-small">@arcasistema</div>
    </div>

    <div class="hr"></div>

    <div class="fw-bold mb-1">RESUMO DE VENDAS (FIM DO TURNO)</div>
    <div class="mb-1">CAIXA: <?= $id ?> OPERADOR: <?= strtoupper(htmlspecialchars($caixa['operador_nome'] ?? 'ADM')) ?></div>
    <div class="mb-1">Data abertura: <?= date('d/m/Y H:i:s', strtotime($caixa['data_abertura'])) ?></div>
    <?php if ($isFechado): ?>
        <div class="mb-1">Data fechamento: <?= date('d/m/Y H:i:s', strtotime($caixa['data_fechamento'])) ?></div>
    <?php endif; ?>

    <div class="hr"></div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 100%;" colspan="3">FORMA PAGTO / DETALHES (SIST | INF | DIF)</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resumoDeth): ?>
                <?php foreach ($resumoDeth as $metodo => $vals): ?>
                <tr>
                    <td colspan="3" class="fw-bold fs-small" style="padding-top: 5px;"><?= $metodo ?></td>
                </tr>
                <tr>
                    <td class="fs-small" style="width: 33%;">R$ <?= number_format($vals['calculado'], 2, ',', '.') ?></td>
                    <td class="fs-small text-center" style="width: 34%;">R$ <?= number_format($vals['informado'], 2, ',', '.') ?></td>
                    <td class="fs-small text-right fw-bold" style="width: 33%;">R$ <?= number_format($vals['diferenca'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="text-center"><em>Resumo detalhado não disponível</em></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="hr"></div>

    <div class="flex fw-bold">
        <span>TOTAIS:</span>
        <div style="display: flex; gap: 10px;">
            <span class="fs-small">R$ <?= number_format($summary['total_vendas'], 2, ',', '.') ?></span>
            <span class="fs-small">R$ <?= number_format($totalInformado - $caixa['valor_abertura'] - ($summary['suprimento'] ?? 0) + ($summary['sangria'] ?? 0), 2, ',', '.') ?></span>
            <span class="fs-small">R$ <?= number_format($diferencaTotal, 2, ',', '.') ?></span>
        </div>
    </div>


    <div class="hr"></div>

    <div class="fw-bold mb-1">RESUMO FINANCEIRO</div>
    <div class="flex">
        <span>DINHEIRO (VENDAS):</span>
        <span>R$ <?= number_format($summary['breakdown']['DINHEIRO'] ?? 0, 2, ',', '.') ?></span>
    </div>
    <div class="flex">
        <span>SUPRIMENTO (+):</span>
        <span>R$ <?= number_format($summary['suprimento'] ?? 0, 2, ',', '.') ?></span>
    </div>
    <div class="flex">
        <span>SANGRIA (-):</span>
        <span>R$ <?= number_format($summary['sangria'] ?? 0, 2, ',', '.') ?></span>
    </div>
    <div class="flex fw-bold">
        <span>SALDO (GAVETA):</span>
        <span>R$ <?= number_format($caixa['valor_abertura'] + ($summary['saldo'] ?? 0), 2, ',', '.') ?></span>
    </div>

    <div class="hr"></div>

    <div class="flex">
        <span>RECEBIMENTOS:</span>
        <span>R$ <?= number_format($summary['recebimentos'] ?? 0, 2, ',', '.') ?></span>
    </div>

    <div class="hr"></div>
    <div class="text-center fs-small" style="margin-top: 10px;">
        Arca Sistema - Gestão Inteligente
    </div>

    <script>
        window.onload = function() {
            // window.print();
        }
    </script>
</body>
</html>
