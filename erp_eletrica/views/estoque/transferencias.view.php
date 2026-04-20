<?php
// views/estoque/transferencias.view.php
// Conteúdo injetado pelo layout main.view.php via BaseController::render()
?>

<style>
    .tab-btn { background: #f1f5f9; border: 1px solid #e2e8f0; padding: 6px 16px; cursor: pointer; font-weight: 600; color: #64748b; border-radius: 8px; transition: all .2s; font-size: .75rem; display: flex; align-items: center; }
    .tab-btn.active { background: var(--primary-color, #0d6efd); color: white; border-color: var(--primary-color, #0d6efd); }
    .tab-btn:hover:not(.active) { background: #e2e8f0; color: #334155; }
    .tab-btn .badge { font-size: 0.65rem; padding: 0.25em 0.6em; }

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
    <div class="card-header bg-white border-bottom py-2">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
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

            <div class="d-flex gap-2">
                <?php if ($isMatriz): ?>
                    <button class="tab-btn <?= $aba == 'recebidas' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=recebidas'">
                        <i class="fas fa-inbox me-1"></i>Solicitações <span class="d-none d-md-inline">Pendentes</span>
                        <?php if (!empty($recebidas)): ?>
                            <span class="badge bg-danger ms-1"><?= count($recebidas) ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="tab-btn <?= $aba == 'nova_transferencia' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=nova_transferencia'">
                        <i class="fas fa-truck-loading me-1"></i>Novo Despacho
                    </button>
                    <button class="tab-btn <?= $aba == 'historico_envios' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=historico_envios'">
                        <i class="fas fa-history me-1"></i>Histórico <span class="d-none d-md-inline">de Envios</span>
                        <?php if (($problemas_pendentes ?? 0) > 0): ?>
                            <span class="badge bg-danger ms-1" title="Ocorrências não resolvidas"><?= $problemas_pendentes ?></span>
                        <?php endif; ?>
                    </button>
                <?php else: ?>
                    <button class="tab-btn <?= $aba == 'nova_solicitacao' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=nova_solicitacao'">
                        <i class="fas fa-plus-circle me-1"></i><span class="d-none d-md-inline">Pedir ao </span>CD (Matriz)
                    </button>
                    <button class="tab-btn <?= $aba == 'em_transito' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=em_transito'">
                        <i class="fas fa-truck me-1"></i>Em Trânsito
                        <?php if (!empty($em_transito)): ?>
                            <span class="badge bg-warning text-dark ms-1"><?= count($em_transito) ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="tab-btn <?= $aba == 'historico_recebimentos' ? 'active' : '' ?>" onclick="location.href='transferencias.php?aba=historico_recebimentos'">
                        <i class="fas fa-history me-1"></i>Histórico
                    </button>
                <?php endif; ?>
            </div>
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
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Código</th>
                                        <th>Filial</th>
                                        <th>Solicitado em</th>
                                        <th>Observação</th>
                                        <th class="text-end pe-3">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recebidas as $req): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="badge bg-warning text-dark extra-small mb-1 d-block" style="width: fit-content;">REQUER APROVAÇÃO</span>
                                            <strong class="text-primary"><?= htmlspecialchars($req['codigo_transferencia']) ?></strong>
                                        </td>
                                        <td><span class="fw-bold small"><?= htmlspecialchars($req['nome_filial']) ?></span></td>
                                        <td><span class="extra-small text-muted"><?= date('d/m/Y H:i', strtotime($req['data_solicitacao'])) ?></span></td>
                                        <td class="small text-muted">
                                            <?= $req['observacoes'] ? '"' . mb_strimwidth(htmlspecialchars($req['observacoes']), 0, 40, "...") . '"' : '<span class="opacity-25">-</span>' ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <button type="button" class="btn btn-primary btn-sm fw-bold px-3 py-1" onclick="abrirProcessarSolicitacao(<?= $req['id'] ?>)">
                                                <i class="fas fa-tasks me-2"></i>Processar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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

                <?php if (empty($historico_envios)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-history fa-3x mb-3 opacity-25"></i>
                        <h6 class="fw-bold">Nenhum envio registrado</h6>
                        <p class="small">Tente ajustar os filtros acima.</p>
                    </div>
                <?php else: ?>
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Destino</th>
                                <th>Data Envio</th>
                                <th>Status</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historico_envios as $he): 
                                $isRelatoPendente = ($he['tem_problema'] == 1 && $he['problema_resolvido'] == 0);
                            ?>
                            <tr class="<?= $isRelatoPendente ? 'table-danger' : '' ?>">
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
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-primary btn-sm py-0" onclick="abrirDetalhesTransferencia(<?= $he['id'] ?>)">
                                        <i class="fas fa-eye me-1"></i>Ver
                                    </button>
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
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Código</th>
                                        <th>Despachado em</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($em_transito as $et): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <strong class="text-primary"><?= htmlspecialchars($et['codigo_transferencia']) ?></strong>
                                        </td>
                                        <td><span class="extra-small text-muted"><?= date('d/m/Y H:i', strtotime($et['data_envio'])) ?></span></td>
                                        <td><span class="badge bg-warning text-dark extra-small">EM TRÂNSITO</span></td>
                                        <td class="text-end pe-3">
                                            <button type="button" class="btn btn-primary btn-sm fw-bold px-3 py-1" onclick="abrirProcessarRecebimento(<?= $et['id'] ?>)">
                                                <i class="fas fa-clipboard-check me-2"></i>Receber
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historico as $h):
                                $badge = 'secondary';
                                if ($h['status'] == 'concluida')   $badge = 'success';
                                if ($h['status'] == 'em_transito') $badge = 'warning text-dark';
                                if ($h['status'] == 'pendente')    $badge = 'primary';

                                $isRelatoPendente = ($h['tem_problema'] == 1 && $h['problema_resolvido'] == 0);
                            ?>
                            <tr class="<?= $isRelatoPendente ? 'table-danger' : '' ?>">
                                <td>
                                    <strong class="text-primary"><?= htmlspecialchars($h['codigo_transferencia']) ?></strong>
                                    <?php if ($h['tem_problema']): ?>
                                        <i class="fas fa-exclamation-triangle text-danger ms-1" title="Problema no recebimento"></i>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?= $badge ?>"><?= strtoupper(str_replace('_', ' ', $h['status'])) ?></span></td>
                                <td><?= date('d/m/Y H:i', strtotime($h['data_solicitacao'])) ?></td>
                                <td><?= $h['data_recebimento'] ? date('d/m/Y H:i', strtotime($h['data_recebimento'])) : '---' ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-primary btn-sm py-0" onclick="abrirDetalhesTransferencia(<?= $h['id'] ?>)">
                                        <i class="fas fa-eye me-1"></i>Ver
                                    </button>
                                </td>
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

        // Botões de página (Com lógica de janela deslizante para evitar "explosão" de botões)
        pagination.innerHTML = '';
        if (totalPages <= 1) return;

        const addBtn = (label, page, disabled = false, dataPage = null) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'page-btn' + (page === currentPage && label !== '...' ? ' active' : '');
            btn.textContent = label;
            btn.disabled = disabled || label === '...';
            if (dataPage) btn.addEventListener('click', () => { currentPage = dataPage; render(); });
            else if (!disabled && label !== '...') btn.addEventListener('click', () => { currentPage = page; render(); });
            pagination.appendChild(btn);
        };

        // Anterior
        addBtn('‹', currentPage - 1, currentPage === 1);

        // Lógica de Janela (Mostra Primeira, Última e as próximas à atual)
        const range = 2; // Páginas para cada lado
        for (let p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || (p >= currentPage - range && p <= currentPage + range)) {
                addBtn(p, p);
            } else if (p === currentPage - range - 1 || p === currentPage + range + 1) {
                addBtn('...', 0, true);
            }
        }

        // Próxima
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
    // Esconde o modal de processamento se estiver aberto
    const procModalEl = document.getElementById('modalProcessarRecebimento');
    if (procModalEl) {
        const procInstance = bootstrap.Modal.getInstance(procModalEl);
        if (procInstance) procInstance.hide();
    }

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
                                    <div class="col-12 mt-1">
                                        <label class="extra-small text-muted d-block"><i class="fas fa-camera me-1"></i>Foto do Item com Defeito</label>
                                        <input type="file" name="ocorrencias_${item.produto_id}_foto" class="form-control form-control-sm" accept="image/*">
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
    // Esconde o modal de processamento se estiver aberto
    const procModalEl = document.getElementById('modalProcessarRecebimento');
    if (procModalEl) {
        const procInstance = bootstrap.Modal.getInstance(procModalEl);
        if (procInstance) procInstance.hide();
    }

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

