<?php $membro = []; ?>
<?php include 'includes/header.php'; ?>

<?php
function valor($campo, $padrao = '')
{
    global $membro;
    return htmlspecialchars($membro[$campo] ?? $padrao);
}
?>

<div class="page-header mb-4">
    <div>
        <h2 class="page-title mb-1">Cadastrar membro</h2>
        <p class="page-subtitle mb-0">Preencha os dados em etapas para manter o cadastro organizado e rápido.</p>
    </div>

    <div class="page-actions">
        <a href="listar.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
    </div>
</div>

<form action="salvar.php" method="post" enctype="multipart/form-data" id="multiStepForm" class="member-form">
    <div class="stepper-wrapper mb-4">
        <div class="stepper-card">
            <div class="stepper-line"></div>

            <div class="step-item active" data-step="1">
                <div class="step-circle">1</div>
                <div class="step-label">
                    <strong>Dados pessoais</strong>
                    <span>Informações básicas</span>
                </div>
            </div>

            <div class="step-item" data-step="2">
                <div class="step-circle">2</div>
                <div class="step-label">
                    <strong>Endereço</strong>
                    <span>Contato e localização</span>
                </div>
            </div>

            <div class="step-item" data-step="3">
                <div class="step-circle">3</div>
                <div class="step-label">
                    <strong>Dados eclesiásticos</strong>
                    <span>Vínculo com a igreja</span>
                </div>
            </div>

            <div class="step-item" data-step="4">
                <div class="step-circle">4</div>
                <div class="step-label">
                    <strong>Finalizar</strong>
                    <span>Revisão e envio</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ETAPA 1 -->
    <section class="form-step active" data-step="1">
        <div class="step-layout">
            <aside class="step-sidebar">
                <div class="form-panel photo-panel">
                    <div class="form-panel-header">
                        <h5>Foto do membro</h5>
                        <p>Adicione uma foto para facilitar a identificação.</p>
                    </div>

                    <div class="form-panel-body">
                        <div class="photo-preview-box">
                            <?php if (!empty($membro['foto'])): ?>
                                <img src="uploads/<?= valor('foto') ?>" id="previewFoto" class="member-photo-preview" alt="foto">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/240x280?text=Foto" id="previewFoto" class="member-photo-preview" alt="foto">
                            <?php endif; ?>
                        </div>

                        <label for="fotoInput" class="upload-btn mt-3">
                            <i class="fas fa-image me-2"></i>Selecionar foto
                        </label>
                        <input type="file" name="foto" class="d-none" accept="image/*" id="fotoInput">

                        <small class="upload-help">Formatos aceitos: JPG, PNG e WEBP</small>

                        <div class="tip-box mt-4">
                            <div class="tip-item">
                                <span>Obrigatório</span>
                                <strong>Nome completo</strong>
                            </div>
                            <div class="tip-item">
                                <span>Importante</span>
                                <strong>Sexo e nascimento</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="step-content">
                <div class="form-panel">
                    <div class="form-panel-header">
                        <h5>Dados pessoais</h5>
                        <p>Informações principais do membro.</p>
                    </div>

                    <div class="form-panel-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nome completo <span class="text-danger">*</span></label>
                                <input type="text" name="nome_completo" class="form-control custom-input required-step-1" required value="<?= valor('nome_completo') ?>">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Sexo</label>
                                <select name="sexo" class="form-select custom-input">
                                    <option value="">Selecione</option>
                                    <option value="M" <?= (($membro['sexo'] ?? '') === 'M') ? 'selected' : '' ?>>Masc.</option>
                                    <option value="F" <?= (($membro['sexo'] ?? '') === 'F') ? 'selected' : '' ?>>Fem.</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Nascimento</label>
                                <input type="date" name="data_nascimento" class="form-control custom-input" value="<?= valor('data_nascimento') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Nacionalidade</label>
                                <input type="text" name="nacionalidade" class="form-control custom-input" value="<?= valor('nacionalidade') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Naturalidade</label>
                                <input type="text" name="naturalidade" class="form-control custom-input" value="<?= valor('naturalidade') ?>">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">UF</label>
                                <input type="text" name="estado_uf" maxlength="2" class="form-control custom-input" value="<?= valor('estado_uf') ?>">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Escolaridade</label>
                                <select name="escolaridade" class="form-select custom-input">
                                    <option value="">Selecione</option>
                                    <?php foreach (['FUNDAMENTAL', 'MEDIO', 'SUPERIOR'] as $esc): ?>
                                        <option value="<?= $esc ?>" <?= (($membro['escolaridade'] ?? '') === $esc) ? 'selected' : '' ?>>
                                            <?= $esc ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Profissão</label>
                                <input type="text" name="profissao" class="form-control custom-input" value="<?= valor('profissao') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Identidade</label>
                                <input type="text" name="identidade" class="form-control custom-input" value="<?= valor('identidade') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">CPF</label>
                                <input type="text" name="cpf" class="form-control custom-input" value="<?= valor('cpf') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Pai</label>
                                <input type="text" name="pai" class="form-control custom-input" value="<?= valor('pai') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Mãe</label>
                                <input type="text" name="mae" class="form-control custom-input" value="<?= valor('mae') ?>">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Estado civil</label>
                                <input type="text" name="estado_civil" class="form-control custom-input" value="<?= valor('estado_civil') ?>">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Filhos</label>
                                <input type="number" name="filhos" min="0" class="form-control custom-input" value="<?= valor('filhos', 0) ?>">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Cônjuge</label>
                                <input type="text" name="conjuge" class="form-control custom-input" value="<?= valor('conjuge') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step-actions">
                    <button type="button" class="btn btn-primary next-step">
                        Próxima etapa <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- ETAPA 2 -->
    <section class="form-step" data-step="2">
        <div class="form-panel">
            <div class="form-panel-header">
                <h5>Endereço residencial</h5>
                <p>Dados de localização e contato do membro.</p>
            </div>

            <div class="form-panel-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Rua</label>
                        <input type="text" name="rua" class="form-control custom-input" value="<?= valor('rua') ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Número</label>
                        <input type="text" name="numero" class="form-control custom-input" value="<?= valor('numero') ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Bairro</label>
                        <input type="text" name="bairro" class="form-control custom-input" value="<?= valor('bairro') ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">CEP</label>
                        <input type="text" name="cep" class="form-control custom-input" value="<?= valor('cep') ?>">
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" class="form-control custom-input" value="<?= valor('cidade') ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <input type="text" name="estado" maxlength="2" class="form-control custom-input" value="<?= valor('estado') ?>">
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control custom-input" value="<?= valor('telefone') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="step-actions">
            <button type="button" class="btn btn-light prev-step">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </button>
            <button type="button" class="btn btn-primary next-step">
                Próxima etapa <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </div>
    </section>

    <!-- ETAPA 3 -->
    <section class="form-step" data-step="3">
        <div class="form-panel">
            <div class="form-panel-header">
                <h5>Dados eclesiásticos</h5>
                <p>Informações sobre ingresso e vínculo com a igreja.</p>
            </div>

            <div class="form-panel-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tipo de ingresso</label>
                        <select name="tipo_ingresso" class="form-select custom-input">
                            <option value="">Selecione</option>
                            <?php foreach (['MUDANCA', 'ACLAMACAO', 'BATISMO'] as $tipo): ?>
                                <option value="<?= $tipo ?>" <?= (($membro['tipo_ingresso'] ?? '') === $tipo) ? 'selected' : '' ?>>
                                    <?= $tipo ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Data da decisão</label>
                        <input type="date" name="data_decisao" class="form-control custom-input" value="<?= valor('data_decisao') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Procedência</label>
                        <input type="text" name="procedencia" class="form-control custom-input" value="<?= valor('procedencia') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Congregação</label>
                        <input type="text" name="congregacao" class="form-control custom-input" value="<?= valor('congregacao') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Área</label>
                        <input type="text" name="area" class="form-control custom-input" value="<?= valor('area') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Núcleo</label>
                        <input type="text" name="nucleo" class="form-control custom-input" value="<?= valor('nucleo') ?>">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Observação</label>
                        <textarea name="observacao" rows="5" class="form-control custom-input"><?= valor('observacao') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="step-actions">
            <button type="button" class="btn btn-light prev-step">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </button>
            <button type="button" class="btn btn-primary next-step">
                Revisar <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </div>
    </section>

    <!-- ETAPA 4 -->
    <section class="form-step" data-step="4">
        <div class="form-panel">
            <div class="form-panel-header">
                <h5>Revisão final</h5>
                <p>Confira os dados antes de salvar.</p>
            </div>

            <div class="form-panel-body">
                <div class="review-grid">
                    <div class="review-item">
                        <span>Nome</span>
                        <strong id="review_nome">-</strong>
                    </div>
                    <div class="review-item">
                        <span>Sexo</span>
                        <strong id="review_sexo">-</strong>
                    </div>
                    <div class="review-item">
                        <span>Nascimento</span>
                        <strong id="review_nascimento">-</strong>
                    </div>
                    <div class="review-item">
                        <span>Telefone</span>
                        <strong id="review_telefone">-</strong>
                    </div>
                    <div class="review-item">
                        <span>Cidade</span>
                        <strong id="review_cidade">-</strong>
                    </div>
                    <div class="review-item">
                        <span>Congregação</span>
                        <strong id="review_congregacao">-</strong>
                    </div>
                    <div class="review-item">
                        <span>Área</span>
                        <strong id="review_area">-</strong>
                    </div>
                    <div class="review-item">
                        <span>Tipo de ingresso</span>
                        <strong id="review_ingresso">-</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="step-actions">
            <button type="button" class="btn btn-light prev-step">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Salvar cadastro
            </button>
        </div>
    </section>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('multiStepForm');
    const steps = document.querySelectorAll('.form-step');
    const indicators = document.querySelectorAll('.step-item');
    const nextButtons = document.querySelectorAll('.next-step');
    const prevButtons = document.querySelectorAll('.prev-step');
    let currentStep = 1;

    function showStep(step) {
        steps.forEach(el => {
            el.classList.toggle('active', Number(el.dataset.step) === step);
        });

        indicators.forEach(el => {
            const itemStep = Number(el.dataset.step);
            el.classList.toggle('active', itemStep === step);
            el.classList.toggle('done', itemStep < step);
        });

        currentStep = step;

        if (step === 4) {
            fillReview();
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(step) {
        if (step !== 1) return true;

        const requiredFields = form.querySelectorAll('.required-step-1');
        let valid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                valid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });

        return valid;
    }

    function fillReview() {
        document.getElementById('review_nome').textContent = form.querySelector('[name="nome_completo"]').value || '-';
        document.getElementById('review_sexo').textContent = form.querySelector('[name="sexo"]').value || '-';
        document.getElementById('review_nascimento').textContent = form.querySelector('[name="data_nascimento"]').value || '-';
        document.getElementById('review_telefone').textContent = form.querySelector('[name="telefone"]').value || '-';
        document.getElementById('review_cidade').textContent = form.querySelector('[name="cidade"]').value || '-';
        document.getElementById('review_congregacao').textContent = form.querySelector('[name="congregacao"]').value || '-';
        document.getElementById('review_area').textContent = form.querySelector('[name="area"]').value || '-';
        document.getElementById('review_ingresso').textContent = form.querySelector('[name="tipo_ingresso"]').value || '-';
    }

    nextButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            if (!validateStep(currentStep)) return;
            if (currentStep < 4) showStep(currentStep + 1);
        });
    });

    prevButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            if (currentStep > 1) showStep(currentStep - 1);
        });
    });

    showStep(1);
});
</script>

<?php include 'includes/footer.php'; ?>