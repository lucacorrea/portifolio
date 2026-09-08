<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Repositories/GovernanceAccessMatrixRepository.php',
    'app/Services/GovernanceAccessMatrixService.php',
    'api/governanca-acessos/matriz-acao.php',
    'frontend/modules/gestao-acessos/pages/matriz-acesso.php',
    'assets/js/modules/gestao-acessos-matrix.js',
    'assets/css/modules/gestao-acessos-matrix.css',
];

foreach ($requiredFiles as $relative) {
    if (!is_file($root . '/' . $relative)) {
        fwrite(STDERR, "FAIL missing {$relative}\n");
        exit(1);
    }
}

$service = file_get_contents($root . '/app/Services/GovernanceAccessMatrixService.php');
$repository = file_get_contents($root . '/app/Repositories/GovernanceAccessMatrixRepository.php');
$api = file_get_contents($root . '/api/governanca-acessos/matriz-acao.php');
$page = file_get_contents($root . '/frontend/modules/gestao-acessos/pages/matriz-acesso.php');
$js = file_get_contents($root . '/assets/js/modules/gestao-acessos-matrix.js');

$expectations = [
    [$service, "private const PROTECTED_LEVELS = ['administrador', 'suporte']", 'protected administrator/support levels'],
    [$service, "requirePermission(\$operator, 'governanca.permissoes')", 'dedicated governance permission'],
    [$service, "str_ends_with(\$slug, '.visualizar')", 'visualization dependency'],
    [$repository, 'replaceEditablePermissions', 'transactional replacement repository'],
    [$repository, 'incrementAuthorizationVersionsForLevel', 'authorization version invalidation'],
    [$repository, 'revokeActiveSessionsForLevel', 'session revocation'],
    [$api, "Csrf::validateAndRotate", 'csrf validation'],
    [$api, "'governance-access-matrix'", 'dedicated csrf scope'],
    [$page, 'data-governance-access-matrix', 'interactive matrix page'],
    [$page, 'permission_ids[]', 'permission checkbox fields'],
    [$js, 'data-matrix-view', 'view permission guard'],
    [$js, 'matriz-acao.php', 'real matrix API call'],
];

foreach ($expectations as [$content, $needle, $label]) {
    if (!is_string($content) || !str_contains($content, $needle)) {
        fwrite(STDERR, "FAIL {$label}\n");
        exit(1);
    }
}

if (str_contains($page, "'demo' => true")) {
    fwrite(STDERR, "FAIL matrix page must not be demo\n");
    exit(1);
}

fwrite(STDOUT, "PASS governance-access-matrix-test\n");
