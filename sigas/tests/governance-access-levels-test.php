<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Repositories/GovernanceAccessLevelRepository.php',
    'app/Services/GovernanceAccessLevelService.php',
    'api/governanca-acessos/nivel-acao.php',
    'frontend/modules/gestao-acessos/pages/perfis.php',
    'assets/js/modules/gestao-acessos-levels.js',
];

foreach ($requiredFiles as $relative) {
    if (!is_file($root . '/' . $relative)) {
        fwrite(STDERR, "FAIL missing {$relative}\n");
        exit(1);
    }
}

$repository = file_get_contents($root . '/app/Repositories/GovernanceAccessLevelRepository.php');
$service = file_get_contents($root . '/app/Services/GovernanceAccessLevelService.php');
$api = file_get_contents($root . '/api/governanca-acessos/nivel-acao.php');
$page = file_get_contents($root . '/frontend/modules/gestao-acessos/pages/perfis.php');
$js = file_get_contents($root . '/assets/js/modules/gestao-acessos-levels.js');

$expectations = [
    [$service, "private const PROTECTED_LEVELS = ['administrador', 'suporte']", 'protected administrator/support levels'],
    [$service, "requirePermission(\$operator, 'governanca.perfis')", 'dedicated governance permission'],
    [$service, 'uniqueSlug', 'automatic immutable slug generation'],
    [$service, 'nivel_acesso_inativado', 'level deactivation audit'],
    [$repository, 'incrementAuthorizationVersionsForLevel', 'authorization invalidation'],
    [$repository, 'revokeActiveSessionsForLevel', 'session revocation'],
    [$api, "Csrf::validateAndRotate", 'csrf validation'],
    [$api, "'governance-access-level'", 'dedicated csrf scope'],
    [$page, 'data-governance-levels', 'interactive levels page'],
    [$page, 'Somente consulta', 'protected levels readonly UI'],
    [$page, 'O identificador é imutável', 'immutable slug UX'],
    [$js, 'nivel-acao.php', 'real level API call'],
    [$js, 'Impacto imediato', 'deactivation impact warning'],
];

foreach ($expectations as [$content, $needle, $label]) {
    if (!is_string($content) || !str_contains($content, $needle)) {
        fwrite(STDERR, "FAIL {$label}\n");
        exit(1);
    }
}

if (preg_match('/function\s+(delete|remove)\s*\(/i', (string) $repository) === 1) {
    fwrite(STDERR, "FAIL access levels must not support physical deletion\n");
    exit(1);
}

if (str_contains((string) $page, "'demo' => true")) {
    fwrite(STDERR, "FAIL levels page must not be demo\n");
    exit(1);
}

fwrite(STDOUT, "PASS governance-access-levels-test\n");
