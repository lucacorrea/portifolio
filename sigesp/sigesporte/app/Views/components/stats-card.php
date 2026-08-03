<?php use Sigesp\Core\View; ?>
<article class="stat-card"><span class="stat-card__icon" aria-hidden="true"><?= View::e($icon ?? '•') ?></span><div><small><?= View::e($label ?? '') ?></small><strong><?= View::e($value ?? '—') ?></strong><?php if (!empty($detail)): ?><span><?= View::e($detail) ?></span><?php endif; ?></div></article>
