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

    /* Paginação da tabela de produtos */
    .b2b-search-bar { position: relative; }
    .b2b-search-bar .fas { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
    .b2b-search-bar input { padding-left: 36px; }
    .b2b-pagination { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
    .b2b-pagination .page-btn { border: 1px solid #e2e8f0; background: white; color: #475569; border-radius: 6px; padding: 4px 10px; font-size: .8rem; font-weight: 600; cursor: pointer; transition: all .15s; }
    .b2b-pagination .page-btn:hover { background: #f1f5f9; }
    .b2b-pagination .page-btn.active { background: var(--primary-color, #0d6efd); color: white; border-color: var(--primary-color, #0d6efd); }
    .b2b-pagination .page-btn:disabled { opacity: .4; cursor: default; }
    .b2b-no-results { text-align: center; color: #94a3b8; padding: 30px 0; }
    .selectable-row { cursor: pointer; transition: background-color 0.1s; }
    .selectable-row:hover { background-color: rgba(var(--bs-primary-rgb), 0.05) !important; }
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
                    <?php if (($problemas_pendentes ?? 0) > 0): ?>
                        <span class="badge bg-danger ms-1" title="Ocorrências não resolvidas"><?= $problemas_pendentes ?></span>
                    <?php endif; ?>
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
                                                    <input type="number" step="1" min="0" max="<?= $itemReq['disp_matriz'] ?>"
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

                    <!-- Barra de busca Matriz -->
                    <div class="row g-2 align-items-center mb-3">
                        <div class="col-md-6">
                            <div class="b2b-search-bar">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchTransf" class="form-control form-control-sm" placeholder="Buscar produto por nome ou SKU..." autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6 d-flex justify-content-end align-items-center gap-2">
                            <small class="text-muted" id="paginfoTransf"></small>
                            <div class="b2b-pagination" id="paginationTransf"></div>
                        </div>
                    </div>

                    <table class="table table-hover align-middle border" id="tableTransf">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">Sel.</th>
                                <th>Produto</th>
                                <th>Estoque Matriz</th>
                                <th style="width:130px">Qtd para Enviar</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyTransf">
                            <?php foreach ($produtosMatriz as $pm): ?>
                            <tr class="prod-row selectable-row" data-search="<?= strtolower(htmlspecialchars($pm['nome'] . ' ' . $pm['codigo'])) ?>">
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
                                    <input type="number" step="1" min="0" max="<?= $pm['qtd_matriz'] ?>"
                                        name="itens[<?= $pm['id'] ?>][quantidade]"
                                        class="qty-input form-control-sm" placeholder="0" disabled>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="b2b-no-results d-none" id="noResultsTransf"><i class="fas fa-search me-2"></i>Nenhum produto encontrado.</p>

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
                <?php else: ?>
                    <!-- Filtros Matriz -->
                    <div class="card border-0 bg-white mb-4 shadow-sm rounded-3">
                        <div class="card-body p-3">
                            <form method="GET" action="transferencias.php" class="row g-3 align-items-end auto-submit-form">
                                <input type="hidden" name="aba" value="historico_envios">
                                <div class="col-md-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                                        <input type="text" name="filtro_codigo" class="form-control border-start-0 ps-0" placeholder="Código do pedido..." value="<?= htmlspecialchars($_GET['filtro_codigo'] ?? '') ?>" onchange="this.form.submit()">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <select name="filtro_status" class="form-select form-select-sm fw-bold shadow-none" onchange="this.form.submit()">
                                        <option value="">Todos os Status</option>
                                        <option value="em_transito" <?= ($_GET['filtro_status'] ?? '') == 'em_transito' ? 'selected' : '' ?>>Em Trânsito</option>
                                        <option value="concluida" <?= ($_GET['filtro_status'] ?? '') == 'concluida' ? 'selected' : '' ?>>Concluída</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white text-muted small">Período:</span>
                                        <input type="date" name="filtro_inicio" class="form-control shadow-none" value="<?= htmlspecialchars($_GET['filtro_inicio'] ?? '') ?>" onchange="this.form.submit()">
                                        <span class="input-group-text bg-white text-muted">até</span>
                                        <input type="date" name="filtro_fim" class="form-control shadow-none" value="<?= htmlspecialchars($_GET['filtro_fim'] ?? '') ?>" onchange="this.form.submit()">
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex justify-content-end">
                                    <a href="transferencias.php?aba=historico_envios" class="btn btn-outline-danger btn-sm w-100 fw-bold border-0">
                                        <i class="fas fa-eraser me-1"></i>Limpar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

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
                                <td>
                                    <strong class="text-primary"><?= htmlspecialchars($he['codigo_transferencia']) ?></strong>
                                    <?php if ($he['tem_problema']): ?>
                                        <i class="fas fa-exclamation-triangle text-danger ms-1" title="Problema reportado"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($he['nome_filial']) ?></td>
                                <td><?= $he['data_envio'] ? date('d/m/Y H:i', strtotime($he['data_envio'])) : '---' ?></td>
                                <td>
                                    <span class="badge bg-<?= $he['status'] == 'concluida' ? 'success' : 'warning text-dark' ?>">
                                        <?= strtoupper(str_replace('_', ' ', $he['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php if ($he['tem_problema']): ?>
                            <tr class="<?= $he['problema_resolvido'] ? 'table-light' : 'table-danger bg-opacity-10' ?>">
                                <td colspan="4">
                                    <div class="p-2 small">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong class="<?= $he['problema_resolvido'] ? 'text-success' : 'text-danger' ?>">
                                                    <i class="fas <?= $he['problema_resolvido'] ? 'fa-check-double' : 'fa-comment-dots' ?> me-2"></i>
                                                    <?= $he['problema_resolvido'] ? 'Ocorrência Resolvida:' : 'Relatos do Recebimento:' ?>
                                                </strong>
                                            </div>
                                            <?php if (!$he['problema_resolvido']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success py-0" style="font-size: 0.7rem"
                                                        onclick="abrirModalResolucao(<?= $he['id'] ?>, '<?= htmlspecialchars($he['codigo_transferencia']) ?>')">
                                                    <i class="fas fa-check me-1"></i>Marcar como Resolvido
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-success small"><i class="fas fa-check-circle me-1"></i>Resolvido</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-2 ps-4">
                                            <?php
                                            $ocs = $pdo->prepare("SELECT oc.*, p.nome FROM erp_transferencias_ocorrencias oc JOIN produtos p ON oc.produto_id = p.id WHERE oc.transferencia_id = ?");
                                            $ocs->execute([$he['id']]);
                                            $listaOc = $ocs->fetchAll();
                                            if ($listaOc):
                                            ?>
                                                <ul class="list-unstyled mb-2">
                                                    <?php foreach ($listaOc as $o): ?>
                                                        <li class="mb-1">
                                                            <span class="badge bg-danger bg-opacity-75 me-1"><?= number_format($o['quantidade_problema'], 2, ',', '.') ?> UN</span>
                                                            <strong><?= htmlspecialchars($o['nome']) ?></strong>: 
                                                            <span class="text-muted italic"><?= strtoupper($o['motivo']) ?></span> - 
                                                            <small>"<?= htmlspecialchars($o['descricao']) ?>"</small>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>

                                            <?php if ($he['relato_problema']): ?>
                                                <div class="alert alert-light border p-2 mb-0 extra-small">
                                                    <strong>Obs. Geral:</strong> "<?= htmlspecialchars($he['relato_problema']) ?>"
                                                    <span class="text-muted ms-2">(em <?= date('d/m/Y H:i', strtotime($he['data_relato'])) ?>)</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
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

                    <!-- Barra de busca Filial -->
                    <div class="row g-2 align-items-center mb-3">
                        <div class="col-md-6">
                            <div class="b2b-search-bar">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchReq" class="form-control form-control-sm" placeholder="Buscar produto por nome ou SKU..." autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6 d-flex justify-content-end align-items-center gap-2">
                            <small class="text-muted" id="paginfoReq"></small>
                            <div class="b2b-pagination" id="paginationReq"></div>
                        </div>
                    </div>

                    <table class="table table-hover align-middle border" id="tableReq">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">Sel.</th>
                                <th>Produto</th>
                                <th style="width:130px">Qtd Desejada</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyReq">
                            <?php foreach ($produtosMatriz as $pm): ?>
                            <tr class="prod-row selectable-row" data-search="<?= strtolower(htmlspecialchars($pm['nome'] . ' ' . $pm['codigo'])) ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input chkItem" name="itens[<?= $pm['id'] ?>][selecionado]" value="1">
                                    <input type="hidden" name="itens[<?= $pm['id'] ?>][produto_id]" value="<?= $pm['id'] ?>">
                                </td>
                                <td>
                                    <div class="fw-bold small"><?= htmlspecialchars($pm['nome']) ?></div>
                                    <div class="extra-small text-muted">SKU: <?= htmlspecialchars($pm['codigo']) ?></div>
                                </td>
                                <td>
                                    <input type="number" step="1" min="0"
                                        name="itens[<?= $pm['id'] ?>][quantidade]"
                                        class="qty-input form-control-sm" placeholder="0" disabled>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="b2b-no-results d-none" id="noResultsReq"><i class="fas fa-search me-2"></i>Nenhum produto encontrado.</p>

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
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-<?= $et['tem_problema'] ? 'warning' : 'success' ?> fw-bold flex-grow-1 py-2" 
                                            onclick="abrirResumoRecebimento(<?= $et['id'] ?>, '<?= htmlspecialchars($et['codigo_transferencia']) ?>', <?= $et['tem_problema'] ? 'true' : 'false' ?>)">
                                        <i class="fas fa-box-open me-2"></i>
                                        <?= $et['tem_problema'] ? 'Confirmar Recebimento com Ressalvas' : 'Confirmar Chegada e Internalizar Estoque' ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger py-2 px-3" onclick="abrirModalRelato(<?= $et['id'] ?>, '<?= htmlspecialchars($et['codigo_transferencia']) ?>')">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($aba == 'historico_recebimentos'): ?>
                <!-- Filtros Filial -->
                <div class="card border-0 bg-white mb-4 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <form method="GET" action="transferencias.php" class="row g-3 align-items-end auto-submit-form">
                            <input type="hidden" name="aba" value="historico_recebimentos">
                            <div class="col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                                    <input type="text" name="filtro_codigo" class="form-control border-start-0 ps-0" placeholder="Código do pedido..." value="<?= htmlspecialchars($_GET['filtro_codigo'] ?? '') ?>" onchange="this.form.submit()">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="filtro_status" class="form-select form-select-sm fw-bold shadow-none" onchange="this.form.submit()">
                                    <option value="">Todos os Status</option>
                                    <option value="pendente" <?= ($_GET['filtro_status'] ?? '') == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="em_transito" <?= ($_GET['filtro_status'] ?? '') == 'em_transito' ? 'selected' : '' ?>>Em Trânsito</option>
                                    <option value="concluida" <?= ($_GET['filtro_status'] ?? '') == 'concluida' ? 'selected' : '' ?>>Concluída</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white text-muted small">Período:</span>
                                    <input type="date" name="filtro_inicio" class="form-control shadow-none" value="<?= htmlspecialchars($_GET['filtro_inicio'] ?? '') ?>" onchange="this.form.submit()">
                                    <span class="input-group-text bg-white text-muted">até</span>
                                    <input type="date" name="filtro_fim" class="form-control shadow-none" value="<?= htmlspecialchars($_GET['filtro_fim'] ?? '') ?>" onchange="this.form.submit()">
                                </div>
                            </div>
                            <div class="col-md-2 d-flex justify-content-end">
                                <a href="transferencias.php?aba=historico_recebimentos" class="btn btn-outline-danger btn-sm w-100 fw-bold border-0">
                                    <i class="fas fa-eraser me-1"></i>Limpar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

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
/**
 * Inicializa busca + paginação para uma tabela de produtos B2B.
 * @param {string} tbodyId     - ID do <tbody>
 * @param {string} searchId    - ID do <input> de busca
 * @param {string} paginationId - ID do container de paginação
 * @param {string} paginfoId   - ID do <small> com info de página
 * @param {string} noResultsId - ID do parágrafo de sem resultados
 * @param {number} pageSize    - Linhas por página
 */
function initB2BTable(tbodyId, searchId, paginationId, paginfoId, noResultsId, pageSize = 6) {
    const tbody      = document.getElementById(tbodyId);
    const searchEl   = document.getElementById(searchId);
    const pagination = document.getElementById(paginationId);
    const paginfo    = document.getElementById(paginfoId);
    const noResults  = document.getElementById(noResultsId);

    if (!tbody || !searchEl) return;

    let currentPage  = 1;
    let filteredRows = [];

    const allRows = () => Array.from(tbody.querySelectorAll('tr.prod-row'));

    function applyFilter() {
        const q = searchEl.value.trim().toLowerCase();
        filteredRows = allRows().filter(tr => {
            const match = !q || tr.dataset.search.includes(q);
            tr.style.display = 'none'; // oculta tudo antes de paginar
            return match;
        });
        currentPage = 1;
        render();
    }

    function render() {
        const total = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * pageSize;
        const end   = start + pageSize;

        allRows().forEach(tr => tr.style.display = 'none');
        filteredRows.slice(start, end).forEach(tr => tr.style.display = '');

        // Info
        const showing = Math.min(end, total);
        paginfo.textContent = total === 0 ? '' : `${start + 1}–${showing} de ${total}`;

        // Sem resultados
        noResults.classList.toggle('d-none', total > 0);
        tbody.closest('table').classList.toggle('d-none', total === 0);

        // Botões de página
        pagination.innerHTML = '';
        if (totalPages <= 1) return;

        const addBtn = (label, page, disabled = false) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'page-btn' + (page === currentPage ? ' active' : '');
            btn.textContent = label;
            btn.disabled = disabled;
            btn.addEventListener('click', () => { currentPage = page; render(); });
            pagination.appendChild(btn);
        };

        addBtn('‹', currentPage - 1, currentPage === 1);
        for (let p = 1; p <= totalPages; p++) addBtn(p, p);
        addBtn('›', currentPage + 1, currentPage === totalPages);
    }

    searchEl.addEventListener('input', applyFilter);
    applyFilter(); // inicializa
}

// Lógica do carrinho (compartilhada)
function initCart(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    const checkboxes = form.querySelectorAll('.chkItem');
    const countLabel = document.getElementById('cartCount');
    const submitBtn  = document.getElementById('btnSubmitReq') || document.getElementById('btnSubmitTransf');

    const getQtyInput = (chk) => chk.closest('tr').querySelector('.qty-input');

    const updateCart = () => {
        let cnt = 0;
        checkboxes.forEach(chk => {
            const qty = getQtyInput(chk);
            if (chk.checked) {
                cnt++;
                qty.removeAttribute('disabled');
                if (!qty.value || parseFloat(qty.value) === 0) qty.value = 1;
            } else {
                qty.setAttribute('disabled', 'true');
                qty.value = '';
            }
        });
        if (countLabel) countLabel.innerText = cnt;
        if (submitBtn)  submitBtn.disabled = cnt === 0;
    };

    checkboxes.forEach(chk => chk.addEventListener('change', updateCart));
    form.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', () => {
            if (parseFloat(input.value) > 0) {
                const chk = input.closest('tr').querySelector('.chkItem');
                if (chk && !chk.checked) { chk.checked = true; updateCart(); }
            }
        });
    });
}
document.addEventListener('DOMContentLoaded', () => {
    // Tabela da Matriz (Novo Despacho)
    initB2BTable('tbodyTransf', 'searchTransf', 'paginationTransf', 'paginfoTransf', 'noResultsTransf', 6);
    initCart('formTransf');

    // Tabela da Filial (Nova Solicitação)
    initB2BTable('tbodyReq', 'searchReq', 'paginationReq', 'paginfoReq', 'noResultsReq', 6);
    initCart('formReq');

    // Clique na linha para selecionar (Global)
    document.addEventListener('click', (e) => {
        const tr = e.target.closest('.selectable-row');
        if (!tr) return;
        
        // Se clicar diretamente num input, select ou label, não fazemos nada extra
        if (['INPUT', 'SELECT', 'TEXTAREA', 'LABEL', 'BUTTON'].includes(e.target.tagName)) return;

        const chk = tr.querySelector('input[type="checkbox"]');
        if (chk) {
            chk.checked = !chk.checked;
            chk.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
});

let reportModalInstance = null;
function abrirModalRelato(id, codigo) {
    document.getElementById('relato_id').value = id;
    document.getElementById('relato_codigo').innerText = codigo;
    
    const container = document.getElementById('itensRelatoContainer');
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-danger" role="status"></div><p class="small text-muted mt-2">Carregando itens...</p></div>';

    fetch(`transferencias.php?action=get_items&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = '';
                data.items.forEach(item => {
                    const html = `
                        <div class="item-relato-row selectable-row border-bottom py-2 mb-2 px-2 rounded">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input chkItem" name="ocorrencias[${item.produto_id}][selecionado]" value="1" id="chk_${item.produto_id}" onchange="toggleItemRelato(this, ${item.produto_id})">
                                    <label class="form-check-label fw-bold small" for="chk_${item.produto_id}">${item.nome}</label>
                                    <div class="extra-small text-muted">SKU: ${item.codigo} | Enviado: ${parseFloat(item.quantidade_enviada).toFixed(2)} UN</div>
                                </div>
                            </div>
                            <div id="campos_oc_${item.produto_id}" class="d-none ps-4">
                                <div class="row g-2 align-items-center">
                                    <div class="col-4">
                                        <label class="extra-small text-muted">Qtd Defeito</label>
                                        <input type="number" step="1" min="0" max="${item.quantidade_enviada}" name="ocorrencias[${item.produto_id}][quantidade]" class="form-control form-control-sm" placeholder="0.00">
                                    </div>
                                    <div class="col-8">
                                        <label class="extra-small text-muted">Motivo</label>
                                        <select name="ocorrencias[${item.produto_id}][motivo]" class="form-select form-select-sm">
                                            <option value="faltante">Faltante / Não Entregue</option>
                                            <option value="quebrado">Quebrado / Avariado</option>
                                            <option value="errado">Produto Errado</option>
                                            <option value="outro">Outro Imprevisto</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mt-1">
                                        <input type="text" name="ocorrencias[${item.produto_id}][descricao]" class="form-control form-control-sm" placeholder="Observação curta sobre este item...">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    container.insertAdjacentHTML('beforeend', html);
                });
            } else {
                container.innerHTML = '<div class="alert alert-warning small">Não foi possível carregar os itens. Tente digitar no campo abaixo.</div>';
            }
        });

    const modalEl = document.getElementById('modalRelato');
    if (!reportModalInstance) {
        reportModalInstance = new bootstrap.Modal(modalEl);
    }
    reportModalInstance.show();
}

let resolucaoModalInstance = null;
function abrirModalResolucao(id, codigo) {
    document.getElementById('res_transf_id').value = id;
    document.getElementById('res_codigo').innerText = codigo;
    
    const modalEl = document.getElementById('modalConfirmarResolucao');
    if (!resolucaoModalInstance) {
        resolucaoModalInstance = new bootstrap.Modal(modalEl);
    }
    resolucaoModalInstance.show();
}

function setFluxoResolucao(fluxo) {
    document.getElementById('res_fluxo').value = fluxo;
    document.getElementById('formResolucao').submit();
}

function toggleItemRelato(chk, id) {
    document.getElementById(`campos_oc_${id}`).classList.toggle('d-none', !chk.checked);
}

let resumoModalInstance = null;
function abrirResumoRecebimento(id, codigo, temProblema) {
    document.getElementById('resumo_transf_id').value = id;
    document.getElementById('resumo_codigo').innerText = codigo;
    
    const container = document.getElementById('resumoItensContainer');
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>';

    fetch(`transferencias.php?action=get_items&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <table class="table table-sm extra-small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Produto</th>
                                <th class="text-center">Enviado</th>
                                <th class="text-center">Problema</th>
                                <th class="text-center bg-success bg-opacity-10">A Estocar</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.items.forEach(item => {
                    const enviada = parseFloat(item.quantidade_enviada);
                    const problema = parseFloat(item.quantidade_problema || 0);
                    const final = Math.max(0, enviada - problema);
                    
                    html += `
                        <tr>
                            <td>${item.nome}</td>
                            <td class="text-center">${enviada.toFixed(2)}</td>
                            <td class="text-center ${problema > 0 ? 'text-danger fw-bold' : 'text-muted'}">${problema.toFixed(2)}</td>
                            <td class="text-center fw-bold text-success bg-success bg-opacity-10">${final.toFixed(2)}</td>
                        </tr>
                    `;
                });
                
                html += `</tbody></table>`;
                container.innerHTML = html;
            }
        });

    const modalEl = document.getElementById('modalResumoRecebimento');
    if (!resumoModalInstance) {
        resumoModalInstance = new bootstrap.Modal(modalEl);
    }
    resumoModalInstance.show();
}
</script>

<?php if (!$isMatriz): ?>
<!-- Modal Resumo de Recebimento -->
<div class="modal fade" id="modalResumoRecebimento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form action="transferencias.php?action=confirmar_recebimento" method="POST">
                <div class="modal-header bg-primary text-white border-0">
                    <h6 class="modal-title fw-bold"><i class="fas fa-clipboard-check me-2"></i>Resumo do Recebimento de Estoque</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="transferencia_id" id="resumo_transf_id">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Confira abaixo o que será internalizado. O sistema calculou automaticamente a diferença baseada nos seus relatos de problemas.
                    </div>
                    
                    <p class="small mb-2">Pedido: <strong id="resumo_codigo"></strong></p>
                    
                    <div id="resumoItensContainer" class="border rounded bg-white overflow-hidden">
                        <!-- Itens calculados via JS -->
                    </div>

                    <div class="mt-3 text-center text-muted extra-small">
                        Ao confirmar, o saldo "A Estocar" será adicionado imediatamente ao estoque da sua filial.
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 pt-0">
                    <button type="button" class="btn btn-light btn-sm fw-bold text-muted px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold px-4">Confirmar Entrada no Estoque</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Relatar Problema (Global para Filial) -->
<div class="modal fade" id="modalRelato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form action="transferencias.php?action=relatar_problema" method="POST">
                <div class="modal-header bg-danger text-white border-0">
                    <h6 class="modal-title fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Relatar Problema na Entrega</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <input type="hidden" name="transferencia_id" id="relato_id">
                    <p class="small text-muted mb-3 border-bottom pb-2">
                        Relatando problemas para o pedido <strong id="relato_codigo" class="text-dark"></strong>.
                    </p>
                    
                    <label class="form-label fw-bold small text-danger"><i class="fas fa-list-check me-2"></i>Selecione os itens afetados:</label>
                    <div id="itensRelatoContainer" style="max-height: 250px; overflow-y: auto; padding-right: 5px;" class="mb-3 border rounded p-2 bg-white">
                        <!-- Itens via JS -->
                    </div>

                    <label class="form-label fw-bold small"><i class="fas fa-comment-dots me-2"></i>Informações Adicionais</label>
                    <textarea name="mensagem" class="form-control border-light-subtle bg-light" rows="2" placeholder="Descreva outros detalhes... " style="resize: none;"></textarea>
                </div>
                <div class="modal-footer border-0 p-3 pt-0">
                    <button type="button" class="btn btn-light btn-sm fw-bold text-muted px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold px-4">Enviar Relato à Matriz</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isMatriz): ?>
<!-- Modal Decisão de Resolução (Apenas Matriz) -->
<div class="modal fade" id="modalConfirmarResolucao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form action="transferencias.php?action=resolver_problema" method="POST" id="formResolucao">
                <div class="modal-header bg-success text-white border-0">
                    <h6 class="modal-title fw-bold"><i class="fas fa-check-circle me-2"></i>Resolver Ocorrência</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="transferencia_id" id="res_transf_id">
                    <input type="hidden" name="fluxo" id="res_fluxo" value="resolver">
                    
                    <div class="mb-3">
                        <i class="fas fa-hands-helping fa-3x text-success opacity-25"></i>
                    </div>
                    <h6 class="fw-bold">Como deseja resolver o pedido <span id="res_codigo" class="text-primary"></span>?</h6>
                    <p class="small text-muted mb-4">Escolha se deseja enviar os itens faltantes/danificados novamente ou apenas encerrar o chamado.</p>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary fw-bold py-2" onclick="setFluxoResolucao('repor')">
                            <i class="fas fa-truck-loading me-2"></i>Repor Produtos (Criar novo despacho REP)
                        </button>
                        <button type="button" class="btn btn-outline-success fw-bold py-2" onclick="setFluxoResolucao('resolver')">
                            <i class="fas fa-check me-2"></i>Apenas Marcar como Resolvido
                        </button>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 pt-0 justify-content-center">
                    <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
