<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="h4 mb-0 fw-bold text-primary">
                <i class="fas fa-certificate me-2"></i>Configuração SEFAZ Global
            </h2>
            <p class="text-muted small">Certificado A1 único para todas as filiais</p>
        </div>
    </div>

    <!-- Verificação de Extensões -->
    <div class="row mb-4">
        <?php foreach ($extensions as $ext => $loaded): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm <?= $loaded ? 'bg-success-light' : 'bg-danger-light' ?>">
                    <div class="card-body py-2 d-flex align-items-center justify-content-between">
                        <span class="fw-bold small text-uppercase">Extensão: <?= $ext ?></span>
                        <i class="fas <?= $loaded ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>"></i>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-info border-0 shadow-sm py-2 mb-4"><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">Gestão do Certificado A1 (.pfx)</h5>
                </div>
                <div class="card-body">
                    <form action="importar_automatico.php?action=config_save" method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="form-label fw-bold small">Arquivo do Certificado (.pfx)</label>
                            <input type="file" name="certificado_pfx" class="form-control" accept=".pfx">
                            <?php if ($config && $config['certificado_path']): ?>
                                <p class="text-success small mt-2">
                                    <i class="fas fa-check me-1"></i> Certificado atual: <?= $config['certificado_path'] ?>
                                </p>
                            <?php else: ?>
                                <p class="text-muted small mt-2">Nenhum certificado enviado ainda.</p>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small">Senha do Certificado</label>
                            <div class="input-group">
                                <input type="password" name="certificado_senha" class="form-control" placeholder="Digite a senha" required>
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility(this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="text-muted extra-small">A senha será armazenada de forma segura.</p>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small">Ambiente SEFAZ</label>
                            <select name="ambiente" class="form-select">
                                <option value="homologacao" <?= ($config && $config['ambiente'] == 'homologacao') ? 'selected' : '' ?>>Homologação (Testes)</option>
                                <option value="producao" <?= ($config && $config['ambiente'] == 'producao') ? 'selected' : '' ?>>Produção (Válido Juridicamente)</option>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary fw-bold py-2">
                                <i class="fas fa-save me-2"></i>SALVAR CONFIGURAÇÃO GLOBAL
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">Instruções Importantes</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item border-0 px-0">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            Esta configuração é **Global**. O arquivo carregado aqui será usado para consultar notas de **todas as filiais**.
                        </li>
                        <li class="list-group-item border-0 px-0">
                            <i class="fas fa-lock text-warning me-2"></i>
                            Certifique-se de que a senha está correta, caso contrário a consulta falhará silenciosamente.
                        </li>
                        <li class="list-group-item border-0 px-0">
                            <i class="fas fa-server text-info me-2"></i>
                            A SEFAZ permite consultas a cada 1 hora no máximo. Evite sincronizar repetidamente.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-success-light { background: rgba(16, 185, 129, 0.15) !important; color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
    .bg-danger-light { background: rgba(239, 68, 68, 0.15) !important; color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
    .bg-success-light .text-success, .bg-danger-light .text-danger { color: inherit !important; }
    .extra-small { font-size: 0.75rem; }
</style>
