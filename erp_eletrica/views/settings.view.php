<div class="row g-4">
    <div class="col-lg-8">
        <form action="configuracoes.php?action=save" method="POST">
            <!-- Identity Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-building me-2 text-primary"></i>Identidade Corporativa</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold">Nome da Empresa / Razão Social</label>
                            <input type="text" name="empresa_nome" class="form-control shadow-sm" value="<?= $settings['empresa_nome'] ?? APP_NAME ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">CNPJ Principal</label>
                            <input type="text" name="empresa_cnpj" class="form-control shadow-sm" value="<?= $settings['empresa_cnpj'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Telefone de Suporte</label>
                            <input type="text" name="empresa_fone" class="form-control shadow-sm" value="<?= $settings['empresa_fone'] ?? '' ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Email Institucional</label>
                            <input type="email" name="empresa_email" class="form-control shadow-sm" value="<?= $settings['empresa_email'] ?? '' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parameters Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-sliders-h me-2 text-secondary"></i>Parâmetros de Operação</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Alerta de Estoque Mínimo (Padrão)</label>
                            <input type="number" name="estoque_min_default" class="form-control shadow-sm" value="<?= $settings['estoque_min_default'] ?? '5' ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Mensagem Padrão para Orçamentos/PV</label>
                            <textarea name="msg_orcamento" class="form-control shadow-sm" rows="4" placeholder="Esta mensagem aparecerá impressa no rodapé dos orçamentos..."><?= $settings['msg_orcamento'] ?? '' ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light border-0 py-3 text-end px-4">
                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="fas fa-save me-2"></i>Salvar Alterações
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Info/Help Column -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4 bg-primary text-white">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>Dica do Sistema</h6>
                <p class="small opacity-75 mb-0">
                    Estas informações são fundamentais para a emissão de documentos fiscais e relatórios técnicos. Mantenha os dados sempre atualizados para garantir a conformidade da sua operação.
                </p>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2"></i>Últimas Alterações</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item py-3 opacity-50 text-center">Nenhum histórico disponível</li>
                </ul>
            </div>
        </div>
    </div>
</div>
