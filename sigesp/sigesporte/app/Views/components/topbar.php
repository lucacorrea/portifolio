<?php
declare(strict_types=1);
use Sigesp\Core\View;
$user = is_array($user ?? null) ? $user : ['nome'=>'Marcos Oliveira','cargo'=>'Administrador do Sistema'];
?>
<header class="topbar">
    <div class="topbar__start"><button class="icon-button topbar__menu" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menu">☰</button><?php View::component('breadcrumb',['items'=>[$title??'Painel']]); ?></div>
    <div class="topbar__actions">
        <span class="demo-badge" title="Ambiente de demonstração">DEMO</span>
        <button class="icon-button topbar__search" type="button" data-toast="Pesquisa global simulada." aria-label="Pesquisar">⌕</button>
        <label class="global-search"><span aria-hidden="true">⌕</span><input type="search" placeholder="Pesquisar no SIGESP" aria-label="Pesquisar no SIGESP" data-global-search></label>
        <button class="icon-button" type="button" data-toast="Nenhuma nova notificação na demonstração." aria-label="Notificações">♧</button>
        <button class="icon-button fullscreen-button" type="button" data-fullscreen-toggle aria-label="Entrar em tela cheia" aria-pressed="false">⛶</button>
        <div class="user-menu" data-dropdown><button class="user-menu__trigger" type="button" data-dropdown-toggle aria-expanded="false" aria-label="Menu do usuário: <?= View::e($user['nome']) ?>"><?php View::component('avatar',['name'=>$user['nome']]); ?><span class="user-menu__name"><?= View::e($user['nome']) ?></span></button><div class="dropdown__menu dropdown__menu--right" data-dropdown-menu hidden><a href="<?= View::e(View::url('/configuracoes')) ?>">Configurações</a><a href="<?= View::e(View::url('/login')) ?>">Sair da demonstração</a></div></div>
    </div>
</header>
