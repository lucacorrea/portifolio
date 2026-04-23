<?php require_once dirname(__DIR__, 2) . '/Helpers/url.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina ?? 'Sistema') ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/global/app.css') ?>">
    <?php if (!empty($cssPagina ?? '')): ?>
        <link rel="stylesheet" href="<?= base_url($cssPagina) ?>">
    <?php endif; ?>
</head>
<body>
