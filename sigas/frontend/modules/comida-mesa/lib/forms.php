<?php

declare(strict_types=1);

/** @param list<array<string,mixed>> $poles @param array<string,string> $programStatuses */
function cm_registration_fields(array $poles, array $programStatuses, string $csrfToken): void
{
    ?>
    <input type="hidden" name="_csrf" value="<?= cm_h($csrfToken) ?>">
    <input type="hidden" name="inscricao_id">
    <input type="hidden" name="versao_atualizacao">
    <div data-form-alert></div>

    <section class="cm-form-section">
        <div class="cm-form-section__title"><span>1</span><div><h3>Responsável familiar</h3><p>Identificação principal da família beneficiária.</p></div></div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label required">Nome</label><input class="form-control" name="nome" autocomplete="name" required></div>
            <div class="col-md-3"><label class="form-label required">CPF</label><input class="form-control" name="cpf" inputmode="numeric" autocomplete="off" required></div>
            <div class="col-md-3"><label class="form-label required">Telefone</label><input class="form-control" name="telefone" inputmode="tel" autocomplete="tel" required></div>
            <div class="col-md-3"><label class="form-label">NIS</label><input class="form-control" name="nis" inputmode="numeric"></div>
            <div class="col-md-3"><label class="form-label">RG</label><input class="form-control" name="rg"></div>
            <div class="col-md-3"><label class="form-label">Nascimento</label><input class="form-control" name="data_nascimento" type="date"></div>
            <div class="col-md-3"><label class="form-label">E-mail</label><input class="form-control" name="email" type="email" autocomplete="email"></div>
        </div>
    </section>

    <section class="cm-form-section">
        <div class="cm-form-section__title"><span>2</span><div><h3>Família e endereço</h3><p>Território, composição familiar e referência de localização.</p></div></div>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Zona</label><select class="form-select" name="zona"><option value="urbana">Urbana</option><option value="rural">Rural</option></select></div>
            <div class="col-md-5"><label class="form-label">Logradouro</label><input class="form-control" name="logradouro" autocomplete="address-line1"></div>
            <div class="col-md-2"><label class="form-label">Número</label><input class="form-control" name="numero"></div>
            <div class="col-md-2"><label class="form-label">CEP</label><input class="form-control" name="cep" inputmode="numeric" autocomplete="postal-code"></div>
            <div class="col-md-4"><label class="form-label">Bairro</label><input class="form-control" name="bairro"></div>
            <div class="col-md-4"><label class="form-label">Comunidade</label><input class="form-control" name="comunidade"></div>
            <div class="col-md-4"><label class="form-label">Complemento</label><input class="form-control" name="complemento" autocomplete="address-line2"></div>
            <div class="col-md-6"><label class="form-label">Ponto de referência</label><input class="form-control" name="ponto_referencia"></div>
            <div class="col-md-3"><label class="form-label">Membros</label><input class="form-control" name="quantidade_membros" type="number" min="1" value="1"></div>
            <div class="col-md-3"><label class="form-label">Renda familiar</label><input class="form-control" name="renda_familiar" inputmode="decimal"></div>
        </div>
    </section>

    <section class="cm-form-section">
        <div class="cm-form-section__title"><span>3</span><div><h3>Inscrição no programa</h3><p>Status, polo, prioridade e observações internas.</p></div></div>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Polo</label><select class="form-select" name="polo_id"><option value="">Sem polo</option><?php foreach ($poles as $pole): ?><option value="<?= cm_h($pole['id']) ?>"><?= cm_h($pole['nome']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Situação</label><select class="form-select" name="status"><?php foreach ($programStatuses as $value => $label): ?><option value="<?= cm_h($value) ?>"><?= cm_h($label) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Prioridade</label><select class="form-select" name="prioridade"><option value="normal">Normal</option><option value="alta">Alta</option><option value="baixa">Baixa</option></select></div>
            <div class="col-md-3"><label class="form-label">Data da inscrição</label><input class="form-control" name="data_inscricao" type="date" value="<?= cm_h(date('Y-m-d')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Motivo suspensão/bloqueio</label><input class="form-control" name="motivo_suspensao"></div>
            <div class="col-md-6"><label class="form-label">Observação</label><textarea class="form-control" name="observacao" rows="2"></textarea></div>
        </div>
    </section>
    <?php
}

/** @param list<array<string,mixed>> $poles @param array<string,string> $programStatuses */
function cm_registration_modal(array $poles, array $programStatuses): void
{
    if (!cm_can('comida_mesa.cadastrar') && !cm_can('comida_mesa.editar')) return;
    ?>
    <div class="modal fade" id="registrationFormModal" tabindex="-1" aria-labelledby="registrationFormTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <form id="registrationForm" action="api/comida-mesa/salvar-cadastro.php" method="post" novalidate>
                <div class="modal-header"><div><div class="eyebrow"><i class="bi bi-person-plus"></i> Família beneficiária</div><h2 class="modal-title" id="registrationFormTitle">Nova inscrição</h2><p class="cm-modal-subtitle">Cadastro, família e vínculo com o programa.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <div class="modal-body"><?php cm_registration_fields($poles, $programStatuses, cm_csrf('comida_mesa_salvar_cadastro')); ?></div>
                <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Salvar inscrição</button></div>
            </form>
        </div></div>
    </div>
    <?php
}

function cm_detail_modal(): void
{
    ?>
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow"><i class="bi bi-eye"></i> Detalhes</div><h2 class="modal-title" id="detailTitle">Detalhes da família</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body" data-detail-content><div class="cm-empty-state"><i class="bi bi-hourglass-split"></i><strong>Carregando</strong></div></div></div></div></div>
    <?php
}

function cm_delivery_modal(?array $competence): void
{
    if (!cm_can('comida_mesa.entregar')) return;
    $competenceId = $competence ? (int) $competence['id'] : 0;
    $label = $competence ? cm_month_label((int) $competence['mes'], (int) $competence['ano']) : 'Sem competência';
    ?>
    <div class="modal fade" id="deliveryModal" tabindex="-1" aria-labelledby="deliveryTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form id="deliveryForm" action="api/comida-mesa/registrar-entrega.php" method="post" novalidate>
        <div class="modal-header"><div><div class="eyebrow"><i class="bi bi-basket2"></i> Entrega</div><h2 class="modal-title" id="deliveryTitle">Registrar entrega</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_registrar_entrega')) ?>"><input type="hidden" name="inscricao_id"><input type="hidden" name="competencia_id" value="<?= $competenceId ?>"><div data-form-alert></div><div class="cm-soft-info"><strong data-delivery-family>Família</strong><span data-delivery-summary><?= cm_h($label) ?></span></div><dl class="cm-detail-list"><dt>Responsável</dt><dd data-delivery-responsible>—</dd><dt>Código familiar</dt><dd data-delivery-code>—</dd><dt>Competência</dt><dd><?= cm_h($label) ?></dd><dt>Polo</dt><dd data-delivery-pole>—</dd></dl><div class="mb-3"><label class="form-label">Nome do recebedor</label><input class="form-control" name="recebedor_nome" required></div><div class="mb-3"><label class="form-label">CPF do recebedor</label><input class="form-control" name="recebedor_cpf" inputmode="numeric"></div><div class="mb-3"><label class="form-label">Parentesco</label><input class="form-control" name="recebedor_parentesco"></div><div><label class="form-label">Observação</label><textarea class="form-control" name="observacao" rows="3"></textarea></div></div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Confirmar entrega</button></div>
    </form></div></div></div>
    <?php
}

function cm_cancel_delivery_modal(?array $competence): void
{
    if (!cm_can('comida_mesa.cancelar_entrega')) return;
    $competenceId = $competence ? (int) $competence['id'] : 0;
    ?>
    <div class="modal fade" id="cancelDeliveryModal" tabindex="-1" aria-labelledby="cancelDeliveryTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="cancelDeliveryForm" action="api/comida-mesa/cancelar-entrega.php" method="post" novalidate>
        <div class="modal-header"><div><div class="eyebrow"><i class="bi bi-x-circle"></i> Cancelamento</div><h2 class="modal-title" id="cancelDeliveryTitle">Cancelar entrega</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_cancelar_entrega')) ?>"><input type="hidden" name="inscricao_id"><input type="hidden" name="competencia_id" value="<?= $competenceId ?>"><div data-form-alert></div><dl class="cm-detail-list"><dt>Responsável</dt><dd data-cancel-responsible>—</dd><dt>Família</dt><dd data-cancel-code>—</dd><dt>Polo</dt><dd data-cancel-pole>—</dd><dt>Data da entrega</dt><dd data-cancel-date>—</dd><dt>Operador</dt><dd data-cancel-operator>—</dd></dl><label class="form-label">Motivo</label><textarea class="form-control" name="motivo" rows="4" minlength="10" maxlength="255" required></textarea></div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn btn-danger" type="submit"><i class="bi bi-x-lg"></i> Cancelar entrega</button></div>
    </form></div></div></div>
    <?php
}

function cm_document_modal(array $registrations = []): void
{
    if (!cm_can('comida_mesa.documentos_enviar')) return;
    ?>
    <div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="documentForm" action="api/comida-mesa/enviar-documento.php" method="post" enctype="multipart/form-data" novalidate>
        <div class="modal-header"><div><div class="eyebrow"><i class="bi bi-paperclip"></i> Documento</div><h2 class="modal-title" id="documentTitle">Enviar documento</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_enviar_documento')) ?>"><div data-form-alert></div><?php if ($registrations !== []): ?><div class="mb-3"><label class="form-label">Família</label><select class="form-select" name="inscricao_id" required><option value="">Selecione</option><?php foreach ($registrations as $row): ?><option value="<?= (int) $row['id'] ?>"><?= cm_h($row['codigo'] . ' — ' . $row['nome']) ?></option><?php endforeach; ?></select></div><?php else: ?><input type="hidden" name="inscricao_id"><?php endif; ?><div class="mb-3"><label class="form-label">Tipo</label><input class="form-control" name="tipo" required></div><div class="mb-3"><label class="form-label">Descrição</label><input class="form-control" name="descricao"></div><div><label class="form-label">Arquivo</label><input class="form-control" name="arquivo" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" required></div></div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-upload"></i> Enviar</button></div>
    </form></div></div></div>
    <?php
}

function cm_competence_modal(): void
{
    if (!cm_can('comida_mesa.competencias_gerenciar')) return;
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Manaus'));
    $defaultStart = $now->modify('first day of this month')->format('Y-m-d');
    $defaultEnd = $now->modify('last day of this month')->format('Y-m-d');
    ?>
    <div class="modal fade" id="competenceModal" tabindex="-1" aria-labelledby="competenceTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="competenceForm" action="api/comida-mesa/salvar-competencia.php" method="post" novalidate>
        <div class="modal-header"><div><div class="eyebrow"><i class="bi bi-calendar-event"></i> Competência</div><h2 class="modal-title" id="competenceTitle">Nova competência</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_salvar_competencia')) ?>"><input type="hidden" name="competencia_id"><div data-form-alert></div><div class="row g-3"><div class="col-6"><label class="form-label">Mês</label><input class="form-control" name="mes" type="number" min="1" max="12" value="<?= (int) $now->format('n') ?>"></div><div class="col-6"><label class="form-label">Ano</label><input class="form-control" name="ano" type="number" min="2020" max="<?= (int) $now->format('Y') + 2 ?>" value="<?= (int) $now->format('Y') ?>"></div><div class="col-12"><label class="form-label">Situação</label><select class="form-select" name="status"><option value="planejada">Planejada</option><option value="aberta">Aberta</option><option value="encerrada">Encerrada</option><option value="cancelada">Cancelada</option></select></div><div class="col-6"><label class="form-label">Início das entregas</label><input class="form-control" name="inicio_entregas" type="date" value="<?= cm_h($defaultStart) ?>"></div><div class="col-6"><label class="form-label">Fim das entregas</label><input class="form-control" name="fim_entregas" type="date" value="<?= cm_h($defaultEnd) ?>"></div><div class="col-12"><label class="form-label">Observação</label><textarea class="form-control" name="observacao" rows="3"></textarea></div></div><div class="alert alert-light border mt-3 mb-0"><i class="bi bi-shield-check"></i> Apenas uma competência pode ficar aberta por vez. Entregas só são liberadas dentro do período informado.</div></div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Salvar</button></div>
    </form></div></div></div>
    <?php
}

function cm_pole_modal(): void
{
    if (!cm_can('comida_mesa.polos_gerenciar')) return;
    ?>
    <div class="modal fade" id="poleModal" tabindex="-1" aria-labelledby="poleModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="poleForm" action="api/comida-mesa/salvar-polo.php" method="post" novalidate>
        <div class="modal-header"><div><div class="eyebrow"><i class="bi bi-geo-alt"></i> Polo de distribuição</div><h2 class="modal-title" id="poleModalTitle">Novo polo</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_salvar_polo')) ?>"><input type="hidden" name="polo_id"><div data-form-alert></div><div class="mb-3"><label class="form-label required">Nome</label><input class="form-control" name="nome" maxlength="150" required></div><div class="mb-3"><label class="form-label">Endereço/localização</label><input class="form-control" name="endereco" maxlength="255"></div><div><label class="form-label">Situação</label><select class="form-select" name="ativo"><option value="1">Ativo</option><option value="0">Inativo</option></select></div></div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Salvar polo</button></div>
    </form></div></div></div>
    <?php
}

function cm_new_registration_lookup_modal(?array $competence): void
{
    if (!cm_can('comida_mesa.cadastrar') || !cm_can('comida_mesa.consultar_cpf')) return;
    $competenceId = $competence ? (int) $competence['id'] : 0;
    ?>
    <div class="modal fade" id="newRegistrationModal" tabindex="-1" aria-labelledby="newRegistrationTitle" aria-hidden="true" data-comida-mesa-consulta>
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><div><div class="eyebrow"><i class="bi bi-person-plus"></i> Nova inscrição</div><h2 class="modal-title" id="newRegistrationTitle">Consultar CPF antes do cadastro</h2><p class="cm-modal-subtitle">A consulta evita duplicidade e reutiliza dados já existentes.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <form id="cpfLookupForm" method="post" action="api/comida-mesa/consultar-cpf.php">
                <div class="modal-body"><input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_consultar_cpf')) ?>"><input type="hidden" name="competencia_id" value="<?= $competenceId ?>"><div class="cm-soft-info"><i class="bi bi-shield-check"></i><span>Informe o CPF para verificar pessoa, família, inscrição e base ANEXO antes de criar um novo cadastro.</span></div><div class="mt-3"><label class="form-label required" for="cpfLookupInput">CPF</label><input class="form-control" id="cpfLookupInput" name="cpf" type="text" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" required><div class="invalid-feedback">Informe um CPF com 11 números.</div></div><div class="mt-3" id="cpfLookupResult" aria-live="polite"></div></div>
                <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit" data-cpf-submit><i class="bi bi-search"></i> Consultar</button></div>
            </form>
        </div></div>
    </div>
    <div class="modal fade anexo-detail-modal" id="anexoDetailModal" tabindex="-1" aria-labelledby="anexoDetailTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable anexo-detail-dialog"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow"><i class="bi bi-database-check"></i> ANEXO</div><h2 class="modal-title" id="anexoDetailTitle">Dados completos do cadastro</h2><p class="cm-modal-subtitle">Consulta somente leitura da base ANEXO.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body" data-anexo-detail-content></div></div></div></div>
    <?php
}
