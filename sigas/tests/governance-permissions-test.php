<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Repositories/GovernancePermissionRepository.php',
    'app/Services/GovernancePermissionService.php',
    'api/governanca-acessos/permissao-acao.php',
    'frontend/modules/gestao-acessos/permissions-bootstrap.php',
    'frontend/modules/gestao-acessos/pages/permissoes.php',
    'assets/js/modules/gestao-acessos-permissions.js',
];

foreach ($requiredFiles as $relative) {
    if (!is_file($root . '/' . $relative)) {
        fwrite(STDERR, "FAIL missing {$relative}\n");
        exit(1);
    }
}

$service = file_get_contents($root . '/app/Services/GovernancePermissionService.php');
$repository = file_get_contents($root . '/app/Repositories/GovernancePermissionRepository.php');
$api = file_get_contents($root . '/api/governanca-acessos/permissao-acao.php');
$page = file_get_contents($root . '/frontend/modules/gestao-acessos/pages/permissoes.php');
$js = file_get_contents($root . '/assets/js/modules/gestao-acessos-permissions.js');

$expectations = [
    [$service, "requirePermission(\$operator, 'governanca.permissoes')", 'dedicated governance permission'],
    [$service, "private const PROTECTED_SLUGS", 'protected governance permissions'],
    [$service, "Permissões internas ou legadas são somente leitura", 'legacy permissions read-only'],
    [$service, "'slug' => (string) \$current['slug']", 'slug preserved on update'],
    [$service, "'modulo' => (string) \$current['modulo']", 'module preserved on update'],
    [$repository, 'incrementAuthorizationVersionsForPermission', 'authorization invalidation'],
    [$repository, 'revokeActiveSessionsForPermission', 'session revocation'],
    [$api, "Csrf::validateAndRotate", 'csrf validation'],
    [$api, "'governance-permission'", 'dedicated csrf scope'],
    [$page, 'data-governance-permissions', 'interactive permissions page'],
    [$page, 'Somente leitura', 'legacy read-only UI'],
    [$js, 'permissao-acao.php', 'real permission API call'],
];

foreach ($expectations as [$content, $needle, $label]) {
    if (!is_string($content) || !str_contains($content, $needle)) {
        fwrite(STDERR, "FAIL {$label}\n");
        exit(1);
    }
}

if (preg_match('/function\s+delete\s*\(/i', (string) $repository) === 1
    || preg_match('/DELETE\s+FROM\s+permissoes/i', (string) $repository) === 1) {
    fwrite(STDERR, "FAIL permissions must not support physical deletion\n");
    exit(1);
}

if (str_contains((string) $page, "'demo' => true")) {
    fwrite(STDERR, "FAIL permissions page must not be demo\n");
    exit(1);
}

fwrite(STDOUT, "PASS governance-permissions-test\n");
