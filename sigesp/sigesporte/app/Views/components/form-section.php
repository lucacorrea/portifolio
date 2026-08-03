<?php use Sigesp\Core\View; ?>
<fieldset class="form-section"><legend><?= View::e($title ?? '') ?></legend><?php if (!empty($description)): ?><p><?= View::e($description) ?></p><?php endif; ?><?= $content ?? '' ?></fieldset>
