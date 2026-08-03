<?php use Sigesp\Core\View; $tone = $tone ?? 'neutral'; ?>
<span class="badge badge--<?= View::e($tone) ?>"><?= View::e($label ?? '') ?></span>
