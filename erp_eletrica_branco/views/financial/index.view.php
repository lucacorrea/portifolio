<div class="row g-4 mb-4">
    <!-- Financial Stat Cards -->
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm border-start border-success border-4">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Contas a Receber</div>
                <h3 class="mb-0 fw-bold text-success"><?= formatarMoeda($stats['areceber']) ?></h3>
                <small class="text-muted">Previsão bruta para o mês</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm border-start border-danger border-4">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Contas a Pagar</div>
                <h3 class="mb-0 fw-bold text-danger"><?= formatarMoeda($stats['apagar']) ?></h3>
                <small class="text-muted">Compromissos pendentes</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm border-start border-primary border-4">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Saldo Projetado</div>
                <h3 class="mb-0 fw-bold text-primary"><?= formatarMoeda($stats['areceber'] - $stats['apagar']) ?></h3>
                <small class="text-muted">Resultado operacional previsto</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Accounts Receivable -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-hand-holding-dollar me-2 text-success"></i>Contas a Receber</h6>
                <button class="btn btn-sm btn-outline-success fw-bold" data-bs-toggle="modal" data-bs-target="#newTransModal" onclick="setTransType('receita')">
                    <i class="fas fa-plus me-1"></i>Receita
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Vencimento</th>
                                <th>Descrição/Cliente</th>
                                <th class="text-end">Valor</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contas_receber as $c): ?>
                            <tr>
                                <td class="ps-4 small"><?= formatarData($c['data_vencimento']) ?></td>
                                <td>
                                    <div class="fw-bold small"><?= $c['descricao'] ?></div>
                                    <div class="text-muted extra-small"><?= $c['cliente_nome'] ?? 'Avulso' ?></div>
                                </td>
                                <td class="text-end fw-bold text-success"><?= formatarMoeda($c['valor']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $c['status'] == 'pago' ? 'success' : 'warning' ?> bg-opacity-10 text-<?= $c['status'] == 'pago' ? 'success' : 'warning' ?> rounded-pill">
                                        <?= ucfirst($c['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($c['status'] == 'pendente'): ?>
                                    <button class="btn btn-sm btn-success" title="Baixar Recebimento" onclick="baixarConta(<?= $c['id'] ?>, 'receber')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Accounts Payable -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-file-invoice-dollar me-2 text-danger"></i>Contas a Pagar</h6>
                <button class="btn btn-sm btn-outline-danger fw-bold" data-bs-toggle="modal" data-bs-target="#newTransModal" onclick="setTransType('despesa')">
                    <i class="fas fa-plus me-1"></i>Despesa
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Vencimento</th>
                                <th>Descrição/Fornecedor</th>
                                <th class="text-end">Valor</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contas_pagar as $c): ?>
                            <tr>
                                <td class="ps-4 small"><?= formatarData($c['data_vencimento']) ?></td>
                                <td>
                                    <div class="fw-bold small"><?= $c['descricao'] ?></div>
                                    <div class="text-muted extra-small">Fornecedor Geral</div>
                                </td>
                                <td class="text-end fw-bold text-danger"><?= formatarMoeda($c['valor']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $c['status'] == 'pago' ? 'success' : 'warning' ?> bg-opacity-10 text-<?= $c['status'] == 'pago' ? 'success' : 'warning' ?> rounded-pill">
                                        <?= ucfirst($c['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($c['status'] == 'pendente'): ?>
                                    <button class="btn btn-sm btn-danger" title="Baixar Pagamento" onclick="baixarConta(<?= $c['id'] ?>, 'pagar')">
                                        <i class="fas fa-hand-holding-dollar"></i>
                                    </button>
                                    <?php endif; ?>
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

<!-- Modal Nova Transação -->
<div class="modal fade" id="newTransModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="financeiro.php?action=save" method="POST">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="transModalTitle">Nova Transação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="tipo" id="trans_tipo">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold">Descrição</label>
                        <input type="text" name="descricao" class="form-control shadow-sm" required placeholder="Ex: Aluguel, Compra de fios, etc">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Valor (R$)</label>
                        <input type="number" step="0.01" name="valor" class="form-control shadow-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Vencimento</label>
                        <input type="date" name="data_vencimento" class="form-control shadow-sm" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Categoria</label>
                        <select name="categoria" class="form-select shadow-sm">
                            <option value="operacional">Operacional</option>
                            <option value="administrativo">Administrativo</option>
                            <option value="infraestrutura">Infraestrutura</option>
                            <option value="venda">Venda de Material</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4">Salvar Lançamento</button>
            </div>
        </form>
    </div>
</div>

<script>
function setTransType(type) {
    document.getElementById('trans_tipo').value = type;
    document.getElementById('transModalTitle').innerText = type === 'receita' ? 'Nova Receita' : 'Nova Despesa';
}

function baixarConta(id, tipo) {
    if(!confirm('Deseja confirmar o pagamento/recebimento desta conta?')) return;
    
    fetch(`financeiro.php?action=pagar&id=${id}&tipo=${tipo}`)
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                location.reload();
            } else {
                alert('Erro: ' + res.error);
            }
        });
}
</script>
