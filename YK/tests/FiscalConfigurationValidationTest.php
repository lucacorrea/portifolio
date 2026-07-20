<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Fiscal/Storage/FiscalCertificateStorage.php';
require dirname(__DIR__) . '/src/Fiscal/Service/FiscalConfigurationService.php';

use App\Fiscal\Service\FiscalConfigurationService;

function fiscal_config_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

fiscal_config_assert(FiscalConfigurationService::environment(null) === 'homologacao', 'Homologação deve ser o ambiente padrão.');
fiscal_config_assert(FiscalConfigurationService::cnpj('04.252.011/0001-10') === '04252011000110', 'CNPJ válido deve ser normalizado.');
fiscal_config_assert(FiscalConfigurationService::isValidIbgeCityCode('1302603', 'AM'), 'Código IBGE de Manaus deve ser aceito para AM.');
fiscal_config_assert(!FiscalConfigurationService::isValidIbgeCityCode('3550308', 'AM'), 'Código IBGE de outra UF deve ser rejeitado.');
fiscal_config_assert(FiscalConfigurationService::isValidStateRegistration('04.145.871-0', 'AM'), 'IE válida do Amazonas deve ser aceita.');
fiscal_config_assert(!FiscalConfigurationService::isValidStateRegistration('04.145.871-8', 'AM'), 'Dígito inválido da IE deve ser rejeitado.');

$productionAccepted = true;
try { FiscalConfigurationService::environment('qualidade'); }
catch (InvalidArgumentException) { $productionAccepted = false; }
fiscal_config_assert(!$productionAccepted, 'Ambiente desconhecido deve ser rejeitado.');

echo "Fiscal configuration validation tests passed.\n";
