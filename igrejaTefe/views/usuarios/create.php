<?php

$roles = is_array($roles ?? null) ? $roles : [];
$old = is_array($old ?? null) ? $old : [];
$oldValue = static fn (string $key, mixed $default = ''): string => (string) ($old[$key] ?? $default);
$roleLabel = static fn (string $role): string => [
    'admin' => 'Administrador',
    'tesoureiro' => 'Tesoureiro',
    'visualizador' => 'Visualizador',
][$role] ?? ucfirst($role);
?>

<section class="page-section module-page users-page">
    <div class="section-header with-actions">
        <div>
            <p class="eyebrow">Usuários</p>
            <h1>Cadastrar usuário</h1>
        </div>

        <a class="button secondary" href="<?= \App\Core\View::e(url('/usuarios')) ?>">
            <i data-lucide="arrow-left"></i>
            Voltar para usuários
        </a>
    </div>

    <article class="form-card">
        <div class="form-card-header">
            <div>
                <span class="section-kicker">Novo acesso</span>
                <h2>Dados do usuário</h2>
            </div>
            <span class="badge badge-muted">Administração</span>
        </div>

        <?php if (is_string($error ?? null)): ?>
            <div class="alert error"><?= \App\Core\View::e($error) ?></div>
        <?php endif; ?>

        <form class="form-stack entry-form" method="post" action="<?= \App\Core\View::e(url('/usuarios')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= \App\Core\Session::csrfToken() ?>">

            <div class="field-grid">
                <label>
                    Nome
                    <input type="text" name="nome" maxlength="180" value="<?= \App\Core\View::e($oldValue('nome')) ?>" placeholder="Nome completo" required>
                </label>

                <label>
                    Email
                    <input type="email" name="email" maxlength="180" value="<?= \App\Core\View::e($oldValue('email')) ?>" placeholder="usuario@igreja.com" required>
                </label>

                <label>
                    Papel
                    <select name="papel" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= \App\Core\View::e($role) ?>" <?= $oldValue('papel', 'visualizador') === $role ? 'selected' : '' ?>>
                                <?= \App\Core\View::e($roleLabel($role)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Senha
                    <input type="password" name="senha" minlength="8" placeholder="Mínimo de 8 caracteres" required>
                </label>
            </div>

            <div class="role-helper-grid">
                <div><strong>Administrador</strong><span>Acesso total ao sistema e usuários.</span></div>
                <div><strong>Tesoureiro</strong><span>Operação financeira diária.</span></div>
                <div><strong>Visualizador</strong><span>Consulta dados e relatórios.</span></div>
            </div>

            <div class="form-actions">
                <button class="button primary" type="submit">
                    <i data-lucide="save"></i>
                    Salvar usuário
                </button>
                <a class="button secondary" href="<?= \App\Core\View::e(url('/usuarios')) ?>">Cancelar</a>
            </div>
        </form>
    </article>
</section>
