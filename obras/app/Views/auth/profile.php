<div class="page-header"><div><span class="eyebrow">SEGURANÇA DA CONTA</span><h1>Meu perfil</h1><p>Gerencie sua senha e confira seus dados de acesso.</p></div></div>
<div class="two-cols profile-layout"><section class="panel"><div class="panel-heading"><h2>Dados institucionais</h2></div><div class="info-pair"><span>Nome</span><b><?= h($user['name']) ?></b></div><div class="info-pair"><span>E-mail</span><b><?= h($user['email']) ?></b></div><div class="info-pair"><span>Perfil</span><b><?= h(role_name($user['role'])) ?></b></div><div class="info-pair"><span>Último acesso</span><b><?= h(site_time($user['last_login_at'])) ?></b></div></section>
<section class="panel"><div class="panel-heading"><div><h2>Alterar senha</h2><p>Use uma senha longa, exclusiva e difícil de adivinhar.</p></div></div>
<?php if ($user['must_change_password']): ?><div class="notice notice-warning">Para continuar, defina uma senha pessoal. A senha temporária deve ser substituída.</div><?php endif; ?>
<form method="post" action="<?= h(url('profile')) ?>" class="form-stack"><?= csrf_field() ?>
<label class="field"><span>Senha atual</span><input type="password" name="current_password" required autocomplete="current-password"></label>
<label class="field"><span>Nova senha (mínimo de 15 caracteres)</span><input type="password" name="new_password" minlength="15" maxlength="256" required autocomplete="new-password"></label>
<label class="field"><span>Confirmar nova senha</span><input type="password" name="confirm_password" minlength="15" maxlength="256" required autocomplete="new-password"></label>
<button class="btn btn-primary" type="submit"><?= ic('shield-check') ?> Alterar minha senha</button></form></section></div>
