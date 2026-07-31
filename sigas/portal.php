<?php

declare(strict_types=1);

use App\Core\PageContext;

require_once __DIR__ . '/bootstrap.php';

$frontendContext = PageContext::requireAuthenticatedFrontendContext();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal de módulos do SIGAS Coari.">
    <title>SIGAS Coari — Portal de módulos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/portal.css" rel="stylesheet">
</head>
<body class="portal-page" data-page="portal">
    <main class="portal-shell">
        <header class="portal-header">
            <a class="portal-brand" href="portal.php" aria-label="SIGAS Coari — Portal de módulos">
                <img src="assets/img/brasao-placeholder.svg" alt="Brasão institucional ilustrativo">
                <span><strong>SIGAS COARI</strong><small>Portal de módulos</small></span>
            </a>
            <div class="portal-user">
                <span class="avatar" data-portal-initials>U</span>
                <span><strong data-portal-name>Usuário</strong><small data-portal-role>—</small></span>
                <form method="post" action="sair.php"><input type="hidden" name="_csrf" data-portal-logout><button class="btn btn-light btn-sm" type="submit"><i class="bi bi-box-arrow-right"></i><span class="portal-exit-label">Sair</span></button></form>
            </div>
        </header>

        <section class="portal-welcome" aria-labelledby="portalTitle">
            <div><span class="eyebrow"><i class="bi bi-grid-1x2"></i>Ambiente de trabalho</span><h1 id="portalTitle">Escolha um módulo para continuar</h1><p data-portal-sector>Os módulos disponíveis são definidos conforme o seu perfil e setor.</p></div>
            <div class="portal-version"><i class="bi bi-shield-check"></i><span>Ambiente institucional<br><strong>SIGAS Coari v1.1</strong></span></div>
        </section>

        <section id="modulos" aria-labelledby="modulesTitle"><div class="portal-section-heading"><div><h2 id="modulesTitle">Módulos disponíveis</h2><p>Selecione o ambiente que deseja acessar.</p></div><span class="status-badge status-info"><i class="bi bi-info-circle"></i>Acesso visual</span></div><div class="module-card-grid" id="moduleCards" aria-live="polite"></div></section>

        <div class="portal-lower-grid">
            <section class="portal-panel" aria-labelledby="recentTitle"><div class="card-heading"><div><div class="card-kicker">Continuidade</div><h2 id="recentTitle">Acessos recentes</h2></div></div><div class="portal-recent-list"><a href="modulo.php"><i class="bi bi-basket2"></i><span><strong>Coari Comida na Mesa</strong><small>Beneficiários e competências</small></span><i class="bi bi-arrow-up-right"></i></a><a href="pessoas.php"><i class="bi bi-people"></i><span><strong>Assistência Social</strong><small>Pessoas e prontuários</small></span><i class="bi bi-arrow-up-right"></i></a></div></section>
            <section class="portal-panel" aria-labelledby="noticeTitle"><div class="card-heading"><div><div class="card-kicker">Comunicados</div><h2 id="noticeTitle">Avisos do sistema</h2></div></div><div class="portal-notices"><div><i class="bi bi-info-circle"></i><span><strong>Novo ambiente Primeiro Emprego</strong><small>Layout disponível para validação e evolução.</small></span></div><div><i class="bi bi-shield-lock"></i><span><strong>Permissões em evolução</strong><small>A validação definitiva será feita no servidor.</small></span></div></div></section>
        </div>
    </main>
    <script><?= PageContext::script($frontendContext) ?></script>
    <script src="assets/js/module-portal.js"></script>
</body>
</html>
