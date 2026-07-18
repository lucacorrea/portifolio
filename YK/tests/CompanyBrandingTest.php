<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Company/Service/CompanyBranding.php';

use App\Company\Service\CompanyBranding;

function company_branding_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

company_branding_assert(
    CompanyBranding::shortName('K. Yamaguchi Produtos e Serviços') === 'K. Yamaguchi Produtos',
    'O nome do menu deve conter somente as três primeiras palavras.'
);
company_branding_assert(
    CompanyBranding::shortName('   Empresa   Teste  ') === 'Empresa Teste',
    'O nome do menu deve normalizar os espaços.'
);
company_branding_assert(
    CompanyBranding::shortName('') === 'K. Yamaguchi',
    'O nome do menu deve usar o valor padrão quando estiver vazio.'
);
company_branding_assert(
    CompanyBranding::safeLogoUrl('empresa-logo.php?v=0123456789abcdef0123456789abcdef.png') !== null,
    'A referência interna da logo deve ser aceita.'
);
company_branding_assert(
    CompanyBranding::safeLogoUrl('https://example.com/logo.png') !== null,
    'Uma URL HTTPS legada válida deve continuar aceita.'
);
company_branding_assert(
    CompanyBranding::safeLogoUrl('javascript:alert(1)') === null,
    'Protocolos perigosos devem ser rejeitados.'
);
company_branding_assert(
    CompanyBranding::safeLogoUrl('../storage/uploads/logo.png') === null,
    'Caminhos com travessia devem ser rejeitados.'
);
company_branding_assert(
    CompanyBranding::safeLogoUrl('configuracoes.php') === null,
    'Uma rota local que não seja imagem deve ser rejeitada.'
);

echo "Company branding tests passed.\n";
