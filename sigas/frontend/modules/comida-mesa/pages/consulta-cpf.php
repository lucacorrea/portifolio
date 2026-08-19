<?php

declare(strict_types=1);

cm_require('comida_mesa.consultar_cpf');

$pageDefinition = [
    'title' => 'Consultar CPF',
    'description' => 'Consulte a pessoa no banco do SIGAS e visualize família, inscrição, competência e situação operacional do benefício.',
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
    'assets/css/consulta-documento-ocr.css',
    'assets/css/consulta-result-modal.css',
    'assets/css/anexo-detail-modal.css',
];
$pageExtraScripts = [
    'assets/js/cpf-ocr.js',
    'assets/js/consulta-documento.js',
];

ob_start();
?>
<section class="cm-consulta-page" data-consulta-documento>
    <div class="cm-scan-notice" role="note">
        <i class="bi bi-shield-lock"></i>
        <div>
            <strong>Leitura rápida e segura do CPF</strong>
            <span>Use a câmera, uma imagem do documento ou digite o número manualmente. O OCR é processado no dispositivo.</span>
        </div>
    </div>

    <section class="scanner-layout" aria-label="Consulta por CPF">
        <article class="content-card scanner-capture-card">
            <div class="scanner-card-heading">
                <div>
                    <div class="card-kicker">Documento</div>
                    <h2>Aproxime o número do CPF</h2>
                    <p>Posicione somente a linha do CPF dentro da faixa destacada. Não é necessário enquadrar toda a identidade.</p>
                </div>
                <span class="scanner-security"><i class="bi bi-cpu"></i>Processamento local</span>
            </div>

            <div class="camera-frame" id="cameraFrame">
                <video id="scannerVideo" playsinline muted hidden aria-label="Visualização da câmera"></video>
                <img id="scannerPreview" alt="Pré-visualização do documento capturado" hidden>
                <div class="cpf-scan-mask" aria-hidden="true"></div>
                <div class="cpf-scan-region" id="cpfScanRegion" aria-hidden="true" hidden>
                    <span class="cpf-scan-label">CPF 000.000.000-00</span>
                    <span class="cpf-scan-line"></span>
                </div>
                <div class="camera-placeholder" id="cameraPlaceholder">
                    <span><i class="bi bi-camera"></i></span>
                    <strong>Abra a câmera e alinhe os 11 números do CPF dentro da faixa.</strong>
                    <small>Se preferir, escolha uma imagem ou digite o CPF manualmente.</small>
                </div>
            </div>

            <div class="ocr-status" id="ocrStatus" aria-live="polite" hidden>
                <div class="ocr-status-heading">
                    <span data-ocr-title>Preparando leitura</span>
                    <strong data-ocr-progress>0%</strong>
                </div>
                <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div class="progress-bar" data-ocr-progress-bar></div>
                </div>
                <p data-ocr-message></p>
            </div>
            <div class="ocr-candidate-list" id="ocrCandidates" aria-live="polite" hidden></div>

            <div class="camera-actions">
                <button class="btn btn-primary btn-lg" id="openCameraButton" type="button"><i class="bi bi-camera"></i>Abrir câmera</button>
                <button class="btn btn-primary btn-lg" id="captureDocumentButton" type="button" hidden><i class="bi bi-upc-scan"></i>Ler CPF</button>
                <button class="btn btn-light btn-lg" id="retryOcrButton" type="button" hidden><i class="bi bi-arrow-repeat"></i>Reposicionar documento</button>
                <button class="btn btn-light btn-lg" id="chooseImageButton" type="button"><i class="bi bi-image"></i>Escolher imagem</button>
                <button class="btn btn-light btn-lg" id="zoomInButton" type="button" hidden><i class="bi bi-zoom-in"></i>Aumentar zoom</button>
                <button class="btn btn-light btn-lg" id="zoomOutButton" type="button" hidden><i class="bi bi-zoom-out"></i>Diminuir zoom</button>
                <button class="btn btn-light btn-lg" id="moveImageUpButton" type="button" hidden><i class="bi bi-arrow-up"></i>Mover imagem para cima</button>
                <button class="btn btn-light btn-lg" id="moveImageDownButton" type="button" hidden><i class="bi bi-arrow-down"></i>Mover imagem para baixo</button>
                <button class="btn btn-outline-primary btn-lg" id="continueOcrButton" type="button" hidden><i class="bi bi-play"></i>Continuar leitura</button>
                <button class="btn btn-outline-danger btn-lg" id="cancelOcrButton" type="button" hidden><i class="bi bi-x-lg"></i>Parar leitura</button>
                <input class="visually-hidden" id="documentImageInput" type="file" accept="image/*" capture="environment" aria-label="Selecionar ou fotografar documento">
            </div>

            <form class="manual-cpf-form mt-4" id="manualCpfForm" action="api/comida-mesa/consultar-cpf.php" method="post" novalidate>
                <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_consultar_cpf')) ?>">
                <input type="hidden" name="consulta_modo" value="entrega_rapida">
                <label class="form-label" for="manualCpf">CPF da pessoa</label>
                <div class="manual-cpf-row">
                    <div class="input-icon-field"><i class="bi bi-person-vcard"></i><input class="form-control form-control-lg" id="manualCpf" name="cpf" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" maxlength="14"></div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i><span>Consultar</span></button>
                </div>
                <div class="invalid-feedback" id="manualCpfFeedback">Informe um CPF válido.</div>
                <p class="manual-cpf-help">A consulta não altera nenhum cadastro.</p>
            </form>
        </article>

        <aside class="scan-result-column" aria-live="polite">
            <article class="content-card scan-result-empty" id="consultaResult">
                <span class="result-empty-icon"><i class="bi bi-person-lines-fill"></i></span>
                <h2>Resultado da consulta</h2>
                <p>Informe o CPF para visualizar pessoa, família, inscrição, competência e situação da entrega.</p>
            </article>
        </aside>
    </section>
