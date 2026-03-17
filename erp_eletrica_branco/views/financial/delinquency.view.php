<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-danger"><i class="fas fa-exclamation-circle me-2"></i>Controle de Inadimplência (Vendas > 30 dias)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Venda</th>
                        <th>Cliente</th>
                        <th>Data da Venda</th>
                        <th class="text-center">Dias em Atraso</th>
                        <th class="text-end">Valor Pendente</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($report as $v): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-muted">#<?= $v['id'] ?></td>
                        <td><?= $v['cliente_nome'] ?></td>
                        <td><?= date('d/m/Y', strtotime($v['data_venda'])) ?></td>
                        <td class="text-center">
                            <span class="badge bg-danger rounded-pill px-3"><?= $v['dias_atraso'] ?> dias</span>
                        </td>
                        <td class="text-end fw-bold text-danger"><?= formatarMoeda($v['valor_total']) ?></td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm">
                                <a href="clientes.php?action=view&id=<?= $v['id'] ?>" class="btn btn-light border" title="Ver Cliente">
                                    <i class="fas fa-user-tag text-primary"></i>
                                </a>
                                <button class="btn btn-light border" title="Cobranca WhatsApp">
                                    <i class="fab fa-whatsapp text-success"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($report)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fas fa-check-double d-block mb-3 fs-3 opacity-25"></i>
                            Parabéns! Nenhuma inadimplência crítica detectada.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
