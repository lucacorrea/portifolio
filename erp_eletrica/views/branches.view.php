<!-- Page Header / Actions -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-network-wired me-2"></i>Unidades Operacionais</h6>
        <button class="btn btn-primary fw-bold" onclick="newBranch()">
            <i class="fas fa-plus-circle me-2"></i>Expandir Operação
        </button>
    </div>
</div>

<!-- Branches Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" width="100">Cód.</th>
                        <th>Nome da Unidade</th>
                        <th>CNPJ / Identificação</th>
                        <th>Tipo / Status</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $b): ?>
                    <tr>
                        <td class="ps-4 small fw-bold text-muted">#<?= str_pad($b['id'], 3, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px;">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="fw-bold text-dark"><?= $b['nome'] ?></div>
                            </div>
                        </td>
                        <td class="small fw-bold text-muted"><?= $b['cnpj'] ?? '---' ?></td>
                        <td>
                            <?php if ($b['principal']): ?>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 border border-success border-opacity-25">SEDE MATRIZ</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 border border-secondary border-opacity-25">FILIAL ATIVA</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-light border" title="Configurar Unidade" onclick="editBranch(<?= htmlspecialchars(json_encode($b)) ?>)">
                                    <i class="fas fa-cog text-primary"></i>
                                </button>
                                <button class="btn btn-light border text-danger" title="Suspender Atividades">
                                    <i class="fas fa-pause-circle"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Fiscal Configuration Modal -->
