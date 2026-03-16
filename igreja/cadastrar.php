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
        <p class="page-subtitle mb-0">Preencha os dados principais do membro de forma organizada.</p>
    </div>

    <div class="page-actions">
        <a href="listar.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
    </div>
</div>

<form action="salvar.php" method="post" enctype="multipart/form-data" class="member-form">
    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="form-card sticky-card">
                <div class="form-card-header">
                    <h5 class="mb-1">Foto do membro</h5>
                    <p class="mb-0">Adicione uma foto para identificação.</p>
                </div>

                <div class="form-card-body">
                    <div class="photo-upload-box">
                        <div class="photo-preview-wrap">
                            <?php if (!empty($membro['foto'])): ?>
                                <img src="uploads/<?= valor('foto') ?>" id="previewFoto" class="member-photo-preview" alt="foto">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/220x260?text=Foto" id="previewFoto" class="member-photo-preview" alt="foto">
                            <?php endif; ?>
                        </div>

                        <label for="fotoInput" class="upload-label mt-3">Selecionar foto</label>
                        <input type="file" name="foto" class="form-control d-none" accept="image/*" id="fotoInput">

                        <small class="text-muted d-block mt-2 text-center">
                            Formatos aceitos: JPG, PNG, WEBP
                        </small>
                    </div>

                    <div class="info-mini-card mt-4">
                        <div class="info-mini-item">
                            <span>Campos obrigatórios</span>
                            <strong>Nome completo</strong>
                        </div>
                        <div class="info-mini-item">
                            <span>Recomendado</span>
                            <strong>Telefone e congregação</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="form-card mb-4">
                <div class="form-card-header">
                    <h5 class="mb-1">Dados pessoais</h5>
                    <p class="mb-0">Informações principais do membro.</p>
                </div>

                <div class="form-card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nome completo <span class="text-danger">*</span></label>
                            <input type="text" name="nome_completo" class="form-control custom-input" required value="<?= valor('nome_completo') ?>">
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

            <div class="form-card mb-4">
                <div class="form-card-header">
                    <h5 class="mb-1">Endereço residencial</h5>
                    <p class="mb-0">Dados de localização e contato.</p>
                </div>

                <div class="form-card-body">
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

            <div class="form-card mb-4">
                <div class="form-card-header">
                    <h5 class="mb-1">Dados eclesiásticos</h5>
                    <p class="mb-0">Informações de ingresso e vínculo com a igreja.</p>
                </div>

                <div class="form-card-body">
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
                            <textarea name="observacao" rows="4" class="form-control custom-input"><?= valor('observacao') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-save">
                    <i class="fas fa-save me-2"></i>Salvar cadastro
                </button>

                <a href="listar.php" class="btn btn-light btn-cancel">
                    Cancelar
                </a>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>