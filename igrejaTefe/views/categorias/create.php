<?php

$old = is_array($old ?? null) ? $old : [];
$oldValue = static fn (string $key, mixed $default = ''): string => (string) ($old[$key] ?? $default);
?>

<section class="page-section module-page">
    <div class="section-header with-actions">
        <div>
            <p class="eyebrow">Categorias</p>
            <h1>Cadastrar categoria</h1>
        </div>

        <a class="button secondary" href="<?= \App\Core\View::e(url('/categorias')) ?>">
            <i data-lucide="arrow-left"></i>
            Voltar para categorias
        </a>
    </div>

    <article class="form-card">
        <div class="form-card-header">
            <div>
                <span class="section-kicker">Nova categoria</span>
                <h2>Dados de classificação</h2>
            </div>
            <span class="badge badge-muted">Plano financeiro</span>
        </div>

        <?php if (is_string($error ?? null)): ?>
            <div class="alert error"><?= \App\Core\View::e($error) ?></div>
        <?php endif; ?>

        <form class="form-stack entry-form" method="post" action="<?= \App\Core\View::e(url('/categorias')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= \App\Core\Session::csrfToken() ?>">

            <div class="field-grid">
                <label>
                    Nome
                    <input type="text" name="nome" maxlength="120" value="<?= \App\Core\View::e($oldValue('nome')) ?>" placeholder="Ex.: Energia elétrica" required>
                </label>

                <label>
                    Cor
                    <input type="color" name="cor" value="<?= \App\Core\View::e($oldValue('cor', '#2FAF8F')) ?>" required>
                </label>
            </div>

            <label>
                Descrição
                <textarea name="descricao" rows="4" placeholder="Finalidade desta categoria"><?= \App\Core\View::e($oldValue('descricao')) ?></textarea>
            </label>

            <div class="form-actions">
                <button class="button primary" type="submit">
                    <i data-lucide="save"></i>
                    Salvar categoria
                </button>
                <a class="button secondary" href="<?= \App\Core\View::e(url('/categorias')) ?>">Cancelar</a>
            </div>
        </form>
    </article>
</section>
