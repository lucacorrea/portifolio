<section class="page-section">
    <div class="section-header">
        <div>
            <p class="eyebrow">Módulo</p>
            <h1><?= \App\Core\View::e($module ?? 'Módulo') ?></h1>
        </div>
    </div>

    <article class="status-card">
        <span>Preparado na estrutura</span>
        <strong><?= \App\Core\View::e($title ?? 'Em construção') ?></strong>
        <p><?= \App\Core\View::e($description ?? 'Este módulo será implementado nas próximas fases do MVP.') ?></p>
    </article>
</section>
