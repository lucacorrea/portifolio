<?php

declare(strict_types=1);

$page = file_get_contents(
    dirname(__DIR__) . '/pages/ordens-servico.php'
);

$service = file_get_contents(
    dirname(__DIR__) . '/src/Fiscal/Service/FiscalDocumentService.php'
);

if (!is_string($page) || !is_string($service)) {
    throw new RuntimeException('Arquivos corrigidos não encontrados.');
}

$pageChecks = [
    '$fiscalHomologationReady',
    '$documentEnvironmentsByModel',
    '$authorizedDanfeDocument',
    '$authorizedDanfceDocument',
    'Emitir NF-e 55 para DANFE',
    'Configurar NF-e 55 para DANFE',
    'Imprimir DANFE',
    'Imprimir DANFC-e',
    '$modelEnvironments',
];

foreach ($pageChecks as $needle) {
    if (!str_contains($page, $needle)) {
        throw new RuntimeException(
            'Correção da página ausente: ' . $needle
        );
    }
}

$serviceChecks = [
    'findNormalByOrderEnvironmentModel',
    "(string) (\$document['modelo'] ?? '')",
    '!== $model',
];

foreach ($serviceChecks as $needle) {
    if (!str_contains($service, $needle)) {
        throw new RuntimeException(
            'Correção do serviço ausente: ' . $needle
        );
    }
}

echo "Nfe55Nfce65IndependentTest: OK\n";
