<?php

use App\Core\Session;
?>
<div class="auth-copy">
    <p class="eyebrow">Acesso</p>
    <h1>Entrar</h1>
    <p>O formulário real será conectado na fase de autenticação.</p>
</div>

<form class="form-stack" method="post" action="/login">
    <input type="hidden" name="_csrf_token" value="<?= Session::csrfToken() ?>">

    <label>
        Email
        <input type="email" name="email" autocomplete="email" placeholder="usuario@igreja.com">
    </label>

    <label>
        Senha
        <input type="password" name="password" autocomplete="current-password" placeholder="Sua senha">
    </label>

    <button class="button primary" type="submit">Entrar</button>
</form>
