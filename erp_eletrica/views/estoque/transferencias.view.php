<?php
// views/estoque/transferencias.view.php
// View interface para o módulo de Transferências B2B Multi-Loja
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logística B2B - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="public/css/corporate.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .tabs-header { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .tab-btn { background: none; border: none; padding: 10px 20px; cursor: pointer; font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab-btn.active { color: var(--erp-primary); border-bottom-color: var(--erp-primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        
        .b2b-cart-summary { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; display: flex; justify-content: space-between; align-items: center; margin-top: 15px; position: sticky; bottom: 10px; z-index: 10; font-family: 'Inter', sans-serif;}
        .qty-input { width: 80px; text-align: center; border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px; }
        
        .transfer-card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 15px; border: 1px solid #e2e8f0; overflow: hidden;}
        .transfer-header { background: #f1f5f9; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0;}
        .transfer-body { padding: 20px; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="top-bar bg-white shadow-sm px-4 py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="toggle-sidebar btn btn-link text-dark p-0 me-3"><i class="fas fa-bars fs-5"></i></button>
                    <div>
                        <h2 class="m-0 fs-4 fw-bold text-dark">Central de Logística B2B</h2>
                        <small class="text-muted">Operação: <?php echo $isMatriz ? "Matriz (Centro de Distribuição)" : "Filial - Suprimentos"; ?></small>
                    </div>
                </div>
            </header>
            
            <main class="p-4 fade-in">
                <?php if ($msg): ?>
                    <div class="alert alert-success border-0 shadow-sm mb-4"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($msg); ?></div>
                <?php endif; ?>
                <?php if ($erro): ?>
                    <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($erro); ?></div>
                <?php endif; ?>

                <div class="card mb-4 bg-white">
                    <div class="card-header bg-white border-bottom-0 pb-0">
                        <div class="tabs-header">
                            <?php if ($isMatriz): ?>
                                <button class="tab-btn <?php echo $aba == 'recebidas' ? 'active' : ''; ?>" onclick="location.href='?aba=recebidas'">
                                    <i class="fas fa-inbox me-2"></i> Solicitações Pendentes
                                </button>
                                <button class="tab-btn <?php echo $aba == 'nova_transferencia' ? 'active' : ''; ?>" onclick="location.href='?aba=nova_transferencia'">
                                    <i class="fas fa-truck-loading me-2"></i> Novo Despacho
                                </button>
                                <button class="tab-btn <?php echo $aba == 'historico_envios' ? 'active' : ''; ?>" onclick="location.href='?aba=historico_envios'">
                                    <i class="fas fa-history me-2"></i> Histórico de Envios
                                </button>
                            <?php else: ?>
                                <button class="tab-btn <?php echo $aba == 'nova_solicitacao' ? 'active' : ''; ?>" onclick="location.href='?aba=nova_solicitacao'">
                                    <i class="fas fa-plus-circle me-2"></i> Pedir ao CD (Matriz)
                                </button>
                                <button class="tab-btn <?php echo $aba == 'em_transito' ? 'active' : ''; ?>" onclick="location.href='?aba=em_transito'">
                                    <i class="fas fa-truck me-2"></i> Em Trânsito
                                </button>
                                <button class="tab-btn <?php echo $aba == 'historico_recebimentos' ? 'active' : ''; ?>" onclick="location.href='?aba=historico_recebimentos'">
                                    <i class="fas fa-history me-2"></i> Histórico
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-body pt-0">
                        <!-- ================= ABAS DA MATRIZ ================= -->
                        <?php if ($isMatriz): ?>
                            
                            <!-- ABA: Pendentes -->
                            <?php if ($aba == 'recebidas'): ?>
                                <?php if (empty($recebidas)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-check-circle fs-1 mb-3 opacity-25"></i>
                                        <h5>Nenhuma solicitação pendente no momento</h5>
                                        <p>As filiais estão abastecidas!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recebidas as $req): ?>
                                        <div class="transfer-card">
                                            <div class="transfer-header">
                                                <div>
                                                    <span class="badge bg-warning text-dark me-2">Requer Aprovação</span>
                                                    <strong><?php echo $req['codigo_transferencia']; ?></strong>
                                                    <span class="text-muted ms-2">Solicitado por: <strong><?php echo $req['nome_filial']; ?></strong></span>
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="far fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($req['data_solicitacao'])); ?>
                                                </div>
                                            </div>
                                            <div class="transfer-body">
                                                <?php if($req['observacoes']): ?>
                                                    <p class="text-muted small mb-3"><em>"<?php echo htmlspecialchars($req['observacoes']); ?>"</em></p>
                                                <?php endif; ?>
                                                
                                                <form action="transferencias.php?action=aprovar_solicitacao" method="POST">
                                                    <input type="hidden" name="transferencia_id" value="<?php echo $req['id']; ?>">
                                                    <table class="table table-sm table-bordered">
                                                        <thead class="bg-light">
                                                            <tr>
                                                                <th>Produto</th>
                                                                <th>Qtd Solicitada</th>
                                                                <th>Disp. Matriz</th>
                                                                <th style="width:150px">Aprovar Envio</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            $itensReq = $pdo->prepare("SELECT ti.*, p.nome, p.codigo, COALESCE((SELECT quantidade FROM estoque_filiais WHERE produto_id = p.id AND filial_id = 1), p.quantidade) as disp_matriz FROM transferencias_itens ti JOIN produtos p ON ti.produto_id = p.id WHERE ti.transferencia_id = ?");
                                                            $itensReq->execute([$req['id']]);
                                                            foreach($itensReq->fetchAll() as $itemReq):
                                                            ?>
                                                            <tr>
                                                                <td><strong><?php echo $itemReq['nome']; ?></strong> <br><small class="text-muted"><?php echo $itemReq['codigo']; ?></small></td>
                                                                <td class="text-center align-middle format-number"><?php echo number_format($itemReq['quantidade_solicitada'], 2, ',', '.'); ?></td>
                                                                <td class="text-center align-middle <?php echo $itemReq['disp_matriz'] < $itemReq['quantidade_solicitada'] ? 'text-danger fw-bold' : 'text-success'; ?>">
                                                                    <?php echo number_format($itemReq['disp_matriz'], 2, ',', '.'); ?>
                                                                </td>
                                                                <td>
                                                                    <input type="number" step="0.01" max="<?php echo $itemReq['disp_matriz']; ?>" name="qtd_enviada[<?php echo $itemReq['produto_id']; ?>]" value="<?php echo $itemReq['quantidade_solicitada'] > $itemReq['disp_matriz'] ? $itemReq['disp_matriz'] : $itemReq['quantidade_solicitada']; ?>" class="form-control form-control-sm text-center">
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                    <div class="text-end mt-3">
                                                        <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i> Aprovar e Despachar Pacote</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- ABA: Nova Transferencia Forçada -->
                            <?php if ($aba == 'nova_transferencia'): ?>
                                <form action="transferencias.php?action=nova_transferencia" method="POST" id="formTransf">
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Filial Destino</label>
                                            <select name="destino_filial_id" class="form-select form-select-lg" required>
                                                <option value="">Selecione o destino...</option>
                                                <?php foreach ($filiais as $f): ?>
                                                    <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['nome']); ?> (Filial #<?php echo $f['id']; ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label fw-bold">Observações do Envio</label>
                                            <input type="text" name="observacoes" class="form-control form-control-lg" placeholder="Ex: Reforço de estoque para promoção...">
                                        </div>
                                    </div>
                                    
                                    <table class="table table-hover align-middle border">
                                        <thead class="bg-light">
                                            <tr>
                                                <th style="width:40px">Sel.</th>
                                                <th>Produto</th>
                                                <th>Estoque Matriz (Disponível)</th>
                                                <th>Qtd para Enviar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($produtosMatriz as $pm): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input chkItem" name="itens[<?php echo $pm['id']; ?>][selecionado]" value="1">
                                                    <input type="hidden" name="itens[<?php echo $pm['id']; ?>][produto_id]" value="<?php echo $pm['id']; ?>">
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($pm['nome']); ?></div>
                                                    <div class="small text-muted">SKU: <?php echo htmlspecialchars($pm['codigo']); ?></div>
                                                </td>
                                                <td><span class="badge bg-secondary"><?php echo number_format($pm['qtd_matriz'], 2, ',', '.'); ?> UN</span></td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="<?php echo $pm['qtd_matriz']; ?>" name="itens[<?php echo $pm['id']; ?>][quantidade]" class="qty-input form-control-sm" placeholder="0" disabled>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    
                                    <div class="b2b-cart-summary">
                                        <div>
                                            <h5 class="m-0 text-dark fw-bold"><i class="fas fa-box-open text-primary me-2"></i> Itens no Caminhão: <span id="cartCount" class="badge bg-primary fs-6 ms-2">0</span></h5>
                                        </div>
                                        <button type="submit" class="btn btn-erp-primary btn-lg fw-bold shadow-sm" id="btnSubmitTransf" disabled>
                                            <i class="fas fa-paper-plane me-2"></i> Faturar e Despachar
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                            
                            <!-- ABA: Histórico Envios -->
                            <?php if ($aba == 'historico_envios'): ?>
                                <table class="table table-hover align-middle">
                                    <thead class="bg-light">
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
                                            <td><strong class="text-primary"><?php echo $he['codigo_transferencia']; ?></strong></td>
                                            <td><?php echo $he['nome_filial']; ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($he['data_envio'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $he['status'] == 'concluida' ? 'success' : 'warning text-dark'; ?>">
                                                    <?php echo strtoupper(str_replace('_', ' ', $he['status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            
                        <?php else: ?>
                        <!-- ================= ABAS DA FILIAL ================= -->
                        
                            <!-- ABA: Nova Solicitacao -->
                            <?php if ($aba == 'nova_solicitacao'): ?>
                                <form action="transferencias.php?action=nova_solicitacao" method="POST" id="formReq">
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Justificativa / Observação do Pedido (Opcional)</label>
                                        <input type="text" name="observacoes" class="form-control form-control-lg" placeholder="Ex: Produto em falta devido as chuvas, cliente aguardando...">
                                    </div>
                                    
                                    <div class="alert alert-primary bg-primary bg-opacity-10 border-0 text-primary">
                                        <i class="fas fa-info-circle me-2"></i> <strong>Catálogo da Matriz:</strong> Abaixo estão os produtos disponíveis no Centro de Distribuição (Matriz). Informe a quantidade desejada.
                                    </div>
                                    
                                    <table class="table table-hover align-middle border">
                                        <thead class="bg-light">
                                            <tr>
                                                <th style="width:40px">Sel.</th>
                                                <th>Produto</th>
                                                <th>Qtd Desejada</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($produtosMatriz as $pm): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input chkItem" name="itens[<?php echo $pm['id']; ?>][selecionado]" value="1">
                                                    <input type="hidden" name="itens[<?php echo $pm['id']; ?>][produto_id]" value="<?php echo $pm['id']; ?>">
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($pm['nome']); ?></div>
                                                    <div class="small text-muted">SKU: <?php echo htmlspecialchars($pm['codigo']); ?></div>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" name="itens[<?php echo $pm['id']; ?>][quantidade]" class="qty-input form-control-sm" placeholder="0" disabled>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    
                                    <div class="b2b-cart-summary">
                                        <div>
                                            <h5 class="m-0 text-dark fw-bold"><i class="fas fa-shopping-cart text-primary me-2"></i> Itens no Carrinho B2B: <span id="cartCount" class="badge bg-primary fs-6 ms-2">0</span></h5>
                                        </div>
                                        <button type="submit" class="btn btn-erp-primary btn-lg fw-bold shadow-sm" id="btnSubmitReq" disabled>
                                            <i class="fas fa-paper-plane me-2"></i> Enviar Pedido à Matriz
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                            
                            <!-- ABA: Em Trânsito (Esperando Recebimento) -->
                            <?php if ($aba == 'em_transito'): ?>
                                <?php if (empty($em_transito)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-truck fs-1 mb-3 opacity-25"></i>
                                        <h5>Nenhum caminhão na estrada para você.</h5>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($em_transito as $et): ?>
                                        <div class="transfer-card border-warning">
                                            <div class="transfer-header bg-warning bg-opacity-10 border-warning">
                                                <div>
                                                    <span class="badge bg-warning text-dark me-2">CHEGANDO</span>
                                                    <strong><?php echo $et['codigo_transferencia']; ?></strong>
                                                    <span class="text-muted ms-2">Despachado em: <strong><?php echo date('d/m/Y H:i', strtotime($et['data_envio'])); ?></strong></span>
                                                </div>
                                            </div>
                                            <div class="transfer-body">
                                                <p>Estes itens foram despachados pela matriz e estão em rota. Assim que a caixa chegar na loja, confira as quantidades e confirme o recebimento para dar entrada no seu estoque físico.</p>
                                                <ul class="list-group mb-3">
                                                    <?php 
                                                    $itensEnv = $pdo->prepare("SELECT ti.*, p.nome FROM transferencias_itens ti JOIN produtos p ON ti.produto_id = p.id WHERE ti.transferencia_id = ?");
                                                    $itensEnv->execute([$et['id']]);
                                                    foreach($itensEnv->fetchAll() as $ie):
                                                        if($ie['quantidade_enviada'] > 0):
                                                    ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <?php echo $ie['nome']; ?>
                                                            <span class="badge bg-primary rounded-pill"><?php echo number_format($ie['quantidade_enviada'], 2, ',', '.'); ?> UN</span>
                                                        </li>
                                                    <?php endif; endforeach; ?>
                                                </ul>
                                                <form action="transferencias.php?action=confirmar_recebimento" method="POST" onsubmit="return confirm('ATENÇÃO: Isso dará entrada imediata desses itens no SEU estoque e finalizará a transferência. Confirmar que a mercadoria chegou na loja físicamente?');">
                                                    <input type="hidden" name="transferencia_id" value="<?php echo $et['id']; ?>">
                                                    <button type="submit" class="btn btn-success fw-bold w-100 py-3"><i class="fas fa-box-open me-2"></i> Confirmar Chegada e Dar Entrada no Estoque</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- ABA: Histórico Recebimentos -->
                            <?php if ($aba == 'historico_recebimentos'): ?>
                                <table class="table table-hover align-middle">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Cód. Romaneio</th>
                                            <th>Status Atual</th>
                                            <th>Data Pedido</th>
                                            <th>Data Conclusão</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($historico)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">Histórico vazio.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($historico as $h): ?>
                                        <tr>
                                            <td><strong class="text-primary"><?php echo $h['codigo_transferencia']; ?></strong></td>
                                            <td>
                                                <?php 
                                                $badgeStatus = 'secondary';
                                                if($h['status'] == 'concluida') $badgeStatus = 'success';
                                                if($h['status'] == 'em_transito') $badgeStatus = 'warning text-dark';
                                                if($h['status'] == 'pendente') $badgeStatus = 'primary bg-opacity-75';
                                                ?>
                                                <span class="badge bg-<?php echo $badgeStatus; ?>">
                                                    <?php echo strtoupper(str_replace('_', ' ', $h['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($h['data_solicitacao'])); ?></td>
                                            <td><?php echo $h['data_recebimento'] ? date('d/m/Y H:i', strtotime($h['data_recebimento'])) : '---'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Lógica de Carrinho B2B Avançado
        document.addEventListener('DOMContentLoaded', () => {
            const forms = document.querySelectorAll('#formReq, #formTransf');
            if(forms.length == 0) return;
            
            const form = forms[0];
            const checkboxes = form.querySelectorAll('.chkItem');
            const qtyInputs = form.querySelectorAll('.qty-input');
            const countLabel = document.getElementById('cartCount');
            const submitBtn = document.getElementById('btnSubmitReq') || document.getElementById('btnSubmitTransf');
            
            const updateCart = () => {
                let cnt = 0;
                checkboxes.forEach((chk, idx) => {
                    if (chk.checked) {
                        cnt++;
                        qtyInputs[idx].removeAttribute('disabled');
                        if(!qtyInputs[idx].value) qtyInputs[idx].value = 1; // Default
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
                if(input.value > 0) {
                    const tr = input.closest('tr');
                    tr.querySelector('.chkItem').checked = true;
                    updateCart();
                }
            }));
        });
    </script>
</body>
</html>
