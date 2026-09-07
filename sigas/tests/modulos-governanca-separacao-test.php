<?php

declare(strict_types=1);

$failures = [];
$root = dirname(__DIR__);

$accessService = file_get_contents($root . '/app/Services/ModuleAccessService.php') ?: '';
$matrixPage = file_get_contents($root . '/frontend/modules/gestao-acessos/pages/matriz-acesso.php') ?: '';
$permissionsPage = file_get_contents($root . '/frontend/modules/gestao-acessos/pages/permissoes.php') ?: '';
$portal = file_get_contents($root . '/portal.php') ?: '';

function assert_module_governance(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures[] = $message;
    }
}

$requiredModulePermissions = [
    "'kit-maternidade' => 'kit_maternidade.visualizar'",
    "'aluguel-social' => 'aluguel_social.visualizar'",
    "'beneficios-eventuais' => 'beneficios_eventuais.visualizar'",
    "'comida-mesa' => 'comida_mesa.visualizar'",
    "'primeiro-emprego' => 'primeiro_emprego.visualizar'",
];

foreach ($requiredModulePermissions as $permissionMapping) {
    assert_module_governance(
        str_contains($accessService, $permissionMapping),
        'controle mínimo ausente em ModuleAccessService: ' . $permissionMapping
    );
}

assert_module_governance(
    str_contains($accessService, "if (\$module === 'gestao-acessos')"),
    'Governança deve permanecer restrita a Administrador/Suporte'
);

$matrixColumns = [
    'modulo_kit_maternidade',
    'modulo_aluguel_social',
    'modulo_beneficios_eventuais',
    'modulo_governanca',
    'modulo_comida_mesa',
    'modulo_primeiro_emprego',
];

foreach ($matrixColumns as $column) {
    assert_module_governance(
        str_contains($matrixPage, "'{$column}'"),
        'módulo ausente da matriz principal: ' . $column
    );
}

assert_module_governance(
    str_contains($matrixPage, "'value' => '6'"),
    'matriz deve declarar seis módulos operacionais'
);

assert_module_governance(
    str_contains($permissionsPage, 'Permissões internas e legadas preservadas'),
    'permissões antigas devem permanecer visíveis em bloco separado'
);

foreach ([
    'Planejamento e Gestão',
    'Vigilância Socioassistencial',
    'Proteção Social Básica',
    'Proteção Social Especial',
] as $legacyPortalCard) {
    assert_module_governance(
        !str_contains($portal, '<h3>' . $legacyPortalCard . '</h3>'),
        'portal não deve renderizar card setorial legado: ' . $legacyPortalCard
    );
}

if ($failures === []) {
    echo 'PASS modulos-governanca-separacao-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}

echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
