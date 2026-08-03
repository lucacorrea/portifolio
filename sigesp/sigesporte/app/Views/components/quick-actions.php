<?php use Sigesp\Core\View; $actions = $actions ?? []; ?>
<section class="quick-actions"><h2>Atalhos rápidos</h2><div><?php foreach ($actions as $action): ?><a href="<?= View::e(View::url((string) $action['href'])) ?>"><span><?= View::e($action['icon']) ?></span><?= View::e($action['label']) ?></a><?php endforeach; ?></div></section>
