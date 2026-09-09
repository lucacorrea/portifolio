<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Repositories/GovernanceSectorRepository.php',
    'app/Services/GovernanceSectorService.php',
    'api/governanca-acessos/setor-acao.php',
    'frontend/modules/gestao-acessos/pages/setores.php',
    'assets/js/modules/gestao-acessos-sectors.js',
];

foreach ($requiredFiles as $relative) {
    if (!is_file($root . '/' . $relative)) {
        fwrite(STDERR, "FAIL missing {$relative}\n");
        exit(1);
    }
}

$repository = file_get_contents($root . '/app/Repositories/GovernanceSectorRepository.php');
$service = file_get_contents($root . '/app/Services/GovernanceSectorService.php');
$api = file_get_contents($root . '/api/governanca-acessos/setor-acao.php');
$page = file_get_contents($root . '/frontend/modules/gestao-acessos/pages/setores.php');
$js = file_get_contents($root . '/assets/js/modules/gestao-acessos-sectors.js');
$auth = file_get_contents($root . '/app/Services/AuthService.php');

$expectations = [
    [$service, "private const PROTECTED_SECTORS = ['administracao-sistema', 'ti-suporte']", 'protected structural sectors'],
    [$service, "requirePermission(\$operator, 'governanca.setores')", 'dedicated governance sector permission'],
    [$service, 'configuredSectorIds', 'legacy fallback compatibility'],
    [$service, 'replaceModuleRules', 'real module matrix update'],
    [$repository, 'incrementAuthorizationVersionsForSector', 'authorization invalidation'],
    [$repository, 'revokeActiveSessionsForSector', 'session revocation'],
    [$repository, 'permitido = VALUES(permitido)', 'explicit allow and deny module rules'],
    [$api, "Csrf::validateAndRotate", 'csrf validation'],
    [$api, "'governance-sector'", 'dedicated csrf scope'],
    [$page, 'data-governance-sectors', 'real sector page'],
    [$page, 'name="modulos[]"', 'module checkbox fields'],
    [$js, 'setor-acao.php', 'real sector API call'],
    [$auth, 'assertActiveSector($user)', 'active sector login validation'],
    [$auth, 'hasActiveSector($user)', 'active sector session validation'],
    [$auth, 'AccessLevelSlug::ADMINISTRATOR', 'global administrator sector bypass'],
    [$auth, 'AccessLevelSlug::SUPPORT', 'global support sector bypass'],
];

foreach ($expectations as [$content, $needle, $label]) {
    if (!is_string($content) || !str_contains($content, $needle)) {
        fwrite(STDERR, "FAIL {$label}\n");
        exit(1);
    }
}

if (str_contains($page, "'demo' => true")) {
    fwrite(STDERR, "FAIL sector page must not be demo\n");
    exit(1);
}

foreach ([$repository, $service, $api] as $content) {
    if (is_string($content) && preg_match('/\bDELETE\s+FROM\s+setores\b/i', $content) === 1) {
        fwrite(STDERR, "FAIL physical sector deletion is forbidden\n");
        exit(1);
    }
}

if (is_string($repository) && preg_match('/UPDATE\s+setores\s+SET[^;]*slug\s*=/is', $repository) === 1) {
    fwrite(STDERR, "FAIL sector slug must remain immutable\n");
    exit(1);
}

fwrite(STDOUT, "PASS governance-sectors-test\n");
