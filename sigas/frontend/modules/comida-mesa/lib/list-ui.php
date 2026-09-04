<?php

declare(strict_types=1);

function cm_list_header(string $kicker, string $title, string $description, string $createLabel = '', string $createTarget = '', string $icon = 'plus-lg'): void
{
    ?>
    <div class="cm-list-hero sigas-workspace-hero">
        <div>
            <div class="card-kicker"><?= cm_h($kicker) ?></div>
            <h2><?= cm_h($title) ?></h2>
            <p><?= cm_h($description) ?></p>
        </div>
        <?php if ($createLabel !== '' && $createTarget !== ''): ?>
            <div class="cm-page-actions sigas-page-actions">
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
    <div class="cm-kpi-grid sigas-kpi-grid">
        <?php foreach ($metrics as $metric): ?>
            <?php $tone = (string) ($metric['tone'] ?? 'neutral'); ?>
            <article class="cm-kpi sigas-kpi cm-kpi--<?= cm_h($tone) ?>">
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
    <div class="cm-empty-state sigas-empty-state">
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
            <div class="modal-content sigas-action-modal">
                <div class="modal-header">
                    <div>
                        <div class="eyebrow">Beneficiário</div>
                        <h2 class="modal-title" id="beneficiaryActionModalTitle" data-cm-action-name>Família beneficiária</h2>
                        <p class="cm-modal-subtitle"><span data-cm-action-code>Sem código</span> · <span data-cm-action-pole>Sem polo</span></p>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="cm-action-review-box">
                        <div class="cm-action-review-box__heading">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            <span>Situação no programa</span>
                        </div>
                        <strong data-cm-action-program>—</strong>
                        <small data-cm-action-note>As ações disponíveis respeitam as permissões do usuário e a situação atual do benefício.</small>
                    </div>

                    <div class="cm-action-summary-grid">
                        <div>
                            <span>Código</span>
                            <strong data-cm-action-code>—</strong>
                        </div>
                        <div>
                            <span>Polo</span>
                            <strong data-cm-action-pole>—</strong>
                        </div>
                        <div>
                            <span>Entrega</span>
                            <strong data-cm-action-delivery>—</strong>
                        </div>
                    </div>

                    <div class="cm-action-section-title">Ações do beneficiário</div>

                    <div class="cm-action-grid">
                        <button type="button" class="cm-action-card" data-cm-action-command="view">
                            <i class="bi bi-eye"></i>
                            <span><strong>Visualizar cadastro</strong><small>Consultar os dados completos da família</small></span>
                            <i class="bi bi-chevron-right"></i>
                        </button>

                        <button type="button" class="cm-action-card" data-cm-action-command="edit">
                            <i class="bi bi-pencil-square"></i>
                            <span><strong>Editar cadastro</strong><small>Atualizar os dados da inscrição</small></span>
                            <i class="bi bi-chevron-right"></i>
                        </button>

                        <button type="button" class="cm-action-card" data-cm-action-command="delivery">
                            <i class="bi bi-basket2"></i>
                            <span><strong data-cm-action-delivery-text>Registrar entrega</strong><small>Executar a operação da competência atual</small></span>
                            <i class="bi bi-chevron-right"></i>
                        </button>

                        <button type="button" class="cm-action-card" data-cm-action-command="document">
                            <i class="bi bi-paperclip"></i>
                            <span><strong>Enviar documento</strong><small>Anexar arquivo ao cadastro da família</small></span>
                            <i class="bi bi-chevron-right"></i>
                        </button>

                        <button type="button" class="cm-action-card" data-cm-action-command="history">
                            <i class="bi bi-clock-history"></i>
                            <span><strong>Histórico</strong><small>Consultar movimentações e entregas registradas</small></span>
                            <i class="bi bi-chevron-right"></i>
                        </button>

                        <button type="button" class="cm-action-card cm-action-card--danger" data-cm-action-command="cancel">
                            <i class="bi bi-x-circle"></i>
                            <span><strong>Cancelar entrega</strong><small>Ação restrita que exige justificativa e permissão</small></span>
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="beneficiaryConflictModal" tabindex="-1" aria-labelledby="beneficiaryConflictModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable cm-action-dialog">
            <div class="modal-content sigas-action-modal">
                <div class="modal-header">
                    <div>
                        <div class="eyebrow"><i class="bi bi-shield-exclamation"></i> Regularização de vínculo</div>
                        <h2 class="modal-title" id="beneficiaryConflictModalTitle" data-cm-conflict-name>Beneficiário com conflito</h2>
                        <p class="cm-modal-subtitle"><span data-cm-conflict-code>Sem código</span> · <span data-cm-conflict-location>Localidade não informada</span></p>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="cm-action-status-grid">
                        <div><span>Situação no programa</span><strong data-cm-conflict-program>—</strong></div>
                        <div><span>Situação da entrega</span><strong data-cm-conflict-delivery>Bloqueada</strong></div>
                    </div>

                    <div class="alert alert-warning mt-3 mb-3" role="alert">
                        <div class="d-flex gap-2 align-items-start">
                            <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
                            <div>
                                <strong>O que está bloqueando este registro?</strong>
                                <div class="mt-1" data-cm-conflict-reason>O vínculo ao cadastro central precisa ser revisado.</div>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex gap-2 align-items-start mb-2">
                            <i class="bi bi-tools" aria-hidden="true"></i>
                            <div>
                                <strong data-cm-conflict-resolution-title>Como desbloquear</strong>
                                <small class="d-block text-muted">Corrija o vínculo antes de liberar operações que dependem da inscrição oficial.</small>
                            </div>
                        </div>
                        <ol class="mb-0 ps-3" data-cm-conflict-steps>
                            <li>Abra a conferência da importação.</li>
                            <li>Revise o vínculo indicado pelo sistema.</li>
                            <li>Após corrigir, reprocessar os vínculos da importação.</li>
                        </ol>
                    </div>

                    <div class="alert alert-light border mb-0">
                        <strong><i class="bi bi-info-circle"></i> Regra de segurança</strong>
                        <div class="mt-1">A pessoa continua visível na lista e mantém a decisão do programa. A entrega só é liberada quando existir um vínculo oficial consistente, evitando duplicidade de pessoa, família ou benefício.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Fechar</button>
                    <a class="btn btn-primary" href="#" data-cm-conflict-review>
                        <i class="bi bi-search"></i> Abrir conferência da importação
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
}
