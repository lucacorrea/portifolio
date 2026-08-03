<?php
use Sigesp\Core\View;

$tabs = $tabs ?? [];
static $tabsInstance = 0;
$tabsInstance++;
$tabListId = 'tabs-' . $tabsInstance;
?>
<div class="tabs" id="<?= View::e($tabListId) ?>" role="tablist" aria-label="Seções da página">
    <?php foreach ($tabs as $index => $tab): ?>
        <?php $panelId = (string) $tab['id']; $tabId = $panelId . '-tab-' . $tabsInstance; ?>
        <button id="<?= View::e($tabId) ?>" role="tab" type="button"
            data-tab-target="<?= View::e($panelId) ?>"
            aria-controls="<?= View::e($panelId) ?>"
            aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
            tabindex="<?= $index === 0 ? '0' : '-1' ?>">
            <?= View::e($tab['label']) ?>
        </button>
    <?php endforeach; ?>
</div>
