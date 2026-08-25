<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Fiscal/Tax/IbsCbsApplicabilityResolver.php';

use App\Fiscal\Tax\IbsCbsApplicabilityResolver;

function rtcAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$resolver = new IbsCbsApplicabilityResolver();

$homologacaoAm = $resolver->resolve(
    '2026-08-19',
    1,
    '65',
    'homologacao',
    'AM'
);

rtcAssert(
    $homologacaoAm['required'] === true,
    'Homologação AM CRT 1 deve incluir IBS/CBS.'
);

$producaoSimples2026 = $resolver->resolve(
    '2026-08-19',
    1,
    '65',
    'producao',
    'AM'
);

rtcAssert(
    $producaoSimples2026['required'] === false,
    'Produção CRT 1 em 2026 deve preservar o cronograma do Simples.'
);

$producaoSimples2027 = $resolver->resolve(
    '2027-01-01',
    1,
    '65',
    'producao',
    'AM'
);

rtcAssert(
    $producaoSimples2027['required'] === true,
    'Produção CRT 1 deve incluir IBS/CBS em 2027.'
);

echo "IbsCbsApplicabilityResolverTest: OK\n";
