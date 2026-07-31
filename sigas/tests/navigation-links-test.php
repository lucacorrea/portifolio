<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Config\ModuleRegistry;

$failures = [];
$root = dirname(__DIR__);
$frontendRoot = $root . '/frontend/modules/';
$registry = ModuleRegistry::all();
$usedViews = [];

function navigation_fail(string $message): void
{
    global $failures;
    $failures[] = $message;
}

function navigation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        navigation_fail($message);
    }
}

navigation_assert(count($registry) === 6, 'o portal deve possuir seis ambientes');

foreach ($registry as $environmentKey => $environment) {
    foreach (['key', 'name', 'kind', 'icon', 'theme', 'home_page', 'home', 'pages', 'assets'] as $field) {
        navigation_assert(isset($environment[$field]) && $environment[$field] !== '', "{$environmentKey}: campo {$field} ausente");
    }

    navigation_assert(($environment['key'] ?? null) === $environmentKey, "{$environmentKey}: chave interna divergente");
    navigation_assert(isset($environment['pages'][$environment['home_page']]), "{$environmentKey}: página inicial não registrada");
    navigation_assert(count(array_filter($environment['pages'], static fn (array $page): bool => $page['mobile'] ?? false)) <= 4, "{$environmentKey}: mais de quatro itens prioritários no mobile");

    foreach (['css', 'js'] as $assetType) {
        $asset = $environment['assets'][$assetType] ?? '';
        navigation_assert($asset !== '' && is_file($root . '/' . $asset), "{$environmentKey}: asset {$assetType} ausente");
    }

    $usedHrefs = [];

    foreach ($environment['pages'] as $pageKey => $page) {
        foreach (['key', 'label', 'icon', 'page', 'href', 'target', 'mobile'] as $field) {
            navigation_assert(array_key_exists($field, $page) && $page[$field] !== '', "{$environmentKey}/{$pageKey}: campo {$field} ausente");
        }

        navigation_assert(($page['key'] ?? null) === $pageKey, "{$environmentKey}/{$pageKey}: chave divergente");
        navigation_assert(($page['page'] ?? null) === $pageKey, "{$environmentKey}/{$pageKey}: página ativa divergente");
        navigation_assert(!isset($usedHrefs[$page['href']]), "{$environmentKey}/{$pageKey}: endereço duplicado");
        $usedHrefs[$page['href']] = true;
        navigation_assert(!preg_match('/\A(?:https?:)?\/\//i', (string) $page['href']), "{$environmentKey}/{$pageKey}: URL externa não permitida");

        if (($page['target'] ?? null) === 'view') {
            $view = $frontendRoot . str_replace('/', DIRECTORY_SEPARATOR, (string) ($page['view'] ?? ''));
            navigation_assert(is_file($view), "{$environmentKey}/{$pageKey}: view ausente {$page['view']}");
            navigation_assert(!isset($usedViews[$page['view']]), "{$environmentKey}/{$pageKey}: view duplicada");
            $usedViews[$page['view']] = true;
            continue;
        }

        navigation_assert(($page['target'] ?? null) === 'public', "{$environmentKey}/{$pageKey}: destino inválido");
        $path = parse_url((string) $page['href'], PHP_URL_PATH);
        navigation_assert(is_string($path) && $path !== '' && is_file($root . '/' . $path), "{$environmentKey}/{$pageKey}: rota pública ausente");
    }
}

$controller = file_get_contents($root . '/setor.php') ?: '';
$authPosition = strpos($controller, 'requireAuthenticatedFrontendContext');
$inputPosition = strpos($controller, "\$_GET['ambiente']");
$helperPosition = strpos($controller, "frontend/support/helpers.php");
$viewPosition = strpos($controller, '$pageDefinition = require $view');
navigation_assert($authPosition !== false && $inputPosition !== false && $authPosition < $inputPosition, 'setor.php deve autenticar antes de tratar a rota');
navigation_assert($helperPosition !== false && $viewPosition !== false && $helperPosition < $viewPosition, 'setor.php deve carregar os helpers antes da view');
navigation_assert(str_contains($controller, 'realpath'), 'setor.php deve resolver views com caminho canônico');
navigation_assert(str_contains($controller, 'str_starts_with'), 'setor.php deve limitar views ao diretório frontend');

if ($failures === []) {
    echo 'PASS navigation-links-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}

echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
