<div class="mb-4">
    <ul class="nav nav-tabs border-0" id="settingsTabs" role="tablist">
        <li class="nav-item shadow-sm me-2 rounded-top bg-white">
            <button class="nav-link active fw-bold border-0 px-4 py-3" id="matriz-tab" data-bs-toggle="tab" data-bs-target="#matriz" type="button" role="tab">
                <i class="fas fa-building me-2 text-primary"></i>Matriz & Central Fiscal
            </button>
        </li>
        <li class="nav-item shadow-sm rounded-top bg-white">
            <button class="nav-link fw-bold border-0 px-4 py-3" id="unidades-tab" data-bs-toggle="tab" data-bs-target="#unidades" type="button" role="tab">
                <i class="fas fa-network-wired me-2 text-secondary"></i>Gestão de Unidades
            </button>
        </li>
    </ul>
</div>

<div class="tab-content" id="settingsTabsContent">
    <!-- ABA 1: MATRIZ & CERTIFICADO -->
    <div class="tab-pane fade show active" id="matriz" role="tabpanel">
        <form action="configuracoes.php?action=saveMatriz" method="POST" enctype="multipart/form-data">
            <div class="row g-4">
                <div class="col-lg-7">
                    <!-- Identidade -->
                    <div class="card border-0 shadow-sm mb-4 h-100">
                        <div class="card-header bg-white py-3 border-0">
                            <h6 class="mb-0 fw-bold text-dark">Identidade Corporativa (Matriz)</h6>
                        </div>
                        <div class="card-body p-4 pt-0">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Razão Social / Nome da Empresa</label>
                                    <input type="text" name="empresa_nome" class="form-control bg-light" value="<?= $currentBranch['nome'] ?? $settings['empresa_nome'] ?? '' ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">CNPJ</label>
                                    <input type="text" name="empresa_cnpj" class="form-control bg-light" value="<?= $currentBranch['cnpj'] ?? $settings['empresa_cnpj'] ?? '' ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Telefone</label>
                                    <input type="text" name="empresa_fone" class="form-control bg-light" value="<?= $currentBranch['telefone'] ?? $settings['empresa_fone'] ?? '' ?>" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Email</label>
                                    <input type="email" name="empresa_email" class="form-control bg-light" value="<?= $currentBranch['email'] ?? $settings['empresa_email'] ?? '' ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">ID Token CSC</label>
                                    <input type="text" class="form-control bg-light text-muted" value="<?= $activeConfig['csc_id'] ?? '' ?>" readonly>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Token CSC</label>
                                    <input type="text" class="form-control bg-light text-muted" value="<?= $activeConfig['csc'] ?? '' ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <!-- Certificado Digital Global -->
                    <div class="card border-0 shadow-sm h-100 bg-dark text-white border-top border-4 border-warning">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3"><i class="fas fa-certificate me-2 text-warning"></i>Certificado Digital A1 (Concentrado)</h6>
                            <p class="extra-small text-white-50 mb-4">Emita notas em todas as filiais com uma única assinatura. Configure o CSC global abaixo.</p>
                            
                            <?php if (!empty($sefaz['certificado_path'])): ?>
                                <div class="alert bg-warning text-dark border-0 small d-flex align-items-center mb-4 py-2">
                                    <i class="fas fa-check-circle me-3 fa-lg"></i>
                                    <div class="overflow-hidden">
                                        <strong class="d-block small">Certificado Ativo</strong>
                                        <span class="extra-small opacity-75 text-truncate d-block"><?= $sefaz['certificado_path'] ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                             <div class="mb-3">
                                <label class="form-label extra-small text-white-50 mb-1">Arquivo do Certificado (.pfx)</label>
                                <input type="file" name="certificado_pfx" class="form-control form-control-sm bg-secondary border-0 text-white">
                            </div>
                            <div class="mb-3">
                                <label class="form-label extra-small text-white-50 mb-1">Senha do Certificado</label>
                                <input type="password" name="certificado_senha" class="form-control form-control-sm bg-secondary border-0 text-white" value="<?= $activeConfig['certificado_senha'] ?? '' ?>">
                            </div>
                            
                            <!-- CSC Global Fields -->
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <label class="form-label extra-small text-white-50 mb-1">ID Token CSC</label>
                                    <input type="text" name="csc_id_global" class="form-control form-control-sm bg-secondary border-0 text-white" value="<?= $activeConfig['csc_id'] ?? '' ?>" placeholder="000001">
                                </div>
                                <div class="col-8">
                                    <label class="form-label extra-small text-white-50 mb-1">Token CSC</label>
                                    <input type="text" name="csc_token_global" class="form-control form-control-sm bg-secondary border-0 text-white" value="<?= $activeConfig['csc'] ?? '' ?>" placeholder="AAAA-BBBB-CCCC">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label extra-small text-white-50 mb-1">Ambiente Sefaz</label>
                                <select name="ambiente" class="form-select form-select-sm bg-secondary border-0 text-white">
                                    <option value="homologacao" <?= (($activeConfig['ambiente'] ?? '') == 'homologacao' || ($activeConfig['ambiente'] ?? '') == '2') ? 'selected' : '' ?>>Homologação (Testes)</option>
                                    <option value="producao" <?= (($activeConfig['ambiente'] ?? '') == 'producao' || ($activeConfig['ambiente'] ?? '') == '1') ? 'selected' : '' ?>>Produção (Real)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-warning w-100 fw-bold py-2 mt-auto shadow">
                                <i class="fas fa-save me-2"></i>Salvar Tudo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- ABA 2: GESTÃO DE UNIDADES (FILIAIS) -->
    <div class="tab-pane fade" id="unidades" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0 fw-bold text-dark">Unidades Registradas</h5>
            <button class="btn btn-primary btn-sm px-4 fw-bold" onclick="abrirModalFilial()">
                <i class="fas fa-plus me-2"></i>Nova Unidade
            </button>
        </div>

        <div class="row g-3">
            <?php foreach ($branches as $branch): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 hover-shadow transition">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="bg-light p-3 rounded-circle">
                                    <i class="fas fa-store text-primary fa-lg"></i>
                                </div>
                                <span class="badge <?= $branch['principal'] ? 'bg-primary' : 'bg-secondary' ?> small">
                                    <?= $branch['principal'] ? 'MATRIZ' : 'FILIAL' ?>
                                </span>
                            </div>
                            <h6 class="fw-bold mb-1"><?= $branch['nome'] ?></h6>
                            <p class="extra-small text-muted mb-3"><?= $branch['cnpj'] ?></p>
                            
                            <div class="extra-small text-muted border-top pt-2 mb-3">
                                <i class="fas fa-map-marker-alt me-1"></i> <?= $branch['municipio'] ?> - <?= $branch['uf'] ?>
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-outline-primary btn-sm fw-bold" onclick='abrirModalFilial(<?= htmlspecialchars(json_encode($branch), ENT_QUOTES, "UTF-8") ?>)'>
                                    <i class="fas fa-edit me-2"></i>Gerenciar Dados
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- MODAL: CADASTRO/EDIÇÃO DE FILIAL -->
<div class="modal fade" id="modalFilial" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="configuracoes.php?action=saveFilial" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="f_id">
                <div class="modal-header bg-dark text-white py-3 border-0">
                    <h6 class="modal-title fw-bold" id="modalTitle"><i class="fas fa-store me-2"></i>Dados da Unidade</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <!-- Nav Tabs Inside Modal -->
                    <ul class="nav nav-pills mb-4 small fw-bold" id="modalTabs">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-basico" type="button">Básico & Endereço</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-fiscal" type="button">Emissão Fiscal (NFC-e)</button></li>
                        <li class="nav-item"><button class="nav-link text-warning" data-bs-toggle="pill" data-bs-target="#tab-cert" type="button">Certificado Próprio</button></li>
                    </ul>

                    <div class="tab-content border-top pt-4">
                        <!-- Aba Básico -->
                        <div class="tab-pane fade show active" id="tab-basico">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Nome Fantasia</label>
                                    <input type="text" name="nome" id="f_nome" class="form-control shadow-sm" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">CNPJ</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="cnpj" id="f_cnpj" class="form-control shadow-sm" required onkeyup="mascaraCNPJ(this)">
                                        <button type="button" class="btn btn-primary fw-bold" onclick="consultarCNPJ()">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Razão Social</label>
                                    <input type="text" name="razao_social" id="f_razao" class="form-control shadow-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Regime (CRT)</label>
                                    <select name="crt" id="f_crt" class="form-select shadow-sm">
                                        <option value="1">1 - Simples Nacional</option>
                                        <option value="2">2 - Simples Nac. (Sublimite)</option>
                                        <option value="3">3 - Regime Normal</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Insc. Estadual</label>
                                    <input type="text" name="inscricao_estadual" id="f_ie" class="form-control shadow-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">CEP</label>
                                    <input type="text" name="cep" id="f_cep" class="form-control shadow-sm" onblur="buscarCEP()">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold">Município</label>
                                    <input type="text" name="municipio" id="f_municipio" class="form-control shadow-sm" onblur="buscarIBGEPorNome()">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label small fw-bold">UF</label>
                                    <input type="text" name="uf" id="f_uf" class="form-control shadow-sm text-center" maxlength="2" onchange="atualizarCodigoUF()">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Código UF</label>
                                    <input type="text" name="codigo_uf" id="f_cod_uf" class="form-control shadow-sm">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Cód IBGE Mun.</label>
                                    <input type="text" name="codigo_municipio" id="f_ibge_mun" class="form-control shadow-sm">
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label small fw-bold">Logradouro</label>
                                    <input type="text" name="logradouro" id="f_logradouro" class="form-control shadow-sm">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Número</label>
                                    <input type="text" name="numero" id="f_numero" class="form-control shadow-sm">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Bairro</label>
                                    <input type="text" name="bairro" id="f_bairro" class="form-control shadow-sm">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Complemento</label>
                                    <input type="text" name="complemento" id="f_complemento" class="form-control shadow-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Telefone</label>
                                    <input type="text" name="telefone" id="f_fone" class="form-control shadow-sm">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold">E-mail</label>
                                    <input type="email" name="email" id="f_email" class="form-control shadow-sm">
                                </div>
                            </div>
                        </div>

                        <!-- Aba Fiscal -->
                        <div class="tab-pane fade" id="tab-fiscal">
                            <div class="alert alert-soft-primary small mb-4">
                                <i class="fas fa-info-circle me-2"></i>Estes parâmetros são usados para a emissão de cupons fiscais (NFC-e) específicos desta unidade.
                            </div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">CSC ID (Token ID)</label>
                                    <input type="text" name="csc_id" id="f_csc_id" class="form-control shadow-sm" placeholder="Ex: 000001">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">CSC Token (Código)</label>
                                    <input type="text" name="csc_token" id="f_csc_token" class="form-control shadow-sm" placeholder="Ex: AAAA-BBBB-CCCC">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Ambiente</label>
                                    <select name="ambiente" id="f_ambiente" class="form-select shadow-sm">
                                        <option value="2">Homologação</option>
                                        <option value="1">Produção</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Tipo Emissão</label>
                                    <select name="tipo_emissao" id="f_tipo_emissao" class="form-select shadow-sm">
                                        <option value="Normal">Normal</option>
                                        <option value="Contingencia">Contingência</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Finalidade</label>
                                    <select name="finalidade_emissao" id="f_finalidade" class="form-select shadow-sm">
                                        <option value="Normal">Normal</option>
                                        <option value="Complementar">Complementar</option>
                                        <option value="Ajuste">Ajuste</option>
                                        <option value="Devolucao">Devolução</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Ind. Presença</label>
                                    <select name="indicador_presenca" id="f_presenca" class="form-select shadow-sm">
                                        <option value="Operacao presencial">Presencial</option>
                                        <option value="Nao presencial, pela Internet">Internet</option>
                                        <option value="Nao presencial, Teleatendimento">Telemarketing</option>
                                        <option value="Nao se aplica">Não se aplica</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Tipo Impressão</label>
                                    <select name="tipo_impressao_danfe" id="f_impressao" class="form-select shadow-sm">
                                        <option value="NFC-e">NFC-e (Bobina)</option>
                                        <option value="Simplificada">Simplificada</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Série NFC-e</label>
                                    <input type="number" name="serie_nfce" id="f_serie" class="form-control shadow-sm" value="1">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Último Nº NFC-e</label>
                                    <input type="number" name="ultimo_numero_nfce" id="f_ultimo" class="form-control shadow-sm" value="0">
                                </div>
                            </div>
                        </div>

                        <!-- Aba Certificado Próprio -->
                        <div class="tab-pane fade" id="tab-cert">
                            <div class="card border-warning border-dashed bg-white p-4">
                                <h6 class="fw-bold mb-3">Certificado Exclusivo (Opcional)</h6>
                                <p class="small text-muted mb-4">Se esta unidade possuir um certificado diferente da matriz, faça o upload aqui. Caso contrário, o sistema usará o Certificado Global configurado na página principal.</p>
                                
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label small fw-bold">Novo Arquivo .pfx</label>
                                        <input type="file" name="certificado" class="form-control shadow-sm">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-bold">Senha do Certificado</label>
                                        <input type="password" name="certificado_senha_filial" id="f_cert_senha" class="form-control shadow-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="principal" id="f_principal" value="0">
                <input type="hidden" name="is_matriz" id="f_is_matriz" value="0">
                <input type="hidden" name="crt" id="f_crt_hidden" value="1">

                <div class="modal-footer bg-white border-0 py-3">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="fas fa-save me-2"></i>Salvar Unidade</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab === 'unidades') {
        const triggerEl = document.querySelector('#unidades-tab');
        if (triggerEl) {
            bootstrap.Tab.getOrCreateInstance(triggerEl).show();
        }
    }
});

