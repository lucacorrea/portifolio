<?php declare(strict_types=1); ?>
<div class="modal fade" id="frontendRowActionModal" tabindex="-1" aria-labelledby="frontendRowActionTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable frontend-row-action-dialog">
        <div class="modal-content frontend-row-action-modal">
            <div class="modal-header">
                <div class="min-w-0">
                    <div class="eyebrow"><i class="bi bi-list-check"></i> Ações do registro</div>
                    <h2 class="modal-title fs-5 text-truncate" id="frontendRowActionTitle" data-row-action-title>Registro selecionado</h2>
                    <p class="frontend-row-action-subtitle" data-row-action-subtitle>Escolha o que deseja fazer.</p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="frontend-row-action-summary" data-row-action-summary></div>
                <div class="frontend-row-action-section-title">Ações disponíveis</div>
                <div class="frontend-row-action-list" data-row-action-list></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
