<?php

use App\Core\Session;
?>
<div class="auth-copy">
    <p class="eyebrow">Primeiro acesso</p>
    <h1>Registro inicial</h1>
    <p>Esta tela criará a igreja e o usuário administrador na fase de autenticação.</p>
</div>

<form class="form-stack" method="post" action="<?= \App\Core\View::e(url('/registro')) ?>">
    <input type="hidden" name="_csrf_token" value="<?= Session::csrfToken() ?>">

    <label>
        Nome da igreja
        <input type="text" name="igreja_nome" autocomplete="organization" placeholder="Igreja local">
    </label>

    <label>
        Nome do administrador
        <input type="text" name="nome" autocomplete="name" placeholder="Nome completo">
    </label>

    <label>
        Email
        <input type="email" name="email" autocomplete="email" placeholder="admin@igreja.com">
    </label>

    <label>
        Senha
        <input type="password" name="password" autocomplete="new-password" placeholder="Crie uma senha segura">
    </label>

    <button class="button primary" type="submit">Criar base inicial</button>
</form>