</section>

<div class="modal fade consulta-result-modal" id="consultaResultModal" tabindex="-1" aria-labelledby="consultaResultModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable consulta-result-dialog"><div class="modal-content consulta-result-content">
        <div class="modal-header consulta-result-header"><div class="consulta-result-header-copy"><div class="consulta-result-eyebrow"><i class="bi bi-person-check"></i> Consulta por CPF</div><h2 class="modal-title consulta-result-title" id="consultaResultModalTitle">Beneficiário localizado</h2><p class="consulta-result-subtitle">Situação operacional no Programa Coari Comida na Mesa.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body consulta-result-body" data-consulta-result-modal-body></div>
        <div class="modal-footer consulta-result-footer" data-consulta-result-modal-footer></div>
    </div></div>
</div>

<div class="modal fade" id="consultaDeliveryModal" tabindex="-1" aria-labelledby="consultaDeliveryTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form id="consultaDeliveryForm" action="api/comida-mesa/registrar-entrega.php" method="post" novalidate>
    <div class="modal-header"><div><div class="eyebrow"><i class="bi bi-basket2"></i> Entrega mensal</div><h2 class="modal-title" id="consultaDeliveryTitle">Registrar entrega</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body"><input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_registrar_entrega')) ?>"><input type="hidden" name="inscricao_id"><input type="hidden" name="competencia_id"><div data-form-alert></div><dl class="cm-detail-list"><dt>Responsável</dt><dd data-delivery-name>—</dd><dt>Família</dt><dd data-delivery-family>—</dd><dt>Competência</dt><dd data-delivery-competence>—</dd><dt>Polo</dt><dd data-delivery-pole>—</dd></dl><div class="mb-3"><label class="form-label">Nome do recebedor</label><input class="form-control" name="recebedor_nome" required></div><div class="mb-3"><label class="form-label">CPF do recebedor</label><input class="form-control" name="recebedor_cpf" inputmode="numeric"></div><div class="mb-3"><label class="form-label">Parentesco</label><input class="form-control" name="recebedor_parentesco"></div><div><label class="form-label">Observação</label><textarea class="form-control" name="observacao" rows="3"></textarea></div></div>
    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Confirmar</button></div>
</form></div></div></div>

<div class="modal fade" id="consultaCancelModal" tabindex="-1" aria-labelledby="consultaCancelTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="consultaCancelForm" action="api/comida-mesa/cancelar-entrega.php" method="post" novalidate>
    <div class="modal-header"><div><div class="eyebrow"><i class="bi bi-x-circle"></i> Cancelamento</div><h2 class="modal-title" id="consultaCancelTitle">Cancelar entrega</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body"><input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_cancelar_entrega')) ?>"><input type="hidden" name="inscricao_id"><input type="hidden" name="competencia_id"><div data-form-alert></div><label class="form-label">Motivo</label><textarea class="form-control" name="motivo" rows="4" minlength="10" maxlength="255" required></textarea></div>
    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn btn-danger" type="submit"><i class="bi bi-x-lg"></i> Cancelar entrega</button></div>
</form></div></div></div>

<div class="modal fade" id="consultaDetailModal" tabindex="-1" aria-labelledby="consultaDetailTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow"><i class="bi bi-eye"></i> Detalhes</div><h2 class="modal-title" id="consultaDetailTitle">Detalhes da família</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body" data-detail-content></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@7.0.0/dist/tesseract.min.js"></script>
<?php
$pageCustomContent = (string) ob_get_clean();