function abrirModalFilial(data = null) {
    const modalEl = document.getElementById('modalFilial');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    document.getElementById('modalTitle').innerHTML = data ? '<i class="fas fa-edit me-2 text-white"></i><span class="text-white">Editar Unidade</span>' : '<i class="fas fa-plus me-2 text-white"></i><span class="text-white">Nova Unidade</span>';
    
    // Global defaults for pre-filling (from actual Matriz/Principal branch)
    const defaultSefaz = {
        csc_id: <?= json_encode($matrizConfig['csc_id'] ?? '') ?>,
        csc_token: <?= json_encode($matrizConfig['csc'] ?? '') ?>,
        ambiente: <?= json_encode(($matrizConfig['ambiente'] ?? '') == '1' || ($matrizConfig['ambiente'] ?? '') == 'producao' ? '1' : '2') ?>,
        senha: <?= json_encode($matrizConfig['certificado_senha'] ?? '') ?>
    };

    // Reset inputs
    document.getElementById('f_id').value = data ? data.id : '';
    document.getElementById('f_nome').value = data ? data.nome : '';
    document.getElementById('f_cnpj').value = data ? data.cnpj : '';
    document.getElementById('f_razao').value = data ? data.razao_social : '';
    document.getElementById('f_crt').value = data ? data.crt : '1';
    document.getElementById('f_ie').value = data ? data.inscricao_estadual : '';
    document.getElementById('f_cep').value = data ? data.cep : '';
    document.getElementById('f_municipio').value = data ? data.municipio : '';
    document.getElementById('f_ibge_mun').value = data ? data.codigo_municipio : '';
    document.getElementById('f_logradouro').value = data ? data.logradouro : '';
    document.getElementById('f_numero').value = data ? data.numero : '';
    document.getElementById('f_bairro').value = data ? data.bairro : '';
    document.getElementById('f_complemento').value = data ? data.complemento : '';
    document.getElementById('f_uf').value = data ? data.uf : '';
    document.getElementById('f_cod_uf').value = data ? data.codigo_uf : '';
    document.getElementById('f_fone').value = data ? data.telefone : '';
    document.getElementById('f_email').value = data ? data.email : '';
    
    // Pre-fill fiscal and certificate with data if editing, or global defaults if new
    document.getElementById('f_csc_id').value = data ? data.csc_id : defaultSefaz.csc_id;
    document.getElementById('f_csc_token').value = data ? data.csc_token : defaultSefaz.csc_token;
    document.getElementById('f_serie').value = data ? data.serie_nfce : '1';
    document.getElementById('f_ultimo').value = data ? data.ultimo_numero_nfce : '0';
    document.getElementById('f_ambiente').value = data ? data.ambiente : defaultSefaz.ambiente;
    
    document.getElementById('f_tipo_emissao').value = data ? data.tipo_emissao : 'Normal';
    document.getElementById('f_finalidade').value = data ? data.finalidade_emissao : 'Normal';
    document.getElementById('f_presenca').value = data ? data.indicador_presenca : 'Operacao presencial';
    document.getElementById('f_impressao').value = data ? data.tipo_impressao_danfe : 'NFC-e';

    document.getElementById('f_cert_senha').value = data ? data.certificado_senha : defaultSefaz.senha;
    
    document.getElementById('f_principal').value = data ? data.principal : '0';

    modal.show();
}