<div class="modal fade" id="branchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" action="filiais.php?action=save" method="POST" enctype="multipart/form-data">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Configuração Fiscal da Unidade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id" id="branch_id">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nome Fantasia (Apelido)</label>
                        <input type="text" name="nome" id="branch_nome" class="form-control shadow-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Razão Social (Contrato Social)</label>
                        <input type="text" name="razao_social" id="branch_razao_social" class="form-control shadow-sm text-uppercase" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold">CNPJ</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="cnpj" id="branch_cnpj" class="form-control shadow-sm" placeholder="00.000.000/0000-00" onkeyup="mascaraCNPJ(this)">
                            <button type="button" class="btn btn-outline-primary fw-bold" onclick="consultarCNPJ()">
                                <i class="fas fa-search me-1"></i> Buscar Dados
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Inscrição Estadual</label>
                        <input type="text" name="inscricao_estadual" id="branch_ie" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Ambiente SEFAZ</label>
                        <select name="ambiente" id="branch_ambiente" class="form-select shadow-sm">
                            <option value="2">Homologação</option>
                            <option value="1">Produção</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Regime (CRT)</label>
                        <select name="crt" id="branch_crt" class="form-select shadow-sm">
                            <option value="1">1 - Simples Nacional</option>
                            <option value="2">2 - Simples Excesso</option>
                            <option value="3">3 - Regime Normal</option>
                        </select>
                    </div>
                    <div class="col-md-6 mt-2">
                        <label class="form-label small fw-bold">Telefone da Unidade</label>
                        <input type="text" name="telefone" id="branch_telefone" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-6 mt-2">
                        <label class="form-label small fw-bold">E-mail de Contato (NFE)</label>
                        <input type="email" name="email" id="branch_email" class="form-control shadow-sm">
                    </div>
                    
                    <div class="col-12 mt-4 mb-2"><h6 class="fw-bold text-primary small border-bottom pb-2">Endereço Fiscal (Identico a SEFAZ)</h6></div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">CEP</label>
                        <input type="text" name="cep" id="branch_cep" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-bold">Logradouro</label>
                        <input type="text" name="logradouro" id="branch_logradouro" class="form-control shadow-sm" placeholder="Av / Rua / Travessa">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Nº</label>
                        <input type="text" name="numero" id="branch_numero" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Complemento</label>
                        <input type="text" name="complemento" id="branch_complemento" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Bairro</label>
                        <input type="text" name="bairro" id="branch_bairro" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Município</label>
                        <input type="text" name="municipio" id="branch_municipio" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small fw-bold">UF</label>
                        <input type="text" name="uf" id="branch_uf" class="form-control shadow-sm" maxlength="2" oninput="this.value = this.value.toUpperCase(); atualizarCodigoUF();" onchange="atualizarCodigoUF()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Código UF</label>
                        <input type="text" name="codigo_uf" id="branch_codigo_uf" class="form-control shadow-sm" placeholder="Ex: 35">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Cód IBGE Mun.</label>
                        <input type="text" name="codigo_municipio" id="branch_codigo_municipio" class="form-control shadow-sm" placeholder="ex: 3550308">
                    </div>

                    <div class="col-12 mt-4 mb-2"><h6 class="fw-bold text-primary small border-bottom pb-2">Configurações Avançadas (NF-e / NFC-e)</h6></div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Tipo de Emissão</label>
                        <select name="tipo_emissao" id="branch_tipo_emissao" class="form-select shadow-sm">
                            <option value="Normal">Normal</option>
                            <option value="Contingencia SVC-AN">Contingência SVC-AN</option>
                            <option value="Contingencia SVC-RS">Contingência SVC-RS</option>
                            <option value="Contingencia Offline">Contingência Offline (NFC-e)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Finalidade da Emissão</label>
                        <select name="finalidade_emissao" id="branch_finalidade_emissao" class="form-select shadow-sm">
                            <option value="Normal">Normal</option>
                            <option value="Complementar">Complementar</option>
                            <option value="Ajuste">Ajuste</option>
                            <option value="Devolucao">Devolução</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Indicador de Presença</label>
                        <select name="indicador_presenca" id="branch_indicador_presenca" class="form-select shadow-sm">
                            <option value="Operacao presencial">Operação Presencial</option>
                            <option value="Operacao pela internet">Operação pela internet</option>
                            <option value="Operacao teleatendimento">Teleatendimento</option>
                            <option value="NFC-e em operacao com entrega a domicilio">Entrega a domicílio</option>
                            <option value="Nao se aplica (Nota complementar, etc)">Não se aplica</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Tipo Impressão DANFE</label>
                        <select name="tipo_impressao_danfe" id="branch_tipo_impressao_danfe" class="form-select shadow-sm">
                            <option value="NFC-e">NFC-e (Bobina)</option>
                            <option value="Retrato">Retrato (A4)</option>
                            <option value="Paisagem">Paisagem (A4)</option>
                            <option value="Simplificado">Simplificado</option>
                        </select>
                    </div>
                    <div class="col-md-2 mt-3">
                        <label class="form-label small fw-bold">Série da NFC-e</label>
                        <input type="number" name="serie_nfce" id="branch_serie_nfce" class="form-control shadow-sm" value="1">
                        <div class="extra-small text-muted mt-1">Geralmente 1.</div>
                    </div>
                    <div class="col-md-3 mt-3">
                        <label class="form-label small fw-bold">Último número NFC-e</label>
                        <input type="number" name="ultimo_numero_nfce" id="branch_ultimo_numero_nfce" class="form-control shadow-sm" value="0">
                        <div class="extra-small text-muted mt-1">Prox. número será este + 1.</div>
                    </div>

                    <div class="col-12 mt-4 mb-2"><h6 class="fw-bold text-primary small border-bottom pb-2">Credenciais e Certificado Digital</h6></div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">CSC ID</label>
                        <input type="text" name="csc_id" id="branch_csc_id" class="form-control shadow-sm" placeholder="000001">
                        <div class="extra-small text-muted mt-1">Obrigatório p/ geração QR Code.</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">CSC Token</label>
                        <input type="text" name="csc_token" id="branch_csc_token" class="form-control shadow-sm" placeholder="A074385C-E888-485A-99A9-...">
                    </div>
                    <div class="col-md-6 mt-3">
                        <label class="form-label small fw-bold">Certificado Digital (A1 .pfx)</label>
                        <input type="file" name="certificado" class="form-control shadow-sm">
                        <div id="cert_info" class="extra-small text-success mt-1 fw-bold"></div>
                    </div>
                    <div class="col-md-6 mt-3">
                        <label class="form-label small fw-bold">Senha do Certificado</label>
                        <input type="password" name="certificado_senha" id="branch_cert_senha" class="form-control shadow-sm" placeholder="Deixe em branco para manter">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Salvar Configuração</button>
            </div>
        </form>
    </div>
