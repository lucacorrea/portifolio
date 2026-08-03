<?php use Sigesp\Core\View; ?>
<div class="confirm-dialog" data-confirm-dialog hidden><p><?= View::e($message ?? 'Confirma esta ação?') ?></p><button class="button button--secondary" type="button" data-confirm-cancel>Cancelar</button><button class="button" type="button" data-confirm-accept>Confirmar</button></div>
