<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Fiscal /</span> <?= $pageTitle ?>
    </h4>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <h5 class="card-header">Configuração da Integração NFC-e</h5>
                <div class="card-body">
                    <form action="fiscal.php?action=salvar_nfce_config" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="global" value="<?= $isGlobal ? '1' : '0' ?>">

                        <div class="row">
                            <!-- Basic Data -->
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="cnpj">CNPJ da Empresa</label>
                                <input type="text" class="form-control" name="cnpj" id="cnpj" maxlength="18" placeholder="00.000.000/0000-00" value="<?= $config['cnpj'] ?? '' ?>" required oninput="maskCnpj(this)">
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="razao_social">Razão Social</label>
                                <input type="text" class="form-control" name="razao_social" id="razao_social" value="<?= $config['razao_social'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="nome_fantasia">Nome Fantasia</label>
                                <input type="text" class="form-control" name="nome_fantasia" id="nome_fantasia" value="<?= $config['nome_fantasia'] ?? '' ?>">
                            </div>
                            <div class="mb-3 col-md-3">
                                <label class="form-label" for="inscricao_estadual">Inscrição Estadual</label>
                                <input type="text" class="form-control" name="inscricao_estadual" id="inscricao_estadual" value="<?= $config['inscricao_estadual'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3 col-md-3">
                                <label class="form-label" for="inscricao_municipal">Inscrição Municipal</label>
                                <input type="text" class="form-control" name="inscricao_municipal" id="inscricao_municipal" value="<?= $config['inscricao_municipal'] ?? '' ?>">
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3">Endereço e Contato</h6>

                            <div class="mb-3 col-md-3">
                                <label class="form-label" for="cep">CEP</label>
                                <input type="text" class="form-control" name="cep" id="cep" maxlength="9" placeholder="00000-000" value="<?= $config['cep'] ?? '' ?>" required oninput="maskCep(this)">
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="logradouro">Logradouro</label>
                                <input type="text" class="form-control" name="logradouro" id="logradouro" value="<?= $config['logradouro'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3 col-md-3">
                                <label class="form-label" for="numero_endereco">Número</label>
                                <input type="text" class="form-control" name="numero_endereco" id="numero_endereco" value="<?= $config['numero_endereco'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3 col-md-4">
                                <label class="form-label" for="complemento">Complemento</label>
                                <input type="text" class="form-control" name="complemento" id="complemento" value="<?= $config['complemento'] ?? '' ?>">
                            </div>
                            <div class="mb-3 col-md-4">
                                <label class="form-label" for="bairro">Bairro</label>
                                <input type="text" class="form-control" name="bairro" id="bairro" value="<?= $config['bairro'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3 col-md-4">
                                <label class="form-label" for="cidade">Cidade</label>
                                <input type="text" class="form-control" name="cidade" id="cidade" value="<?= $config['cidade'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3 col-md-3">
                                <label class="form-label" for="uf">UF</label>
                                <select class="form-select" name="uf" id="uf" required onchange="updateIbge()">
                                    <option value="">Selecione</option>
                                    <?php 
                                    $ufs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                                    foreach($ufs as $uf): ?>
                                    <option value="<?= $uf ?>" <?= ($config['uf'] ?? '') == $uf ? 'selected' : '' ?>><?= $uf ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3 col-md-3">
                                <label class="form-label" for="codigo_uf">Código UF (IBGE)</label>
                                <input type="number" class="form-control" name="codigo_uf" id="codigo_uf" value="<?= $config['codigo_uf'] ?? '' ?>" readonly placeholder="Ex.: 35 p/ SP">
                                <small class="text-muted">Preenchido automaticamente pela UF.</small>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="codigo_municipio">Código do Município (IBGE)</label>
                                <input type="text" class="form-control" name="codigo_municipio" id="codigo_municipio" value="<?= $config['codigo_municipio'] ?? '' ?>" required maxlength="7" pattern="[0-9]{7}" oninput="autoFillUf(this)">
                                <small class="text-muted">Código de 7 dígitos do IBGE para o município.</small>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="telefone">Telefone</label>
                                <input type="text" class="form-control" name="telefone" id="telefone" value="<?= $config['telefone'] ?? '' ?>" placeholder="(00) 0000-0000" oninput="maskTel(this)" maxlength="15">
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3">Certificado Digital e SEFAZ</h6>

                            <div class="mb-3 col-md-6">
                                <label class="form-label">Certificado Digital (.pfx)</label>
                                <input type="file" class="form-control" name="certificado_digital">
                                <?php if (!empty($config['certificado_path'])): ?>
                                    <div class="form-text text-success">Certificado atual: <?= $config['certificado_path'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="senha_certificado">Senha do Certificado</label>
                                <input type="password" class="form-control" name="senha_certificado" id="senha_certificado" placeholder="<?= !empty($config['certificado_senha']) ? '••••••••' : 'Digite a senha' ?>">
                            </div>
                            
                            <div class="mb-3 col-md-4">
                                <label class="form-label" for="ambiente">Ambiente</label>
                                <select class="form-select" name="ambiente" id="ambiente" required>
                                    <option value="homologacao" <?= ($config['ambiente'] ?? '') == 'homologacao' ? 'selected' : '' ?>>Homologação (Teste)</option>
                                    <option value="producao" <?= ($config['ambiente'] ?? '') == 'producao' ? 'selected' : '' ?>>Produção (Real)</option>
                                </select>
                            </div>
                            <div class="mb-3 col-md-4">
                                <label class="form-label" for="regime_tributario">Regime Tributário</label>
                                <select class="form-select" name="regime_tributario" id="regime_tributario" required>
                                    <option value="1" <?= ($config['regime_tributario'] ?? '') == '1' ? 'selected' : '' ?>>Simples Nacional</option>
                                    <option value="2" <?= ($config['regime_tributario'] ?? '') == '2' ? 'selected' : '' ?>>Simples - Excesso de Sublimite</option>
                                    <option value="3" <?= ($config['regime_tributario'] ?? '') == '3' ? 'selected' : '' ?>>Regime Normal (Lucro Real/Presumido)</option>
                                </select>
                            </div>
                            <div class="mb-3 col-md-4">
                                <label class="form-label" for="tipo_emissao">Tipo de Emissão</label>
                                <select class="form-select" name="tipo_emissao" id="tipo_emissao" required>
                                    <option value="1" <?= ($config['tipo_emissao'] ?? '') == '1' ? 'selected' : '' ?>>Normal</option>
                                    <option value="9" <?= ($config['tipo_emissao'] ?? '') == '9' ? 'selected' : '' ?>>Contingência Offline</option>
                                </select>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3">Configurações Específicas NFC-e</h6>

                            <div class="mb-3 col-md-3">
                                <label class="form-label" for="serie_nfce">Série NFC-e</label>
                                <input type="number" class="form-control" name="serie_nfce" id="serie_nfce" value="<?= $config['serie_nfce'] ?? '1' ?>" required>
                            </div>
                            <div class="mb-3 col-md-3">
                                <label class="form-label" for="ultimo_numero_nfce">Último Nº NFC-e</label>
                                <input type="number" class="form-control" name="ultimo_numero_nfce" id="ultimo_numero_nfce" value="<?= $config['ultimo_numero_nfce'] ?? '0' ?>" required>
                            </div>
                            <div class="mb-3 col-md-3">
                                <label class="form-label" for="csc">CSC (Token)</label>
                                <input type="text" class="form-control" name="csc" id="csc" value="<?= $config['csc'] ?? '' ?>" placeholder="AABBCC...">
                            </div>
                            <div class="mb-3 col-md-3">
                                <label class="form-label" for="csc_id">ID do CSC</label>
                                <input type="text" class="form-control" name="csc_id" id="csc_id" value="<?= $config['csc_id'] ?? '000001' ?>">
                            </div>

                            <div class="mb-3 col-md-4">
                                <label class="form-label" for="finalidade">Finalidade</label>
                                <select class="form-select" name="finalidade" id="finalidade" required>
                                    <option value="1" <?= ($config['finalidade'] ?? '') == '1' ? 'selected' : '' ?>>Normal</option>
                                    <option value="2" <?= ($config['finalidade'] ?? '') == '2' ? 'selected' : '' ?>>Complementar</option>
                                    <option value="3" <?= ($config['finalidade'] ?? '') == '3' ? 'selected' : '' ?>>Ajuste</option>
                                    <option value="4" <?= ($config['finalidade'] ?? '') == '4' ? 'selected' : '' ?>>Devolução</option>
                                </select>
                            </div>
                            <div class="mb-3 col-md-4">
                                <label class="form-label" for="ind_pres">Presença do Comprador</label>
                                <select class="form-select" name="ind_pres" id="ind_pres" required>
                                    <option value="1" <?= ($config['ind_pres'] ?? '') == '1' ? 'selected' : '' ?>>Operação presencial</option>
                                    <option value="4" <?= ($config['ind_pres'] ?? '') == '4' ? 'selected' : '' ?>>NFC-e em operação com entrega em domicílio</option>
                                </select>
                            </div>
                            <div class="mb-3 col-md-4">
                                <label class="form-label" for="tipo_impressao">Tipo de Impressão</label>
                                <select class="form-select" name="tipo_impressao" id="tipo_impressao" required>
                                    <option value="4" <?= ($config['tipo_impressao'] ?? '') == '4' ? 'selected' : '' ?>>DANFE NFC-e</option>
                                    <option value="5" <?= ($config['tipo_impressao'] ?? '') == '5' ? 'selected' : '' ?>>DANFE NFC-e em mensagem eletrônica</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary me-2">Salvar Configurações</button>
                            <a href="fiscal.php?action=settings" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const MAP_UF_IBGE = {
    "RO": 11, "AC": 12, "AM": 13, "RR": 14, "PA": 15, "AP": 16, "TO": 17, "MA": 21, "PI": 22, "CE": 23, "RN": 24, "PB": 25, "PE": 26, "AL": 27, "SE": 28, "BA": 29, "MG": 31, "ES": 32, "RJ": 33, "SP": 35, "PR": 41, "SC": 42, "RS": 43, "MS": 50, "MT": 51, "GO": 52, "DF": 53
};

const MAP_IBGE_UF = Object.fromEntries(Object.entries(MAP_UF_IBGE).map(([k, v]) => [v, k]));

function updateIbge() {
    const uf = document.getElementById('uf').value;
    const cod = MAP_UF_IBGE[uf] || '';
    document.getElementById('codigo_uf').value = cod;
}

function autoFillUf(input) {
    const val = input.value.replace(/\D/g, '');
    if (val.length >= 2) {
        const codUf = val.substring(0, 2);
        const uf = MAP_IBGE_UF[codUf];
        if (uf) {
            document.getElementById('uf').value = uf;
            document.getElementById('codigo_uf').value = codUf;
        }
    }
}

function maskCnpj(i) {
    let v = i.value.replace(/\D/g, '').slice(0, 14);
    if (v.length > 2) v = v.slice(0, 2) + '.' + v.slice(2);
    if (v.length > 6) v = v.slice(0, 6) + '.' + v.slice(6);
    if (v.length > 10) v = v.slice(0, 10) + '/' + v.slice(10);
    if (v.length > 15) v = v.slice(0, 15) + '-' + v.slice(15);
    i.value = v;
}

function maskCep(i) {
    let v = i.value.replace(/\D/g, '').slice(0, 8);
    if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
    i.value = v;
}

function maskTel(i) {
    let v = i.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 2) v = '(' + v.slice(0, 2) + ') ' + v.slice(2);
    if (v.length > 9) v = v.slice(0, 9) + '-' + v.slice(9);
    i.value = v;
}

window.addEventListener('load', updateIbge);
</script>