let detalhesModalInstance = null;
function abrirDetalhesTransferencia(id) {
    const modalEl = document.getElementById('modalDetalhesPedido');
    if (!detalhesModalInstance) detalhesModalInstance = new bootstrap.Modal(modalEl);
    
    // Reset modal
    document.getElementById('det_loading').classList.remove('d-none');
    document.getElementById('det_content').classList.add('d-none');
    document.getElementById('det_footer_acoes').innerHTML = '';
    detalhesModalInstance.show();

    fetch(`transferencias.php?action=get_items&id=${id}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                alert(res.message);
                return;
            }

            const t = res.transfer;
            document.getElementById('det_codigo').innerText = t.codigo_transferencia;
            document.getElementById('det_subtitulo').innerText = `${t.nome_origem} → ${t.nome_destino}`;
            document.getElementById('det_data').innerText = new Date(t.data_solicitacao).toLocaleString();
            
            const badge = document.getElementById('det_status_badge');
            badge.innerText = t.status.toUpperCase().replace('_', ' ');
            badge.className = 'badge bg-' + (t.status === 'concluida' ? 'success' : (t.status === 'em_transito' ? 'warning text-dark' : 'primary'));

            // Itens
            const tbody = document.getElementById('det_tbody_items');
            tbody.innerHTML = res.items.map(it => `
                <tr>
                    <td>
                        <div class="fw-bold">${it.nome}</div>
                        <div class="extra-small text-muted">SKU: ${it.codigo}</div>
                    </td>
                    <td class="text-center">${parseFloat(it.quantidade_solicitada)}</td>
                    <td class="text-center">${parseFloat(it.quantidade_enviada || 0)}</td>
                    <td class="text-center">${parseFloat(it.quantidade_recebida || 0)}</td>
                    <td class="text-center">
                        ${it.quantidade_problema > 0 ? `<span class="badge bg-danger">${parseFloat(it.quantidade_problema)}</span>` : '<span class="text-muted opacity-25">---</span>'}
                    </td>
                </tr>
            `).join('');

            // Ocorrências
            const secaoOc = document.getElementById('det_secao_ocorrencias');
            if (res.ocorrencias && res.ocorrencias.length > 0) {
                secaoOc.classList.remove('d-none');
                document.getElementById('det_lista_ocorrencias').innerHTML = res.ocorrencias.map(oc => `
                    <div class="border rounded-3 p-3 mb-3 bg-white shadow-sm border-danger-subtle">
                        <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                            <div class="d-flex align-items-center">
                                <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 24px; height: 24px; font-size: 10px;">
                                    <i class="fas fa-exclamation"></i>
                                </div>
                                <strong class="text-dark small">${oc.nome}</strong>
                            </div>
                            <span class="badge bg-danger rounded-pill px-3">${parseFloat(oc.quantidade_problema)} UN</span>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-8">
                                <div class="extra-small text-muted text-uppercase fw-bold mb-1">Motivo do Relato</div>
                                <div class="small fw-bold text-danger text-uppercase mb-2">${oc.motivo}</div>
                                
                                <div class="extra-small text-muted text-uppercase fw-bold mb-1">Descrição Detalhada</div>
                                <div class="small text-dark bg-light p-2 rounded border-start border-3 border-danger">
                                    ${oc.descricao || '<em class="text-muted">Sem descrição detalhada.</em>'}
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                ${oc.foto ? `
                                    <div class="extra-small text-muted text-uppercase fw-bold mb-1">Evidência</div>
                                    <div class="product-zoom-container d-inline-block rounded border overflow-hidden bg-white shadow-sm" style="width: 80px; height: 80px; cursor: pointer;" title="Clique para expandir">
                                        <img src="${oc.foto}" style="width: 100%; height: 100%; object-fit: contain;">
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                secaoOc.classList.add('d-none');
            }
            } else {
                secaoOc.classList.add('d-none');
            }

            // Obs
            document.getElementById('det_observacao').innerText = t.observacoes || 'Nenhuma observação registrada.';

            // Ações Matriz
            if (res.isMatriz && t.tem_problema == 1 && t.problema_resolvido == 0) {
                document.getElementById('det_footer_acoes').innerHTML = `
                    <button type="button" class="btn btn-success btn-sm fw-bold px-4" 
                            onclick="detalhesModalInstance.hide(); abrirModalResolucao(${t.id}, '${t.codigo_transferencia}')">
                        <i class="fas fa-check-circle me-1"></i>Resolver Ocorrência
                    </button>
                `;
            } else if (t.problema_resolvido == 1) {
                document.getElementById('det_footer_acoes').innerHTML = '<span class="badge bg-success py-2 px-3 fw-bold"><i class="fas fa-check-double me-2"></i>Ocorrência Resolvida</span>';
            }

            document.getElementById('det_loading').classList.add('d-none');
            document.getElementById('det_content').classList.remove('d-none');
        });
}

let processarModalInstance = null;
function abrirProcessarSolicitacao(id) {
    const modalEl = document.getElementById('modalProcessarSolicitacao');
    if (!processarModalInstance) processarModalInstance = new bootstrap.Modal(modalEl);
    
    // Reset modal
    document.getElementById('proc_loading').classList.remove('d-none');
    document.getElementById('proc_content').classList.add('d-none');
    processarModalInstance.show();

    fetch(`transferencias.php?action=get_items&id=${id}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                alert(res.message);
                return;
            }

            const t = res.transfer;
            document.getElementById('proc_codigo').innerText = t.codigo_transferencia;
            document.getElementById('proc_subtitulo').innerText = `Solicitante: ${t.nome_destino}`;
            document.getElementById('proc_transf_id').value = t.id;
            
            // Obs
            const obsEl = document.getElementById('proc_observacao');
            if (t.observacoes) {
                obsEl.parentElement.classList.remove('d-none');
                obsEl.innerText = t.observacoes;
            } else {
                obsEl.parentElement.classList.add('d-none');
            }

            // Itens com Inputs
            const tbody = document.getElementById('proc_tbody_items');
            tbody.innerHTML = res.items.map(it => {
                const disp = parseFloat(it.disp_matriz || 0);
                const solicitada = parseFloat(it.quantidade_solicitada);
                const padrao = solicitada > disp ? disp : solicitada;
                const corDisp = disp < solicitada ? 'text-danger' : 'text-success';

                return `
                    <tr>
                        <td>
                            <div class="fw-bold small">${it.nome}</div>
                            <div class="extra-small text-muted">SKU: ${it.codigo}</div>
                        </td>
                        <td class="text-center small">${solicitada}</td>
                        <td class="text-center fw-bold ${corDisp} small">${disp}</td>
                        <td class="p-2">
                            <input type="number" step="1" min="0" max="${disp}"
                                name="qtd_enviada[${it.produto_id}]"
                                value="${padrao}"
                                class="form-control form-control-sm text-center fw-bold border-primary-subtle"
                                onchange="if(this.value < 0) this.value = 0; if(parseFloat(this.value) > ${disp}) this.value = ${disp};"
                            >
                        </td>
                    </tr>
                `;
            }).join('');

            document.getElementById('proc_loading').classList.add('d-none');
            document.getElementById('proc_content').classList.remove('d-none');
        });
}

