<div class="row g-4 mb-4">
    <!-- Client Header & Stats -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                    <i class="fas fa-user-tie fs-1"></i>
                </div>
                <h5 class="fw-bold mb-1"><?= $client['nome'] ?></h5>
                <p class="text-muted small mb-3"><?= $client['email'] ?></p>
                <div class="badge bg-primary rounded-pill px-3 py-2 mb-4">Segmento: <?= $stats['segmento'] ?></div>
                
                <div class="row g-2 text-start">
                    <div class="col-12 py-2 border-top">
                        <span class="text-muted small d-block">LTV (Total Investido)</span>
                        <span class="fw-bold text-success fs-5">R$ <?= number_format($stats['ltv'], 2, ',', '.') ?></span>
                    </div>
                    <div class="col-6 py-2 border-top">
                        <span class="text-muted small d-block">Pedidos</span>
                        <span class="fw-bold"><?= $stats['total_pedidos'] ?></span>
                    </div>
                    <div class="col-6 py-2 border-top">
                        <span class="text-muted small d-block">Ticket Médio</span>
                        <span class="fw-bold">R$ <?= number_format($stats['ticket_medio'], 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white border-0 p-3">
                <a href="clientes.php" class="btn btn-light w-100 fw-bold border"><i class="fas fa-arrow-left me-2"></i>Voltar à Lista</a>
            </div>
        </div>
    </div>

    <!-- Purchase History Chart (Place-holder for now, could be ApexCharts) -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-shopping-bag me-2 text-primary"></i>Histórico de Compras</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Data</th>
                                <th>Venda #</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Valor Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                            <tr>
                                <td class="ps-4 small"><?= date('d/m/Y H:i', strtotime($h['data_venda'])) ?></td>
                                <td class="fw-bold text-primary">#<?= $h['id'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $h['status'] == 'concluido' ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $h['status'] == 'concluido' ? 'success' : 'danger' ?> rounded-pill px-3 small">
                                        <?= strtoupper($h['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4 fw-bold">R$ <?= number_format($h['valor_total'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted small">Nenhuma compra registrada.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm bg-light">
                    <div class="card-body p-3">
                        <h6 class="small fw-bold text-uppercase text-muted mb-2">Ações de Relacionamento</h6>
                        <button class="btn btn-sm btn-outline-primary w-100 mb-2"><i class="fab fa-whatsapp me-2"></i>Enviar Cupom (Fidelidade)</button>
                        <button class="btn btn-sm btn-outline-secondary w-100"><i class="far fa-envelope me-2"></i>Enviar Oferta Personalizada</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                 <div class="card border-0 shadow-sm bg-light">
                    <div class="card-body p-3 text-center">
                        <h6 class="small fw-bold text-uppercase text-muted mb-2">Saúde do Cliente</h6>
                        <div class="display-6 fw-bold text-success font-monospace">10/10</div>
                        <div class="extra-small text-muted">Baseado na recorrência dos últimos 90 dias</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
