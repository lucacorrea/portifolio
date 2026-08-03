<?php
use Sigesp\Core\View;

$dialogId = (string) ($id ?? 'confirm-dialog');
$titleId = $dialogId . '-title';
$messageId = $dialogId . '-message';
?>
<div class="modal" id="<?= View::e($dialogId) ?>" data-confirm-dialog role="alertdialog"
    aria-modal="true" aria-labelledby="<?= View::e($titleId) ?>"
    aria-describedby="<?= View::e($messageId) ?>" hidden>
    <div class="modal__backdrop" data-modal-close aria-hidden="true"></div>
    <section class="modal__panel" data-modal-panel tabindex="-1">
        <header>
            <h2 id="<?= View::e($titleId) ?>"><?= View::e($title ?? 'Confirmar ação') ?></h2>
            <button type="button" class="icon-button" data-confirm-cancel aria-label="Fechar confirmação">×</button>
        </header>
        <p id="<?= View::e($messageId) ?>" data-confirm-message><?= View::e($message ?? 'Confirma esta ação simulada?') ?></p>
        <footer class="form-actions">
            <button class="button button--secondary" type="button" data-confirm-cancel>Cancelar</button>
            <button class="button" type="button" data-confirm-accept>Confirmar</button>
        </footer>
    </section>
</div>
