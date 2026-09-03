<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ui.php';

$pageTitle = $pageTitle ?? 'Bianka Oficina';
$currentPage = $currentPage ?? 'dashboard';
$cssPath = dirname(__DIR__) . '/assets/css/bianka.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : (string) time();
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> • Bianka Oficina</title>
    <meta name="theme-color" content="#ffffff">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bianka.css?v=<?= h($cssVersion) ?>">
</head>
<body>
<div class="app-shell">
