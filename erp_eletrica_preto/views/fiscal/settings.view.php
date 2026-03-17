<!-- SEFAZ Settings Dashboard -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 me-3">
                            <i class="fas fa-server fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Central de Transmissão SEFAZ</h5>
                            <p class="text-muted small mb-0">Gerencie certificados digitais e credenciais para emissão de NF-e e NFC-e.</p>
                        </div>
                    </div>
                    <div>
                        <a href="fiscal.php?action=diagnostic" class="btn btn-warning fw-bold shadow-sm">
                            <i class="fas fa-stethoscope me-2"></i>DIAGNÓSTICO COMPLETO
                        </a>
                    </div>
                </div>

                <!-- Global Certificate Status -->
                <div class="alert bg-light border-0 d-flex align-items-center p-4 mb-4">
                    <div class="me-4 text-primary">
                        <i class="fas fa-shield-halved fa-3x"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="fw-bold mb-0">Configuração de Certificado Global A1</h6>
                            <?php if ($_SESSION['is_matriz'] ?? false): ?>
                                <a href="importar_automatico.php?action=config" class="btn btn-sm btn-primary fw-bold">
                                    <i class="fas fa-cog me-2"></i>GERENCIAR GLOBAL
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php if ($globalConfig && $globalConfig['certificado_path']): ?>
                            <div class="text-success small fw-bold">
                                <i class="fas fa-check-circle me-1"></i> ATIVO: Todas as filiais estão configuradas via Certificado Concentrado.
                            </div>
                            <div class="extra-small text-muted mt-1">
                                <strong>Arquivo:</strong> <?= $globalConfig['certificado_path'] ?> | 
                                <strong>Ambiente:</strong> <?= $globalConfig['ambiente'] == 'producao' ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO' ?>
                            </div>
                        <?php else: ?>
                            <div class="text-warning small fw-bold">
                                <i class="fas fa-exclamation-triangle me-1"></i> NENHUM CERTIFICADO GLOBAL CONFIGURADO.
                            </div>
                            <div class="extra-small text-muted mt-1">O sistema utilizará os certificados individuais das filiais (se houver).</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle border-top">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Unidade / Filial</th>
                                <th>CNPJ Configurado</th>
                                <th>Certificado Digital</th>
                                <th class="text-center">Ambiente</th>
                                <th class="text-end pe-4">Ações de Conectividade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branches as $b): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= $b['nome'] ?></div>
                                    <?php if($b['principal']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success extra-small">MATRIZ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small fw-bold text-muted"><?= $b['cnpj'] ?: '<span class="text-danger">NÃO CONFIGURADO</span>' ?></div>
                                    <div class="extra-small text-muted">IE: <?= $b['inscricao_estadual'] ?: '---' ?></div>
                                </td>
                                <td>
                                    <?php if ($globalConfig && $globalConfig['certificado_path']): ?>
                                        <div class="d-flex align-items-center text-primary small">
                                            <i class="fas fa-link me-2"></i>
                                            <div>
                                                <div class="fw-bold">HERDADO DO GLOBAL</div>
                                                <div class="extra-small text-muted">Certificado Concentrado Ativo</div>
                                            </div>
                                        </div>
                                    <?php elseif($b['certificado_pfx']): ?>
                                        <div class="d-flex align-items-center text-success small">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <div>
                                                <div class="fw-bold">PFX Individual</div>
                                                <div class="extra-small text-muted"><?= $b['certificado_pfx'] ?></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center text-danger small">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <span class="fw-bold">SEM CERTIFICADO</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($globalConfig && $globalConfig['certificado_path']): ?>
                                        <span class="badge <?= $globalConfig['ambiente'] == 'producao' ? 'bg-danger' : 'bg-warning' ?> rounded-pill px-3">
                                            <?= $globalConfig['ambiente'] == 'producao' ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge <?= $b['ambiente'] == 1 ? 'bg-danger' : 'bg-warning' ?> rounded-pill px-3">
                                            <?= $b['ambiente'] == 1 ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary fw-bold" onclick="testConnection(<?= $b['id'] ?>)">
                                            <i class="fas fa-plug-circle-check me-2"></i>Testar Conexão
                                        </button>
                                        <button class="btn btn-sm btn-light border" title="Editar Configuração" onclick="window.location.href='filiais.php'">
                                            <i class="fas fa-edit"></i>
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
    </div>
</div>

<!-- Connection Test Results (Modal or Toast) -->
<div class="modal fade" id="modalStatus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body p-5 text-center" id="statusResult">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <h5 class="fw-bold">Consultando Status SEFAZ...</h5>
                <p class="text-muted small">Aguarde a resposta do Web Service.</p>
            </div>
        </div>
    </div>
</div>

<script>
async function testConnection(branchId) {
    const modal = new bootstrap.Modal(document.getElementById('modalStatus'));
    const resultDiv = document.getElementById('statusResult');
    
    // Reset modal
    resultDiv.innerHTML = `
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <h5 class="fw-bold">Consultando Status SEFAZ...</h5>
        <p class="text-muted small">Aguarde a resposta do Web Service.</p>
    `;
    modal.show();

    try {
        const res = await fetch(`fiscal.php?action=test_connection&id=${branchId}`);
        const data = await res.json();
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                </div>
                <h4 class="fw-bold text-success mb-2">Conexão Estabelecida!</h4>
                <div class="bg-light p-3 rounded text-start small mb-4">
                    <div class="d-flex justify-content-between mb-1"><span>Status:</span> <span class="fw-bold text-success">${data.status} - ${data.motivo}</span></div>
                    <div class="d-flex justify-content-between mb-1"><span>Ambiente:</span> <span class="fw-bold">${data.ambiente}</span></div>
                    <div class="d-flex justify-content-between mb-1"><span>Versão Apl:</span> <span class="fw-bold">${data.verAplic}</span></div>
                    <hr>
                    <div class="text-center text-muted extra-small">${data.timestamp}</div>
                </div>
                <button class="btn btn-primary w-100 fw-bold" data-bs-dismiss="modal">Excelente!</button>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="mb-4">
                    <i class="fas fa-times-circle text-danger" style="font-size: 4rem;"></i>
                </div>
                <h4 class="fw-bold text-danger mb-2">Falha na Conexão</h4>
                <p class="text-muted mb-4">${data.error}</p>
                <div class="alert alert-warning small text-start border-0">
                    <strong>Sugestão:</strong> Verifique se a senha do certificado está correta ou se o CNPJ é o mesmo vinculado ao certificado.
                </div>
                <button class="btn btn-secondary w-100 fw-bold" data-bs-dismiss="modal">Fechar</button>
            `;
        }
    } catch (e) {
        resultDiv.innerHTML = `
            <div class="mb-4"><i class="fas fa-bug text-dark" style="font-size: 4rem;"></i></div>
            <h4 class="fw-bold mb-2">Erro Local</h4>
            <p class="text-muted">${e.message}</p>
            <button class="btn btn-secondary w-100 fw-bold" data-bs-dismiss="modal">Fechar</button>
        `;
    }
}
</script>
