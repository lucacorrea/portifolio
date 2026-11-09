<?php declare(strict_types=1); ?>
<div class="modal fade" id="frontendDetailModal" tabindex="-1" aria-labelledby="frontendDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="eyebrow"><i class="bi bi-eye"></i> Visualização do registro</div>
                    <h2 class="modal-title fs-5" id="frontendDetailTitle">Detalhes do registro</h2>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <dl class="frontend-detail-list" data-detail-content></dl>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Fechar</button>
                <button class="btn btn-primary" type="button" data-return-row-actions>
                    <i class="bi bi-list-check"></i> Ações
                </button>
            </div>
        </div>
    </div>
</div>
