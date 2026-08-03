<?php
use Sigesp\Core\{Auth, Csrf, Flash, Request, Url, View};

$user = Auth::user();
$flash = Flash::pull();
$currentPath = Request::pathFromUri((string) ($_SERVER['REQUEST_URI'] ?? '/dashboard'));
$menu = [
    ['Visão geral', '/dashboard', '⌂'],
    ['Gestão de atletas', null, null],
    ['Atletas', '/atletas', '◉'],
    ['Novo atleta', '/atletas/novo', '+'],
    ['Responsáveis', '/responsaveis', '♙'],
    ['Documentos', '/documentos', '▣'],
    ['Carteiras digitais', '#', '▤'],
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
    <link rel="stylesheet" href="<?= View::e(View::asset('css/app.css')) ?>">
</head>
<body class="app-shell" data-page="<?= View::e($pageId ?? '') ?>">
<a class="skip-link" href="#main-content">Ir para o conteúdo principal</a>
<aside class="sidebar" id="sidebar" aria-label="Menu principal">
    <div class="sidebar__head">
        <a class="brand" href="<?= View::e(View::url('/dashboard')) ?>" aria-label="SIGESP — Visão geral">
            <span class="brand-mark" aria-hidden="true">S</span>
            <span><strong>SIGESP</strong><small>Gestão Esportiva</small></span>
        </a>
        <button class="sidebar__compact icon-button" type="button" data-sidebar-compact
            aria-controls="sidebar" aria-expanded="true" aria-label="Recolher menu">‹</button>
    </div>
    <nav class="sidebar__nav" id="sidebar-navigation" aria-label="Navegação do sistema">
        <?php foreach ($menu as [$label, $href, $icon]): ?>
            <?php if ($href === null): ?>
                <p><?= View::e($label) ?></p>
            <?php else: ?>
                <?php
                $publicHref = $href === '#' ? '#' : View::url($href);
                $isActive = $href !== '#'
                    && ($currentPath === $href || ($href !== '/dashboard' && str_starts_with($currentPath, $href . '/')));
                ?>
                <a href="<?= View::e($publicHref) ?>" class="<?= $isActive ? 'is-active' : '' ?>"
                    aria-label="<?= View::e($label) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>
                    <?= $href === '#' ? ' data-toast="Carteiras digitais estão preparadas para a próxima etapa operacional."' : '' ?>>
                    <span aria-hidden="true"><?= View::e($icon) ?></span><b><?= View::e($label) ?></b>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <footer class="sidebar__user">
        <?php View::component('avatar', ['name' => $user['nome'] ?? 'Usuário']); ?>
        <div>
            <strong><?= View::e($user['nome'] ?? 'Usuário') ?></strong>
            <small>Ambiente administrativo</small>
        </div>
        <form method="post" action="<?= View::e(View::url('/logout')) ?>">
            <input type="hidden" name="_token" value="<?= Csrf::token() ?>">
            <button class="icon-button" type="submit" aria-label="Sair">↪</button>
        </form>
    </footer>
</aside>
<div class="app-backdrop" data-sidebar-close aria-hidden="true"></div>
<main class="app-main">
    <header class="topbar">
        <div class="topbar__start">
            <button class="icon-button topbar__menu" type="button" data-sidebar-toggle
                aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menu">☰</button>
            <?php View::component('breadcrumb', ['items' => [$title ?? 'Painel']]); ?>
        </div>
        <div class="topbar__actions">
            <button class="icon-button topbar__search" type="button"
                data-toast="A pesquisa global será conectada aos módulos disponíveis." aria-label="Pesquisar">⌕</button>
            <label class="global-search">
                <span aria-hidden="true">⌕</span>
                <input type="search" placeholder="Pesquisar no SIGESP" aria-label="Pesquisar no SIGESP">
            </label>
            <button class="icon-button" type="button" data-toast="Nenhuma nova notificação." aria-label="Notificações">♧</button>
            <button class="icon-button fullscreen-button" type="button" data-fullscreen-toggle
                aria-label="Entrar em tela cheia" aria-pressed="false">⛶</button>
            <div class="user-menu" data-dropdown>
                <button class="user-menu__trigger" type="button" data-dropdown-toggle aria-expanded="false"
                    aria-label="Menu do usuário: <?= View::e($user['nome'] ?? 'Usuário') ?>">
                    <?php View::component('avatar', ['name' => $user['nome'] ?? 'Usuário']); ?>
                    <span class="user-menu__name"><?= View::e($user['nome'] ?? 'Usuário') ?></span>
                </button>
                <div class="dropdown__menu dropdown__menu--right" data-dropdown-menu hidden>
                    <a href="<?= View::e(View::url('/configuracoes')) ?>">Configurações</a>
                    <form method="post" action="<?= View::e(View::url('/logout')) ?>">
                        <input type="hidden" name="_token" value="<?= Csrf::token() ?>">
                        <button type="submit">Sair</button>
                    </form>
                </div>
            </div>
        </div>
    </header>
    <div class="app-main__content" id="main-content" tabindex="-1">
        <?php if ($flash): ?>
            <div class="alert alert--<?= View::e($flash['type']) ?>" role="status"><?= View::e($flash['message']) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </div>
</main>
<?php View::component('toast'); ?>
<script>window.SIGESP_CONFIG = <?= json_encode(['basePath' => Url::basePath(), 'baseUrl' => Url::to('/')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= View::e(View::asset('js/app.js')) ?>" type="module"></script>
</body>
</html>
