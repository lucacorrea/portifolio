<?php

declare(strict_types=1);

/**
 * Componentes de listagem/modal do módulo Meu Primeiro Emprego.
 * Mantém as páginas operacionais consistentes sem duplicar o esqueleto das ações.
 */

function pe_list_header(string $kicker, string $title, string $description, string $createLabel, string $createTarget, string $icon = 'plus-lg'): void
{
    ?>
    <div class="pe-page-hero pe-list-hero">
        <div>
            <div class="card-kicker"><?= pe_h($kicker) ?></div>
            <h2><?= pe_h($title) ?></h2>
            <p><?= pe_h($description) ?></p>
        </div>
        <div class="pe-page-actions pe-no-print">
            <button class="btn btn-primary" type="button" data-pe-open="<?= pe_h($createTarget) ?>" data-pe-mode="create">
                <i class="bi bi-<?= pe_h($icon) ?>"></i> <?= pe_h($createLabel) ?>
            </button>
        </div>
    </div>
    <?php
}

function pe_list_toolbar(int $count, string $placeholder = 'Buscar na listagem...'): void
{
    ?>
    <div class="pe-list-toolbar pe-no-print">
        <div class="pe-list-count"><strong><?= $count ?></strong> registro(s)</div>
        <label class="pe-list-search">
            <i class="bi bi-search"></i>
            <input class="form-control" type="search" placeholder="<?= pe_h($placeholder) ?>" data-pe-list-search autocomplete="off">
        </label>
    </div>
    <?php
}

function pe_crud_actions_dialog(string $dialogId, string $entityLabel, string $viewTarget, string $editTarget, string $deleteTarget): void
{
    ?>
    <dialog class="pe-modal pe-modal--actions" id="<?= pe_h($dialogId) ?>">
        <div class="pe-modal__shell">
            <header class="pe-modal__header">
                <div>
                    <div class="card-kicker"><?= pe_h($entityLabel) ?></div>
                    <h2 data-pe-current-title>Registro selecionado</h2>
                    <p data-pe-current-subtitle>Escolha uma ação.</p>
                </div>
                <button type="button" class="pe-modal__close" data-pe-dialog-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
            </header>
            <div class="pe-modal__body">
                <div class="pe-modal-actions-title">Ações disponíveis</div>
                <div class="pe-modal-actions">
                    <button class="pe-modal-action" type="button" data-pe-open="<?= pe_h($viewTarget) ?>" data-pe-mode="view">
                        <span class="pe-modal-action__icon"><i class="bi bi-eye"></i></span>
                        <span><strong>Visualizar</strong><small>Consultar todos os dados deste registro</small></span>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button class="pe-modal-action pe-modal-action--primary" type="button" data-pe-open="<?= pe_h($editTarget) ?>" data-pe-mode="edit">
                        <span class="pe-modal-action__icon"><i class="bi bi-pencil-square"></i></span>
                        <span><strong>Editar</strong><small>Atualizar os dados cadastrados</small></span>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button class="pe-modal-action pe-modal-action--danger" type="button" data-pe-open="<?= pe_h($deleteTarget) ?>" data-pe-mode="delete">
                        <span class="pe-modal-action__icon"><i class="bi bi-trash3"></i></span>
                        <span><strong>Excluir</strong><small>Remover o registro após confirmação</small></span>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </dialog>
    <?php
}

function pe_delete_dialog(string $dialogId, string $entityLabel, string $action): void
{
    ?>
    <dialog class="pe-modal pe-modal--confirm" id="<?= pe_h($dialogId) ?>">
        <div class="pe-modal__shell">
            <header class="pe-modal__header">
                <div>
                    <div class="card-kicker">Excluir <?= pe_h($entityLabel) ?></div>
                    <h2>Confirmar exclusão</h2>
                    <p>Esta ação não deve ser usada apenas para corrigir dados. Prefira editar quando o registro ainda for válido.</p>
                </div>
                <button type="button" class="pe-modal__close" data-pe-dialog-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
            </header>
            <div class="pe-modal__body">
                <div class="pe-delete-warning"><i class="bi bi-exclamation-triangle"></i><div><strong data-pe-current-title>Registro selecionado</strong><span>A exclusão será executada somente após sua confirmação.</span></div></div>
                <form method="post" class="pe-delete-form">
                    <?= pe_csrf_field() ?>
                    <input type="hidden" name="pe_action" value="<?= pe_h($action) ?>">
                    <input type="hidden" name="id" value="" data-pe-field="id">
                    <label class="pe-check-option pe-delete-confirm"><input type="checkbox" required><span>Confirmo que desejo excluir este registro.</span></label>
                    <footer class="pe-action-modal-footer">
                        <button type="button" class="btn btn-light" data-pe-dialog-close>Cancelar</button>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Excluir</button>
                    </footer>
                </form>
            </div>
        </div>
    </dialog>
    <?php
}
