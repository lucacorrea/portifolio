<?php use Sigesp\Core\{Url, View}; ?>
<!doctype html>
<html lang="pt-BR" data-base-path="<?= View::e(Url::basePath()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#071426">
    <title><?= View::e($title ?? 'SIGESP') ?></title>
    <link rel="icon" href="<?= View::e(View::asset('images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= View::e(View::asset('css/app.css') . '?v=20260803-2') ?>">
</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-brand" aria-label="Apresentação do SIGESP">
        <a class="brand" href="<?= View::e(View::url('/login')) ?>"><span class="brand-mark">S</span><span><strong>SIGESP</strong><small>Gestão Esportiva Municipal</small></span></a>
        <div class="auth-brand__copy">
            <p class="eyebrow">Secretaria Municipal de Esporte</p>
            <h1>Gestão esportiva municipal em um único ambiente.</h1>
            <p>Cadastre atletas, acompanhe equipes, organize competições e transforme dados em políticas públicas.</p>
            <ul><li>Indicadores claros para decisões melhores</li><li>Organização de atletas, equipes e eventos</li><li>Experiência segura, acessível e responsiva</li></ul>
        </div>
        <div class="auth-brand__visual" aria-hidden="true"><span>●</span><span>◒</span><span>◆</span><span>◌</span></div>
    </section>
    <section class="auth-card">
        <a class="auth-card__brand" href="<?= View::e(View::url('/login')) ?>"><span class="brand-mark">S</span><strong>SIGESP</strong></a>
        <?= $content ?>
        <p class="auth-security">◉ Ambiente de demonstração · Nenhuma credencial ou dado é armazenado.</p>
    </section>
</main>
<script>window.SIGESP_CONFIG = <?= json_encode(['basePath' => Url::basePath(), 'baseUrl' => Url::to('/')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= View::e(View::asset('js/app.js') . '?v=20260803-2') ?>" type="module"></script>
</body>
</html>
