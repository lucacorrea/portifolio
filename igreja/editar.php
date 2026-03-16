<?php include 'conexao.php'; ?>
<?php
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
$stmt->execute([$id]);
$membro = $stmt->fetch();

if (!$membro) {
    die('Membro não encontrado.');
}
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Editar membro</h3>
        <small class="text-muted">Atualize as informações do cadastro</small>
    </div>
    <a href="visualizar.php?id=<?= $id ?>" class="btn btn-outline-dark">Ver cadastro</a>
</div>

<form action="atualizar.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $id ?>">

<?php
function valor($campo, $padrao = '') {
    global $membro;
    return htmlspecialchars($membro[$campo] ?? $padrao);
}
?>
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white"><strong>Dados pessoais</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Foto</label>
                <input type="file" name="foto" class="form-control" accept="image/*" id="fotoInput">
                <div class="mt-2">
                    <?php if (!empty($membro['foto'])): ?>
                        <img src="uploads/<?= valor('foto') ?>" id="previewFoto" class="foto-preview" alt="foto">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/120x150?text=Foto" id="previewFoto" class="foto-preview" alt="foto">
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-9">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Nome completo *</label>
                        <input type="text" name="nome_completo" class="form-control" required value="<?= valor('nome_completo') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sexo</label>
                        <select name="sexo" class="form-select">
                            <option value="">Selecione</option>
                            <option value="M" <?= (($membro['sexo'] ?? '') === 'M') ? 'selected' : '' ?>>Masc.</option>
                            <option value="F" <?= (($membro['sexo'] ?? '') === 'F') ? 'selected' : '' ?>>Fem.</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Nascimento</label>
                        <input type="date" name="data_nascimento" class="form-control" value="<?= valor('data_nascimento') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Nacionalidade</label>
                        <input type="text" name="nacionalidade" class="form-control" value="<?= valor('nacionalidade') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Naturalidade</label>
                        <input type="text" name="naturalidade" class="form-control" value="<?= valor('naturalidade') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">UF</label>
                        <input type="text" name="estado_uf" maxlength="2" class="form-control" value="<?= valor('estado_uf') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Escolaridade</label>
                        <select name="escolaridade" class="form-select">
                            <option value="">Selecione</option>
                            <?php foreach (['FUNDAMENTAL','MEDIO','SUPERIOR'] as $esc): ?>
                                <option value="<?= $esc ?>" <?= (($membro['escolaridade'] ?? '') === $esc) ? 'selected' : '' ?>><?= $esc ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Profissão</label>
                        <input type="text" name="profissao" class="form-control" value="<?= valor('profissao') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Identidade</label>
                        <input type="text" name="identidade" class="form-control" value="<?= valor('identidade') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CPF</label>
                        <input type="text" name="cpf" class="form-control" value="<?= valor('cpf') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Pai</label>
                        <input type="text" name="pai" class="form-control" value="<?= valor('pai') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mãe</label>
                        <input type="text" name="mae" class="form-control" value="<?= valor('mae') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado civil</label>
                        <input type="text" name="estado_civil" class="form-control" value="<?= valor('estado_civil') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Filhos</label>
                        <input type="number" name="filhos" class="form-control" min="0" value="<?= valor('filhos', 0) ?>">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Cônjuge</label>
                        <input type="text" name="conjuge" class="form-control" value="<?= valor('conjuge') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white"><strong>Endereço residencial</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Rua</label>
                <input type="text" name="rua" class="form-control" value="<?= valor('rua') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Número</label>
                <input type="text" name="numero" class="form-control" value="<?= valor('numero') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Bairro</label>
                <input type="text" name="bairro" class="form-control" value="<?= valor('bairro') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">CEP</label>
                <input type="text" name="cep" class="form-control" value="<?= valor('cep') ?>">
            </div>

            <div class="col-md-5">
                <label class="form-label">Cidade</label>
                <input type="text" name="cidade" class="form-control" value="<?= valor('cidade') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <input type="text" name="estado" class="form-control" maxlength="2" value="<?= valor('estado') ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control" value="<?= valor('telefone') ?>">
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white"><strong>Dados eclesiásticos</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tipo de ingresso</label>
                <select name="tipo_ingresso" class="form-select">
                    <option value="">Selecione</option>
                    <?php foreach (['MUDANCA','ACLAMACAO','BATISMO'] as $tipo): ?>
                        <option value="<?= $tipo ?>" <?= (($membro['tipo_ingresso'] ?? '') === $tipo) ? 'selected' : '' ?>><?= $tipo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Data da decisão</label>
                <input type="date" name="data_decisao" class="form-control" value="<?= valor('data_decisao') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Procedência</label>
                <input type="text" name="procedencia" class="form-control" value="<?= valor('procedencia') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Congregação</label>
                <input type="text" name="congregacao" class="form-control" value="<?= valor('congregacao') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Área</label>
                <input type="text" name="area" class="form-control" value="<?= valor('area') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Núcleo</label>
                <input type="text" name="nucleo" class="form-control" value="<?= valor('nucleo') ?>">
            </div>

            <div class="col-md-12">
                <label class="form-label">Observação</label>
                <textarea name="observacao" rows="3" class="form-control"><?= valor('observacao') ?></textarea>
            </div>
        </div>
    </div>
</div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary">Salvar alterações</button>
        <a href="listar.php" class="btn btn-light border">Cancelar</a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
