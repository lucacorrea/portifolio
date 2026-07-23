<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Fiscal/Storage/FiscalCertificateStorage.php';
require dirname(__DIR__) . '/src/Fiscal/Service/FiscalConfigurationService.php';
require dirname(__DIR__) . '/src/Company/Service/CompanySettingsService.php';

use App\Company\Service\CompanySettingsService;
use App\Fiscal\Service\FiscalConfigurationService;

function fiscal_config_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function fiscal_config_assert_throws(callable $callback, string $expectedMessage, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        fiscal_config_assert($exception->getMessage() === $expectedMessage, $message . ' Mensagem inesperada.');
        return;
    }
    throw new RuntimeException($message);
}

fiscal_config_assert(FiscalConfigurationService::environment(null) === 'homologacao', 'Homologação deve ser o ambiente padrão.');
fiscal_config_assert(FiscalConfigurationService::cnpj('04.252.011/0001-10') === '04252011000110', 'CNPJ válido deve ser normalizado.');
fiscal_config_assert(FiscalConfigurationService::isValidIbgeCityCode('1302603', 'AM'), 'Código IBGE de Manaus deve ser aceito para AM.');
fiscal_config_assert(!FiscalConfigurationService::isValidIbgeCityCode('3550308', 'AM'), 'Código IBGE de outra UF deve ser rejeitado.');
fiscal_config_assert(FiscalConfigurationService::isValidStateRegistration('04.145.871-0', 'AM'), 'IE válida do Amazonas deve ser aceita.');
fiscal_config_assert(!FiscalConfigurationService::isValidStateRegistration('04.145.871-8', 'AM'), 'Dígito inválido da IE deve ser rejeitado.');

$companyReflection = new ReflectionClass(CompanySettingsService::class);
$companySettings = $companyReflection->newInstanceWithoutConstructor();
$companyDocument = $companyReflection->getMethod('document');
fiscal_config_assert(
    $companyDocument->invoke($companySettings, '04.252.011/0001-10') === '04252011000110',
    'O CNPJ da empresa deve ser validado e persistido sem máscara.'
);
fiscal_config_assert(
    $companyDocument->invoke($companySettings, '04252011000110') === '04252011000110',
    'O CNPJ sem máscara também deve ser aceito.'
);
fiscal_config_assert(
    $companyDocument->invoke($companySettings, '529.982.247-25') === '52998224725',
    'O CPF da empresa deve ser validado e persistido sem máscara.'
);
fiscal_config_assert(
    $companyDocument->invoke($companySettings, '') === null,
    'O documento opcional ausente deve permanecer nulo.'
);
fiscal_config_assert_throws(
    fn() => $companyDocument->invoke($companySettings, '04.252.011/0001-11'),
    'Informe um CNPJ válido para a empresa.',
    'Um CNPJ com dígito verificador inválido não pode ser salvo.'
);

$companyCnpj = (new ReflectionClass(FiscalConfigurationService::class))->getMethod('companyCnpj');
fiscal_config_assert_throws(
    fn() => $companyCnpj->invoke(null, ''),
    'Cadastre um CNPJ válido em Configurações > Dados da empresa antes de enviar o certificado.',
    'O upload deve orientar a correção do cadastro da empresa antes de processar o A1.'
);

$productionAccepted = true;
try { FiscalConfigurationService::environment('qualidade'); }
catch (InvalidArgumentException) { $productionAccepted = false; }
fiscal_config_assert(!$productionAccepted, 'Ambiente desconhecido deve ser rejeitado.');

echo "Fiscal configuration validation tests passed.\n";
