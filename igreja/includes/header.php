<?php
$pagina = basename($_SERVER['PHP_SELF']);

function ativo($arquivo, $pagina)
{
    return $arquivo === $pagina ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Igreja Vida Nova</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/css.css">
</head>
<body>

<div class="dashboard">
    <div class="header">
        <div class="logo-area">
            <div class="logo-icon">
                <i class="fas fa-church"></i>
            </div>
            <div class="logo-text">
                <h1>Deus Nascer de Novo.</h1>
                <p>Administração eclesiástica</p>
            </div>
        </div>

        <div class="nav-menu">
            <a href="index.php" class="nav-item <?= ativo('index.php', $pagina) ?>">
                <i class="fas fa-home"></i> Início
            </a>
            <a href="cadastrar.php" class="nav-item <?= ativo('cadastrar.php', $pagina) ?>">
                <i class="fas fa-user-plus"></i> Cadastrar
            </a>
            <a href="listar.php" class="nav-item <?= ativo('listar.php', $pagina) ?>">
                <i class="fas fa-users"></i> Membros
            </a>
            
        </div>

        <div class="user-profile">
            <div class="notification-badge">
                <i class="far fa-bell"></i>
            </div>
            <div class="avatar">
                <div class="avatar-img">IG</div>
                <span class="avatar-name">Secretaria</span>
                <i class="fas fa-chevron-down" style="font-size:0.8rem;color:#7c9bd4;"></i>
            </div>
        </div>
    </div>