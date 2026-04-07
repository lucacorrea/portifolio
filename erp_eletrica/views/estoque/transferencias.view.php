<?php
// views/estoque/transferencias.view.php
// Conteúdo injetado pelo layout main.view.php via BaseController::render()
?>

<style>
    .tabs-header { display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; flex-wrap: wrap; }
    .tab-btn { background: none; border: none; padding: 10px 20px; cursor: pointer; font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: color .2s; font-size: .875rem; }
    .tab-btn.active { color: var(--primary-color, #0d6efd); border-bottom-color: var(--primary-color, #0d6efd); }
    .tab-btn:hover:not(.active) { color: #334155; }

    .b2b-cart-summary { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; margin-top: 15px; position: sticky; bottom: 10px; z-index: 10; box-shadow: 0 4px 15px rgba(0,0,0,.07); }
    .qty-input { width: 90px; text-align: center; border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px 8px; font-size: .875rem; }

    .transfer-card { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); margin-bottom: 15px; border: 1px solid #e2e8f0; overflow: hidden; }
    .transfer-header { background: #f1f5f9; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
    .transfer-body { padding: 20px; }
</style>

<?php if (isset($_GET['erro']) && $_GET['erro']): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['erro']) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <?php if ($isMatriz): ?>
                        <i class="fas fa-warehouse me-2 text-primary"></i>Centro de Distribuição (Matriz)
                    <?php else: ?>
                        <i class="fas fa-store me-2 text-primary"></i>Suprimentos da Filial
                    <?php endif; ?>
                </h6>
                <small class="text-muted extra-small">
                    <?= $isMatriz ? 'Gerencie solicitações das filiais e despache transferências.' : 'Solicite materiais à Matriz e acompanhe seus pedidos.' ?>
                </small>
            </div>
        </div>

        <div class="tabs-header mb-0">
            <?php if ($isMatriz): ?>
                <button class="tab-btn <?= $aba == 'recebidas' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=recebidas'">
                    <i class="fas fa-inbox me-2"></i>Solicitações Pendentes
                    <?php if (!empty($recebidas)): ?>
                        <span class="badge bg-danger ms-1"><?= count($recebidas) ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn <?= $aba == 'nova_transferencia' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=nova_transferencia'">
                    <i class="fas fa-truck-loading me-2"></i>Novo Despacho
                </button>
                <button class="tab-btn <?= $aba == 'historico_envios' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=historico_envios'">
                    <i class="fas fa-history me-2"></i>Histórico de Envios
                </button>
            <?php else: ?>
                <button class="tab-btn <?= $aba == 'nova_solicitacao' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=nova_solicitacao'">
                    <i class="fas fa-plus-circle me-2"></i>Pedir ao CD (Matriz)
                </button>
                <button class="tab-btn <?= $aba == 'em_transito' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=em_transito'">
                    <i class="fas fa-truck me-2"></i>Em Trânsito
                    <?php if (!empty($em_transito)): ?>
                        <span class="badge bg-warning text-dark ms-1"><?= count($em_transito) ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn <?= $aba == 'historico_recebimentos' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=historico_recebimentos'">
                    <i class="fas fa-history me-2"></i>Histórico
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body">

        <?php if ($isMatriz): ?>
        <!-- ================= ABAS DA MATRIZ ================= -->

            <?php if ($aba == 'recebidas'): ?>
                <?php if (empty($recebidas)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-check-circle fa-3x mb-3 opacity-25"></i>
                        <h6 class="fw-bold">Nenhuma solicitação pendente</h6>
                        <p class="small">As filiais estão abastecidas!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recebidas as $req): ?>
                        <div class="transfer-card">
                            <div class="transfer-header">
                                <div>
                                    <span class="badge bg-warning text-dark me-2">Requer Aprovação</span>
                                    <strong><?= htmlspecialchars($req['codigo_transferencia']) ?></strong>
                                    <span class="text-muted ms-2 small">Solicitado por: <strong><?= htmlspecialchars($req['nome_filial']) ?></strong></span>
                                </div>
                                <div class="text-muted small">
                                    <i class="far fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($req['data_solicitacao'])) ?>
                                </div>
                            </div>
                            <div class="transfer-body">
                                <?php if ($req['observacoes']): ?>
                                    <p class="text-muted small mb-3"><i class="fas fa-comment me-2"></i><em>"<?= htmlspecialchars($req['observacoes']) ?>"</em></p>
                                <?php endif; ?>

                                <form action="transferencias.php?action=aprovar_solicitacao" method="POST">
                                    <input type="hidden" name="transferencia_id" value="<?= $req['id'] ?>">
                                    <table class="table table-sm table-bordered align-middle mb-3">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Produto</th>
                                                <th class="text-center" style="width:130px">Qtd Solicitada</th>
                                                <th class="text-center" style="width:130px">Disp. Matriz</th>
                                                <th class="text-center" style="width:150px">Qtd p/ Enviar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $itensReq = $pdo->prepare("SELECT ti.*, p.nome, p.codigo, COALESCE((SELECT quantidade FROM estoque_filiais WHERE produto_id = p.id AND filial_id = 1), p.quantidade) as disp_matriz FROM erp_transferencias_itens ti JOIN produtos p ON ti.produto_id = p.id WHERE ti.transferencia_id = ?");
                                            $itensReq->execute([$req['id']]);
                                            foreach ($itensReq->fetchAll() as $itemReq):
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold small"><?= htmlspecialchars($itemReq['nome']) ?></div>
                                                    <div class="extra-small text-muted">SKU: <?= htmlspecialchars($itemReq['codigo']) ?></div>
                                                </td>
                                                <td class="text-center"><?= number_format($itemReq['quantidade_solicitada'], 2, ',', '.') ?></td>
                                                <td class="text-center fw-bold <?= $itemReq['disp_matriz'] < $itemReq['quantidade_solicitada'] ? 'text-danger' : 'text-success' ?>">
                                                    <?= number_format($itemReq['disp_matriz'], 2, ',', '.') ?>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="<?= $itemReq['disp_matriz'] ?>"
                                                        name="qtd_enviada[<?= $itemReq['produto_id'] ?>]"
                                                        value="<?= $itemReq['quantidade_solicitada'] > $itemReq['disp_matriz'] ? $itemReq['disp_matriz'] : $itemReq['quantidade_solicitada'] ?>"
                                                        class="form-control form-control-sm text-center">
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary fw-bold px-4">
                                            <i class="fas fa-check me-2"></i>Aprovar e Despachar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($aba == 'nova_transferencia'): ?>
                <form action="transferencias.php?action=nova_transferencia" method="POST" id="formTransf">
                    <div class="row mb-4 g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Filial Destino</label>
                            <select name="destino_filial_id" class="form-select" required>
                                <option value="">Selecione o destino...</option>
                                <?php foreach ($filiais as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nome']) ?> (Filial #<?= $f['id'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Observações do Envio</label>
                            <input type="text" name="observacoes" class="form-control" placeholder="Ex: Reforço de estoque para promoção...">
                        </div>
                    </div>

                    <table class="table table-hover align-middle border">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">Sel.</th>
                                <th>Produto</th>
                                <th>Estoque Matriz</th>
                                <th style="width:130px">Qtd para Enviar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtosMatriz as $pm): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input chkItem" name="itens[<?= $pm['id'] ?>][selecionado]" value="1">
                                    <input type="hidden" name="itens[<?= $pm['id'] ?>][produto_id]" value="<?= $pm['id'] ?>">
                                </td>
                                <td>
                                    <div class="fw-bold small"><?= htmlspecialchars($pm['nome']) ?></div>
                                    <div class="extra-small text-muted">SKU: <?= htmlspecialchars($pm['codigo']) ?></div>
                                </td>
                                <td><span class="badge bg-secondary"><?= number_format($pm['qtd_matriz'], 2, ',', '.') ?> UN</span></td>
                                <td>
                                    <input type="number" step="0.01" min="0" max="<?= $pm['qtd_matriz'] ?>"
                                        name="itens[<?= $pm['id'] ?>][quantidade]"
                                        class="qty-input form-control-sm" placeholder="0" disabled>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="b2b-cart-summary">
                        <h6 class="m-0 fw-bold"><i class="fas fa-box-open text-primary me-2"></i>Itens no Caminhão: <span id="cartCount" class="badge bg-primary ms-1">0</span></h6>
                        <button type="submit" class="btn btn-primary fw-bold px-4" id="btnSubmitTransf" disabled>
                            <i class="fas fa-paper-plane me-2"></i>Faturar e Despachar
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($aba == 'historico_envios'): ?>
                <?php if (empty($historico_envios)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-history fa-3x mb-3 opacity-25"></i>
                        <h6 class="fw-bold">Nenhum envio registrado</h6>
                    </div>
                <?php else: ?>
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Destino</th>
                                <th>Data Envio</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historico_envios as $he): ?>
                            <tr>
                                <td><strong class="text-primary"><?= htmlspecialchars($he['codigo_transferencia']) ?></strong></td>
                                <td><?= htmlspecialchars($he['nome_filial']) ?></td>
                                <td><?= $he['data_envio'] ? date('d/m/Y H:i', strtotime($he['data_envio'])) : '---' ?></td>
                                <td>
                                    <span class="badge bg-<?= $he['status'] == 'concluida' ? 'success' : 'warning text-dark' ?>">
                                        <?= strtoupper(str_replace('_', ' ', $he['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>
        <!-- ================= ABAS DA FILIAL ================= -->

            <?php if ($aba == 'nova_solicitacao'): ?>
                <form action="transferencias.php?action=nova_solicitacao" method="POST" id="formReq">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Justificativa / Observação (Opcional)</label>
                        <input type="text" name="observacoes" class="form-control" placeholder="Ex: Produto em falta devido às chuvas...">
                    </div>

                    <div class="alert alert-primary bg-primary bg-opacity-10 border-0 text-primary small mb-3">
                        <i class="fas fa-info-circle me-2"></i><strong>Catálogo da Matriz:</strong> Selecione os produtos e informe a quantidade desejada.
                    </div>

                    <table class="table table-hover align-middle border">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">Sel.</th>
                                <th>Produto</th>
                                <th style="width:130px">Qtd Desejada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtosMatriz as $pm): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input chkItem" name="itens[<?= $pm['id'] ?>][selecionado]" value="1">
                                    <input type="hidden" name="itens[<?= $pm['id'] ?>][produto_id]" value="<?= $pm['id'] ?>">
                                </td>
                                <td>
                                    <div class="fw-bold small"><?= htmlspecialchars($pm['nome']) ?></div>
                                    <div class="extra-small text-muted">SKU: <?= htmlspecialchars($pm['codigo']) ?></div>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0"
                                        name="itens[<?= $pm['id'] ?>][quantidade]"
                                        class="qty-input form-control-sm" placeholder="0" disabled>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="b2b-cart-summary">
                        <h6 class="m-0 fw-bold"><i class="fas fa-shopping-cart text-primary me-2"></i>Itens no Carrinho: <span id="cartCount" class="badge bg-primary ms-1">0</span></h6>
                        <button type="submit" class="btn btn-primary fw-bold px-4" id="btnSubmitReq" disabled>
                            <i class="fas fa-paper-plane me-2"></i>Enviar Pedido à Matriz
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($aba == 'em_transito'): ?>
                <?php if (empty($em_transito)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-truck fa-3x mb-3 opacity-25"></i>
                        <h6 class="fw-bold">Nenhum pedido em trânsito</h6>
                        <p class="small">Quando a Matriz despachar um pedido, ele aparecerá aqui.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($em_transito as $et): ?>
                        <div class="transfer-card border-warning">
                            <div class="transfer-header bg-warning bg-opacity-10">
                                <div>
                                    <span class="badge bg-warning text-dark me-2">CHEGANDO</span>
                                    <strong><?= htmlspecialchars($et['codigo_transferencia']) ?></strong>
                                    <span class="text-muted ms-2 small">Despachado em: <strong><?= date('d/m/Y H:i', strtotime($et['data_envio'])) ?></strong></span>
                                </div>
                            </div>
                            <div class="transfer-body">
                                <p class="small text-muted mb-3">Estes itens estão em rota. Confira as quantidades físicas ao receber e confirme abaixo para dar entrada no estoque.</p>
                                <ul class="list-group list-group-flush mb-3 border rounded">
                                    <?php
                                    $itensEnv = $pdo->prepare("SELECT ti.*, p.nome FROM erp_transferencias_itens ti JOIN produtos p ON ti.produto_id = p.id WHERE ti.transferencia_id = ?");
                                    $itensEnv->execute([$et['id']]);
                                    foreach ($itensEnv->fetchAll() as $ie):
                                        if ($ie['quantidade_enviada'] > 0):
                                    ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center small">
                                            <?= htmlspecialchars($ie['nome']) ?>
                                            <span class="badge bg-primary rounded-pill"><?= number_format($ie['quantidade_enviada'], 2, ',', '.') ?> UN</span>
                                        </li>
                                    <?php endif; endforeach; ?>
                                </ul>
                                <form action="transferencias.php?action=confirmar_recebimento" method="POST"
                                      onsubmit="return confirm('ATENÇÃO: Isso dará entrada imediata no seu estoque e finalizará a transferência. Confirmar que a mercadoria chegou fisicamente?');">
                                    <input type="hidden" name="transferencia_id" value="<?= $et['id'] ?>">
                                    <button type="submit" class="btn btn-success fw-bold w-100 py-2">
                                        <i class="fas fa-box-open me-2"></i>Confirmar Chegada e Dar Entrada no Estoque
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($aba == 'historico_recebimentos'): ?>
                <?php if (empty($historico)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-history fa-3x mb-3 opacity-25"></i>
                        <h6 class="fw-bold">Histórico vazio</h6>
                    </div>
                <?php else: ?>
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Cód. Romaneio</th>
                                <th>Status</th>
                                <th>Data Pedido</th>
                                <th>Data Conclusão</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historico as $h):
                                $badge = 'secondary';
                                if ($h['status'] == 'concluida')   $badge = 'success';
                                if ($h['status'] == 'em_transito') $badge = 'warning text-dark';
                                if ($h['status'] == 'pendente')    $badge = 'primary';
                            ?>
                            <tr>
                                <td><strong class="text-primary"><?= htmlspecialchars($h['codigo_transferencia']) ?></strong></td>
                                <td><span class="badge bg-<?= $badge ?>"><?= strtoupper(str_replace('_', ' ', $h['status'])) ?></span></td>
                                <td><?= date('d/m/Y H:i', strtotime($h['data_solicitacao'])) ?></td>
                                <td><?= $h['data_recebimento'] ? date('d/m/Y H:i', strtotime($h['data_recebimento'])) : '---' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>

        <?php endif; ?>

    </div><!-- /.card-body -->
</div><!-- /.card -->

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#formReq, #formTransf');
    if (!form) return;

    const checkboxes = form.querySelectorAll('.chkItem');
    const qtyInputs  = form.querySelectorAll('.qty-input');
    const countLabel = document.getElementById('cartCount');
    const submitBtn  = document.getElementById('btnSubmitReq') || document.getElementById('btnSubmitTransf');

    const updateCart = () => {
        let cnt = 0;
        checkboxes.forEach((chk, idx) => {
            if (chk.checked) {
                cnt++;
                qtyInputs[idx].removeAttribute('disabled');
                if (!qtyInputs[idx].value || qtyInputs[idx].value == 0) qtyInputs[idx].value = 1;
            } else {
                qtyInputs[idx].setAttribute('disabled', 'true');
                qtyInputs[idx].value = '';
            }
        });
        countLabel.innerText = cnt;
        submitBtn.disabled = cnt === 0;
    };

    checkboxes.forEach(chk => chk.addEventListener('change', updateCart));
    qtyInputs.forEach(input => input.addEventListener('input', () => {
        if (parseFloat(input.value) > 0) {
            const chk = input.closest('tr').querySelector('.chkItem');
            if (chk && !chk.checked) { chk.checked = true; updateCart(); }
        }
    }));
});
</script>
