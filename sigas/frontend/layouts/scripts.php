<?php

declare(strict_types=1);

/**
 * Monta a lista de JavaScript adicionais sem duplicar arquivos.
 * Os assets oficiais de cada módulo vêm do ModuleRegistry.
 *
 * @return array<int,string>
 */
if (!function_exists('sigas_frontend_module_scripts')) {
    function sigas_frontend_module_scripts(
        ?array $environment,
        ?string $environmentKey,
        array $extraScripts
    ): array {
        $scripts = [];

        $environmentJs = $environment['assets']['js'] ?? null;
        if (is_string($environmentJs) && trim($environmentJs) !== '') {
            $scripts[] = trim($environmentJs);
        }

        foreach ($extraScripts as $extraScript) {
            if (is_string($extraScript) && trim($extraScript) !== '') {
                $scripts[] = trim($extraScript);
            }
        }

        return array_values(array_unique($scripts));
    }
}

$moduleScripts = sigas_frontend_module_scripts(
    isset($environment) && is_array($environment) ? $environment : null,
    isset($environmentKey) && is_string($environmentKey) ? $environmentKey : null,
    isset($extraScripts) && is_array($extraScripts) ? $extraScripts : []
);
?>
<div class="toast-container position-fixed top-0 end-0 p-3" id="frontendToastContainer" aria-live="polite"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?= App\Core\PageContext::script($frontendContext) ?>
<script src="<?= sigas_frontend_asset('assets/js/frontend-modules.js') ?>"></script>

<?php foreach ($moduleScripts as $moduleScript): ?>
    <?php
    $absoluteScript = dirname(__DIR__, 2) . '/' . $moduleScript;
    if (!is_file($absoluteScript)) {
        continue;
    }
    ?>
    <script src="<?= sigas_frontend_asset($moduleScript) ?>" data-sigas-module-script="<?= sigas_frontend_escape($environmentKey ?? '') ?>"></script>
<?php endforeach; ?>
