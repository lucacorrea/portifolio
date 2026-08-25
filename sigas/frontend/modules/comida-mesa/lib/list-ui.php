<?php

declare(strict_types=1);

function cm_list_header(string $kicker, string $title, string $description, string $createLabel = '', string $createTarget = '', string $icon = 'plus-lg'): void
{
    ?>
    <div class="cm-list-hero">
        <div>
            <div class="card-kicker"><?= cm_h($kicker) ?></div>
            <h2><?= cm_h($title) ?></h2>
            <p><?= cm_h($description) ?></p>
        </div>
        <?php if ($createLabel !== '' && $createTarget !== ''): ?>
            <div class="cm-page-actions">
                <button class="btn btn-primary" type="button" data-cm-open="<?= cm_h($createTarget) ?>">
                    <i class="bi bi-<?= cm_h($icon) ?>"></i><?= cm_h($createLabel) ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/** @param array<int,array<string,mixed>> $metrics */
function cm_metrics(array $metrics): void
{
    if ($metrics === []) return;
    ?>
    <div class="cm-kpi-grid">
        <?php foreach ($metrics as $metric): ?>
            <?php $tone = (string) ($metric['tone'] ?? 'neutral'); ?>
            <article class="cm-kpi cm-kpi--<?= cm_h($tone) ?>">
                <span><?= cm_h($metric['label'] ?? 'Indicador') ?></span>
                <strong><?= cm_h((string) ($metric['value'] ?? 0)) ?></strong>
                <?php if (!empty($metric['hint'])): ?><small><?= cm_h((string) $metric['hint']) ?></small><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}

function cm_empty(string $title, string $text, string $icon = 'inbox'): void
{
    ?>
    <div class="cm-empty-state">
        <i class="bi bi-<?= cm_h($icon) ?>"></i>
        <strong><?= cm_h($title) ?></strong>
        <span><?= cm_h($text) ?></span>
    </div>
    <?php
}

function cm_action_modal(): void
{
    ?>
    <div class="modal fade" id="beneficiaryActionModal" tabindex="-1" aria-labelledby="beneficiaryActionModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable cm-action-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div class="eyebrow"><i class="bi bi-grid"></i> Ações do beneficiário</div>
                        <h2 class="modal-title" id="beneficiaryActionModalTitle" data-cm-action-name>Família beneficiária</h2>
                        <p class="cm-modal-subtitle"><span data-cm-action-code>Sem código</span> · <span data-cm-action-pole>Sem polo</span></p>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="cm-action-status-grid">
                        <div><span>Situação no programa</span><strong data-cm-action-program>—</strong></div>
                        <div><span>Situação da entrega</span><strong data-cm-action-delivery>—</strong></div>
                    </div>
                    <div class="cm-action-grid">
                        <button type="button" class="cm-action-card" data-cm-action-command="view"><i class="bi bi-eye"></i><span><strong>Visualizar</strong><small>Dados completos da família</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" class="cm-action-card" data-cm-action-command="edit"><i class="bi bi-pencil-square"></i><span><strong>Editar cadastro</strong><small>Atualizar dados da inscrição</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" class="cm-action-card" data-cm-action-command="delivery"><i class="bi bi-basket2"></i><span><strong data-cm-action-delivery-text>Registrar entrega</strong><small>Operação da competência atual</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" class="cm-action-card" data-cm-action-command="document"><i class="bi bi-paperclip"></i><span><strong>Enviar documento</strong><small>Anexar arquivo ao cadastro</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" class="cm-action-card" data-cm-action-command="history"><i class="bi bi-clock-history"></i><span><strong>Histórico</strong><small>Movimentações e entregas</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" class="cm-action-card cm-action-card--danger" data-cm-action-command="cancel"><i class="bi bi-x-circle"></i><span><strong>Cancelar entrega</strong><small>Exige justificativa e permissão</small></span><i class="bi bi-chevron-right"></i></button>
                    </div>
                    <p class="cm-action-note" data-cm-action-note>As ações respeitam as permissões do usuário e a situação atual do benefício.</p>
                </div>
                <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Fechar</button></div>
            </div>
        </div>
    </div>
    <?php
}
