<?php

use App\Core\Config;
use App\Core\Session;
use App\Core\View;

$appName = Config::get('app.name', 'Igreja Tefe Financeiro');
$userName = Session::get('user_name');
$userRole = Session::get('user_role');
$churchName = trim((string) (Session::get('igreja_nome')
    ?? Session::get('church_name')
    ?? 'Igreja cadastrada'));
$churchName = $churchName !== '' ? $churchName : 'Igreja cadastrada';
$churchLogo = Session::get('igreja_logo_url')
    ?? Session::get('igreja_logo')
    ?? Session::get('church_logo');
$cssVersion = is_file(BASE_PATH . '/public/assets/css/app.css')
    ? (string) filemtime(BASE_PATH . '/public/assets/css/app.css')
    : (string) time();
$jsVersion = is_file(BASE_PATH . '/public/assets/js/app.js')
    ? (string) filemtime(BASE_PATH . '/public/assets/js/app.js')
    : $cssVersion;
$monthNames = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Março',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro',
];
$currentMonth = $monthNames[(int) date('n')] . ' ' . date('Y');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isActive = static function (string $path) use ($currentPath): string {
    $normalizedCurrent = rtrim($currentPath, '/') ?: '/';
    $normalizedPath = rtrim($path, '/') ?: '/';

    return $normalizedCurrent === $normalizedPath || str_starts_with($normalizedCurrent, $normalizedPath . '/')
        ? ' is-active'
        : '';
};
$makeInitials = static function (string $name): string {
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';

    foreach ($words as $word) {
        if ($word === '' || in_array(strtolower($word), ['de', 'da', 'do', 'das', 'dos'], true)) {
            continue;
        }

        $initials .= strtoupper(substr($word, 0, 1));

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'IG';
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e(($title ?? 'Sistema') . ' | ' . $appName) ?></title>
    <link rel="stylesheet" href="<?= \App\Core\View::e(url('/assets/css/app.css?v=' . $cssVersion)) ?>">
</head>
<body>
    <div class="sidebar-overlay" data-sidebar-overlay></div>

    <div class="app-shell">
        <aside class="sidebar" data-sidebar>
            <a class="brand-card" href="<?= View::e(url('/dashboard')) ?>" aria-label="Ir para o dashboard">
                <span class="brand-logo" aria-hidden="true">
                    <?php if (is_string($churchLogo) && $churchLogo !== ''): ?>
                        <img src="<?= View::e($churchLogo) ?>" alt="">
                    <?php else: ?>
                        <?= View::e($makeInitials((string) $churchName)) ?>
                    <?php endif; ?>
                </span>
                <span class="brand-copy">
                    <strong><?= View::e((string) $churchName) ?></strong>
                    <small>Gestão Financeira</small>
                </span>
            </a>

            <nav class="nav-list" aria-label="Navegação principal">
                <a class="nav-item<?= $isActive('/dashboard') ?>" href="<?= View::e(url('/dashboard')) ?>">
                    <i data-lucide="layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>
                <a class="nav-item<?= $isActive('/entradas') ?>" href="<?= View::e(url('/entradas')) ?>">
                    <i data-lucide="arrow-down-circle"></i>
                    <span>Entradas</span>
                </a>
                <a class="nav-item<?= $isActive('/saidas') ?>" href="<?= View::e(url('/saidas')) ?>">
                    <i data-lucide="arrow-up-circle"></i>
                    <span>Saídas</span>
                </a>
                <a class="nav-item<?= $isActive('/categorias') ?>" href="<?= View::e(url('/categorias')) ?>">
                    <i data-lucide="tags"></i>
                    <span>Categorias</span>
                </a>
                <a class="nav-item<?= $isActive('/relatorios') ?>" href="<?= View::e(url('/relatorios')) ?>">
                    <i data-lucide="file-text"></i>
                    <span>Relatórios</span>
                </a>
                <a class="nav-item<?= $isActive('/configuracoes') ?>" href="<?= View::e(url('/configuracoes')) ?>">
                    <i data-lucide="settings"></i>
                    <span>Configurações</span>
                </a>
            </nav>

            <?php if ($userName): ?>
                <form class="logout-form" method="post" action="<?= \App\Core\View::e(url('/logout')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= Session::csrfToken() ?>">
                    <button class="nav-item logout-button" type="submit">
                        <i data-lucide="log-out"></i>
                        <span>Sair</span>
                    </button>
                </form>
            <?php endif; ?>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <button class="icon-button" type="button" data-sidebar-toggle aria-label="Abrir menu" aria-expanded="false">
                    <i data-lucide="menu"></i>
                </button>

                <div class="topbar-title">
                    <strong><?= View::e($title ?? 'Sistema') ?></strong>
                    <span><?= View::e((string) $churchName) ?></span>
                </div>

                <div class="topbar-actions">
                    <span class="month-chip">
                        <i data-lucide="calendar-days"></i>
                        <?= View::e($currentMonth) ?>
                    </span>

                    <div class="user-chip">
                        <span class="user-avatar" aria-hidden="true"><?= View::e($makeInitials((string) ($userName ?: 'Usuário'))) ?></span>
                        <span>
                            <strong><?= View::e((string) ($userName ?: 'Usuário')) ?></strong>
                            <small><?= View::e(ucfirst((string) ($userRole ?: 'Sessão'))) ?></small>
                        </span>
                    </div>
                </div>
            </header>

            <?= $content ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="<?= \App\Core\View::e(url('/assets/js/app.js?v=' . $jsVersion)) ?>" defer></script>
    <script>
        if (window.lucide) {
            lucide.createIcons();
        }
    </script>
</body>
</html>
