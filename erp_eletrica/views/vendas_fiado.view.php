<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-secondary"><i class="fas fa-hand-holding-usd me-2"></i>Controle de Vendas Fiado</h5>
                <span class="badge bg-primary rounded-pill"><?= count($debitos) ?> Pendências</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Cliente</th>
                                <th class="text-end">Valor Total</th>
                                <th class="text-end">Valor Pago</th>
                                <th class="text-end">Saldo Devedor</th>
                                <th class="text-center">Vencimento</th>
                                <th class="text-center">Atraso</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($debitos)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-check-circle fs-1 d-block mb-3 opacity-25"></i>
                                        Nenhuma venda fiado pendente no momento.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($debitos as $d): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold"><?= htmlspecialchars($d['cliente_nome']) ?></div>
                                        <small class="text-muted">Ref. Venda #<?= $d['venda_id'] ?></small>
                                    </td>
                                    <td class="text-end">R$ <?= number_format($d['valor'], 2, ',', '.') ?></td>
                                    <td class="text-end text-success">R$ <?= number_format($d['valor_pago'], 2, ',', '.') ?></td>
                                    <td class="text-end fw-bold text-danger">R$ <?= number_format($d['saldo'], 2, ',', '.') ?></td>
                                    <td class="text-center"><?= date('d/m/Y', strtotime($d['data_vencimento'])) ?></td>
                                    <td class="text-center">
                                        <?php if ($d['dias_atraso'] > 0): ?>
                                            <span class="badge bg-danger rounded-pill"><?= $d['dias_atraso'] ?> dias</span>
                                        <?php else: ?>
                                            <span class="badge bg-success rounded-pill">No prazo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-primary px-3" onclick="abrirPagamento(<?= $d['id'] ?>, '<?= htmlspecialchars($d['cliente_nome']) ?>', <?= $d['saldo'] ?>)">
                                            <i class="fas fa-cash-register me-1"></i> Baixa
                                        </button>
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

<!-- Modal Pagamento -->
<div class="modal fade" id="modalPagamento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Registrar Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Receber de: <strong id="modal_cliente_nome"></strong></p>
                <div class="bg-light p-3 rounded mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Saldo Atual:</span>
                        <span class="fw-bold text-danger" id="modal_saldo_atual"></span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Valor do Pagamento (R$)</label>
                    <input type="number" id="valor_pagamento" class="form-control form-control-lg" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary px-4" onclick="confirmarPagamento()">Confirmar Recebimento</button>
            </div>
        </div>
    </div>
</div>

<script>
let debitoIdAtual = null;

function abrirPagamento(id, nome, saldo) {
    debitoIdAtual = id;
    document.getElementById('modal_cliente_nome').innerText = nome;
    document.getElementById('modal_saldo_atual').innerText = 'R$ ' + saldo.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('valor_pagamento').value = saldo.toFixed(2);
    new bootstrap.Modal(document.getElementById('modalPagamento')).show();
}

async function confirmarPagamento() {
    const valor = document.getElementById('valor_pagamento').value;
    if (!valor || valor <= 0) return alert('Informe um valor válido.');

    const res = await fetch('fiado.php?action=pagar', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: debitoIdAtual, valor: valor})
    });

    const result = await res.json();
    if (result.success) {
        location.reload();
    } else {
        alert('Erro: ' + result.error);
    }
}
</script>
