<?php
declare(strict_types=1);
use Sigesp\Core\View;

$menu = is_array($menu ?? null) ? $menu : [];
$user = is_array($user ?? null) ? $user : ['nome'=>'Marcos Oliveira','cargo'=>'Administrador do Sistema'];
$currentPath = (string) ($currentPath ?? '/dashboard');
?>
<aside class="sidebar" id="sidebar" aria-label="Menu principal">
    <div class="sidebar__head"><a class="brand" href="<?= View::e(View::url('/dashboard')) ?>" aria-label="SIGESP — Visão geral"><span class="brand-mark" aria-hidden="true">S</span><span><strong>SIGESP</strong><small>Gestão Esportiva</small></span></a><button class="sidebar__compact icon-button" type="button" data-sidebar-compact aria-controls="sidebar" aria-expanded="true" aria-label="Recolher menu">‹</button></div>
    <nav class="sidebar__nav" id="sidebar-navigation" aria-label="Navegação do sistema">
        <?php foreach($menu as [$label,$href,$icon]): ?>
            <?php if($href===null): ?><p><?= View::e($label) ?></p>
            <?php else: ?><?php $active=$currentPath===$href||($href!=='/dashboard'&&str_starts_with($currentPath,$href.'/')); ?><a href="<?= View::e(View::url($href)) ?>" class="<?= $active?'is-active':'' ?>" aria-label="<?= View::e($label) ?>"<?= $active?' aria-current="page"':'' ?>><span aria-hidden="true"><?= View::e($icon) ?></span><b><?= View::e($label) ?></b></a><?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <footer class="sidebar__user"><?php View::component('avatar',['name'=>$user['nome']]); ?><div><strong><?= View::e($user['nome']) ?></strong><small><?= View::e($user['cargo']) ?></small></div><a class="icon-button" href="<?= View::e(View::url('/login')) ?>" aria-label="Voltar ao login demonstrativo">↪</a></footer>
</aside>
