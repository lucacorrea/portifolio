<?php use Sigesp\Core\View; $title = 'Demonstração SIGESP'; ?>
<div class="auth-title">
    <span class="demo-badge">DEMO</span>
    <h1>Conheça o SIGESP</h1>
    <p>Navegue por uma demonstração completa da gestão esportiva municipal.</p>
</div>
<div class="demo-identity" aria-label="Perfil demonstrativo">
    <span class="avatar">MO</span><div><strong>Administrador Demonstrativo</strong><small>Secretaria Municipal de Esporte · Perfil Administrador</small></div>
</div>
<form method="post" action="<?= View::e(View::url('/login')) ?>" class="form" data-demo-login data-demo-form data-demo-redirect="/dashboard">
    <label class="field">CPF ou e-mail<input name="identificador" autocomplete="username" placeholder="Digite qualquer informação" aria-describedby="login-help"><small id="login-help">Nenhuma informação será validada ou armazenada.</small></label>
    <label class="field">Senha<span class="password-field"><input name="senha" type="password" autocomplete="current-password" placeholder="Digite qualquer senha"><button type="button" data-password-toggle>Mostrar</button></span></label>
    <div class="remember-row"><label><input type="checkbox" name="lembrar" value="1"> Lembrar acesso</label><a href="<?= View::e(View::url('/recuperar-senha')) ?>">Esqueci minha senha</a></div>
    <button class="button" type="submit">Entrar</button>
    <a class="button button--secondary demo-entry" href="<?= View::e(View::url('/dashboard')) ?>">Entrar no modo demonstração</a>
</form>
<p class="demo-help">Para visualizar a demonstração, utilize qualquer informação nos campos ou clique em “Entrar no modo demonstração”.</p>
