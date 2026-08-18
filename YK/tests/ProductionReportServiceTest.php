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

$decimalMethod = new ReflectionMethod(ProductionReportService::class, 'centsToDecimal');
$decimalMethod->setAccessible(true);
report_assert(
    $decimalMethod->invoke(null, -150) === '-1.50',
    'Saldo líquido negativo deve preservar sinal e casas decimais.'
);

$repositorySource = file_get_contents(dirname(__DIR__) . '/src/Report/Repository/ProductionReportRepository.php');
$serviceSource = file_get_contents(dirname(__DIR__) . '/src/Report/Service/ProductionReportService.php');
$pageSource = file_get_contents(dirname(__DIR__) . '/pages/relatorios.php');
$entrySource = file_get_contents(dirname(__DIR__) . '/relatorios.php');
$scriptSource = file_get_contents(dirname(__DIR__) . '/assets/js/relatorios.js');
report_assert(str_contains((string) $repositorySource, 'companyOrderDetails'), 'Empresa deve possuir detalhamento próprio sem duplicar OS por funcionário.');
report_assert(str_contains((string) $repositorySource, 'LIMIT 200'), 'Detalhamento empresarial deve possuir limite de segurança.');
report_assert(str_contains((string) $repositorySource, 'financialSummary'), 'Relatório completo deve separar fluxo financeiro da produção.');
report_assert(str_contains((string) $repositorySource, 'itemRanking'), 'Relatório deve consolidar serviços e peças utilizados.');
report_assert(str_contains((string) $serviceSource, "'financial' => false"), 'Serviço deve negar escopos de dados por padrão e liberá-los somente por permissão.');
report_assert(str_contains((string) $pageSource, "can('relatorio.financeiro')"), 'Valores empresariais devem exigir permissão financeira.');
report_assert(str_contains((string) $pageSource, "can('produto.visualizar_preco_custo')"), 'Custo do estoque deve exigir permissão específica.');
report_assert(str_contains((string) $pageSource, 'data-report-panel="empresa"'), 'A visão da empresa deve possuir painel independente.');
report_assert(str_contains((string) $pageSource, 'data-report-panel="funcionarios"'), 'A visão por funcionário deve possuir painel independente.');
$compactPageSource = preg_replace('/\s+/', '', (string) $pageSource);
report_assert(
    is_string($compactPageSource) && str_contains($compactPageSource, "\$activeView==='empresa'?'':'hidden'"),
    'O painel inativo deve usar o atributo hidden.'
);
report_assert(str_contains((string) $entrySource, 'assets/js/relatorios.js'), 'A página deve carregar o controlador das visões.');
report_assert(str_contains((string) $scriptSource, 'panel.hidden ='), 'O controlador deve alternar a visibilidade real dos painéis.');
echo "Production report service tests passed.\n";
