<?php
use Sigesp\Core\{Request, Url, View};

$user = ['nome' => 'Marcos Oliveira', 'cargo' => 'Administrador do Sistema'];
$currentPath = Request::pathFromUri((string) ($_SERVER['REQUEST_URI'] ?? '/dashboard'));
$menu = [
    ['Visão geral', '/dashboard', '⌂'],
    ['Gestão de atletas', null, null],
    ['Atletas', '/atletas', '◉'],
    ['Novo atleta', '/atletas/novo', '+'],
    ['Responsáveis', '/responsaveis', '♙'],
    ['Documentos', '/documentos', '▣'],
    ['Carteiras digitais', '/carteiras', '▤'],
    ['Gestão esportiva', null, null],
    ['Modalidades', '/modalidades', '◌'],
    ['Categorias', '/categorias', '◫'],
    ['Equipes', '/equipes', '♜'],
    ['Treinos', '/treinos', '◷'],
    ['Frequências', '/frequencias', '✓'],
    ['Avaliações', '/avaliacoes', '⌁'],
    ['Eventos e competições', null, null],
    ['Eventos', '/eventos', '★'],
    ['Competições', '/competicoes', '⌘'],
    ['Inscrições', '/inscricoes', '⊕'],
    ['Resultados', '/resultados', '♕'],
    ['Recursos', null, null],
    ['Benefícios', '/beneficios', '♥'],
    ['Espaços esportivos', '/espacos-esportivos', '⌂'],
    ['Reservas', '/reservas', '◴'],
    ['Materiais esportivos', '/materiais', '▤'],
    ['Relatórios', '/relatorios', '▥'],
    ['Administração', null, null],
    ['Usuários', '/usuarios', '♙'],
    ['Perfis e permissões', '/permissoes', '⚿'],
    ['Auditoria', '/auditoria', '◷'],
    ['Configurações', '/configuracoes', '⚙'],
];
?>
<!doctype html>
<html lang="pt-BR" data-base-path="<?= View::e(Url::basePath()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#10233F">
    <title><?= View::e($title ?? 'SIGESP') ?> · SIGESP</title>
    <link rel="icon" href="<?= View::e(View::asset('images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= View::e(View::asset('css/app.css') . '?v=20260803-2') ?>">
</head>
<body class="app-shell" data-page="<?= View::e($pageId ?? '') ?>">
<a class="skip-link" href="#main-content">Ir para o conteúdo principal</a>
<?php View::component('sidebar', compact('menu','currentPath','user')); ?>
<div class="app-backdrop" data-sidebar-close aria-hidden="true"></div>
<main class="app-main">
    <?php View::component('topbar', ['title'=>$title??'Painel','user'=>$user]); ?>
    <div class="app-main__content" id="main-content" tabindex="-1">
        <?php View::component('demo-banner'); ?>
        <?= $content ?>
    </div>
</main>
<?php View::component('toast'); ?>
<script>window.SIGESP_CONFIG = <?= json_encode(['basePath' => Url::basePath(), 'baseUrl' => Url::to('/')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= View::e(View::asset('js/app.js') . '?v=20260803-2') ?>" type="module"></script>
</body>
</html>
