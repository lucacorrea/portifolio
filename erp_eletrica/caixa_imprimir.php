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
if (!function_exists('caixa_fmt_moeda')) {
    function caixa_fmt_moeda($valor) {
        return 'R$' . number_format((float)$valor, 2, ',', '.');
    }
}

$metodosObrigatorios = ['A PRAZO', 'CARTAO CREDITO', 'CARTAO DEBITO', 'DINHEIRO', 'PIX'];
if ((float)($summary['breakdown']['CARTAO'] ?? 0) > 0 || (is_array($resumoDeth) && isset($resumoDeth['CARTAO']))) {
    array_splice($metodosObrigatorios, 3, 0, 'CARTAO');
}
$breakdownParaMostrar = [];
$totalCalculadoPagamentos = 0;
$totalInformadoPagamentos = 0;
$divergencias = [];

foreach ($metodosObrigatorios as $metodo) {
    $calc = (float)($summary['breakdown'][$metodo] ?? 0);
    $inf = $calc;

    if (is_array($resumoDeth) && isset($resumoDeth[$metodo]) && is_array($resumoDeth[$metodo])) {
        $calc = (float)($resumoDeth[$metodo]['calculado'] ?? $calc);
        $inf = (float)($resumoDeth[$metodo]['informado'] ?? $calc);
    }

    $diff = $inf - $calc;
    $breakdownParaMostrar[$metodo] = [
        'calculado' => $calc,
        'informado' => $inf,
        'diferenca' => $diff
    ];

    $totalCalculadoPagamentos += $calc;
    $totalInformadoPagamentos += $inf;

    if (abs($diff) >= 0.005) {
        $divergencias[] = [
            'metodo' => $metodo,
            'diferenca' => $diff
        ];
    }
}

$dinheiroInformado = $breakdownParaMostrar['DINHEIRO']['informado'] ?? (float)($summary['breakdown']['DINHEIRO'] ?? 0);
$saldoFinalSistema = (float)$caixa['valor_abertura'] + (float)($summary['saldo'] ?? 0);
$saldoFinalInformado = (float)$caixa['valor_abertura'] + $dinheiroInformado + (float)($summary['suprimento'] ?? 0) - (float)($summary['sangria'] ?? 0);
$diferencaPagamentos = $totalInformadoPagamentos - $totalCalculadoPagamentos;
$diferencaGaveta = $saldoFinalInformado - $saldoFinalSistema;
$labelsPagamento = [
    'CARTAO CREDITO' => 'CREDITO',
    'CARTAO DEBITO' => 'DEBITO',
];

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
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 23mm 23mm;
            gap: 2mm;
            align-items: baseline;
        }
        .payment-grid span:nth-child(2),
        .payment-grid span:nth-child(3) {
            text-align: right;
            white-space: nowrap;
        }
        .mt-1 { margin-top: 3px; }
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
    <div class="payment-grid fw-bold">
        <span>FORMA</span>
        <span>FEITO</span>
        <span>CONF.</span>
    </div>

    <div class="hr"></div>

    <?php foreach ($breakdownParaMostrar as $metodo => $vals): ?>
    <div class="payment-grid fw-bold fs-small mt-1">
        <span><?= $labelsPagamento[$metodo] ?? $metodo ?></span>
        <span><?= caixa_fmt_moeda($vals['calculado']) ?></span>
        <span><?= caixa_fmt_moeda($vals['informado']) ?></span>
    </div>
    <?php endforeach; ?>


    <div class="hr"></div>

    <div class="payment-grid fw-bold">
        <span>TOTAIS:</span>
        <span><?= caixa_fmt_moeda($totalCalculadoPagamentos) ?></span>
        <span><?= caixa_fmt_moeda($totalInformadoPagamentos) ?></span>
    </div>

    <div class="hr"></div>
    <div class="fw-bold">CONFERENCIA</div>
    <?php if (empty($divergencias)): ?>
        <div class="fw-bold fs-small">OK - valores conferidos batem com o sistema.</div>
    <?php else: ?>
        <?php foreach ($divergencias as $div): ?>
            <div class="fw-bold fs-small">
                <?= $labelsPagamento[$div['metodo']] ?? $div['metodo'] ?>:
                <?= $div['diferenca'] > 0 ? 'SOBRA' : 'FALTA' ?>
                <?= caixa_fmt_moeda(abs($div['diferenca'])) ?>
            </div>
        <?php endforeach; ?>
        <div class="fs-small">
            Total pagamentos:
            <?php if (abs($diferencaPagamentos) < 0.005): ?>
                divergencias compensadas
            <?php else: ?>
                <?= $diferencaPagamentos > 0 ? 'SOBRA' : 'FALTA' ?>
                <?= caixa_fmt_moeda(abs($diferencaPagamentos)) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="hr"></div>
    <div class="fw-bold mb-1">TOTAIS</div>
    
    <div class="flex">
        <span class="fw-bold">DINHEIRO CONF.</span>
        <span class="fw-bold"><?= caixa_fmt_moeda($dinheiroInformado) ?></span>
    </div>
    <div class="flex">
        <span class="fw-bold">SUPRIMENTO</span>
        <span class="fw-bold"><?= caixa_fmt_moeda($summary['suprimento'] ?? 0) ?></span>
    </div>
    <div class="flex">
        <span class="fw-bold">SANGRIA</span>
        <span class="fw-bold"><?= caixa_fmt_moeda($summary['sangria'] ?? 0) ?></span>
    </div>
    <div class="flex">
        <span class="fw-bold">SALDO GAVETA</span>
        <span class="fw-bold"><?= caixa_fmt_moeda($saldoFinalInformado) ?></span>
    </div>
    <?php if (abs($diferencaGaveta) >= 0.005): ?>
    <div class="flex fs-small">
        <span>Esperado sistema</span>
        <span><?= caixa_fmt_moeda($saldoFinalSistema) ?></span>
    </div>
    <?php endif; ?>

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
