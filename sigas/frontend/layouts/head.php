<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/support/helpers.php';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?= sigas_frontend_escape($pageDefinition['description'] ?? $errorMessage ?? '') ?>">
    <meta name="theme-color" content="#176b3a">
    <title>SIGAS Coari — <?= sigas_frontend_escape($pageDefinition['title'] ?? $errorTitle ?? 'Ambiente') ?></title>
    <?php if (!empty($baseHref)): ?><base href="<?= sigas_frontend_escape($baseHref) ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= sigas_frontend_asset('assets/css/style.css') ?>" rel="stylesheet">
    <link href="<?= sigas_frontend_asset('assets/css/module-navigation.css') ?>" rel="stylesheet">
    <link href="<?= sigas_frontend_asset('assets/css/frontend-modules.css') ?>" rel="stylesheet">
    <?php if (isset($environment['assets']['css']) && is_file(dirname(__DIR__, 2) . '/' . $environment['assets']['css'])): ?>
        <link href="<?= sigas_frontend_asset($environment['assets']['css']) ?>" rel="stylesheet">
    <?php endif; ?>
    <?php foreach ($extraStyles ?? [] as $extraStyle): ?>
        <?php if (is_string($extraStyle) && is_file(dirname(__DIR__, 2) . '/' . $extraStyle)): ?><link href="<?= sigas_frontend_asset($extraStyle) ?>" rel="stylesheet"><?php endif; ?>
    <?php endforeach; ?>
</head>
