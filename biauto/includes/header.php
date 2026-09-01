<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pageTitle = $pageTitle ?? 'Bianka Oficina';
$currentPage = $currentPage ?? '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lucide-static@0.468.0/font/Lucide.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="app-shell">
