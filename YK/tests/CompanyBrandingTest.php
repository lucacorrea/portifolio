<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Company/Service/CompanyBranding.php';
require_once __DIR__ . '/../src/Company/Service/CompanyLogoStorage.php';

use App\Company\Service\CompanyBranding;
use App\Company\Service\CompanyLogoStorage;

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
company_branding_assert(
    str_replace('\\', '/', CompanyLogoStorage::resolveStorageRoot('/home/usuario/public_html/YK'))
        === '/home/usuario/configuracoes/yk/assets/img',
    'A logo deve ser armazenada fora do public_html, no diretório privado configuracoes/yk/assets/img.'
);

$storageTestRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yk-company-logo-' . bin2hex(random_bytes(6));
$storageTestFile = $storageTestRoot . DIRECTORY_SEPARATOR . '0123456789abcdef0123456789abcdef.png';
if (!mkdir($storageTestRoot, 0750) || file_put_contents($storageTestFile, 'test-image') === false) {
    throw new RuntimeException('Não foi possível preparar o teste de armazenamento da logo.');
}

try {
    $logoStorage = new CompanyLogoStorage($storageTestRoot);
    company_branding_assert(
        $logoStorage->resolve('empresa-logo.php?v=0123456789abcdef0123456789abcdef.png') === realpath($storageTestFile),
        'A referência válida deve resolver o arquivo diretamente no diretório privado.'
    );
    company_branding_assert(
        $logoStorage->resolve('empresa-logo.php?v=../0123456789abcdef0123456789abcdef.png') === null,
        'A resolução da logo deve rejeitar travessia de diretórios.'
    );
    company_branding_assert(
        $logoStorage->resolve('empresa-logo.php?v=0123456789abcdef0123456789abcdef.php') === null,
        'A resolução da logo deve rejeitar extensões não permitidas.'
    );
} finally {
    @unlink($storageTestFile);
    @rmdir($storageTestRoot);
}

echo "Company branding tests passed.\n";
