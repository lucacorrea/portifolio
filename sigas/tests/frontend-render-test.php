<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
require_once dirname(__DIR__) . '/frontend/support/helpers.php';

App\Core\Autoloader::register();

use App\Config\ModuleRegistry;

$failures = [];
$root = dirname(__DIR__);
$frontendContext = [
    'user' => ['name' => 'Usuário de Teste', 'initials' => 'UT', 'jobTitle' => 'Perfil demonstrativo', 'sector' => 'SEMAS'],
    'urls' => ['dashboard' => 'portal.php', 'logout' => 'sair.php'],
    'csrf' => ['logout' => 'token-demonstrativo'],
    'navigation' => ModuleRegistry::all(),
];

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

foreach (ModuleRegistry::all() as $environmentKey => $environment) {
    foreach ($environment['pages'] as $pageKey => $page) {
        if ($page['target'] !== 'view') {
            continue;
        }

        try {
            $view = $root . '/frontend/modules/' . $page['view'];
            $pageDefinition = require $view;

            if (!is_array($pageDefinition)) {
                $failures[] = "{$environmentKey}/{$pageKey}: definição inválida";
                continue;
            }

            $frontendContext['module'] = $environmentKey;
            $frontendContext['page'] = $pageKey;
            unset($baseHref);
            ob_start();
            require $root . '/frontend/layouts/module-layout.php';
            $html = (string) ob_get_clean();

            foreach ([
                '<h1>' . sigas_frontend_escape($pageDefinition['title']) . '</h1>' => 'título',
                'Recurso visual — integração futura' => 'aviso demonstrativo',
                'id="moduleSidebar"' => 'sidebar',
                'module-mobile-nav' => 'navegação móvel',
                'frontend-state-gallery' => 'estados visuais',
            ] as $needle => $label) {
                if (!str_contains($html, $needle)) {
                    $failures[] = "{$environmentKey}/{$pageKey}: {$label} ausente";
                }
            }
        } catch (Throwable $exception) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $failures[] = "{$environmentKey}/{$pageKey}: {$exception->getMessage()}";
        }
    }
}

restore_error_handler();

if ($failures === []) {
    echo 'PASS frontend-render-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}

echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
