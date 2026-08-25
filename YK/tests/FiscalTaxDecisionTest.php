<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

use App\Fiscal\Tax\FiscalIdentityValidator;
use App\Fiscal\Tax\GtinValidator;
use App\Fiscal\Tax\IbsCbsApplicabilityResolver;
use App\Fiscal\Service\FiscalProductionGate;

function fiscalTaxAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$resolver = new IbsCbsApplicabilityResolver();
$ruleMetadata = $resolver->resolve('2026-08-03', 3, '55', 'producao');
fiscalTaxAssert(
    $ruleMetadata['rule_version'] === 'NT_2025_002_V1_50'
    && str_contains($ruleMetadata['source'], 'NT 2025.002 v1.50'),
    'O snapshot deve registrar a versão vigente da regra IBS/CBS.'
);
fiscalTaxAssert(
    !$resolver->resolve('2026-08-02', 3, '55', 'producao')['required'],
    'CRT 3 não deve antecipar a vigência de produção.'
);
foreach (['55', '65'] as $model) {
    fiscalTaxAssert(
        $resolver->resolve('2026-08-03', 3, $model, 'producao')['required'],
        'CRT 3 deve exigir IBS/CBS nos modelos 55 e 65 desde 03/08/2026 em produção.'
    );
    fiscalTaxAssert(
        !$resolver->resolve('2026-08-17', 1, $model, 'producao')['required'],
        'Simples Nacional não deve ser obrigado a informar IBS/CBS em agosto de 2026.'
    );
    fiscalTaxAssert(
        $resolver->resolve('2027-01-01', 1, $model, 'producao')['required'],
        'Simples Nacional deve entrar na vigência legal de 2027.'
    );
}
fiscalTaxAssert(
    $resolver->resolve('2026-07-01', 3, '55', 'homologacao')['required'],
    'A matriz de homologação CRT 3 deve ser independente da produção.'
);
$resolver->assertCatalogRuleSupports([
    'calculation_mode' => 'standard',
    'indicators' => ['ind_gIBSCBS' => 1, 'indNFe' => 1, 'indNFCe' => 1],
], '65');

$blocked = false;
try {
    $resolver->assertCatalogRuleSupports([
        'calculation_mode' => 'standard',
        'indicators' => ['ind_gIBSCBS' => 1, 'indNFe' => 1, 'indNFCe' => 0],
    ], '65');
} catch (InvalidArgumentException) {
    $blocked = true;
}
fiscalTaxAssert($blocked, 'Classificação proibida para NFC-e deve falhar fechada.');

fiscalTaxAssert(GtinValidator::normalize('7894900011517') === '7894900011517', 'GTIN-13 válido deve ser preservado.');
fiscalTaxAssert(GtinValidator::normalize('7894900011518') === 'SEM GTIN', 'GTIN com dígito inválido deve virar SEM GTIN.');
fiscalTaxAssert(GtinValidator::normalize('') === 'SEM GTIN', 'Produto sem GTIN deve usar literal oficial.');
fiscalTaxAssert(FiscalIdentityValidator::isValidCpfOrCnpj('123.456.789-09'), 'CPF válido deve ser aceito.');
fiscalTaxAssert(FiscalIdentityValidator::isValidCpfOrCnpj('14.171.052/0001-35'), 'CNPJ válido deve ser aceito.');
fiscalTaxAssert(!FiscalIdentityValidator::isValidCpfOrCnpj('111.111.111-11'), 'CPF repetido deve ser rejeitado.');

$gate = FiscalProductionGate::evaluate('65', [
    'ambiente' => 'producao', 'modelo' => '65', 'qr_code_versao' => 3,
], [
    'runtime_production_enabled' => true,
    'schema_compatible' => true,
    'production_status_service' => true,
    'authorized_homologation' => true,
]);
fiscalTaxAssert($gate['allowed'], 'Gate completo deve liberar a ativação de produção.');
$blockedGate = FiscalProductionGate::evaluate('65', [
    'ambiente' => 'producao', 'modelo' => '65', 'qr_code_versao' => 2,
], [
    'runtime_production_enabled' => true,
    'schema_compatible' => false,
    'production_status_service' => true,
    'authorized_homologation' => false,
]);
fiscalTaxAssert(!$blockedGate['allowed'], 'Gate deve bloquear sem homologação e com QR legado.');

echo "FiscalTaxDecisionTest: OK\n";
