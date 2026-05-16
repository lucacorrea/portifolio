<?php

defined('APP_PATH') || exit('Acesso direto negado.');

$pageTitle = trim((string) ($pageTitle ?? 'Dashboard'));
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | FluxEmpresa</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