</div>

<script>
function newBranch() {
    const modal = new bootstrap.Modal(document.getElementById('branchModal'));
    document.getElementById('branch_id').value = '';
    document.getElementById('branch_nome').value = '';
    document.getElementById('branch_razao_social').value = '';
    document.getElementById('branch_ambiente').value = 2;
    document.getElementById('branch_crt').value = 1;
    document.getElementById('branch_cnpj').value = '';
    document.getElementById('branch_ie').value = '';
    
    // Novas Configs NFCe Default Defaults
    document.getElementById('branch_tipo_emissao').value = 'Normal';
    document.getElementById('branch_finalidade_emissao').value = 'Normal';
    document.getElementById('branch_indicador_presenca').value = 'Operacao presencial';
    document.getElementById('branch_tipo_impressao_danfe').value = 'NFC-e';
    document.getElementById('branch_serie_nfce').value = 1;
    document.getElementById('branch_ultimo_numero_nfce').value = 0;

    document.getElementById('branch_telefone').value = '';
    document.getElementById('branch_email').value = '';
    document.getElementById('branch_logradouro').value = '';
    document.getElementById('branch_numero').value = '';
    document.getElementById('branch_complemento').value = '';
    document.getElementById('branch_bairro').value = '';
    document.getElementById('branch_municipio').value = '';
    document.getElementById('branch_codigo_municipio').value = '';
    document.getElementById('branch_uf').value = '';
    document.getElementById('branch_codigo_uf').value = '';
    document.getElementById('branch_cep').value = '';
    document.getElementById('branch_csc_id').value = '';
    document.getElementById('branch_csc_token').value = '';
    document.getElementById('branch_cert_senha').value = '';
    document.getElementById('cert_info').innerText = 'Novo certificado (.pfx) será carregado';
    modal.show();
}

