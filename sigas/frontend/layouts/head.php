<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/support/helpers.php';

/**
 * Monta a lista de CSS adicionais sem duplicar arquivos.
 * O Primeiro Emprego recebe carregamento explícito do module2.0.css.
 * O Design System operacional híbrido global continua no Primeiro Emprego.
 * O Comida na Mesa usa seu próprio module.css modular e isolado.
 *
 * @return array<int,string>
 */
if (!function_exists('sigas_frontend_module_styles')) {
    function sigas_frontend_module_styles(
        ?array $environment,
        ?string $environmentKey,
        array $extraStyles
    ): array {
        $styles = [];

        $environmentCss = $environment['assets']['css'] ?? null;
        if (is_string($environmentCss) && trim($environmentCss) !== '') {
            $styles[] = trim($environmentCss);
        }

        if ($environmentKey === 'primeiro-emprego') {
            $styles[] = 'frontend/modules/primeiro-emprego/module2.0.css';
        }

        foreach ($extraStyles as $extraStyle) {
            if (is_string($extraStyle) && trim($extraStyle) !== '') {
                $styles[] = trim($extraStyle);
            }
        }

        return array_values(array_unique($styles));
    }
}


$moduleStyles = sigas_frontend_module_styles(
    isset($environment) && is_array($environment) ? $environment : null,
    isset($environmentKey) && is_string($environmentKey) ? $environmentKey : null,
    isset($extraStyles) && is_array($extraStyles) ? $extraStyles : []
);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?= sigas_frontend_escape($pageDefinition['description'] ?? $errorMessage ?? '') ?>">
    <meta name="theme-color" content="#176b3a">
    <title>SIGAS Coari — <?= sigas_frontend_escape($pageDefinition['title'] ?? $errorTitle ?? 'Ambiente') ?></title>

    <?php if (!empty($baseHref)): ?>
        <base href="<?= sigas_frontend_escape($baseHref) ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link href="<?= sigas_frontend_asset('assets/css/style.css') ?>" rel="stylesheet">
    <link href="<?= sigas_frontend_asset('assets/css/module-navigation.css') ?>" rel="stylesheet">
    <link href="<?= sigas_frontend_asset('assets/css/frontend-modules.css') ?>" rel="stylesheet">

    <?php foreach ($moduleStyles as $moduleStyle): ?>
        <?php
        $absoluteStyle = dirname(__DIR__, 2) . '/' . $moduleStyle;
        if (!is_file($absoluteStyle)) {
            continue;
        }
        ?>
        <link href="<?= sigas_frontend_asset($moduleStyle) ?>" rel="stylesheet" data-sigas-module-style="<?= sigas_frontend_escape($environmentKey ?? '') ?>">
    <?php endforeach; ?>

    <?php if (($environmentKey ?? '') === 'primeiro-emprego'): ?>
        <?php $operationalUiPath = 'assets/css/sigas-operational-ui.css'; ?>
        <?php if (is_file(dirname(__DIR__, 2) . '/' . $operationalUiPath)): ?>
            <link href="<?= sigas_frontend_asset($operationalUiPath) ?>" rel="stylesheet" data-sigas-operational-ui="v3">
        <?php endif; ?>
    <?php endif; ?>
</head>
