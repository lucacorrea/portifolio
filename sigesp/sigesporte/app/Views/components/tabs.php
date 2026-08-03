<?php use Sigesp\Core\View; $tabs = $tabs ?? []; ?>
<div class="tabs" role="tablist"><?php foreach ($tabs as $index => $tab): ?><button role="tab" type="button" data-tab-target="<?= View::e($tab['id']) ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"><?= View::e($tab['label']) ?></button><?php endforeach; ?></div>