function editBranch(branch) {
    const modal = new bootstrap.Modal(document.getElementById('branchModal'));
    document.getElementById('branch_id').value = branch.id;
    document.getElementById('branch_nome').value = branch.nome;
    document.getElementById('branch_razao_social').value = branch.razao_social || '';
    document.getElementById('branch_ambiente').value = branch.ambiente || 2;
    document.getElementById('branch_crt').value = branch.crt || 1;
    document.getElementById('branch_cnpj').value = branch.cnpj || '';
    document.getElementById('branch_ie').value = branch.inscricao_estadual || '';

    // Advanced NFce Configs
    document.getElementById('branch_tipo_emissao').value = branch.tipo_emissao || 'Normal';
    document.getElementById('branch_finalidade_emissao').value = branch.finalidade_emissao || 'Normal';
    document.getElementById('branch_indicador_presenca').value = branch.indicador_presenca || 'Operacao presencial';
    document.getElementById('branch_tipo_impressao_danfe').value = branch.tipo_impressao_danfe || 'NFC-e';
    document.getElementById('branch_serie_nfce').value = branch.serie_nfce || 1;
    document.getElementById('branch_ultimo_numero_nfce').value = branch.ultimo_numero_nfce || 0;

    document.getElementById('branch_telefone').value = branch.telefone || '';
    document.getElementById('branch_email').value = branch.email || '';
    document.getElementById('branch_logradouro').value = branch.logradouro || '';
    document.getElementById('branch_numero').value = branch.numero || '';
    document.getElementById('branch_complemento').value = branch.complemento || '';
    document.getElementById('branch_bairro').value = branch.bairro || '';
    document.getElementById('branch_municipio').value = branch.municipio || '';
    document.getElementById('branch_codigo_municipio').value = branch.codigo_municipio || '';
    document.getElementById('branch_uf').value = branch.uf || '';
    document.getElementById('branch_codigo_uf').value = branch.codigo_uf || '';
    document.getElementById('branch_cep').value = branch.cep || '';
    document.getElementById('branch_csc_id').value = branch.csc_id || '';
    document.getElementById('branch_csc_token').value = branch.csc_token || '';
    document.getElementById('branch_cert_senha').value = branch.certificado_senha || '';
    
    document.getElementById('cert_info').innerText = branch.certificado_pfx ? 'Certificado atual: ' + branch.certificado_pfx : 'Nenhum certificado carregado';
    
    modal.show();
}

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
    const cnpjInput = document.getElementById('branch_cnpj');
    const cnpj = cnpjInput.value.replace(/\D/g, '');
    const btn = cnpjInput.nextElementSibling;
    const originalText = btn.innerHTML;
    
    if (cnpj.length !== 14) {
        alert('Por favor, informe um CNPJ válido com 14 dígitos.');
        return;
    }

    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
    btn.disabled = true;

    try {
        const response = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`);
        if (!response.ok) {
            throw new Error('Falha na consulta. CNPJ rejeitado ou limitação de API.');
        }
        
        const data = await response.json();
        
        let ibgeCode = '';
        if (data.cep) {
            try {
                const cepRes = await fetch(`https://brasilapi.com.br/api/cep/v2/${data.cep.replace(/\D/g, '')}`);
                if (cepRes.ok) {
                    const cepData = await cepRes.json();
                    if (cepData.ibge) ibgeCode = cepData.ibge;
                }
            } catch(e) {}
        }
        
        // Auto-Fill Fields
        document.getElementById('branch_razao_social').value = data.razao_social || '';
        document.getElementById('branch_nome').value = data.nome_fantasia || data.razao_social || '';
        document.getElementById('branch_telefone').value = data.ddd_telefone_1 || '';
        document.getElementById('branch_email').value = data.email || '';
        
        document.getElementById('branch_cep').value = data.cep || '';
        document.getElementById('branch_logradouro').value = data.logradouro || '';
        document.getElementById('branch_numero').value = data.numero || '';
        document.getElementById('branch_complemento').value = data.complemento || '';
        document.getElementById('branch_bairro').value = data.bairro || '';
        document.getElementById('branch_municipio').value = data.municipio || '';
        document.getElementById('branch_uf').value = data.uf || '';
        atualizarCodigoUF();
        document.getElementById('branch_codigo_municipio').value = ibgeCode;
        
        // Flash success
        btn.innerHTML = '<i class="fas fa-check text-success"></i> Sucesso!';
        setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 2000);
        
    } catch (error) {
        alert('Erro ao buscar CNPJ: ' + error.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

const MAP_UF_IBGE = {
    "RO": 11, "AC": 12, "AM": 13, "RR": 14, "PA": 15, "AP": 16, "TO": 17,
    "MA": 21, "PI": 22, "CE": 23, "RN": 24, "PB": 25, "PE": 26, "AL": 27, "SE": 28, "BA": 29,
    "MG": 31, "ES": 32, "RJ": 33, "SP": 35, "PR": 41, "SC": 42, "RS": 43, "MS": 50, "MT": 51, "GO": 52, "DF": 53
};

function atualizarCodigoUF() {
    const uf = (document.getElementById('branch_uf').value || '').trim().toUpperCase();
    const codigo = MAP_UF_IBGE[uf] || '';
    if (codigo) {
        document.getElementById('branch_codigo_uf').value = codigo;
    }
}
</script>
