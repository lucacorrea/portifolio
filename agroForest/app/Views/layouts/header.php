<?php require_once dirname(__DIR__, 2) . '/Helpers/url.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina ?? 'Sistema') ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/global/app.css') ?>">
    <?php
    $cssArquivos = $cssPagina ?? null;
    if (is_string($cssArquivos) && $cssArquivos !== '') {
        $cssArquivos = [$cssArquivos];
    }
    if (is_array($cssArquivos)):
        foreach ($cssArquivos as $arquivoCss):
            if (!empty($arquivoCss)):
    ?>
        <link rel="stylesheet" href="<?= base_url($arquivoCss) ?>">
    <?php
            endif;
        endforeach;
    endif;
    ?>
</head>
<body>
