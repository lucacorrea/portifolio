<?php

declare(strict_types=1);

cm_require('comida_mesa.consultar_cpf');

$pageDefinition = [
    'title' => 'Consultar CPF',
    'description' => 'Localize a pessoa pelo CPF e siga um fluxo simples para conferir situação, dados, entregas e ações disponíveis.',
    'actions' => [
        ['label' => 'Beneficiários', 'icon' => 'people', 'href' => 'comida-mesa/beneficiarios.php'],
        ['label' => 'Nova consulta', 'icon' => 'arrow-counterclockwise', 'primary' => true, 'href' => 'comida-mesa/consulta-cpf.php'],
    ],
    'demo' => false,
    'show_states' => false,
];

$frontendContext['consultaDocumento'] = [
    'permissions' => [
        'create' => cm_can('comida_mesa.cadastrar'),
        'edit' => cm_can('comida_mesa.editar'),
        'deliver' => cm_can('comida_mesa.entregar'),
        'cancelDelivery' => cm_can('comida_mesa.cancelar_entrega'),
        'manageCompetences' => cm_can('comida_mesa.competencias_gerenciar'),
        'viewDocuments' => cm_can('comida_mesa.documentos_visualizar'),
        'viewHistory' => cm_can('comida_mesa.historico_visualizar'),
    ],
];

$pageExtraStyles = [
    'assets/css/modules/comida-mesa-consulta-cpf.css',
];
$pageExtraScripts = [
    'assets/js/modules/comida-mesa-consulta-cpf.js',
];

ob_start();
?>
<section class="cm-cpf-page" data-cm-cpf-consulta>
    <article class="content-card cm-cpf-search-card">
        <div class="cm-cpf-search-head">
            <div>
                <div class="card-kicker">Consulta operacional</div>
                <h2>Localizar beneficiário pelo CPF</h2>
                <p>Digite somente o CPF. Depois da consulta, o sistema organiza o resultado e as ações em etapas sequenciais.</p>
            </div>
            <span class="cm-cpf-safe-badge"><i class="bi bi-shield-check"></i> Somente consulta</span>
        </div>

        <div class="cm-cpf-steps" aria-label="Etapas da consulta">
            <div class="cm-cpf-step is-active" data-cm-step="1">
                <span>1</span>
                <div><strong>Informar CPF</strong><small>Digite os 11 números</small></div>
            </div>
            <div class="cm-cpf-step" data-cm-step="2">
                <span>2</span>
                <div><strong>Conferir situação</strong><small>Família, inscrição e entrega</small></div>
            </div>
            <div class="cm-cpf-step" data-cm-step="3">
                <span>3</span>
                <div><strong>Escolher ação</strong><small>Visualizar, editar ou registrar</small></div>
            </div>
        </div>

        <form class="cm-cpf-form" id="cmCpfForm" action="api/comida-mesa/consultar-cpf.php" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_consultar_cpf')) ?>">
            <input type="hidden" name="consulta_modo" value="entrega_rapida">

            <label class="form-label" for="cmCpfInput">CPF da pessoa</label>
            <div class="cm-cpf-input-row">
                <div class="cm-cpf-input-wrap">
                    <i class="bi bi-person-vcard"></i>
                    <input
                        class="form-control form-control-lg"
                        id="cmCpfInput"
                        name="cpf"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="000.000.000-00"
                        maxlength="14"
                        aria-describedby="cmCpfHelp cmCpfFeedback"
                    >
                </div>
                <button class="btn btn-primary btn-lg" type="submit" data-cm-cpf-submit>
                    <i class="bi bi-search"></i>
                    <span>Consultar</span>
                </button>
            </div>
            <div class="invalid-feedback" id="cmCpfFeedback">Informe um CPF válido.</div>
            <div class="cm-cpf-form-meta" id="cmCpfHelp">
                <span><i class="bi bi-info-circle"></i>A consulta não altera o cadastro.</span>
                <span><i class="bi bi-keyboard"></i>Você pode pressionar Enter para consultar.</span>
            </div>
            <div class="cm-cpf-inline-alert" data-cm-cpf-alert aria-live="polite"></div>
        </form>

        <section class="cm-cpf-last-result" data-cm-last-result hidden aria-live="polite"></section>
    </article>
</section>

<!-- ETAPA 2: RESULTADO -->
<div class="modal fade cm-flow-modal" id="cmCpfResultModal" tabindex="-1" aria-labelledby="cmCpfResultTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable cm-flow-dialog cm-flow-dialog--result">
        <div class="modal-content">
            <div class="modal-header cm-flow-header">
                <div>
                    <div class="cm-flow-eyebrow"><span>2</span> Conferir situação</div>
                    <h2 class="modal-title" id="cmCpfResultTitle">Resultado da consulta</h2>
                    <p>Confira a pessoa antes de continuar para as ações operacionais.</p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" data-cm-result-body></div>
            <div class="modal-footer cm-flow-footer" data-cm-result-footer></div>
        </div>
    </div>
</div>

