<?php

declare(strict_types=1);

$folderModuleHomes = [
    'kit-maternidade' => 'kit-maternidade/index.php',
    'aluguel-social' => 'aluguel-social/index.php',
    'beneficios-eventuais' => 'beneficios-eventuais/index.php',
];

$headerEnvironmentKey = isset($environmentKey) && is_string($environmentKey) ? $environmentKey : '';
$headerModuleHome = $folderModuleHomes[$headerEnvironmentKey] ?? (string) ($environment['home'] ?? 'portal.php');

$normalizeHeaderHref = static function (string $href) use ($folderModuleHomes): string {
    $href = trim($href);
    if ($href === '') {
        return '';
    }

    $path = parse_url($href, PHP_URL_PATH);
    $query = parse_url($href, PHP_URL_QUERY);

    if (!is_string($path) || basename($path) !== 'setor.php' || !is_string($query)) {
        return $href;
    }

    parse_str($query, $params);
    $module = trim((string) ($params['ambiente'] ?? ''));
    $page = trim((string) ($params['pagina'] ?? ''));

    if (!isset($folderModuleHomes[$module])) {
        return $href;
    }

    $home = $folderModuleHomes[$module];
    return $page === '' || $page === 'painel'
        ? $home
        : $home . '?pagina=' . rawurlencode($page);
};
?>
<nav class="frontend-breadcrumb sigas-breadcrumb" aria-label="Navegação estrutural">
    <a href="portal.php">Portal</a>
    <i class="bi bi-chevron-right"></i>
    <a href="<?= sigas_frontend_escape($headerModuleHome) ?>"><?= sigas_frontend_escape($environment['name']) ?></a>
    <i class="bi bi-chevron-right"></i>
    <span aria-current="page"><?= sigas_frontend_escape($pageDefinition['title']) ?></span>
</nav>
<header class="page-header frontend-page-header sigas-page-header">
    <div>
        <div class="eyebrow"><i class="bi bi-<?= sigas_frontend_escape($page['icon']) ?>"></i><?= sigas_frontend_escape($environment['name']) ?></div>
        <h1><?= sigas_frontend_escape($pageDefinition['title']) ?></h1>
        <p><?= sigas_frontend_escape($pageDefinition['description']) ?></p>
    </div>
    <?php if ($pageDefinition['actions'] !== []): ?>
        <div class="page-actions sigas-page-actions">
            <?php foreach ($pageDefinition['actions'] as $action): ?>
                <?php $actionHref = $normalizeHeaderHref((string) ($action['href'] ?? '')); ?>
                <?php if ($actionHref !== ''): ?>
                    <a class="btn <?= !empty($action['primary']) ? 'btn-primary' : 'btn-light' ?>" href="<?= sigas_frontend_escape($actionHref) ?>">
                        <i class="bi bi-<?= sigas_frontend_escape($action['icon'] ?? 'plus-lg') ?>"></i><?= sigas_frontend_escape($action['label']) ?>
                    </a>
                <?php else: ?>
                    <button class="btn <?= !empty($action['primary']) ? 'btn-primary' : 'btn-light' ?>" type="button" data-demo-action="<?= sigas_frontend_escape($action['label']) ?>">
                        <i class="bi bi-<?= sigas_frontend_escape($action['icon'] ?? 'plus-lg') ?>"></i><?= sigas_frontend_escape($action['label']) ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</header>
