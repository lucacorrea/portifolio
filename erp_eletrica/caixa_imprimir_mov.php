<?php
require_once 'config.php';
require_once 'autoloader.php';

use App\Services\AuthService;

AuthService::checkPermission('caixa', 'visualizar');

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID da movimentação não fornecido.");
}

$db = \App\Config\Database::getInstance()->getConnection();

// Fetch movement details with branch info
$stmt = $db->prepare("
    SELECT cm.*, u.nome as operador_nome, c.id as caixa_id, f.nome as filial_nome
    FROM caixa_movimentacoes cm
    JOIN caixas c ON cm.caixa_id = c.id
    JOIN usuarios u ON cm.operador_id = u.id
    JOIN filiais f ON c.filial_id = f.id
    WHERE cm.id = ?
");
$stmt->execute([$id]);
$mov = $stmt->fetch();

if (!$mov) {
    die("Movimentação não encontrada.");
}

$tipoLabel = ($mov['tipo'] === 'suprimento') ? 'Suprimento' : 'Sangria';
$dataHora = date('d/m/Y H:i:s', strtotime($mov['created_at']));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de <?= $tipoLabel ?> #<?= $id ?></title>
    <style>
        @page { size: 72mm auto; margin: 0; }
        body { 
            font-family: 'Courier New', Courier, monospace; 
            width: 72mm; 
            margin: 0 auto; 
            padding: 3mm 2mm; 
            font-size: 12px; 
            line-height: 1.15;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .hr { border-top: 1px dashed #000; margin: 4px 0; }
        .mb-1 { margin-bottom: 2px; }
        .flex { display: flex; justify-content: space-between; align-items: baseline; }
        .fs-large { font-size: 14px; }
        .fs-small { font-size: 11px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; font-weight: bold;">IMPRIMIR AGORA</button>
    </div>

    <header class="text-center">
        <div class="fw-bold fs-large"><?= strtoupper(htmlspecialchars($mov['filial_nome'])) ?></div>
    </header>

    <div class="hr"></div>
    <div class="text-center fw-bold fs-large"><?= strtoupper($tipoLabel) ?></div>
    <div class="hr"></div>

    <div class="flex">
        <span>Data:</span>
        <span class="fw-bold"><?= $dataHora ?></span>
    </div>
    <div class="flex">
        <span>Operador(a):</span>
        <span class="fw-bold"><?= strtoupper(htmlspecialchars($mov['operador_nome'])) ?></span>
    </div>

    <div class="hr"></div>

    <div class="flex fs-large">
        <span class="fw-bold">Valor:</span>
        <span class="fw-bold">R$ <?= number_format($mov['valor'], 2, ',', '.') ?></span>
    </div>
    <div class="flex" style="margin-top: 4px; align-items: flex-start;">
        <span class="fw-bold">Motivo:</span>
        <span style="max-width: 70%; text-align: right;"><?= htmlspecialchars($mov['motivo'] ?: 'NÃO INFORMADO') ?></span>
    </div>

    <script>
        window.onload = function() {
            window.print();
            // Close window after print if it was opened via window.open
            window.onafterprint = function() {
                // window.close();
            };
        }
    </script>
</body>
</html>