<!-- ETAPA 3: CENTRAL DE AÇÕES -->
<div class="modal fade cm-flow-modal" id="cmCpfActionsModal" tabindex="-1" aria-labelledby="cmCpfActionsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable cm-flow-dialog cm-flow-dialog--actions">
        <div class="modal-content">
            <div class="modal-header cm-flow-header">
                <div>
                    <div class="cm-flow-eyebrow"><span>3</span> Escolher ação</div>
                    <h2 class="modal-title" id="cmCpfActionsTitle">Ações do beneficiário</h2>
                    <p data-cm-actions-subtitle>Escolha o que deseja fazer sem sair do fluxo da consulta.</p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="cm-action-person" data-cm-actions-person></div>
                <div class="cm-action-grid" data-cm-actions-grid></div>
                <div class="cm-action-note"><i class="bi bi-shield-check"></i>As ações respeitam as permissões do usuário e a situação atual do benefício.</div>
            </div>
            <div class="modal-footer cm-flow-footer">
                <button class="btn btn-light" type="button" data-cm-back-result><i class="bi bi-arrow-left"></i>Voltar ao resultado</button>
                <button class="btn btn-outline-secondary" type="button" data-cm-new-query><i class="bi bi-arrow-repeat"></i>Nova consulta</button>
            </div>
        </div>
    </div>
</div>

<!-- VISUALIZAÇÃO ORGANIZADA EM ABAS -->
<div class="modal fade cm-flow-modal" id="cmCpfDetailModal" tabindex="-1" aria-labelledby="cmCpfDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable cm-flow-dialog">
        <div class="modal-content">
            <div class="modal-header cm-flow-header">
                <div>
                    <div class="cm-flow-eyebrow"><i class="bi bi-eye"></i> Visualização</div>
                    <h2 class="modal-title" id="cmCpfDetailTitle">Dados do beneficiário</h2>
                    <p>Cadastro, entregas, documentos e histórico organizados em uma única visualização.</p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="cm-detail-loading" data-cm-detail-loading>
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    <span>Carregando informações...</span>
                </div>
                <div data-cm-detail-content hidden></div>
            </div>
            <div class="modal-footer cm-flow-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal"><i class="bi bi-arrow-left"></i>Voltar às ações</button>
            </div>
        </div>
    </div>
</div>

<!-- REGISTRAR / REATIVAR ENTREGA -->
<div class="modal fade cm-flow-modal" id="cmCpfDeliveryModal" tabindex="-1" aria-labelledby="cmCpfDeliveryTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable cm-flow-dialog">
        <div class="modal-content">
            <form id="cmCpfDeliveryForm" action="api/comida-mesa/registrar-entrega.php" method="post" novalidate>
                <div class="modal-header cm-flow-header">
                    <div>
                        <div class="cm-flow-eyebrow"><i class="bi bi-basket2"></i> Entrega mensal</div>
                        <h2 class="modal-title" id="cmCpfDeliveryTitle">Registrar entrega</h2>
                        <p>Confira a competência e informe quem está recebendo o benefício.</p>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_registrar_entrega')) ?>">
                    <input type="hidden" name="inscricao_id">
                    <input type="hidden" name="competencia_id">
                    <div data-cm-form-alert></div>

                    <div class="cm-operation-summary">
                        <div><span>Responsável</span><strong data-cm-delivery-name>—</strong></div>
                        <div><span>Família</span><strong data-cm-delivery-family>—</strong></div>
                        <div><span>Competência</span><strong data-cm-delivery-competence>—</strong></div>
                        <div><span>Polo</span><strong data-cm-delivery-pole>—</strong></div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Nome do recebedor <span class="text-danger">*</span></label>
                            <input class="form-control" name="recebedor_nome" maxlength="160" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CPF do recebedor</label>
                            <input class="form-control" name="recebedor_cpf" inputmode="numeric" maxlength="14">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parentesco</label>
                            <input class="form-control" name="recebedor_parentesco" maxlength="80" placeholder="Ex.: responsável familiar">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observação</label>
                            <textarea class="form-control" name="observacao" rows="3" maxlength="500"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer cm-flow-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal"><i class="bi bi-arrow-left"></i>Voltar às ações</button>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i>Confirmar entrega</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CANCELAMENTO -->
<div class="modal fade cm-flow-modal" id="cmCpfCancelModal" tabindex="-1" aria-labelledby="cmCpfCancelTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered cm-flow-dialog cm-flow-dialog--small">
        <div class="modal-content">
            <form id="cmCpfCancelForm" action="api/comida-mesa/cancelar-entrega.php" method="post" novalidate>
                <div class="modal-header cm-flow-header cm-flow-header--danger">
                    <div>
                        <div class="cm-flow-eyebrow"><i class="bi bi-exclamation-triangle"></i> Ação crítica</div>
                        <h2 class="modal-title" id="cmCpfCancelTitle">Cancelar entrega</h2>
                        <p>O registro não será apagado. O cancelamento ficará preservado no histórico.</p>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_cancelar_entrega')) ?>">
                    <input type="hidden" name="inscricao_id">
                    <input type="hidden" name="competencia_id">
                    <div data-cm-form-alert></div>
                    <div class="cm-danger-notice"><i class="bi bi-info-circle"></i><span>Informe um motivo objetivo para manter a rastreabilidade da operação.</span></div>
                    <label class="form-label mt-3">Motivo <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="motivo" rows="4" minlength="10" maxlength="255" required></textarea>
                </div>
                <div class="modal-footer cm-flow-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal"><i class="bi bi-arrow-left"></i>Voltar às ações</button>
                    <button class="btn btn-danger" type="submit"><i class="bi bi-x-circle"></i>Confirmar cancelamento</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$pageCustomContent = (string) ob_get_clean();
