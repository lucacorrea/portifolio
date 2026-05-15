<?php

$roles = is_array($roles ?? null) ? $roles : [];
$usuario = is_array($usuario ?? null) ? $usuario : [];
$old = is_array($old ?? null) ? $old : [];
$currentUserId = (int) ($currentUserId ?? 0);
$oldValue = static fn (string $key, mixed $default = ''): string => (string) ($old[$key] ?? ($usuario[$key] ?? $default));
$roleLabel = static fn (string $role): string => [
    'admin' => 'Administrador',
    'tesoureiro' => 'Tesoureiro',
    'visualizador' => 'Visualizador',
][$role] ?? ucfirst($role);
$isCurrentUser = (int) ($usuario['id'] ?? 0) === $currentUserId;
?>

<section class="page-section module-page users-page">
    <div class="section-header with-actions">
        <div>
            <p class="eyebrow">Usuários</p>
            <h1>Editar usuário</h1>
        </div>

        <a class="button secondary" href="<?= \App\Core\View::e(url('/usuarios')) ?>">
            <i data-lucide="arrow-left"></i>
            Voltar para usuários
        </a>
    </div>

    <article class="form-card">
        <div class="form-card-header">
            <div>
                <span class="section-kicker">Acesso</span>
                <h2><?= \App\Core\View::e($oldValue('nome', 'Usuário')) ?></h2>
            </div>
            <span class="badge <?= (int) ($usuario['ativo'] ?? 0) === 1 ? 'badge-success' : 'badge-danger' ?>">
                <?= (int) ($usuario['ativo'] ?? 0) === 1 ? 'Ativo' : 'Desativado' ?>
            </span>
        </div>

        <?php if (is_string($error ?? null)): ?>
            <div class="alert error"><?= \App\Core\View::e($error) ?></div>
        <?php endif; ?>

        <form class="form-stack entry-form" method="post" action="<?= \App\Core\View::e(url('/usuarios/atualizar')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= \App\Core\Session::csrfToken() ?>">
            <input type="hidden" name="id" value="<?= \App\Core\View::e($usuario['id'] ?? '') ?>">

            <div class="field-grid">
                <label>
                    Nome
                    <input type="text" name="nome" maxlength="180" value="<?= \App\Core\View::e($oldValue('nome')) ?>" required>
                </label>

                <label>
                    Email
                    <input type="email" name="email" maxlength="180" value="<?= \App\Core\View::e($oldValue('email')) ?>" required>
                </label>

                <label>
                    Papel
                    <?php if ($isCurrentUser): ?>
                        <input type="hidden" name="papel" value="<?= \App\Core\View::e($oldValue('papel')) ?>">
                    <?php endif; ?>
                    <select name="papel" required <?= $isCurrentUser ? 'disabled' : '' ?>>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= \App\Core\View::e($role) ?>" <?= $oldValue('papel') === $role ? 'selected' : '' ?>>
                                <?= \App\Core\View::e($roleLabel($role)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Nova senha
                    <input type="password" name="senha" minlength="8" placeholder="Deixe em branco para manter">
                </label>
            </div>

            <label class="check-row">
                <input type="checkbox" name="ativo" value="1" <?= (int) $oldValue('ativo', 1) === 1 ? 'checked' : '' ?> <?= $isCurrentUser ? 'disabled' : '' ?>>
                <span>Usuário ativo</span>
            </label>

            <?php if ($isCurrentUser): ?>
                <p class="form-note">Você está editando seu próprio usuário. O sistema mantém este acesso ativo para evitar bloqueio acidental.</p>
            <?php endif; ?>

            <div class="form-actions">
                <button class="button primary" type="submit">
                    <i data-lucide="save"></i>
                    Atualizar usuário
                </button>
                <a class="button secondary" href="<?= \App\Core\View::e(url('/usuarios')) ?>">Cancelar</a>
            </div>
        </form>
    </article>
</section>