// Auto behavior for hash
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash === '#unidades') {
        const tab = new bootstrap.Tab(document.getElementById('unidades-tab'));
        tab.show();
    }
});
function mascaraCNPJ(input) {
    let v = input.value.replace(/\D/g, "");
    if (v.length > 14) v = v.substring(0, 14);
    v = v.replace(/^(\d{2})(\d)/, "$1.$2");
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
    v = v.replace(/\.(\d{3})(\d)/, ".$1/$2");
    v = v.replace(/(\d{4})(\d)/, "$1-$2");
    input.value = v;
}

async function consultarCNPJ() {
    const cnpjInput = document.getElementById('f_cnpj');
    if (!cnpjInput) return;
    const cnpj = cnpjInput.value.replace(/\D/g, '');
    const btn = cnpjInput.parentElement.querySelector('button');
    const originalText = btn.innerHTML;
    
    if (cnpj.length !== 14) {
        alert('Por favor, informe um CNPJ válido com 14 dígitos.');
        return;
    }

    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    try {
        const response = await fetch(`api/cnpj_search.php?cnpj=${cnpj}`);
        if (!response.ok) {
            if (response.status === 429) throw new Error('Limite de consultas excedido. Aguarde alguns minutos e tente novamente.');
            const text = await response.text();
            let errMessage = 'Falha na consulta.';
            try { const errData = JSON.parse(text); errMessage = errData.error || errMessage; } catch(e) {}
            throw new Error(errMessage);
        }
        
        const data = await response.json();
        
        // Auto-Fill Fields based on settings.view.php IDs
        const fieldMapping = {
            'f_razao': data.razao_social || '',
            'f_nome': data.nome_fantasia || data.razao_social || '',
            'f_fone': data.ddd_telefone_1 || '',
            'f_email': data.email || '',
            'f_cep': data.cep || '',
            'f_logradouro': data.logradouro || '',
            'f_numero': data.numero || '',
            'f_complemento': data.complemento || '',
            'f_bairro': data.bairro || '',
            'f_municipio': data.municipio || '',
            'f_uf': data.uf || ''
        };

        Object.keys(fieldMapping).forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = fieldMapping[id];
        });
        
        atualizarCodigoUF();
        
        // Now try to get IBGE via CEP
        if (data.cep) {
            await buscarCEP();
        } else {
            buscarIBGEPorNome();
        }
        
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 2000);
        
    } catch (error) {
        alert('Erro ao buscar CNPJ: ' + error.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function buscarCEP() {
    const cepInput = document.getElementById('f_cep');
    if (!cepInput) return;
    const cep = cepInput.value.replace(/\D/g, '');
    if (cep.length !== 8) return;

    try {
        const response = await fetch(`api/cep_search.php?cep=${cep}`);
        if (!response.ok) {
            if (response.status === 429) return; // Silent limit reached
            return;
        }
        const data = await response.json();
            if (data.logradouro) document.getElementById('f_logradouro').value = data.logradouro;
            if (data.bairro) document.getElementById('f_bairro').value = data.bairro;
            if (data.localidade || data.city) document.getElementById('f_municipio').value = data.localidade || data.city;
            if (data.uf || data.state) document.getElementById('f_uf').value = data.uf || data.state;
            
            atualizarCodigoUF();
            
            if (data.ibge) {
                document.getElementById('f_ibge_mun').value = data.ibge;
            } else {
                buscarIBGEPorNome();
            }
        }
    } catch (e) {
        console.error("Erro ao buscar CEP", e);
    }
}

async function buscarIBGEPorNome() {
    const munInput = document.getElementById('f_municipio');
    const ufInput = document.getElementById('f_uf');
    const ibgeInput = document.getElementById('f_ibge_mun');
    
    if (!munInput || !ufInput || !ibgeInput) return;
    if (munInput.value === '' || ufInput.value === '') return;

    try {
        const uf = ufInput.value.trim().toUpperCase();
        const municipio = munInput.value.trim().toUpperCase();
        
        const response = await fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios`);
        if (response.ok) {
            const municipios = await response.json();
            const normalize = (str) => str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toUpperCase();
            const target = normalize(municipio);
            
            const match = municipios.find(m => normalize(m.nome) === target);
            if (match) {
                ibgeInput.value = match.id;
            }
        }
    } catch (e) {
        console.error("Erro ao buscar IBGE por nome", e);
    }
}

const MAP_UF_IBGE = {
    "RO": 11, "AC": 12, "AM": 13, "RR": 14, "PA": 15, "AP": 16, "TO": 17,
    "MA": 21, "PI": 22, "CE": 23, "RN": 24, "PB": 25, "PE": 26, "AL": 27, "SE": 28, "BA": 29,
    "MG": 31, "ES": 32, "RJ": 33, "SP": 35, "PR": 41, "SC": 42, "RS": 43, "MS": 50, "MT": 51, "GO": 52, "DF": 53
};

function atualizarCodigoUF() {
    const ufInput = document.getElementById('f_uf');
    if (!ufInput) return;
    const uf = ufInput.value.trim().toUpperCase();
    const codigo = MAP_UF_IBGE[uf] || '';
    if (codigo) {
        const codUfInput = document.getElementById('f_cod_uf');
        if (codUfInput) codUfInput.value = codigo;
    }
}
</script>

<style>
.hover-shadow:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; transform: translateY(-3px); }
.transition { transition: all .2s ease-in-out; }
.alert-soft-warning { background-color: rgba(255, 193, 7, 0.1); color: #856404; }
.alert-soft-primary { background-color: rgba(13, 110, 253, 0.1); color: #084298; }
.border-dashed { border-style: dashed !important; }
.extra-small { font-size: 0.75rem; }

/* Custom Overrides for Tabs */
.nav-pills .nav-link.active {
    background-color: #ffc107 !important;
    color: #000 !important;
}
.nav-pills .nav-link {
    color: #6c757d;
}
</style>
