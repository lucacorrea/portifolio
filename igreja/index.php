<?php
$paginaAtual = basename($_SERVER['PHP_SELF']);

function menuAtivo(array $paginas, string $paginaAtual): string
{
    return in_array($paginaAtual, $paginas, true) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Membros</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logo">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <h5 class="mb-0">Membros</h5>
                <small>Sistema Igreja</small>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="index.php" class="sidebar-link <?= menuAtivo(['index.php'], $paginaAtual) ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>

            <a href="cadastrar.php" class="sidebar-link <?= menuAtivo(['cadastrar.php'], $paginaAtual) ?>">
                <i class="bi bi-person-plus-fill"></i>
                <span>Cadastrar</span>
            </a>

            <a href="listar.php" class="sidebar-link <?= menuAtivo(['listar.php', 'visualizar.php', 'editar.php', 'ficha.php'], $paginaAtual) ?>">
                <i class="bi bi-card-list"></i>
                <span>Membros</span>
            </a>
        </nav>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle d-lg-none" id="menuToggle" type="button">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h4 class="topbar-title mb-0">Sistema de Membros</h4>
                    <small class="text-muted">Cadastro e acompanhamento da membresia</small>
                </div>
            </div>

            <div class="topbar-right">
                <a href="cadastrar.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Novo cadastro
                </a>
            </div>
        </header>

        <main class="page-content">