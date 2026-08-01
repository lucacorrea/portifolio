<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Config\ModuleRegistry;

$failures = [];
$root = dirname(__DIR__);
$login = file_get_contents($root . '/index.php') ?: '';
$portal = file_get_contents($root . '/portal.php') ?: '';
$portalScript = file_get_contents($root . '/assets/js/module-portal.js') ?: '';
$registry = ModuleRegistry::all();

function assert_portal_flow(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures[] = $message;
    }
}

$expected = [
    'planejamento-gestao' => 'Planejamento e Gestão',
    'vigilancia-socioassistencial' => 'Vigilância Socioassistencial',
    'protecao-social-basica' => 'Proteção Social Básica',
    'protecao-social-especial' => 'Proteção Social Especial',
    'comida-mesa' => 'Coari Comida na Mesa',
    'primeiro-emprego' => 'Coari Meu Primeiro Emprego',
];

assert_portal_flow(count($registry) === 6, 'registro central deve possuir exatamente seis ambientes');
assert_portal_flow(array_keys($registry) === array_keys($expected), 'ordem e chaves dos seis ambientes devem permanecer estáveis');

foreach ($expected as $key => $name) {
    assert_portal_flow(($registry[$key]['name'] ?? null) === $name, "nome incorreto para {$key}");
    assert_portal_flow(!empty($registry[$key]['home']), "página inicial ausente para {$key}");
    assert_portal_flow(!empty($registry[$key]['items']), "menu ausente para {$key}");
}

assert_portal_flow(
    substr_count($login, "header('Location: portal.php');") === 2,
    'sessão existente e login bem-sucedido devem redirecionar ao portal'
);
assert_portal_flow(
    !str_contains($login, "header('Location: dashboard.php');"),
    'index.php não deve mais redirecionar ao dashboard'
);
assert_portal_flow(
    str_contains($portal, '<?= PageContext::script($frontendContext) ?>'),
    'portal deve injetar o contexto diretamente'
);
assert_portal_flow(
    !str_contains($portal, '<script><?= PageContext::script($frontendContext) ?></script>'),
    'portal não deve aninhar tags script'
);
assert_portal_flow(
    str_contains($portal, 'module-portal.js?v='),
    'script do portal deve possuir versão para invalidar cache publicado'
);
assert_portal_flow(
    str_contains($portalScript, 'Object.entries(environments)'),
    'cards devem ser renderizados a partir do registro central'
);

if ($failures === []) {
    echo 'PASS portal-auth-flow-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}

echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
