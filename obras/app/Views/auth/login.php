<div class="auth-wrap"><div class="auth-aside">
 <div class="auth-big-icon"><?= ic('hard-hat') ?></div>
 <div class="auth-eyebrow">PREFEITURA MUNICIPAL DE COARI · AM</div>
 <h1>Gestão de obras mais clara, integrada e eficiente.</h1>
 <p>Planejamento, fiscalização, contratos, patrimônio e operações da Secretaria em um único ambiente.</p>
 <div class="auth-feature-list"><span><?= ic('shield-check') ?> Autenticação e perfis de acesso</span><span><?= ic('map') ?> Monitoramento territorial</span><span><?= ic('chart') ?> Indicadores e relatórios</span></div>
 <small>OBRAS360 · Plataforma administrativa · Dados protegidos</small>
 </div><div class="auth-card-wrap"><div class="auth-card">
  <div class="auth-logo"><span class="brand-mark"><?= ic('hard-hat') ?></span><div><strong>OBRAS<span>360</span></strong><small>GESTÃO MUNICIPAL</small></div></div>
  <span class="eyebrow">ACESSO RESTRITO</span>
  <?php if (empty($databaseConfigured)): ?><div class="notice notice-warning login-setup-notice" role="status"><?= ic('alert') ?><span><strong>Conexão MySQL pendente.</strong> Configure a senha no arquivo privado <code>config/database.local.php</code> na Hostinger. Consulte o README.</span></div><?php endif; ?><h2>Bem-vindo de volta</h2><p>Entre com suas credenciais institucionais.</p>
  <form method="post" action="<?= h(url('login')) ?>" autocomplete="on" class="form-stack">
   <?= csrf_field() ?><label class="field"><span>E-mail institucional</span><input type="email" name="email" required maxlength="190" autocomplete="username" placeholder="seu.email@prefeitura.gov.br" autofocus></label>
   <label class="field"><span>Senha</span><span class="password-wrap"><input type="password" id="login-password" name="password" required autocomplete="current-password" placeholder="Digite sua senha"><button type="button" class="password-visibility" data-password-toggle="login-password" aria-controls="login-password" aria-label="Mostrar senha" aria-pressed="false" title="Mostrar senha"><?= ic('eye') ?></button></span></label>
   <button class="btn btn-primary btn-block" type="submit">Entrar no sistema <?= ic('arrow-right') ?></button>
  </form>
  <div class="auth-foot-note"><?= ic('shield-check') ?> Sessão protegida e tentativas de acesso monitoradas.</div>
  <p class="small muted">Primeiro acesso? O administrador fornece sua senha temporária. <a class="text-link" href="<?= h(url('apresentacao')) ?>">Conheça os módulos do sistema</a>.</p>
 </div></div></div>
