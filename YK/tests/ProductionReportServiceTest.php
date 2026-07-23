<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Report/Service/ProductionReportService.php';

use App\Report\Service\ProductionReportService;
function report_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

report_assert(
    ProductionReportService::brazilianMoneyToCents('R$ 11.000,00') === 1100000,
    'A meta em formato brasileiro deve ser convertida exatamente para centavos.'
);
report_assert(
    ProductionReportService::brazilianPercentageToUnits('5,25%') === 525,
    'O percentual brasileiro deve preservar duas casas decimais.'
);
report_assert(
    ProductionReportService::prizeCents(1200000, 500) === 60000,
    'A comissão de 5% deve incidir sobre todo o realizado após atingir a meta.'
);
report_assert(
    ProductionReportService::prizeCents(1, 5000) === 1,
    'O prêmio deve usar arredondamento comercial em centavos.'
);
report_assert(
    ProductionReportService::goalOutcome(1099999, 1100000, 500) === ['qualified' => false, 'prize_cents' => 0],
    'Produção abaixo da meta não deve gerar prêmio.'
);
report_assert(
    ProductionReportService::goalOutcome(1100000, 1100000, 500) === ['qualified' => true, 'prize_cents' => 55000],
    'Atingir exatamente a meta deve gerar prêmio sobre todo o realizado.'
);
report_assert(
    ProductionReportService::goalOutcome(1200000, 1100000, 500) === ['qualified' => true, 'prize_cents' => 60000],
    'Ultrapassar a meta deve gerar percentual sobre os R$ 12 mil completos.'
);

$invalid = false;
try {
    ProductionReportService::brazilianMoneyToCents('11.000,999');
} catch (\InvalidArgumentException) {
    $invalid = true;
}
report_assert($invalid, 'Valor monetário com mais de duas casas deve ser rejeitado.');

echo "Production report service tests passed.\n";
