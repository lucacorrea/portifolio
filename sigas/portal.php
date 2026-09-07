<?php

declare(strict_types=1);

use App\Core\PageContext;

require_once __DIR__ . '/bootstrap.php';

$frontendContext = PageContext::requireAuthenticatedFrontendContext();

// O portal principal passa a exibir somente módulos operacionais independentes.
// Os antigos ambientes setoriais permanecem registrados para compatibilidade
// com rotas e funcionalidades legadas, mas não são mais cards de entrada.
$frontendContext['navigation'] = array_filter(
    $frontendContext['navigation'] ?? [],
    static fn (array $environment): bool => ($environment['kind'] ?? null) === 'module'
);

$moduleDescriptions = [
    'kit-maternidade' => 'Acompanha gestantes desde o cadastro e triagem até visitas, avaliação, entrega do kit e pós-parto.',
    'aluguel-social' => 'Gerencia solicitações, vistorias, pareceres, concessões, pagamentos e reavaliações do benefício.',
    'beneficios-eventuais' => 'Organiza solicitações, triagens, análises, concessões e entregas dos Benefícios Eventuais.',
    'gestao-acessos' => 'Administra usuários, cargos, perfis, permissões, setores, sessões e auditoria de acessos do SIGAS.',
    'comida-mesa' => 'Gerencia inscrições, beneficiários, competências, polos, documentos e entregas do programa Comida na Mesa.',
    'primeiro-emprego' => 'Acompanha candidatos, vagas, parceiros, encaminhamentos, frequência, bolsas, capacitações e resultados do programa.',
];

foreach ($frontendContext['navigation'] as $moduleKey => &$module) {
    if (isset($moduleDescriptions[$moduleKey])) {
        $module['description'] = $moduleDescriptions[$moduleKey];
    }
}
unset($module);
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
    <link href="assets/css/portal.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/portal.css') ?>" rel="stylesheet">
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
            <div><span class="eyebrow"><i class="bi bi-grid-1x2"></i>Ambiente de trabalho</span><h1 id="portalTitle">Escolha um módulo para continuar</h1><p data-portal-sector>Os módulos disponíveis são liberados conforme seu perfil, setor e permissões.</p></div>
            <div class="portal-version"><i class="bi bi-shield-check"></i><span>Ambiente institucional<br><strong>SIGAS Coari v1.1</strong></span></div>
        </section>

        <section id="modulos" aria-labelledby="modulesTitle"><div class="portal-section-heading"><div><h2 id="modulesTitle">Módulos disponíveis</h2><p>Cada módulo possui ambiente, fluxo, navegação e permissões próprios.</p></div><span class="status-badge status-info"><i class="bi bi-grid"></i>Módulos independentes</span></div><div class="module-card-grid" id="moduleCards" aria-live="polite"></div></section>
    </main>
    <?= PageContext::script($frontendContext) ?>
    <script src="assets/js/module-portal.js?v=<?= (int) filemtime(__DIR__ . '/assets/js/module-portal.js') ?>"></script>
</body>
</html>
