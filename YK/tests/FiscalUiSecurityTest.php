<?php

declare(strict_types=1);

function fiscalUiAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
require_once $root . '/src/Access/DTO/AuthenticatedUser.php';
require_once $root . '/src/Access/Service/AuthenticationService.php';
require_once $root . '/src/Access/Service/AuthorizationService.php';

$authenticationReflection = new ReflectionClass(App\Access\Service\AuthenticationService::class);
$authentication = $authenticationReflection->newInstanceWithoutConstructor();
$currentUserProperty = $authenticationReflection->getProperty('currentUser');
$resolvedProperty = $authenticationReflection->getProperty('currentUserResolved');
$resolvedProperty->setValue($authentication, true);
$authorization = new App\Access\Service\AuthorizationService($authentication);

$currentUserProperty->setValue($authentication, new App\Access\DTO\AuthenticatedUser(
    1,
    1,
    'Administrador',
    'Administrador operacional',
    'admin',
    'admin@example.invalid',
    ['nota_fiscal.ativar_producao']
));
fiscalUiAssert(
    !$authorization->can('nota_fiscal.ativar_producao'),
    'Administrador operacional não pode ativar emissão fiscal em produção.'
);

$currentUserProperty->setValue($authentication, new App\Access\DTO\AuthenticatedUser(
    2,
    2,
    'Dono',
    'Dono',
    'dono',
    'dono@example.invalid',
    ['nota_fiscal.ativar_producao']
));
fiscalUiAssert(
    $authorization->can('nota_fiscal.ativar_producao'),
    'Dono explicitamente autorizado deve poder ativar emissão fiscal em produção.'
);

$permissionRepository = file_get_contents($root . '/src/Access/Repository/PermissionRepository.php');
fiscalUiAssert(
    is_string($permissionRepository)
    && str_contains($permissionRepository, "codigo <> 'nota_fiscal.ativar_producao'"),
    'A sincronização do perfil protegido não pode reatribuir ativação de produção ao Administrador.'
);

$actions = [
    'configuracao-fiscal-certificado-salvar.php' => 'nota_fiscal.gerenciar_credenciais',
];
foreach ($actions as $file => $permission) {
    $source = file_get_contents($root . '/actions/' . $file);
    fiscalUiAssert(is_string($source), 'A ação fiscal deve existir.');
    fiscalUiAssert(str_contains($source, 'os_require_post_request()'), 'A ação fiscal deve aceitar apenas POST.');
    fiscalUiAssert(
        preg_match('/os_action_context\(\s*[\'\"]' . preg_quote($permission, '/') . '[\'\"]\s*\)/', $source) === 1,
        'A ação fiscal deve exigir sua permissão específica e CSRF.'
    );
    fiscalUiAssert(!str_contains($source, "error_log('" . '$exception->getMessage()'), 'Erros fiscais não podem registrar detalhes potencialmente sensíveis.');
}

foreach ([
    'configuracao-fiscal-salvar.php',
    'configuracao-fiscal-serie-salvar.php',
    'configuracao-fiscal-ativar.php',
    'configuracao-fiscal-testar-sefaz.php',
] as $file) {
    $source = file_get_contents($root . '/actions/' . $file);
    fiscalUiAssert(is_string($source) && str_contains($source, 'os_require_post_request()'), 'Ação fiscal deve aceitar apenas POST.');
    fiscalUiAssert(
        is_string($source)
        && str_contains($source, 'nota_fiscal.ativar_producao')
        && (str_contains($source, 'nota_fiscal.configurar') || str_contains($source, 'nota_fiscal.testar_integracao')),
        'Ação fiscal deve separar a permissão de produção.'
    );
}
$inutilizationAction = file_get_contents($root . '/actions/nota-fiscal-inutilizar.php');
fiscalUiAssert(
    is_string($inutilizationAction)
    && str_contains($inutilizationAction, 'os_require_post_request()')
    && preg_match('/os_action_context\(\s*[\'\"]nota_fiscal\.inutilizar[\'\"]\s*\)/', $inutilizationAction) === 1
    && str_contains($inutilizationAction, "requirePermission('nota_fiscal.ativar_producao')"),
    'Inutilização deve exigir POST, sua permissão específica e autorização adicional em produção.'
);

foreach ([
    'nfse-preparar.php'=>'nfse.emitir',
    'nfse-transmitir.php'=>'nfse.emitir',
    'nfse-reconsultar.php'=>'nfse.reconsultar',
    'nfse-cancelar.php'=>'nfse.cancelar',
    'nfse-substituir.php'=>'nfse.substituir',
] as $file=>$permission) {
    $source = file_get_contents($root . '/actions/' . $file);
    fiscalUiAssert(is_string($source) && str_contains($source, 'os_require_post_request()'), 'Ação NFS-e deve aceitar apenas POST.');
    fiscalUiAssert(
        is_string($source) && preg_match('/os_action_context\(\s*[\'\"]' . preg_quote($permission, '/') . '[\'\"]\s*\)/', $source) === 1,
        'Ação NFS-e deve exigir sua permissão específica e CSRF.'
    );
}

$page = file_get_contents($root . '/pages/configuracoes-fiscais.php');
fiscalUiAssert(is_string($page) && str_contains($page, 'autocomplete="new-password"'), 'Segredos fiscais devem usar campos sem recuperação automática.');
fiscalUiAssert(!str_contains($page, "['csc_ciphertext']") && !str_contains($page, "['senha_ciphertext']"), 'A tela nunca pode renderizar material cifrado.');
fiscalUiAssert(str_contains($page, 'Testar comunicação com a SEFAZ'), 'A configuração deve expor o teste real de homologação.');
fiscalUiAssert(str_contains($page, "\$integrationTest['success']"), 'A ativação deve depender do último teste SEFAZ bem-sucedido.');
fiscalUiAssert(
    str_contains($page, "\$selectedEnvironment === 'producao' && !\$canActivateProduction"),
    'Metadados de produção não podem ser carregados sem a permissão específica.'
);
$certificateScript = file_get_contents($root . '/assets/js/configuracoes-fiscais.js');
fiscalUiAssert(
    is_string($certificateScript) && str_contains($certificateScript, 'AbortController'),
    'O envio do certificado deve encerrar o carregamento quando o servidor não responder.'
);

$billing = file_get_contents($root . '/pages/faturamento.php');
fiscalUiAssert(is_string($billing) && str_contains($billing, 'A tela não simula notas fiscais.'), 'Faturamento não deve apresentar notas fictícias como reais.');

$htaccess = file_get_contents($root . '/.htaccess');
fiscalUiAssert(is_string($htaccess) && str_contains($htaccess, 'RewriteCond %{HTTPS} !=on'), 'O sistema deve redirecionar HTTP para HTTPS.');
fiscalUiAssert(str_contains((string) $htaccess, 'Strict-Transport-Security'), 'HTTPS deve publicar HSTS.');
fiscalUiAssert(str_contains((string) $htaccess, 'frame-ancestors \'none\''), 'Telas sensíveis devem bloquear incorporação em frames.');

echo "Fiscal UI security tests passed.\n";