function abrirProcessarRecebimento(id) {
    const modalEl = document.getElementById('modalProcessarRecebimento');
    let instance = bootstrap.Modal.getInstance(modalEl);
    if (!instance) instance = new bootstrap.Modal(modalEl);
    
    // Reset modal
    document.getElementById('receb_loading').classList.remove('d-none');
    document.getElementById('receb_content').classList.add('d-none');
    document.getElementById('receb_footer_acoes').innerHTML = '';
    instance.show();

    fetch(`transferencias.php?action=get_items&id=${id}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                alert(res.message);
                return;
            }

            const t = res.transfer;
            document.getElementById('receb_codigo').innerText = t.codigo_transferencia;
            document.getElementById('receb_subtitulo').innerText = `Despachado em: ${new Date(t.data_envio).toLocaleString()}`;
            
            // Itens Enviados
            const tbody = document.getElementById('receb_tbody_items');
            tbody.innerHTML = res.items.filter(it => it.quantidade_enviada > 0).map(it => `
                <li class="list-group-item d-flex justify-content-between align-items-center small py-2">
                    <div class="fw-bold text-dark">${it.nome}</div>
                    <span class="badge bg-primary rounded-pill px-3">${parseFloat(it.quantidade_enviada)} UN</span>
                </li>
            `).join('');

            // Ações
            const footer = document.getElementById('receb_footer_acoes');
            footer.innerHTML = `
                <button type="button" class="btn btn-outline-danger btn-sm fw-bold px-3" 
                        onclick="bootstrap.Modal.getInstance(document.getElementById('modalProcessarRecebimento')).hide(); abrirModalRelato(${t.id}, '${t.codigo_transferencia}')">
                    <i class="fas fa-exclamation-triangle me-2"></i>Relatar Problema
                </button>
                <button type="button" class="btn btn-${t.tem_problema == 1 ? 'warning' : 'success'} btn-sm fw-bold px-4 flex-grow-1" 
                        onclick="bootstrap.Modal.getInstance(document.getElementById('modalProcessarRecebimento')).hide(); abrirResumoRecebimento(${t.id}, '${t.codigo_transferencia}', ${t.tem_problema == 1})">
                    <i class="fas fa-box-open me-2"></i>
                    ${t.tem_problema == 1 ? 'Internalizar com Ressalvas' : 'Internalizar Estoque'}
                </button>
            `;

            document.getElementById('receb_loading').classList.add('d-none');
            document.getElementById('receb_content').classList.remove('d-none');
        });
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
            <form action="transferencias.php?action=relatar_problema" method="POST" enctype="multipart/form-data">
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

<!-- Modal Detalhes do Pedido (Novo) -->
<div class="modal fade" id="modalDetalhesPedido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h6 class="modal-title fw-bold" id="det_codigo">---</h6>
                    <small class="text-muted" id="det_subtitulo">---</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="det_loading" class="text-center py-5">
                    <div class="spinner-border text-primary opacity-50" role="status"></div>
                    <p class="small text-muted mt-2">Carregando detalhes...</p>
                </div>
                
                <div id="det_content" class="d-none">
                    <!-- Resumo Status -->
                    <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded shadow-sm border">
                        <div>
                            <span class="extra-small text-muted d-block text-uppercase fw-bold">Status Atual</span>
                            <span id="det_status_badge" class="badge">---</span>
                        </div>
                        <div class="text-end">
                            <span class="extra-small text-muted d-block text-uppercase fw-bold">Data da Solicitação</span>
                            <span id="det_data" class="small fw-bold">---</span>
                        </div>
                    </div>

                    <!-- Lista de Itens -->
                    <h6 class="fw-bold mb-3 small"><i class="fas fa-list me-2"></i>Itens da Solicitação</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover border small align-middle">
                            <thead class="table-light extra-small text-uppercase">
                                <tr>
                                    <th>Produto</th>
                                    <th class="text-center">Pedido</th>
                                    <th class="text-center">Enviado</th>
                                    <th class="text-center">Recebido</th>
                                    <th class="text-center">Problema</th>
                                </tr>
                            </thead>
                            <tbody id="det_tbody_items" class="bg-white"></tbody>
                        </table>
                    </div>

                    <!-- Bloco de Ocorrências -->
                    <div id="det_secao_ocorrencias" class="mt-4 d-none">
                        <h6 class="fw-bold mb-3 small text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Ocorrências Reportadas</h6>
                        <div id="det_lista_ocorrencias"></div>
                    </div>

                    <!-- Observações -->
                    <div class="mt-4" id="det_secao_obs">
                        <h6 class="fw-bold mb-2 extra-small text-muted text-uppercase">Observações do Pedido</h6>
                        <div class="p-3 bg-light border-0 rounded small" id="det_observacao">---</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 pt-0">
                <button type="button" class="btn btn-light btn-sm fw-bold px-4" data-bs-dismiss="modal">Fechar</button>
                <div id="det_footer_acoes" class="d-inline-block"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Processar Solicitação (Nova) -->
<div class="modal fade" id="modalProcessarSolicitacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form action="transferencias.php?action=aprovar_solicitacao" method="POST">
                <input type="hidden" name="transferencia_id" id="proc_transf_id">
                
                <div class="modal-header bg-primary text-white border-0">
                    <div>
                        <h6 class="modal-title fw-bold text-white" id="proc_codigo">---</h6>
                        <small class="opacity-75" id="proc_subtitulo">---</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div id="proc_loading" class="text-center py-5">
                        <div class="spinner-border text-primary opacity-50" role="status"></div>
                        <p class="small text-muted mt-2">Carregando itens...</p>
                    </div>
                    
                    <div id="proc_content" class="d-none">
                        <!-- Observações -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-2 extra-small text-muted text-uppercase">Nota da Filial</h6>
                            <div class="p-3 bg-light border-start border-3 border-warning rounded small" id="proc_observacao">---</div>
                        </div>

                        <!-- Lista de Itens com Inputs -->
                        <h6 class="fw-bold mb-3 small"><i class="fas fa-edit me-2 text-primary"></i>Ajustar Quantidades para Envio</h6>
                        <div class="table-responsive border rounded bg-white shadow-sm" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light sticky-top shadow-sm" style="z-index: 5;">
                                    <tr>
                                        <th>Produto</th>
                                        <th class="text-center" style="width:110px">Solicitado</th>
                                        <th class="text-center" style="width:110px">Disp. Matriz</th>
                                        <th class="text-center" style="width:140px">Qtd p/ Enviar</th>
                                    </tr>
                                </thead>
                                <tbody id="proc_tbody_items"></tbody>
                            </table>
                        </div>
                        <div class="mt-2 text-muted extra-small">
                            <i class="fas fa-info-circle me-1"></i> A quantidade para enviar não pode exceder o estoque disponível na matriz.
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-3 pt-0">
                    <button type="button" class="btn btn-light btn-sm fw-bold px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-5">
                        <i class="fas fa-truck-dispatch me-2"></i>Aprovar e Despachar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Processar Recebimento (Filial - Novo) -->
<div class="modal fade" id="modalProcessarRecebimento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h6 class="modal-title fw-bold" id="receb_codigo">---</h6>
                    <small class="text-muted" id="receb_subtitulo">---</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="receb_loading" class="text-center py-5">
                    <div class="spinner-border text-primary opacity-50" role="status"></div>
                    <p class="small text-muted mt-2">Carregando itens...</p>
                </div>
                
                <div id="receb_content" class="d-none">
                    <p class="small text-muted mb-3 border-bottom pb-2">Confira se as quantidades abaixo chegaram corretamente à sua filial.</p>
                    
                    <h6 class="fw-bold mb-2 extra-small text-muted text-uppercase">Itens no Romaneio</h6>
                    <ul class="list-group list-group-flush border rounded bg-light mb-3" id="receb_tbody_items">
                        <!-- Itens via JS -->
                    </ul>

                    <div class="alert alert-info extra-small mb-0 shadow-sm border-0">
                        <i class="fas fa-info-circle me-1 text-primary"></i> Se faltar algo ou estiver quebrado, use o botão de <strong>Relatar Problema</strong> primeiro.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 pt-0 d-flex gap-2" id="receb_footer_acoes">
                <!-- Botões via JS -->
            </div>
        </div>
    </div>
</div>
