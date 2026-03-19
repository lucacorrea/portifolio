<style>
    :root { --theme-yellow: #FFC107; --theme-dark: #121212; --theme-dark-soft: #1e1e1e; }
    .theme-color { color: var(--theme-yellow) !important; }
    .theme-bg { background-color: var(--theme-yellow) !important; color: #000 !important; }
    .theme-bg-soft { background-color: rgba(255, 193, 7, 0.1); color: var(--theme-yellow) !important; }
    .theme-border { border-color: var(--theme-yellow) !important; }
    .nav-tabs .nav-link.active { border-bottom: 3px solid var(--theme-yellow) !important; color: #000 !important; font-weight: 800 !important; }
    .erp-card-dark { background-color: var(--theme-dark) !important; color: #fff !important; }
    .erp-input-dark { background-color: var(--theme-dark-soft) !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; }
    .erp-input-dark:focus { background-color: var(--theme-dark-soft); color: #fff; border-color: var(--theme-yellow); }
    .btn-theme { background-color: var(--theme-yellow); color: #000; font-weight: 700; transition: all 0.2s; border: none; }
    .btn-theme:hover { background-color: #e6af06; transform: translateY(-1px); color: #000; }
    .theme-alert { background-color: rgba(255, 193, 7, 0.15); border-left: 4px solid var(--theme-yellow); }
    .hover-shadow:hover { box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.1)!important; transform: translateY(-3px); }
    .transition { transition: all .2s ease-in-out; }
    .extra-small { font-size: 0.75rem; }
    .border-dashed { border-style: dashed !important; }
</style>

<div class="mb-4">
    <ul class="nav nav-tabs border-0" id="settingsTabs" role="tablist">
        <li class="nav-item me-2">
            <button class="nav-link active fw-bold border-0 px-4 py-3 rounded-top transition shadow-sm" id="matriz-tab" data-bs-toggle="tab" data-bs-target="#matriz" type="button" role="tab">
                <i class="fas fa-building me-2 theme-color"></i>Matriz & Central Fiscal
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold border-0 px-4 py-3 rounded-top transition shadow-sm" id="unidades-tab" data-bs-toggle="tab" data-bs-target="#unidades" type="button" role="tab">
                <i class="fas fa-network-wired me-2 text-muted"></i>Gestão de Unidades
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
                                    <input type="text" name="empresa_nome" class="form-control bg-light" value="<?= $settings['empresa_nome'] ?? '' ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">CNPJ</label>
                                    <input type="text" name="empresa_cnpj" class="form-control bg-light" value="<?= $settings['empresa_cnpj'] ?? '' ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Telefone</label>
                                    <input type="text" name="empresa_fone" class="form-control bg-light" value="<?= $settings['empresa_fone'] ?? '' ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Email</label>
                                    <input type="email" name="empresa_email" class="form-control bg-light" value="<?= $settings['empresa_email'] ?? '' ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Alerta Estoque Mínimo</label>
                                    <input type="number" name="estoque_min_default" class="form-control bg-light" value="<?= $settings['estoque_min_default'] ?? '5' ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <!-- Certificado Digital Global -->
                    <div class="card border-0 shadow-lg h-100 erp-card-dark text-white border-top border-4 theme-border">
                        <div class="card-body p-4 d-flex flex-column">
                            <h6 class="fw-bold mb-3 d-flex align-items-center"><i class="fas fa-certificate me-2 theme-color"></i>Certificado Digital A1</h6>
                            <p class="extra-small text-white-50 mb-4 lh-sm">Centralize a emissão de todas as unidades através do certificado da matriz.</p>
                            
                            <?php if (!empty($sefaz['certificado_path'])): ?>
                                <div class="theme-alert rounded p-3 mb-4 d-flex align-items-center">
                                    <div class="theme-icon-bg me-3 bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <strong class="d-block extra-small text-white">Certificado Ativo</strong>
                                        <span class="extra-small text-white-50 text-truncate d-block"><?= $sefaz['certificado_path'] ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label extra-small text-white-50 mb-1">Arquivo do Certificado (.pfx)</label>
                                <input type="file" name="certificado_pfx" class="form-control form-control-sm erp-input-dark border-0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label extra-small text-white-50 mb-1">Senha do Certificado</label>
                                <input type="password" name="certificado_senha" class="form-control form-control-sm erp-input-dark border-0" value="<?= $sefaz['certificado_senha'] ?? '' ?>">
                            </div>
                            
                            <!-- CSC Global Fields -->
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <label class="form-label extra-small text-white-50 mb-1">ID Token</label>
                                    <input type="text" name="csc_id_global" class="form-control form-control-sm erp-input-dark border-0" value="<?= $sefaz['csc_id'] ?? '' ?>" placeholder="000001">
                                </div>
                                <div class="col-8">
                                    <label class="form-label extra-small text-white-50 mb-1">CSC (Token)</label>
                                    <input type="text" name="csc_token_global" class="form-control form-control-sm erp-input-dark border-0" value="<?= $sefaz['csc'] ?? '' ?>" placeholder="AAAA-BBBB-CCCC">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label extra-small text-white-50 mb-1">Ambiente Sefaz</label>
                                <select name="ambiente" class="form-select form-select-sm erp-input-dark border-0">
                                    <option value="homologacao" <?= ($sefaz['ambiente'] ?? '') === 'homologacao' ? 'selected' : '' ?> class="bg-dark">Homologação (Testes)</option>
                                    <option value="producao" <?= ($sefaz['ambiente'] ?? '') === 'producao' ? 'selected' : '' ?> class="bg-dark">Produção (Real)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-theme w-100 fw-bold py-2 shadow-sm mt-auto">
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
                                    <i class="fas fa-store theme-color fa-lg"></i>
                                </div>
                                <span class="badge <?= $branch['principal'] ? 'theme-bg' : 'bg-light text-muted border' ?> small fw-bold">
                                    <?= $branch['principal'] ? 'MATRIZ' : 'FILIAL' ?>
                                </span>
                            </div>
                            <h6 class="fw-bold mb-1"><?= $branch['nome'] ?></h6>
                            <p class="extra-small text-muted mb-3"><?= $branch['cnpj'] ?></p>
                            
                            <div class="extra-small text-muted border-top pt-2 mb-3 text-truncate">
                                <i class="fas fa-map-marker-alt me-1 text-muted"></i> <?= $branch['municipio'] ?> - <?= $branch['uf'] ?>
                            </div>

                            <div class="d-grid mt-auto">
                                <button class="btn btn-outline-dark btn-sm fw-bold transition" onclick='abrirModalFilial(<?= json_encode($branch) ?>)'>
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
                    <ul class="nav nav-pills mb-4 small fw-bold gap-2" id="modalTabs">
                        <li class="nav-item"><button class="nav-link active theme-bg shadow-sm px-4" data-bs-toggle="pill" data-bs-target="#tab-basico" type="button">Básico & Endereço</button></li>
                        <li class="nav-item"><button class="nav-link text-muted px-4" data-bs-toggle="pill" data-bs-target="#tab-fiscal" type="button">Emissão Fiscal (NFC-e)</button></li>
                        <li class="nav-item"><button class="nav-link text-muted px-4" data-bs-toggle="pill" data-bs-target="#tab-cert" type="button">Certificado Próprio</button></li>
                    </ul>

                    <div class="tab-content border-top pt-4">
                        <!-- Aba Básico -->
                        <div class="tab-pane fade show active" id="tab-basico">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label extra-small fw-bold text-muted">Nome Fantasia / Identificação</label>
                                    <input type="text" name="nome" id="f_nome" class="form-control border-0 bg-white shadow-sm" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label extra-small fw-bold text-muted">CNPJ</label>
                                    <input type="text" name="cnpj" id="f_cnpj" class="form-control border-0 bg-white shadow-sm" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label extra-small fw-bold text-muted">Razão Social</label>
                                    <input type="text" name="razao_social" id="f_razao" class="form-control border-0 bg-white shadow-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label extra-small fw-bold text-muted">Insc. Estadual</label>
                                    <input type="text" name="inscricao_estadual" id="f_ie" class="form-control border-0 bg-white shadow-sm">
                                                    <div class="col-md-4">
                                    <label class="form-label extra-small fw-bold text-muted">CEP</label>
                                    <input type="text" name="cep" id="f_cep" class="form-control border-0 bg-white shadow-sm">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label extra-small fw-bold text-muted">Município</label>
                                    <input type="text" name="municipio" id="f_municipio" class="form-control border-0 bg-white shadow-sm">
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label extra-small fw-bold text-muted">Logradouro</label>
                                    <input type="text" name="logradouro" id="f_logradouro" class="form-control border-0 bg-white shadow-sm">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label extra-small fw-bold text-muted">Número</label>
                                    <input type="text" name="numero" id="f_numero" class="form-control border-0 bg-white shadow-sm">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label extra-small fw-bold text-muted">Bairro</label>
                                    <input type="text" name="bairro" id="f_bairro" class="form-control border-0 bg-white shadow-sm">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label extra-small fw-bold text-muted">Complemento</label>
                                    <input type="text" name="complemento" id="f_complemento" class="form-control border-0 bg-white shadow-sm">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label extra-small fw-bold text-muted">UF</label>
                                    <input type="text" name="uf" id="f_uf" class="form-control border-0 bg-white shadow-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label extra-small fw-bold text-muted">Telefone</label>
                                    <input type="text" name="telefone" id="f_fone" class="form-control border-0 bg-white shadow-sm">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label extra-small fw-bold text-muted">E-mail</label>
                                    <input type="email" name="email" id="f_email" class="form-control border-0 bg-white shadow-sm">
                                </div>
              </div>
                            </div>
                        </div>

                        <!-- Aba Fiscal -->
                        <div class="tab-pane fade" id="tab-fiscal">
                            <div class="theme-alert rounded p-3 mb-4 d-flex align-items-center">
                                <i class="fas fa-info-circle theme-color me-3 fa-lg"></i>
                                <div class="extra-small text-muted fw-bold">Parâmetros exclusivos para emissão de NFC-e nesta unidade.</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label extra-small fw-bold text-muted">ID Token CSC</label>
                                    <input type="text" name="csc_id" id="f_csc_id" class="form-control border-0 bg-white shadow-sm" placeholder="Ex: 000001">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label extra-small fw-bold text-muted">Token CSC (Código)</label>
                                    <input type="text" name="csc_token" id="f_csc_token" class="form-control border-0 bg-white shadow-sm" placeholder="Ex: AAAA-BBBB-CCCC">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label extra-small fw-bold text-muted">Série NFC-e</label>
                                    <input type="number" name="serie_nfce" id="f_serie" class="form-control border-0 bg-white shadow-sm" value="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label extra-small fw-bold text-muted">Último Nº NFC-e</label>
                                    <input type="number" name="ultimo_numero_nfce" id="f_ultimo" class="form-control border-0 bg-white shadow-sm" value="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label extra-small fw-bold text-muted">Ambiente</label>
                                    <select name="ambiente" id="f_ambiente" class="form-select border-0 bg-white shadow-sm">
                                        <option value="2">Homologação</option>
                                        <option value="1">Produção</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Aba Certificado Próprio -->
                        <div class="tab-pane fade" id="tab-cert">
                            <div class="card border-warning border-dashed border-2 bg-white p-4 shadow-sm rounded-4">
                                <div class="d-flex align-items-center mb-4">
                                    <i class="fas fa-certificate theme-color fa-2x me-3"></i>
                                    <div>
                                        <h6 class="fw-bold mb-1">Certificado Exclusivo</h6>
                                        <p class="extra-small text-muted mb-0">Opcional. Se vazio, usará o Certificado Global da Matriz.</p>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label extra-small fw-bold text-muted">Novo Arquivo (.pfx)</label>
                                        <input type="file" name="certificado" class="form-control border-0 bg-light shadow-sm">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label extra-small fw-bold text-muted">Senha do Certificado</label>
                                        <input type="password" name="certificado_senha_filial" id="f_cert_senha" class="form-control border-0 bg-light shadow-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="principal" id="f_principal" value="0">
                <input type="hidden" name="is_matriz" id="f_is_matriz" value="0">
                <input type="hidden" name="crt" id="f_crt" value="1">

                <div class="modal-footer bg-white border-0 py-3">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-theme px-5 fw-bold"><i class="fas fa-save me-2"></i>Salvar Unidade</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalFilial(data = null) {
    const modal = new bootstrap.Modal(document.getElementById('modalFilial'));
    document.getElementById('modalTitle').innerHTML = data ? '<i class="fas fa-edit me-2"></i>Editar Unidade' : '<i class="fas fa-plus me-2"></i>Nova Unidade';
    
    // Reset inputs
    document.getElementById('f_id').value = data ? data.id : '';
    document.getElementById('f_nome').value = data ? data.nome : '';
    document.getElementById('f_cnpj').value = data ? data.cnpj : '';
    document.getElementById('f_razao').value = data ? data.razao_social : '';
    document.getElementById('f_ie').value = data ? data.inscricao_estadual : '';
    document.getElementById('f_cep').value = data ? data.cep : '';
    document.getElementById('f_municipio').value = data ? data.municipio : '';
    document.getElementById('f_logradouro').value = data ? data.logradouro : '';
    document.getElementById('f_numero').value = data ? data.numero : '';
    document.getElementById('f_bairro').value = data ? data.bairro : '';
    document.getElementById('f_complemento').value = data ? data.complemento : '';
    document.getElementById('f_uf').value = data ? data.uf : '';
    document.getElementById('f_fone').value = data ? data.telefone : '';
    document.getElementById('f_email').value = data ? data.email : '';
    
    document.getElementById('f_csc_id').value = data ? data.csc_id : '';
    document.getElementById('f_csc_token').value = data ? data.csc_token : '';
    document.getElementById('f_serie').value = data ? data.serie_nfce : '1';
    document.getElementById('f_ultimo').value = data ? data.ultimo_numero_nfce : '0';
    document.getElementById('f_ambiente').value = data ? data.ambiente : '2';
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
</script>

