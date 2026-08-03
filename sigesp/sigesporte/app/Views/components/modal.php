<?php
use Sigesp\Core\View;

$modalId = (string) ($id ?? 'modal');
$titleId = $modalId . '-title';
?>
<div class="modal" id="<?= View::e($modalId) ?>" role="dialog" aria-modal="true"
    aria-labelledby="<?= View::e($titleId) ?>" hidden>
    <div class="modal__backdrop" data-modal-close aria-hidden="true"></div>
    <section class="modal__panel" data-modal-panel tabindex="-1">
        <header>
            <h2 id="<?= View::e($titleId) ?>"><?= View::e($title ?? 'Detalhes') ?></h2>
            <button type="button" class="icon-button" data-modal-close aria-label="Fechar modal">×</button>
        </header>
        <div><?= $content ?? '' ?></div>
    </section>
</div>
