<?php

use App\Core\Session;
use App\Core\View;

$old = is_array($old ?? null) ? $old : [];
?>
<div class="auth-copy">
    <p class="eyebrow">Acesso</p>
    <h1>Entrar</h1>
    <p>Use seu email, senha e o código da igreja para acessar o painel financeiro.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert error" role="alert">
        <?= View::e($error) ?>
    </div>
<?php endif; ?>

<form class="form-stack" method="post" action="/login">
    <input type="hidden" name="_csrf_token" value="<?= Session::csrfToken() ?>">

    <label>
        Código da igreja
        <input
            type="number"
            name="igreja_id"
            min="1"
            inputmode="numeric"
            autocomplete="organization"
            value="<?= View::e($old['igreja_id'] ?? '1') ?>"
            required
        >
    </label>

    <label>
        Email
        <input
            type="email"
            name="email"
            autocomplete="email"
            placeholder="usuario@igreja.com"
            value="<?= View::e($old['email'] ?? '') ?>"
            required
            autofocus
        >
    </label>

    <label>
        Senha
        <input type="password" name="password" autocomplete="current-password" placeholder="Sua senha" required>
    </label>

    <button class="button primary" type="submit">Entrar</button>
</form>

<p class="auth-link">
    Primeiro acesso? <a href="/registro">Criar igreja e administrador</a>
</p>
