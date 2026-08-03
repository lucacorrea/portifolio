<?php use Sigesp\Core\View; ?>
<div data-upload>
    <label class="upload-area">
        <input type="file" data-upload-input accept="<?= View::e($accept ?? 'image/*,.pdf') ?>">
        <span aria-hidden="true">⇧</span>
        <strong><?= View::e($label ?? 'Selecionar arquivo') ?></strong>
        <small><?= View::e($hint ?? 'Preview local; o arquivo não será enviado.') ?></small>
    </label>
    <div class="record-card" data-upload-preview role="status" hidden>
        <div class="record-card__head">
            <img class="avatar avatar--large" data-upload-image alt="Pré-visualização do arquivo selecionado" hidden>
            <span class="avatar" data-upload-icon aria-hidden="true">▣</span>
            <div>
                <strong data-upload-name></strong>
                <small data-upload-meta></small>
                <small>Arquivo selecionado apenas para demonstração.</small>
            </div>
        </div>
        <div class="record-card__meta">
            <button class="button button--secondary" type="button" data-upload-replace>Substituir</button>
            <button class="button button--secondary" type="button" data-upload-remove>Remover</button>
        </div>
    </div>
</div>
