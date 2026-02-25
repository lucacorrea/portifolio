<!-- Page Header / Actions -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-network-wired me-2"></i>Unidades Operacionais</h6>
        <button class="btn btn-primary fw-bold" onclick="alert('Funcionalidade de Nova Filial em desenvolvimento')">
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
                        <label class="form-label small fw-bold">Nome da Unidade</label>
                        <input type="text" name="nome" id="branch_nome" class="form-control shadow-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Ambiente SEFAZ</label>
                        <select name="ambiente" id="branch_ambiente" class="form-select shadow-sm">
                            <option value="2">Homologação (Testes)</option>
                            <option value="1">Produção (Real)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">CNPJ</label>
                        <input type="text" name="cnpj" id="branch_cnpj" class="form-control shadow-sm" placeholder="00.000.000/0000-00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Inscrição Estadual</label>
                        <input type="text" name="inscricao_estadual" id="branch_ie" class="form-control shadow-sm">
                    </div>
                    
                    <div class="col-12 mt-4 mb-2"><h6 class="fw-bold text-primary small border-bottom pb-2">Endereço Fiscal</h6></div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Logradouro</label>
                        <input type="text" name="logradouro" id="branch_logradouro" class="form-control shadow-sm" placeholder="Av / Rua / Travessa">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Número</label>
                        <input type="text" name="numero" id="branch_numero" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold">Bairro</label>
                        <input type="text" name="bairro" id="branch_bairro" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold">Município</label>
                        <input type="text" name="municipio" id="branch_municipio" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">UF</label>
                        <input type="text" name="uf" id="branch_uf" class="form-control shadow-sm" maxlength="2">
                    </div>

                    <div class="col-12 mt-4 mb-2"><h6 class="fw-bold text-primary small border-bottom pb-2">Integração NFC-e (SEFAZ)</h6></div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">CSC ID</label>
                        <input type="text" name="csc_id" id="branch_csc_id" class="form-control shadow-sm" placeholder="000001">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">CSC Token</label>
                        <input type="text" name="csc_token" id="branch_csc_token" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Certificado Digital (A1 .pfx)</label>
                        <input type="file" name="certificado" class="form-control shadow-sm">
                        <div id="cert_info" class="extra-small text-muted mt-1"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Senha do Certificado</label>
                        <input type="password" name="certificado_senha" id="branch_cert_senha" class="form-control shadow-sm">
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
function editBranch(branch) {
    const modal = new bootstrap.Modal(document.getElementById('branchModal'));
    document.getElementById('branch_id').value = branch.id;
    document.getElementById('branch_nome').value = branch.nome;
    document.getElementById('branch_ambiente').value = branch.ambiente || 2;
    document.getElementById('branch_cnpj').value = branch.cnpj || '';
    document.getElementById('branch_ie').value = branch.inscricao_estadual || '';
    document.getElementById('branch_logradouro').value = branch.logradouro || '';
    document.getElementById('branch_numero').value = branch.numero || '';
    document.getElementById('branch_bairro').value = branch.bairro || '';
    document.getElementById('branch_municipio').value = branch.municipio || '';
    document.getElementById('branch_uf').value = branch.uf || '';
    document.getElementById('branch_csc_id').value = branch.csc_id || '';
    document.getElementById('branch_csc_token').value = branch.csc_token || '';
    document.getElementById('branch_cert_senha').value = branch.certificado_senha || '';
    
    document.getElementById('cert_info').innerText = branch.certificado_pfx ? 'Certificado atual: ' + branch.certificado_pfx : 'Nenhum certificado carregado';
    
    modal.show();
}
</script>
