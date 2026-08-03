<?php use Sigesp\Core\View; ?>
<label class="upload-area"><input type="file" data-upload-input accept="<?= View::e($accept ?? 'image/*,.pdf') ?>"><span aria-hidden="true">⇧</span><strong><?= View::e($label ?? 'Enviar arquivo') ?></strong><small><?= View::e($hint ?? 'Selecione um arquivo do dispositivo.') ?></small></label>
