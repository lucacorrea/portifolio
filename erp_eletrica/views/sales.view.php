<?php if (!$caixaAberto): ?>
<div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4 p-3 rounded-4">
    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
    <div class="flex-grow-1">
        <h6 class="mb-1 fw-bold">ATENÇÃO: CAIXA FECHADO!</h6>
        <p class="mb-0 small">Você não pode realizar vendas enquanto o seu caixa estiver fechado. 
           <a href="caixa.php" class="fw-bold text-danger text-decoration-underline ms-1">Clique aqui para abrir seu caixa agora.</a>
        </p>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 <?php echo !$caixaAberto ? 'opacity-50 select-none' : ''; ?>" style="<?php echo !$caixaAberto ? 'pointer-events: none;' : ''; ?>">
    <!-- Left Side: Product selection & Preview -->
    <div class="col-lg-7 d-flex flex-column">
        <div class="row g-4 mb-4" style="position: relative; z-index: 1050; overflow: visible !important;">
            <div class="col-md-8" style="position: relative; z-index: 1060; overflow: visible !important;">
                <div class="card border-0 shadow-sm h-100" style="overflow: visible !important;">
                    <div class="card-body" style="overflow: visible !important;">
                        <div class="position-relative" style="overflow: visible !important;">
                            <div class="input-group input-group-lg shadow-sm rounded">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" id="pdvSearch" class="form-control border-start-0 ps-0" placeholder="Pesquisar Produto (F4)..." autocomplete="off" style="flex: 3;">
                                <span class="input-group-text bg-light border-start-0 text-muted extra-small fw-bold">QTD</span>
                                <input type="number" id="pdvQty" class="form-control border-start-0 text-center fw-bold" value="1" min="1" step="0.001" style="flex: 1; max-width: 90px;" title="Quantidade">
                            </div>
                            <div id="searchResults" class="list-group shadow-lg d-none" style="position: absolute; top: 100%; left: 0; z-index: 10000; width: 100%; max-height: 400px; overflow-y: auto;">
                                <!-- Results will be injected here -->
                            </div>
                        </div>
                        <!-- Quick Actions Bar (F9/F10) for Mobile/Desktop -->
                        <div class="row g-2 mt-2">
                            <div class="col-6 col-sm-auto">
                                <button class="btn btn-outline-primary fw-bold w-100 px-3 py-2 shadow-sm d-flex align-items-center justify-content-center" onclick="loadPendingPreSales()" title="Importar Pré-Venda (F9)">
                                    <i class="fas fa-file-import me-2"></i><span class="d-none d-sm-inline">Importar</span> (F9)
                                </button>
                            </div>
                            <div class="col-6 col-sm-auto">
                                <button class="btn btn-outline-info fw-bold w-100 px-3 py-2 shadow-sm d-flex align-items-center justify-content-center" onclick="saveCurrentSaleAsPreSale()" id="btnPauseSale" title="Pausar Venda (F10)">
                                    <i class="fas fa-pause-circle me-2"></i><span class="d-none d-sm-inline">Pausar</span> (F10)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 text-center d-flex flex-column align-items-center justify-content-center p-3">
                    <div id="productPreviewImg" class="bg-light rounded mb-2 d-flex align-items-center justify-content-center border product-zoom-container" style="width: 120px; height: 120px; overflow: hidden;">
                        <i class="fas fa-image fs-1 text-muted opacity-25"></i>
                    </div>
                    <div id="productPreviewName" class="extra-small fw-bold text-uppercase text-muted" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.8em; line-height: 1.4em;">Aguardando...</div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm flex-grow-1 overflow-auto">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-list me-2"></i>Itens da Venda</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="cartTable">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th class="ps-4" width="80">Item</th>
                                <th>Produto</th>
                                <th class="text-center" width="120">Qtd</th>
                                <th class="text-end" width="120">Unitário</th>
                                <th class="text-end" width="120">Subtotal</th>
                                <th class="text-center" width="60"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Cart items injected here -->
                        </tbody>
                    </table>
                </div>
                <!-- Empty state -->
                <div id="cartEmptyState" class="text-center py-5 text-muted">
                    <i class="fas fa-cart-plus fs-1 d-block mb-3 opacity-25"></i>
                    Aguardando inclusão de produtos...
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side: Checkout Summary -->
    <div class="col-lg-5">
        <div class="card border-0 glass-card h-100 d-flex flex-column" style="border: 1px solid var(--primary-color) !important;">
            <div class="card-header bg-erp-primary py-3 border-0">
                <h5 class="mb-0 fw-bold text-white"><i class="fas fa-cash-register me-2 text-white"></i>Checkout SaaS</h5>
            </div>
            <div class="card-body flex-grow-1">
                <div class="mb-4">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Identificar Cliente (Obrigatório para Fiado)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fas fa-user-tag"></i>
                        </span>
                        <input type="text" id="customerSearch" class="form-control border-start-0 ps-0" placeholder="Nome, CPF ou Telefone...">
                        <button class="btn btn-outline-primary" type="button" onclick="abrirModalQuickClient()" title="Novo Cliente">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div id="customerResults" class="list-group mt-2 shadow-sm position-absolute w-100" style="z-index: 1050; display: none; left:0; right:0;"></div>
                    
                    <div id="selectedCustomerInfo" class="mt-3 p-3 bg-primary bg-opacity-10 border border-primary border-opacity-10 rounded d-none">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-0 fw-bold text-primary" id="customerNameDisplay"></p>
                                <p class="mb-0 small text-muted" id="customerDocDisplay"></p>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="clearCustomer()">
                                <i class="fas fa-times me-1"></i>Remover
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-uppercase text-muted">Método de Pagamento</label>
                    <div class="row g-2">
                        <div class="col-12 col-sm-6">
                            <input type="radio" class="btn-check" name="payment" id="pay_dinheiro" value="dinheiro" checked>
                            <label class="btn btn-outline-secondary d-block text-start p-3 border" for="pay_dinheiro">
                                <i class="fas fa-money-bill-wave me-2 text-success"></i> Dinheiro (F1)
                            </label>
                        </div>
                        <div class="col-12 col-sm-6">
                            <input type="radio" class="btn-check" name="payment" id="pay_pix" value="pix">
                            <label class="btn btn-outline-secondary d-block text-start p-3 border" for="pay_pix">
                                <i class="fa-brands fa-pix me-2 text-info"></i> Pix (Ctrl+F1)
                            </label>
                        </div>
                        <div class="col-12 col-sm-6">
                            <input type="radio" class="btn-check" name="payment" id="pay_credito" value="cartao_credito">
                            <label class="btn btn-outline-secondary d-block text-start p-3 border" for="pay_credito">
                                <i class="fas fa-credit-card me-2 text-primary"></i> Crédito (F6)
                            </label>
                        </div>
                        <div class="col-12 col-sm-6">
                            <input type="radio" class="btn-check" name="payment" id="pay_debito" value="cartao_debito">
                            <label class="btn btn-outline-secondary d-block text-start p-3 border" for="pay_debito">
                                <i class="fas fa-credit-card me-2 text-info"></i> Débito (F7)
                            </label>
                        </div>
                        <div class="col-12 col-sm-6">
                            <input type="radio" class="btn-check" name="payment" id="pay_boleto" value="boleto">
                            <label class="btn btn-outline-secondary d-block text-start p-3 border" for="pay_boleto">
                                <i class="fas fa-barcode me-2 text-secondary"></i> Boleto (F11)
                            </label>
                        </div>
                        <div class="col-12 col-sm-6">
                            <input type="radio" class="btn-check" name="payment" id="pay_fiado" value="fiado">
                            <label class="btn btn-outline-secondary d-block text-start p-3 border" for="pay_fiado">
                                <i class="fas fa-hand-holding-usd me-2 text-warning"></i> A Prazo (Fiado) (F8)
                            </label>
                        </div>
                    </div>
                    
                    <!-- Card Tax Input -->
                    <div id="cardTaxContainer" class="d-none mt-3 p-3 bg-info bg-opacity-10 border border-info border-opacity-10 rounded">
                        <label class="form-label extra-small fw-bold text-uppercase opacity-75">Taxa da Maquininha (%)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-info"><i class="fas fa-percent"></i></span>
                            <input type="number" id="taxa_cartao" class="form-control border-start-0 ps-0" placeholder="0,00" step="0.01" min="0" oninput="renderCart()">
                        </div>
                    </div>

                    <!-- Cash Change Calculator (Dinheiro) -->
                    <div id="cashChangeContainer" class="d-none mt-3 p-3 bg-success bg-opacity-10 border border-success border-opacity-10 rounded">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label extra-small fw-bold text-uppercase opacity-75">Valor Recebido (R$)</label>
                                <input type="number" id="valor_recebido" class="form-control form-control-lg fw-bold text-success border-success" placeholder="0,00" step="0.01" min="0" oninput="calculateChange()">
                            </div>
                            <div class="col-6">
                                <label class="form-label extra-small fw-bold text-uppercase opacity-75">Troco (R$)</label>
                                <div id="troco_display" class="form-control form-control-lg fw-bold bg-white text-danger border-0 d-flex align-items-center justify-content-center" style="height: calc(1.5em + 1rem + 2px);">
                                    R$ 0,00
                                </div>
                                <input type="hidden" id="troco_input" value="0">
                            </div>
                        </div>
                        <div class="extra-small text-success mt-1"><i class="fas fa-calculator me-1"></i> O troco é calculado automaticamente.</div>
                    </div>
                </div>

                <!-- Fiscal Toggle -->
                <div class="mb-4">
                    <label class="form-label small fw-bold text-uppercase text-muted">Tipo de Venda</label>
                    <div class="row g-2">
                        <div class="col-12 col-sm-6">
                            <input type="radio" class="btn-check" name="tipo_nota" id="tipo_fiscal" value="fiscal" autocomplete="off">
                            <label class="btn btn-outline-success d-block text-start p-3 w-100" for="tipo_fiscal">
                                <i class="fas fa-file-invoice-dollar me-2"></i>
                                <span class="fw-bold">Nota Fiscal</span>
                                <div class="extra-small opacity-75 mt-1">Emite NFC-e SEFAZ</div>
                            </label>
                        </div>
                        <div class="col-12 col-sm-6">
                            <input type="radio" class="btn-check" name="tipo_nota" id="tipo_nao_fiscal" value="nao_fiscal" autocomplete="off" checked>
                            <label class="btn btn-outline-secondary d-block text-start p-3 w-100" for="tipo_nao_fiscal">
                                <i class="fas fa-receipt me-2"></i>
                                <span class="fw-bold">Não Fiscal</span>
                                <div class="extra-small opacity-75 mt-1">Só recibo simples</div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-light p-4 rounded-3 border mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-bold" id="totalSub">R$ 0,00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Desconto (%)</span>
                        <div style="width: 80px;">
                            <input type="number" id="discountPercent" class="form-control form-control-sm text-end fw-bold text-success border-success bg-success bg-opacity-10" value="0" min="0" max="100" step="0.1" onfocus="interceptDiscount(event)" onmousedown="interceptDiscount(event)" onkeydown="interceptDiscount(event)" oninput="renderCart()">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted text-success">Desconto</span>
                        <span class="fw-bold text-success" id="totalDesc">- R$ 0,00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 d-none" id="taxRow">
                        <span class="text-muted text-info">Taxa Maquininha</span>
                        <span class="fw-bold text-info" id="totalTax">+ R$ 0,00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold">TOTAL</h4>
                        <h2 class="mb-0 fw-bold text-primary" id="finalTotal">R$ 0,00</h2>
                    </div>
                </div>

                <?php if (($_SESSION['usuario_nivel'] ?? '') !== 'vendedor'): ?>
                <button class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm" id="btnCheckout" disabled>
                    <i class="fas fa-check-circle me-2"></i>CONFIRMAR VENDA (F2)
                </button>
                <?php else: ?>
                <div class="alert alert-info small mb-0">
                    <i class="fas fa-info-circle me-1"></i> Usuários nível vendedor não podem finalizar vendas.
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Sales History (Últimos Cupons) -->
            <div class="card-footer bg-white py-3 border-0 mt-auto">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="small fw-bold text-muted text-uppercase mb-0">Últimos Cupons</h6>
                    <button class="btn btn-sm btn-link text-decoration-none p-0 extra-small" onclick="loadRecentSales()">Atualizar</button>
                </div>
                <div id="recentSalesList" class="small overflow-auto" style="max-height: 150px;">
                    <div class="text-center py-2 opacity-50">Carregando histórico...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Pending Pre-sales -->
<div class="modal fade" id="modalPendingPV" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 shadow-sm">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-file-import me-2 text-white"></i>Pré-Vendas Pendentes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Código</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Vendedor</th>
                                <th class="text-end pe-4">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="listPendingPVs">
                            <!-- Injected via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Discount Authorization -->
<div class="modal fade" id="modalDiscountAuth" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0 shadow-sm">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-shield-halved me-2 text-white"></i>Autorização de Administrador</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="resetDiscount()"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-key fs-3"></i>
                    </div>
                    <h6 class="fw-bold mb-1">Acesso Restrito</h6>
                    <p class="text-muted small">Esta operação requer a presença e senha de um Administrador.</p>
                </div>
                
                <div class="mb-4 text-start">
                    <label class="form-label small fw-bold text-uppercase opacity-75" id="authLabel">Senha ou Código de Autorização</label>
                    <input type="text" id="authCredential" class="form-control form-control-lg text-center shadow-sm border-2" placeholder="Digite a senha ou código..." autofocus>
                </div>

                <div class="d-grid">
                    <button class="btn btn-dark fw-bold py-3 shadow-sm" onclick="validateAuthorization()">
                        <i class="fas fa-check-circle me-2 text-primary"></i>CONFIRMAR IDENTIDADE
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Sale Management -->
<div class="modal fade" id="modalSaleManager" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 shadow-sm">
                <h5 class="modal-title fw-bold text-white" style="color: #ffffff !important;"><i class="fas fa-cash-register me-2 text-white" style="color: #ffffff !important;"></i>Gestão de Venda <span class="text-white" style="color: #ffffff !important;">#</span><span id="manageSaleId" class="text-white" style="color: #ffffff !important;"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded mb-3 border">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Cliente:</span>
                        <span class="fw-bold fw-bold" id="manageSaleCustomer"></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Valor Total:</span>
                        <span class="fw-bold text-primary fs-5" id="manageSaleTotal"></span>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-danger fw-bold py-3" onclick="cancelSaleAction()">
                        <i class="fas fa-trash-alt me-2"></i>CANCELAR VENDA (ESTORNO)
                    </button>
                    <button class="btn btn-outline-secondary fw-bold py-3" onclick="openExchangeFlow()">
                        <i class="fas fa-exchange-alt me-2"></i>SOLICITAR TROCA
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cancelamento Triplo (PDV) -->
<div class="modal fade" id="modalTripleCancel" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 py-3 shadow-sm">
                <h5 class="modal-title fw-bold text-white" style="color: #ffffff !important;"><i class="fas fa-exclamation-triangle me-2 text-white" style="color: #ffffff !important;"></i>Cancelar Venda <span class="text-white" style="color: #ffffff !important;">#</span><span id="cancel-id-label" class="text-white" style="color: #ffffff !important;"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Passo 1: Escolha do Modelo -->
                <div id="cancel-step-1">
                    <p class="text-muted mb-4 uppercase small fw-bold">Como deseja cancelar esta venda?</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="cancel-choice-card p-3 border rounded text-center h-100 cursor-pointer hover-shadow" onclick="selectCancelMode('por_chave')">
                                <div class="icon mb-2 text-danger"><i class="fas fa-file-invoice-dollar fa-2x"></i></div>
                                <div class="fw-bold small">Padrão (110111)</div>
                                <p class="extra-small text-muted mb-0 mt-1">Cancela a nota autorizada normalmente na SEFAZ.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cancel-choice-card p-3 border rounded text-center h-100 cursor-pointer hover-shadow" onclick="selectCancelMode('por_substituicao')">
                                <div class="icon mb-2 text-primary"><i class="fas fa-sync-alt fa-2x"></i></div>
                                <div class="fw-bold small">Substituição (110112)</div>
                                <p class="extra-small text-muted mb-0 mt-1">Cancela vinculando a uma nota de contingência.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cancel-choice-card p-3 border rounded text-center h-100 cursor-pointer hover-shadow" onclick="selectCancelMode('por_motivo')">
                                <div class="icon mb-2 text-secondary"><i class="fas fa-database fa-2x"></i></div>
                                <div class="fw-bold small">Apenas Sistema</div>
                                <p class="extra-small text-muted mb-0 mt-1">Cancela internamente sem comunicar a SEFAZ.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Passo 2: Formulário -->
                <div id="cancel-step-2" class="d-none">
                    <button type="button" class="btn btn-link btn-sm p-0 mb-3 text-muted text-decoration-none" onclick="backToCancelChoices()">
                        <i class="fas fa-arrow-left me-1"></i> Voltar para opções
                    </button>
                    
                    <div class="mb-3 d-none" id="field-chave-substituta">
                        <label class="form-label fw-bold small">Chave da Nota Substituta (44 dígitos)</label>
                        <input type="text" id="cancel-chave-subst" class="form-control fw-bold" maxlength="44" placeholder="0000 0000 0000 0000 0000...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small" id="label-motivo">Motivo do Cancelamento</label>
                        <textarea id="cancel-motivo" class="form-control" rows="3" placeholder="Descreva o motivo..."></textarea>
                    </div>

                    <?php if (($_SESSION['usuario_nivel'] ?? '') !== 'admin'): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-danger"><i class="fas fa-lock me-1"></i>Senha ou Código de Autorização</label>
                        <input type="text" id="cancel-auth-code" class="form-control fw-bold text-center" placeholder="Senha ou Código (Ex: 123456)" maxlength="20" style="font-size: 1.1rem;">
                    </div>
                    <?php endif; ?>

                    <div id="fiscal-alert" class="alert alert-info small d-none">
                        <i class="fas fa-info-circle me-1"></i> <b>Nota Fiscal:</b> Este modelo exige validação da SEFAZ. O motivo deve ter 15+ caracteres.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 d-none" id="cancel-footer-btns">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Fechar</button>
                <button type="button" id="confirmCancelBtn" class="btn btn-danger px-4 rounded-pill">Confirmar Cancelamento</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Exchange Flow -->
<div class="modal fade" id="modalExchangeFlow" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white border-0 shadow-sm">
                <h5 class="modal-title fw-bold text-white" style="color: #ffffff !important;"><i class="fas fa-exchange-alt me-2 text-white" style="color: #ffffff !important;"></i>Solicitação de Troca (Venda <span class="text-white" style="color: #ffffff !important;">#</span><span id="exchangeSaleId" class="text-white" style="color: #ffffff !important;"></span>)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <h6 class="fw-bold mb-3">1. Selecione o item que será DEVOLVIDO à loja</h6>
                <div class="list-group mb-4" id="exchangeItemsList">
                    <div class="text-center py-3 text-muted">Carregando itens...</div>
                </div>

                <div id="exchangeStep2" class="d-none">
                    <h6 class="fw-bold mb-3">2. Selecione o NOVO item que o cliente vai levar</h6>
                    <div class="input-group input-group-lg shadow-sm border rounded mb-2">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" id="exchangeProductSearch" class="form-control border-start-0 ps-0" placeholder="Pesquisar novo produto...">
                    </div>
                    <div id="exchangeSearchResults" class="list-group shadow-sm" style="max-height: 200px; overflow-y: auto;"></div>
                </div>
                
                <div id="exchangeStep3" class="d-none mt-4 p-4 bg-light border rounded shadow-sm">
                    <h6 class="fw-bold text-center text-primary mb-4 text-uppercase">Resumo da Troca</h6>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-danger fw-bold"><i class="fas fa-arrow-down me-2"></i>DEVOLVENDO:</span>
                        <span class="fw-bold text-end" id="exchangeOldName"></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-success fw-bold"><i class="fas fa-arrow-up me-2"></i>LEVANDO (1 UN):</span>
                        <span class="fw-bold text-end" id="exchangeNewName"></span>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-between align-items-center bg-white p-3 border rounded">
                        <span class="text-muted fw-bold">Ajuste de caixa sugerido:</span>
                        <span class="fw-bold fs-4" id="exchangeDiff"></span>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button class="btn btn-primary btn-lg fw-bold shadow-sm py-3" onclick="confirmExchange()">
                            <i class="fas fa-check-circle me-2"></i>CONFIRMAR E PROCESSAR TROCA
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Entrada Fiado -->
<div class="modal fade" id="modalEntrada" data-bs-backdrop="static" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-white border-0 shadow-sm">
                <h6 class="modal-title fw-bold text-white"><i class="fas fa-hand-holding-dollar me-2 text-white"></i>Entrada / Sinal</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="text-muted small mb-3">Deseja registrar uma <strong>entrada / sinal</strong> para esta venda fiado?</p>
                <div class="mb-3 text-start">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Valor da Entrada (R$)</label>
                    <input type="number" id="entradaValor" class="form-control form-control-lg text-center fw-bold text-success" placeholder="0,00" step="0.01" min="0">
                </div>
                <div class="mb-3 text-start">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Meio de Pagamento da Entrada</label>
                    <select id="entradaMetodo" class="form-select">
                        <option value="dinheiro">Dinheiro (Gaveta)</option>
                        <option value="pix">PIX</option>
                        <option value="cartao">Cartão</option>
                    </select>
                </div>
                <div class="d-grid">
                    <button class="btn btn-warning fw-bold py-2 shadow-sm" onclick="confirmarCheckoutFiado()">
                        FINALIZAR VENDA
                    </button>
                </div>
                <button class="btn btn-link btn-sm text-muted mt-2 text-decoration-none" onclick="document.getElementById('entradaValor').value=0; confirmarCheckoutFiado()">Continuar sem entrada</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Complemento de Cadastro (Fiado) -->
<div class="modal fade" id="modalCompleteClient" data-bs-backdrop="static" tabindex="-1" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 shadow-sm">
                <h6 class="modal-title fw-bold text-white"><i class="fas fa-user-edit me-2 text-white"></i>Completar Cadastro para Fiado</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">Para realizar vendas a prazo (Fiado), é obrigatório que o cliente possua os dados abaixo preenchidos:</p>
                
                <input type="hidden" id="edit_client_id">
                
                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">CPF ou CNPJ</label>
                    <input type="text" id="edit_client_doc" class="form-control" placeholder="000.000.000-00">
                </div>
                
                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Telefone / WhatsApp</label>
                    <input type="text" id="edit_client_phone" class="form-control" placeholder="(00) 00000-0000">
                </div>
                
                <div class="mb-4">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Endereço Completo</label>
                    <textarea id="edit_client_address" class="form-control" rows="2" placeholder="Rua, Número, Bairro, Cidade..."></textarea>
                </div>

                <div class="d-grid">
                    <button class="btn btn-primary fw-bold py-3 shadow-sm" onclick="updateClientAndContinue()">
                        SALVAR E CONTINUAR VENDA
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cadastro Rápido de Cliente -->
<div class="modal fade" id="modalQuickClient" tabindex="-1" style="z-index: 1080;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 shadow-sm">
                <h6 class="modal-title fw-bold text-white"><i class="fas fa-user-plus me-2 text-white"></i>Cadastro Rápido de Cliente</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Nome Completo / Razão Social</label>
                    <input type="text" id="qc_nome" class="form-control" placeholder="Ex: João da Silva">
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label extra-small fw-bold text-uppercase opacity-75">CPF / CNPJ</label>
                        <input type="text" id="qc_cpf_cnpj" class="form-control" placeholder="000.000.000-00">
                    </div>
                    <div class="col-6">
                        <label class="form-label extra-small fw-bold text-uppercase opacity-75">Telefone</label>
                        <input type="text" id="qc_telefone" class="form-control" placeholder="(00) 00000-0000">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Endereço (Opcional, mas recomendado)</label>
                    <input type="text" id="qc_endereco" class="form-control" placeholder="Rua, Número, Bairro...">
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary fw-bold py-3 shadow-sm" onclick="salvarQuickClient()">
                        CADASTRAR E SELECIONAR
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];
let currentPvId = null;
let currentPvCode = null;
let activeManageId = null;
let selectedCustomerId = null;
let selectedCustomerName = null;
let selectedCustomerCPF = null;
let pdvSearchIndex = -1;
let currentSearchResults = [];
const currentUserLevel = '<?= $_SESSION['usuario_id'] ? ($_SESSION['usuario_nivel'] ?? 'vendedor') : 'vendedor' ?>';

const pdvSearch = document.getElementById('pdvSearch');
const searchResults = document.getElementById('searchResults');
const cartTable = document.getElementById('cartTable').querySelector('tbody');
const cartEmptyState = document.getElementById('cartEmptyState');
const finalTotal = document.getElementById('finalTotal');
const btnCheckout = document.getElementById('btnCheckout');
const productPreviewImg = document.getElementById('productPreviewImg');
const productPreviewName = document.getElementById('productPreviewName');

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadRecentSales();
    
    // Check initial payment method
    const initialPayment = document.querySelector('input[name="payment"]:checked');
    if (initialPayment && initialPayment.value === 'dinheiro') {
        document.getElementById('cashChangeContainer').classList.remove('d-none');
    }
    
    // Payment Options Handler
    document.querySelectorAll('input[name="payment"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            const cardContainer = document.getElementById('cardTaxContainer');
            const cashContainer = document.getElementById('cashChangeContainer');
            
            // Toggle Card Tax
            if (e.target.value.includes('cartao')) {
                cardContainer.classList.remove('d-none');
                document.getElementById('taxa_cartao').focus();
            } else {
                cardContainer.classList.add('d-none');
            }

            // Toggle Cash Change Calculator
            if (e.target.value === 'dinheiro') {
                cashContainer.classList.remove('d-none');
                const valorRecebido = document.getElementById('valor_recebido');
                valorRecebido.value = '';
                setTimeout(() => valorRecebido.focus(), 100);
            } else {
                cashContainer.classList.add('d-none');
            }

            renderCart();
        });
    });

    // Enter in Valor Recebido to finalize
    const valorRecebidoInput = document.getElementById('valor_recebido');
    if (valorRecebidoInput) {
        valorRecebidoInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                calculateChange(); // Ensure calculations are fresh
                const recebido = parseFloat(valorRecebidoInput.value) || 0;
                const finalTotalText = document.getElementById('finalTotal').innerText.replace('R$ ', '').replace(/\./g, '').replace(',', '.');
                const total = parseFloat(finalTotalText) || 0;
                
                if (recebido >= total && !btnCheckout.disabled) {
                    e.preventDefault();
                    btnCheckout.click();
                } else if (recebido < total) {
                    alert("Valor recebido é insuficiente.");
                }
            }
        });
    }
});

function calculateChange() {
    const finalTotalText = document.getElementById('finalTotal').innerText.replace('R$ ', '').replace(/\./g, '').replace(',', '.');
    const total = parseFloat(finalTotalText) || 0;
    const recebido = parseFloat(document.getElementById('valor_recebido').value) || 0;
    
    const troco = Math.max(0, recebido - total);
    
    document.getElementById('troco_display').innerText = `R$ ${troco.toFixed(2).replace('.', ',')}`;
    document.getElementById('troco_input').value = troco.toFixed(2);

    if (recebido > 0 && recebido < total) {
        document.getElementById('troco_display').classList.add('text-muted');
        document.getElementById('troco_display').classList.remove('text-danger');
    } else {
        document.getElementById('troco_display').classList.remove('text-muted');
        document.getElementById('troco_display').classList.add('text-danger');
    }

    // New: Trigger button state update on change calculation
    updateCheckoutButtonState();
}

function updateCheckoutButtonState() {
    if (cart.length === 0) {
        btnCheckout.disabled = true;
        return;
    }

    const payment = document.querySelector('input[name="payment"]:checked')?.value;
    if (payment === 'dinheiro') {
        const finalTotalText = document.getElementById('finalTotal').innerText.replace('R$ ', '').replace(/\./g, '').replace(',', '.');
        const total = parseFloat(finalTotalText) || 0;
        const recebido = parseFloat(document.getElementById('valor_recebido').value) || 0;
        
        btnCheckout.disabled = (recebido < total || recebido === 0);
    } else {
        btnCheckout.disabled = false;
    }
}

// Search functionality
pdvSearch.addEventListener('input', async (e) => {
    if (isProcessingBarcode) return;
    const term = e.target.value;
    if (term.length < 2) {
        searchResults.classList.add('d-none');
        currentSearchResults = [];
        pdvSearchIndex = -1;
        return;
    }

    try {
        const response = await fetch(`vendas.php?action=search&term=${encodeURIComponent(term)}`);
        const products = await response.json();
        if (isProcessingBarcode) return; // Re-check after async
        renderSearchResults(products);
    } catch (err) {
        console.error("Erro na busca:", err);
    }
});

pdvSearch.addEventListener('keydown', (e) => {
    const isResultsVisible = !searchResults.classList.contains('d-none');
    const items = isResultsVisible ? searchResults.querySelectorAll('.list-group-item') : [];

    if (e.key === 'Enter') {
        if (items.length > 0) {
            e.preventDefault();
            if (pdvSearchIndex === -1) pdvSearchIndex = 0;
            items[pdvSearchIndex].click();
            return;
        }
        
        // If search is empty, move to payment
        if (pdvSearch.value.trim() === '' && cart.length > 0) {
            e.preventDefault();
            const payDinheiro = document.getElementById('pay_dinheiro');
            if (payDinheiro) {
                payDinheiro.checked = true;
                // Force triggering the change logic
                const changeEvent = new Event('change', { bubbles: true });
                payDinheiro.dispatchEvent(changeEvent);
                
                // Extra assurance: focus directly too
                const valorRecebido = document.getElementById('valor_recebido');
                if (valorRecebido) {
                    setTimeout(() => {
                        valorRecebido.focus();
                        valorRecebido.select();
                    }, 150);
                }
            }
            return;
        }

        // Fast barcode handling: if no results yet but has content
        const searchVal = pdvSearch.value.trim();
        if (searchVal.length >= 8 && !isNaN(searchVal)) {
            e.preventDefault();
            handleBarcode(searchVal);
        }
    } else if (e.key === 'ArrowDown') {
        if (items.length === 0) return;
        e.preventDefault();
        pdvSearchIndex = Math.min(pdvSearchIndex + 1, items.length - 1);
        highlightPdvSearchResult(items);
    } else if (e.key === 'ArrowUp') {
        if (items.length === 0) return;
        e.preventDefault();
        pdvSearchIndex = Math.max(pdvSearchIndex - 1, -1);
        highlightPdvSearchResult(items);
    } else if (e.key === 'Escape') {
        searchResults.classList.add('d-none');
        pdvSearchIndex = -1;
    }
});

function highlightPdvSearchResult(items) {
    items.forEach((item, idx) => {
        if (idx === pdvSearchIndex) {
            item.classList.add('active');
            item.scrollIntoView({ block: 'nearest' });
            // Show preview for the selected item
            if (currentSearchResults[idx]) {
                showPreview(currentSearchResults[idx]);
            }
        } else {
            item.classList.remove('active');
        }
    });

    if (pdvSearchIndex === -1) {
        productPreviewImg.innerHTML = `<i class="fas fa-image fs-1 text-muted opacity-25"></i>`;
        productPreviewName.innerText = 'Aguardando...';
    }
}

function renderSearchResults(products) {
    searchResults.innerHTML = '';
    // Safety check: ensure products is an array
    if (!Array.isArray(products)) {
        console.error("PDV Search: Expected array but received:", products);
        searchResults.classList.add('d-none');
        return;
    }
    currentSearchResults = products;
    pdvSearchIndex = -1;

    if (products.length === 0) {
        searchResults.classList.add('d-none');
        return;
    }

    products.forEach(p => {
        const item = document.createElement('button');
        item.className = 'list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3';
        
        const isPV = p.type === 'pre_sale';
        const icon = isPV ? 'fa-file-invoice-dollar text-warning' : 'fa-box text-primary';
        const badge = isPV ? '<span class="badge bg-warning text-dark extra-small ms-2">PRÉ-VENDA</span>' : '';

        item.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas ${icon} fs-4 me-3 opacity-75"></i>
                <div>
                    <div class="fw-bold ${isPV ? 'text-warning' : 'text-primary'}">${p.nome} ${badge}</div>
                    <small class="text-muted">Cód: ${p.codigo || p.id} | Un: ${p.unidade}</small>
                </div>
            </div>
            <div class="text-end">
                <div class="fw-bold">R$ ${parseFloat(p.preco_venda).toFixed(2).replace('.', ',')}</div>
                ${isPV ? '<small class="text-success extra-small fw-bold">CLIQUE PARA IMPORTAR</small>' : ''}
            </div>
        `;
        
        if (isPV) {
            item.onclick = (e) => {
                e.preventDefault();
                importPreSale(p.codigo);
                pdvSearch.value = '';
                searchResults.classList.add('d-none');
            };
        } else {
            item.onmouseover = () => showPreview(p);
            item.onclick = () => addToCart(p);
        }
        
        searchResults.appendChild(item);
    });
    searchResults.classList.remove('d-none');
}

function showPreview(p) {
    if (p.imagens) {
        productPreviewImg.innerHTML = `<img src="public/uploads/produtos/${p.imagens}" style="width:100%; height:100%; object-fit:contain; cursor:pointer;" class="fade-in" onclick="if(window.openLightbox) window.openLightbox(this.src)">`;
    } else {
        productPreviewImg.innerHTML = `<i class="fas fa-image fs-1 text-muted opacity-25"></i>`;
    }
    productPreviewName.innerText = p.nome;
}

function addToCart(product) {
    const qtyInput = document.getElementById('pdvQty');
    const qtyToAdd = parseFloat(qtyInput.value) || 1;

    const existing = cart.find(i => i.id === product.id);
    if (existing) {
        existing.qty += qtyToAdd;
    } else {
        cart.push({
            id: product.id,
            nome: product.nome,
            price: parseFloat(product.preco_venda),
            price1: parseFloat(product.preco_venda),
            price2: parseFloat(product.preco_venda_2) || 0,
            price3: parseFloat(product.preco_venda_3) || 0,
            price_tier: 1,
            qty: qtyToAdd,
            imagens: product.imagens
        });
    }
    
    pdvSearch.value = '';
    qtyInput.value = 1; // Reseta para 1 após adicionar
    searchResults.classList.add('d-none');
    searchResults.innerHTML = '';
    currentSearchResults = [];
    pdvSearchIndex = -1;
    isAuthorized = false; // Reset auth on new items
    renderCart();
}

function renderCart() {
    cartTable.innerHTML = '';
    let total = 0;

    if (cart.length === 0) {
        cartEmptyState.classList.remove('d-none');
        btnCheckout.disabled = true;
    } else {
        cartEmptyState.classList.add('d-none');
        btnCheckout.disabled = false;
    }

    cart.forEach((item, index) => {
        const subtotal = item.price * item.qty;
        total += subtotal;
        
        const row = document.createElement('tr');
        row.onmouseover = () => showPreview(item);
        row.innerHTML = `
            <td class="ps-4 fw-bold text-muted">#${item.id}</td>
            <td>
                <div>${item.nome}</div>
                <div class="mt-1">
                    <select class="form-select form-select-sm d-inline-block w-auto py-0 extra-small border-primary border-opacity-25" onchange="updatePriceTier(${index}, this.value)">
                        <option value="1" ${item.price_tier == 1 ? 'selected' : ''}>Preço 1 (R$ ${item.price1.toFixed(2).replace('.', ',')})</option>
                        <option value="2" ${item.price_tier == 2 ? 'selected' : ''}>Preço 2 (R$ ${item.price2.toFixed(2).replace('.', ',')})</option>
                        <option value="3" ${item.price_tier == 3 ? 'selected' : ''}>Preço 3 (R$ ${item.price3.toFixed(2).replace('.', ',')})</option>
                    </select>
                </div>
            </td>
            <td class="text-center">
                <input type="number" class="form-control form-control-sm text-center mx-auto" style="width: 70px" value="${item.qty}" min="1" onchange="updateQty(${index}, this.value)">
            </td>
            <td class="text-end">R$ ${item.price.toFixed(2).replace('.', ',')}</td>
            <td class="text-end fw-bold">R$ ${subtotal.toFixed(2).replace('.', ',')}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-link text-danger p-0" onclick="removeFromCart(${index})"><i class="fas fa-times"></i></button>
            </td>
        `;
        cartTable.appendChild(row);
    });

    const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
    const discountVal = total * (discountPercent / 100);
    const baseVal = total - discountVal;

    const payment = document.querySelector('input[name="payment"]:checked').value;
    const taxPercent = (payment.includes('cartao')) ? (parseFloat(document.getElementById('taxa_cartao').value) || 0) : 0;
    const taxVal = baseVal * (taxPercent / 100);
    const finalTotalVal = baseVal + taxVal;

    document.getElementById('totalSub').innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
    document.getElementById('totalDesc').innerText = `- R$ ${discountVal.toFixed(2).replace('.', ',')}`;
    
    const taxRow = document.getElementById('taxRow');
    if (taxVal > 0) {
        taxRow.classList.remove('d-none');
        document.getElementById('totalTax').innerText = `+ R$ ${taxVal.toFixed(2).replace('.', ',')}`;
    } else {
        taxRow.classList.add('d-none');
    }

    finalTotal.innerText = `R$ ${finalTotalVal.toFixed(2).replace('.', ',')}`;

    checkDiscountAuth();
    calculateChange();
    updateCheckoutButtonState();
}

function updatePriceTier(index, tier) {
    const item = cart[index];
    item.price_tier = parseInt(tier);
    if (tier == 1) item.price = item.price1;
    else if (tier == 2) item.price = item.price2;
    else if (tier == 3) item.price = item.price3;
    renderCart();
}

function updateQty(index, val) {
    cart[index].qty = Math.max(1, parseFloat(val));
    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

const customerSearch = document.getElementById('customerSearch');
const customerResults = document.getElementById('customerResults');
const selectedCustomerInfo = document.getElementById('selectedCustomerInfo');

if (customerSearch) {
    customerSearch.addEventListener('input', async (e) => {
        const term = e.target.value;
        const cleanTerm = term.replace(/\D/g, '');
        
        // Clear previous timer
        if (window.customerSearchTimer) clearTimeout(window.customerSearchTimer);

        // Auto-select 
        if (cleanTerm.length === 14) {
            selectCustomer(null, 'Consumidor Final', term);
            return;
        } else if (cleanTerm.length === 11) {
            // Wait 400ms to see if more digits are coming (CNPJ)
            window.customerSearchTimer = setTimeout(() => {
                selectCustomer(null, 'Consumidor Final', term);
            }, 400);
            return;
        }

        if (term.length < 2) {
            customerResults.style.display = 'none';
            return;
        }

        try {
            const response = await fetch(`vendas.php?action=search_clients&term=${encodeURIComponent(term)}`);
            const clients = await response.json();
            renderCustomerSearchResults(clients, term);
        } catch (err) {
            console.error("PDV: Erro ao buscar clientes:", err);
        }
    });

    customerSearch.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const term = customerSearch.value.trim();
            const cleanTerm = term.replace(/\D/g, '');
            if (cleanTerm.length === 11 || cleanTerm.length === 14) {
                if (window.customerSearchTimer) clearTimeout(window.customerSearchTimer);
                e.preventDefault();
                selectCustomer(null, 'Consumidor Final', term);
            }
        }
    });
}

function renderCustomerSearchResults(clients, term = '') {
    customerResults.innerHTML = '';
    
    // Safety check: ensure clients is an array
    if (!Array.isArray(clients)) {
        console.error("PDV Client Search: Expected array but received:", clients);
        customerResults.style.display = 'none';
        return;
    }
    
    // Check if term looks like a CPF or CNPJ
    const cleanTerm = term.replace(/\D/g, '');
    const isDoc = cleanTerm.length === 11 || cleanTerm.length === 14;

    if (isDoc) {
        const avulsoBtn = document.createElement('button');
        avulsoBtn.className = 'list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center bg-primary text-black shadow';
        avulsoBtn.innerHTML = `
            <div>
                <div class="fw-bold">IDENTIFICAR CONSUMIDOR</div>
                <small class="opacity-75">Documento: ${term}</small>
            </div>
            <i class="fas fa-id-card fa-lg"></i>
        `;
        avulsoBtn.onclick = () => {
            if (window.customerSearchTimer) clearTimeout(window.customerSearchTimer);
            selectCustomer(null, 'Consumidor Final', term);
        }
        customerResults.appendChild(avulsoBtn);
    }

    if (clients.length === 0 && !isDoc) {
        customerResults.style.display = 'none';
        return;
    }

    clients.forEach(c => {
        const item = document.createElement('button');
        item.className = 'list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center';
        item.innerHTML = `
            <div>
                <div class="fw-bold">${c.nome}</div>
                <small class="text-muted">${c.doc || 'Sem CPF/CNPJ'}</small>
            </div>
            <i class="fas fa-chevron-right text-muted small"></i>
        `;
        item.onclick = () => {
            selectCustomer(c.id, c.nome, c.doc);
        };
        customerResults.appendChild(item);
    });
    customerResults.style.display = 'block';
}

function selectCustomer(id, nome, doc) {
    selectedCustomerId = id;
    selectedCustomerName = nome;
    selectedCustomerCPF = doc;
    
    document.getElementById('customerNameDisplay').innerText = nome;
    document.getElementById('customerDocDisplay').innerText = doc || 'Sem documento';
    
    selectedCustomerInfo.classList.remove('d-none');
    customerResults.style.display = 'none';
    customerSearch.value = '';
    
    // Hide search group
    customerSearch.closest('.input-group').classList.add('d-none');
    customerSearch.closest('div.mb-4').querySelector('label').classList.add('d-none');
}

function clearCustomer() {
    selectedCustomerId = null;
    selectedCustomerName = null;
    selectedCustomerCPF = null;
    
    selectedCustomerInfo.classList.add('d-none');
    customerSearch.closest('.input-group').classList.remove('d-none');
    customerSearch.closest('div.mb-4').querySelector('label').classList.remove('d-none');
    customerSearch.value = '';
    customerSearch.focus();
}

function abrirModalQuickClient() {
    document.getElementById('qc_nome').value = '';
    document.getElementById('qc_cpf_cnpj').value = '';
    document.getElementById('qc_telefone').value = '';
    document.getElementById('qc_endereco').value = '';
    new bootstrap.Modal(document.getElementById('modalQuickClient')).show();
}

async function salvarQuickClient() {
    const nome = document.getElementById('qc_nome').value;
    const cpf_cnpj = document.getElementById('qc_cpf_cnpj').value;
    const telefone = document.getElementById('qc_telefone').value;
    const endereco = document.getElementById('qc_endereco').value;

    if (!nome) return alert('O nome é obrigatório.');

    const btn = event.currentTarget || document.querySelector('button[onclick="salvarQuickClient()"]');
    const originalText = btn?.innerHTML || 'SALVAR';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
    }

    try {
        const res = await fetch('vendas.php?action=quick_register_client', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nome, cpf_cnpj, telefone, endereco })
        });

        const result = await res.json();
        if (result.success) {
            selectCustomer(result.client_id, nome, cpf_cnpj);
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalQuickClient'));
            if (modal) modal.hide();
        } else {
            alert('Erro ao cadastrar: ' + result.error);
        }
    } catch (err) {
        alert('Erro de conexão: ' + err.message);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

// Pre-sale flow
async function loadPendingPreSales() {
    console.log("PDV: Carregando pré-vendas pendentes...");
    const term = '';
    
    try {
        const res = await fetch(`pre_vendas.php?action=list_pending&term=${encodeURIComponent(term)}`);
        if (!res.ok) throw new Error("Falha ao comunicar com pre_vendas.php");
        
        const data = await res.json();
        const list = document.getElementById('listPendingPVs');
        if (!list) return;
        
        list.innerHTML = '';

        // Handle error response from server
        if (data.error) {
            list.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">
                <i class="fas fa-exclamation-circle me-1"></i> Erro no servidor: ${data.error}
            </td></tr>`;
            return;
        }

        const pvs = Array.isArray(data) ? data : [];
        
        if (pvs.length === 0) {
            list.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Nenhuma pré-venda encontrada.</td></tr>';
        }

        pvs.forEach(pv => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="ps-4 fw-bold text-primary">${pv.codigo}</td>
                <td>${pv.cliente_nome || 'Consumidor Final'}</td>
                <td class="fw-bold">R$ ${parseFloat(pv.valor_total).toFixed(2).replace('.', ',')}</td>
                <td class="small text-muted">${pv.vendedor_nome}</td>
                <td class="text-end pe-4">
                    <button class="btn btn-sm btn-primary fw-bold" onclick="importPreSale('${pv.codigo}')">CARREGAR</button>
                </td>
            `;
            list.appendChild(row);
        });
        
        const modalEl = document.getElementById('modalPendingPV');
        if (modalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    } catch (err) {
        console.error("PDV: Erro ao carregar pré-vendas:", err);
        alert("Erro ao carregar pré-vendas. Verifique o console.");
    }
}

async function importPreSale(code) {
    const res = await fetch(`pre_vendas.php?action=get_by_code&code=${code}`);
    const pv = await res.json();
    
    if (pv) {
        cart = pv.itens.map(i => ({
            id: i.produto_id,
            nome: i.produto_nome,
            price: parseFloat(i.preco_unitario),
            price1: parseFloat(i.preco_venda || 0),
            price2: parseFloat(i.preco_venda_2 || 0),
            price3: parseFloat(i.preco_venda_3 || 0),
            price_tier: parseInt(i.preco_tier || 1),
            qty: parseFloat(i.quantidade),
            imagens: i.imagens
        }));
        
        currentPvId = pv.id;
        currentPvCode = pv.codigo;
        
        // Auto-select customer if present in pre-sale
        if (pv.cliente_id) {
            selectCustomer(pv.cliente_id, pv.cliente_nome, pv.cliente_doc || '');
        } else if (pv.nome_cliente_avulso) {
            // If it's a walk-in name, we can just set the name for the record but not a DB ID
            selectedCustomerId = null;
            selectedCustomerName = pv.nome_cliente_avulso;
            selectedCustomerCPF = pv.cliente_doc || null;
            
            // UI Update for walk-in
            const customerInfo = document.getElementById('selectedCustomerInfo');
            const customerNameDisplay = document.getElementById('customerNameDisplay'); // Fixed ID reference
            const customerDocDisplay = document.getElementById('customerDocDisplay');   // Fixed ID reference
            if (customerInfo && customerNameDisplay) {
                customerNameDisplay.innerText = pv.nome_cliente_avulso;
                customerDocDisplay.innerText = selectedCustomerCPF || 'Consumidor Avulso';
                customerInfo.classList.remove('d-none');
                customerSearch.closest('.input-group').classList.add('d-none');
                customerSearch.closest('div.mb-4').querySelector('label').classList.add('d-none');
            }
        } else {
            clearCustomer();
        }
        
        renderCart();
        
        const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalPendingPV'));
        if (modalInstance) modalInstance.hide();
        
        pdvSearch.focus();
    }
}

async function saveCurrentSaleAsPreSale() {
    if (cart.length === 0) {
        alert("O carrinho está vazio.");
        return;
    }

    const btn = document.getElementById('btnPauseSale');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';

    const subtotal = cart.reduce((acc, i) => acc + (i.price * i.qty), 0);
    const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
    const total = subtotal * (1 - (discountPercent / 100));

    const data = {
        id: currentPvId,
        codigo: currentPvCode,
        cliente_id: selectedCustomerId,
        nome_cliente_avulso: selectedCustomerId ? null : selectedCustomerName,
        cpf_cliente: selectedCustomerCPF,
        valor_total: total,
        items: cart
    };

    try {
        const res = await fetch('pre_vendas.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await res.json();
        if (result.success) {
            alert(`Venda guardada com sucesso!\nCódigo: ${result.codigo}`);
            
            // Clear current sale
            cart = [];
            currentPvId = null;
            currentPvCode = null;
            clearCustomer();
            document.getElementById('discountPercent').value = 0;
            renderCart();
            pdvSearch.focus();
        } else {
            alert("Erro ao guardar venda: " + result.error);
        }
    } catch (err) {
        alert("Erro de conexão ao guardar venda.");
        console.error(err);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Recent Sales (History)
async function loadRecentSales() {
    const res = await fetch('vendas.php?action=list_recent');
    const data = await res.json();
    const list = document.getElementById('recentSalesList');
    list.innerHTML = '';
    
    if (data.sales.length === 0) {
        list.innerHTML = '<div class="text-center py-2 opacity-50">Nenhuma venda recente</div>';
        return;
    }

    data.sales.forEach(sale => {
        const item = document.createElement('div');
        item.className = 'd-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded border cursor-pointer';
        item.style.cursor = 'pointer';
        item.onclick = () => manageSale(sale);
        item.innerHTML = `
            <div style="font-size: 0.75rem;">
                <div class="fw-bold">Venda #${sale.id}</div>
                <div class="text-muted">${sale.cliente_nome || 'Consumidor'}</div>
            </div>
            <div class="text-end">
                <div class="fw-bold text-primary">R$ ${parseFloat(sale.valor_total).toFixed(2).replace('.', ',')}</div>
                <div class="extra-small ${sale.status === 'cancelado' ? 'text-danger' : 'text-success'}">${sale.status.toUpperCase()}</div>
            </div>
        `;
        list.appendChild(item);
    });
}

function manageSale(sale) {
    activeManageId = sale.id;
    document.getElementById('manageSaleId').innerText = sale.id;
    document.getElementById('manageSaleCustomer').innerText = sale.cliente_nome || 'Consumidor Final';
    document.getElementById('manageSaleTotal').innerText = 'R$ ' + parseFloat(sale.valor_total).toFixed(2).replace('.', ',');
    new bootstrap.Modal(document.getElementById('modalSaleManager')).show();
}

let currentCancelModelo = 'por_chave';

function selectCancelMode(mode) {
    currentCancelModelo = mode;
    document.getElementById('cancel-step-1').classList.add('d-none');
    document.getElementById('cancel-step-2').classList.remove('d-none');
    document.getElementById('cancel-footer-btns').classList.remove('d-none');
    
    const fieldSubst = document.getElementById('field-chave-substituta');
    const alertFiscal = document.getElementById('fiscal-alert');
    const labelMotivo = document.getElementById('label-motivo');
    
    if (mode === 'por_substituicao') {
        fieldSubst.classList.remove('d-none');
        alertFiscal.classList.remove('d-none');
        labelMotivo.textContent = 'Motivo (Substituição)';
    } else if (mode === 'por_chave') {
        fieldSubst.classList.add('d-none');
        alertFiscal.classList.remove('d-none');
        labelMotivo.textContent = 'Motivo do Cancelamento';
    } else {
        fieldSubst.classList.add('d-none');
        alertFiscal.classList.add('d-none');
        labelMotivo.textContent = 'Motivo do Cancelamento (Interno)';
    }
}

function backToCancelChoices() {
    document.getElementById('cancel-step-1').classList.remove('d-none');
    document.getElementById('cancel-step-2').classList.add('d-none');
    document.getElementById('cancel-footer-btns').classList.add('d-none');
}

async function cancelSaleAction() {
    document.getElementById('cancel-id-label').textContent = activeManageId;
    document.getElementById('cancel-motivo').value = '';
    document.getElementById('cancel-chave-subst').value = '';
    
    const authInput = document.getElementById('cancel-auth-code');
    if (authInput) authInput.value = '';
    
    backToCancelChoices();
    bootstrap.Modal.getInstance(document.getElementById('modalSaleManager')).hide();
    bootstrap.Modal.getOrCreateInstance('#modalTripleCancel').show();
}

// Handler for the confirm button inside the triple cancel modal
document.addEventListener('DOMContentLoaded', () => {
    const confirmBtn = document.getElementById('confirmCancelBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', async function() {
            const motivo = document.getElementById('cancel-motivo').value.trim();
            const chaveSubst = document.getElementById('cancel-chave-subst').value.replace(/\D+/g, '');
            const authCodeEl = document.getElementById('cancel-auth-code');
            const authCode = authCodeEl ? authCodeEl.value.trim() : null;
            
            if (authCodeEl && !authCode) {
                alert('É necessário inserir o Código de Autorização fornecido pelo administrador.');
                return;
            }
            
            if (currentCancelModelo === 'por_substituicao' && chaveSubst.length !== 44) {
                alert('A chave substituta deve ter 44 dígitos.');
                return;
            }

            if (currentCancelModelo !== 'por_motivo' && motivo.length < 15) {
                alert('Para cancelamentos na SEFAZ, o motivo deve ter no mínimo 15 caracteres.');
                return;
            } else if (motivo.length < 5) {
                alert('Por favor, descreva o motivo do cancelamento.');
                return;
            }

            this.disabled = true;
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';

            try {
                const res = await fetch('vendas.php?action=cancel_sale', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        id: activeManageId, 
                        motivo, 
                        modelo: currentCancelModelo,
                        chave_substituta: chaveSubst,
                        auth_code: authCode 
                    })
                });
                const data = await res.json();
                if (data.success) {
                    bootstrap.Modal.getOrCreateInstance('#modalTripleCancel').hide();
                    alert('Cancelamento processado com sucesso!');
                    loadRecentSales();
                } else {
                    alert('Erro: ' + data.error);
                }
            } catch (err) {
                alert('Erro de conexão ao cancelar venda.');
            } finally {
                this.disabled = false;
                this.innerHTML = originalText;
            }
        });
    }
});

// --- LOGICA DE TROCA DE ITENS ---
let exchangeState = {
    vendaId: null,
    oldItemId: null,
    oldItemName: null,
    oldItemPrice: 0,
    newProductId: null,
    newProductName: null,
    newProductPrice: 0
};

async function openExchangeFlow() {
    exchangeState.vendaId = activeManageId;
    document.getElementById('exchangeSaleId').innerText = activeManageId;
    
    bootstrap.Modal.getInstance(document.getElementById('modalSaleManager')).hide();
    new bootstrap.Modal(document.getElementById('modalExchangeFlow')).show();
    
    document.getElementById('exchangeStep2').classList.add('d-none');
    document.getElementById('exchangeStep3').classList.add('d-none');
    document.getElementById('exchangeProductSearch').value = '';
    document.getElementById('exchangeSearchResults').innerHTML = '';
    
    const res = await fetch(`vendas.php?action=get_sale_detail&id=${activeManageId}`);
    const data = await res.json();
    
    const list = document.getElementById('exchangeItemsList');
    list.innerHTML = '';
    
    if (!data.success || !data.sale || !data.sale.itens || data.sale.itens.length === 0) {
        list.innerHTML = '<div class="alert alert-warning text-center">Nenhum item encontrado nesta venda.</div>';
        return;
    }
    
    if(data.sale.status === 'cancelado') {
        list.innerHTML = '<div class="alert alert-danger text-center">Não é possível realizar troca em venda cancelada.</div>';
        return;
    }
    
    data.sale.itens.forEach(item => {
        const btn = document.createElement('button');
        btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3';
        btn.innerHTML = `
            <div>
                <div class="fw-bold">${item.produto_nome}</div>
                <small class="opacity-75">${item.quantidade}x R$ ${item.preco_formatado}</small>
            </div>
            <span class="btn btn-sm btn-outline-danger fw-bold px-3">DEVOLVER</span>
        `;
        btn.onclick = () => {
            Array.from(list.children).forEach(c => {
                c.classList.remove('active', 'bg-danger', 'text-white', 'border-danger');
                c.querySelector('.btn')?.classList.replace('btn-light', 'btn-outline-danger');
            });
            
            btn.classList.add('active', 'bg-danger', 'text-white', 'border-danger');
            btn.querySelector('.btn').classList.replace('btn-outline-danger', 'btn-light');
            
            exchangeState.oldItemId = item.id;
            exchangeState.oldItemName = item.produto_nome;
            // The item price here is unitario because we swap 1 unit at a time physically in this flow
            exchangeState.oldItemPrice = parseFloat(item.preco_unitario); 
            
            document.getElementById('exchangeStep2').classList.remove('d-none');
            document.getElementById('exchangeStep3').classList.add('d-none');
            
            setTimeout(() => document.getElementById('exchangeProductSearch').focus(), 300);
        };
        list.appendChild(btn);
    });
}

document.getElementById('exchangeProductSearch').addEventListener('input', async (e) => {
    const term = e.target.value;
    const resultsDiv = document.getElementById('exchangeSearchResults');
    if (term.length < 2) {
        resultsDiv.innerHTML = '';
        return;
    }

    const res = await fetch(`vendas.php?action=search&term=${encodeURIComponent(term)}`);
    const products = await res.json();
    
    resultsDiv.innerHTML = '';
    products.forEach(p => {
        if (p.type === 'pre_sale') return;
        
        const btn = document.createElement('button');
        btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3';
        btn.innerHTML = `
            <div>
                <div class="fw-bold text-primary">${p.nome}</div>
                <small class="text-muted">Valor Unitário: R$ ${parseFloat(p.preco_venda).toFixed(2).replace('.', ',')}</small>
            </div>
            <i class="fas fa-check text-success fa-lg opacity-50"></i>
        `;
        btn.onclick = () => {
            exchangeState.newProductId = p.id;
            exchangeState.newProductName = p.nome;
            exchangeState.newProductPrice = parseFloat(p.preco_venda);
            
            document.getElementById('exchangeOldName').innerText = exchangeState.oldItemName;
            document.getElementById('exchangeNewName').innerText = exchangeState.newProductName;
            
            const diff = exchangeState.newProductPrice - exchangeState.oldItemPrice;
            const diffEl = document.getElementById('exchangeDiff');
            if (diff > 0) {
                diffEl.innerHTML = `<span class="text-success"><i class="fas fa-plus me-1"></i>RECEBER R$ ${diff.toFixed(2).replace('.', ',')}</span>`;
            } else if (diff < 0) {
                diffEl.innerHTML = `<span class="text-danger"><i class="fas fa-minus me-1"></i>DEVOLVER R$ ${Math.abs(diff).toFixed(2).replace('.', ',')}</span>`;
            } else {
                diffEl.innerHTML = `<span class="text-secondary">R$ 0,00 (Tudo Certo)</span>`;
            }
            
            document.getElementById('exchangeStep3').classList.remove('d-none');
            resultsDiv.innerHTML = '';
            document.getElementById('exchangeProductSearch').value = '';
            
            setTimeout(() => document.getElementById('exchangeStep3').scrollIntoView({behavior: 'smooth'}), 200);
        };
        resultsDiv.appendChild(btn);
    });
});

async function confirmExchange() {
    if (!exchangeState.vendaId || !exchangeState.oldItemId || !exchangeState.newProductId) {
        return alert("Por favor, selecione qual item será devolvido e qual produto será pego no lugar.");
    }
    
    if (!confirm('Deseja realmente confirmar esta troca?\n\nIsso fará o ajuste automático no estoque (dando entrada no defeituoso/antigo e baixando o novo) e registrará as devidas diferenças financeiras.')) return;
    
    const res = await fetch('vendas.php?action=exchange_item', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            venda_id: exchangeState.vendaId,
            item_id: exchangeState.oldItemId,
            new_product_id: exchangeState.newProductId,
            new_qty: 1, // Currently fixed at 1 unit exchanged at a time for safety
            new_price: exchangeState.newProductPrice
        })
    });
    
    const result = await res.json();
    if (result.success) {
        alert("S-U-C-E-S-S-O! A Troca foi registrada com sucesso, os estoques foram atualizados e o caixa ajustado.");
        bootstrap.Modal.getInstance(document.getElementById('modalExchangeFlow')).hide();
        loadRecentSales(); // Reload the history UI
    } else {
        alert("Vish! Erro ao tentar processar troca: " + result.error);
    }
}

async function interceptDiscount(e) {
    if (currentUserLevel === 'admin' || isAuthorized) return;
    
    e.preventDefault();
    e.stopPropagation();
    if (e.target) e.target.blur();
    
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDiscountAuth'));
    modal.show();
    
    await loadAdmins();
}

let isAuthorized = false;
let authSupervisorId = null;
let authSupervisorCredential = null;
let authAdmins = [];

async function checkDiscountAuth() {
    const discount = parseFloat(document.getElementById('discountPercent').value) || 0;
    
    // Admins don't need authorization modal for themselves
    if (currentUserLevel === 'admin') {
        isAuthorized = true;
        btnCheckout.disabled = cart.length === 0;
        return;
    }

    if (discount > 0 && !isAuthorized) {
        await loadAdmins();
        new bootstrap.Modal(document.getElementById('modalDiscountAuth')).show();
        btnCheckout.disabled = true;
    } else {
        btnCheckout.disabled = cart.length === 0;
    }
}

async function loadAdmins() {
    const res = await fetch('vendas.php?action=list_admins');
    authAdmins = await res.json();
    
    if (authAdmins.length > 0) {
        const admin = authAdmins[0]; // Auto-select the first admin
        authSupervisorId = admin.id;
        
        const input = document.getElementById('authCredential');
        const label = document.getElementById('authLabel');
        
        input.type = 'text'; // Allow both password (text) and numeric codes
        input.placeholder = 'Senha ou Código (Ex: 123456)';
        label.innerText = 'SENHA OU CÓDIGO DE AUTORIZAÇÃO';
    }
}

async function validateAuthorization() {
    const credential = document.getElementById('authCredential').value;

    if (!authSupervisorId || !credential) {
        alert('Credenciais incompletas ou nenhum administrador encontrado.');
        return;
    }

    const res = await fetch('vendas.php?action=authorize_discount', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: authSupervisorId, credential: credential })
    });

    const result = await res.json();
    if (result.success) {
        isAuthorized = true;
        authSupervisorCredential = credential;
        bootstrap.Modal.getInstance(document.getElementById('modalDiscountAuth')).hide();
        renderCart();
        
        // Focus and select the discount field so the user can type immediately
        const discountInput = document.getElementById('discountPercent');
        setTimeout(() => {
            discountInput.focus();
            discountInput.select();
        }, 500);
        
        alert('Desconto autorizado com sucesso!');
    } else {
        alert('Erro: ' + result.error);
    }
}

function resetDiscount() {
    if (!isAuthorized) {
        document.getElementById('discountPercent').value = 0;
        renderCart();
    }
}

// Checkout
btnCheckout.onclick = async () => {
    if (cart.length === 0) return;
    
    const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
    
    if (discountPercent > 0 && !isAuthorized && currentUserLevel !== 'admin') {
        alert('Esta venda contém um desconto não autorizado. Por favor, autorize primeiro.');
        await loadAdmins();
        new bootstrap.Modal(document.getElementById('modalDiscountAuth')).show();
        return;
    }

    const payment = document.querySelector('input[name="payment"]:checked').value;
    
    if (payment === 'fiado') {
        if (!selectedCustomerId) {
            alert('Vendas a prazo (Fiado) exigem a seleção de um cliente cadastrado.');
            customerSearch.focus();
            return;
        }

        // Validation: Completeness for Fiado
        try {
            const res = await fetch(`vendas.php?action=check_client_completeness&id=${selectedCustomerId}`);
            const data = await res.json();
            
            if (!data.is_complete) {
                // Show completion modal
                document.getElementById('edit_client_id').value = selectedCustomerId;
                document.getElementById('edit_client_doc').value = data.client.cpf_cnpj || '';
                document.getElementById('edit_client_phone').value = data.client.telefone || '';
                document.getElementById('edit_client_address').value = data.client.endereco || '';
                
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCompleteClient')).show();
                return;
            }
        } catch (err) {
            console.error("Erro validando cliente:", err);
        }

        // Proceed to entry modal if complete
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEntrada'));
        document.getElementById('entradaValor').value = '';
        modal.show();
        setTimeout(() => document.getElementById('entradaValor').focus(), 500);
    } else {
        processarCheckout();
    }
};

async function updateClientAndContinue() {
    const id = document.getElementById('edit_client_id').value;
    const doc = document.getElementById('edit_client_doc').value;
    const phone = document.getElementById('edit_client_phone').value;
    const address = document.getElementById('edit_client_address').value;

    if (!doc || !phone || !address) {
        alert('Por favor, preencha todos os campos obrigatórios para o Fiado.');
        return;
    }

    try {
        const res = await fetch('vendas.php?action=update_client_quick', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, cpf_cnpj: doc, telefone: phone, endereco: address })
        });
        
        const result = await res.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalCompleteClient')).hide();
            // Now show the entry modal
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEntrada'));
            document.getElementById('entradaValor').value = '';
            modal.show();
            setTimeout(() => document.getElementById('entradaValor').focus(), 500);
        } else {
            alert('Erro ao atualizar cliente: ' + result.error);
        }
    } catch (err) {
        alert('Erro de conexão: ' + err.message);
    }
}

async function confirmarCheckoutFiado() {
    processarCheckout();
}

async function processarCheckout() {
    const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
    const subtotal = cart.reduce((acc, i) => acc + (i.price * i.qty), 0);
    const baseVal = subtotal * (1 - (discountPercent / 100));
    const payment = document.querySelector('input[name="payment"]:checked').value;
    const taxaCartaoPercent = (payment.includes('cartao')) ? (parseFloat(document.getElementById('taxa_cartao').value) || 0) : 0;
    const total = baseVal + (baseVal * (taxaCartaoPercent / 100));
    const taxaCartao = parseFloat(document.getElementById('taxa_cartao').value) || 0;

    if (payment === 'cartao_credito') {
        if (!taxaCartao || taxaCartao <= 0) {
            alert("Para pagamentos em CRÉDITO, é obrigatório informar a taxa da maquininha.");
            document.getElementById('taxa_cartao').focus();
            return;
        }
    }

    const entrada = parseFloat(document.getElementById('entradaValor')?.value) || 0;
    const entradaMetodo = document.getElementById('entradaMetodo')?.value || 'dinheiro';

    // Troco / valor recebido (only relevant for dinheiro)
    let valorRecebido = null;
    let troco = 0;
    if (payment === 'dinheiro') {
        const valorRecebidoEl = document.getElementById('valor_recebido');
        valorRecebido = valorRecebidoEl ? (parseFloat(valorRecebidoEl.value) || total) : total;
        if (valorRecebido < total) valorRecebido = total; // ensure at least total
        troco = parseFloat(document.getElementById('troco_input').value) || (valorRecebido - total);
    }

    if (payment === 'fiado' && entrada >= total) {
        alert('O valor da entrada não pode ser maior ou igual ao total da venda a prazo. Se o cliente vai pagar tudo agora, selecione outro método de pagamento.');
        return;
    }

    const tipoNota = document.querySelector('input[name="tipo_nota"]:checked')?.value || 'nao_fiscal';

    const data = {
        subtotal: subtotal,
        discount_percent: discountPercent,
        total: total,
        items: cart,
        pagamento: payment,
        entrada_valor: entrada,
        entrada_metodo: entradaMetodo,
        valor_recebido: valorRecebido,
        troco: troco,
        cliente_id: selectedCustomerId,
        nome_cliente_avulso: selectedCustomerId ? null : selectedCustomerName,
        cpf_cliente: selectedCustomerCPF,
        pv_id: currentPvId,
        taxa_cartao: taxaCartao,
        supervisor_id: authSupervisorId,
        supervisor_credential: authSupervisorCredential,
        tipo_nota: tipoNota
    };

    const res = await fetch('vendas.php?action=checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });

    const result = await res.json();
    if (result.success) {
        // Close modals if open
        const modalEntrada = bootstrap.Modal.getInstance(document.getElementById('modalEntrada'));
        if (modalEntrada) modalEntrada.hide();

        showSuccessModal(result.sale_id, data.total, result.tipo_nota || data.tipo_nota, troco, valorRecebido);
        cart = [];
        currentPvId = null;
        isAuthorized = false;
        authSupervisorId = null;
        authSupervisorCredential = null;
        document.getElementById('discountPercent').value = 0;
        if (document.getElementById('entradaValor')) document.getElementById('entradaValor').value = 0;
        document.getElementById('taxa_cartao').value = '';
        renderCart();
        loadRecentSales();
    } else {
        alert('Erro ao finalizar: ' + result.error);
    }
}

function showSuccessModal(saleId, total, tipoNota, troco = 0, valorRecebido = null) {
    const isFiscal = tipoNota === 'fiscal';
    const isContingencia = tipoNota === 'contingencia';
    const isOffline = String(saleId).startsWith('OFF-');

    let tipoLabel;
    if (isContingencia) {
        tipoLabel = '<span class="badge bg-warning text-dark mb-3"><i class="fas fa-exclamation-triangle me-1"></i>Venda em Contingência (Offline)</span>';
    } else if (isFiscal) {
        tipoLabel = '<span class="badge bg-success mb-3"><i class="fas fa-file-invoice-dollar me-1"></i>Venda Fiscal</span>';
    } else if (isOffline) {
        tipoLabel = '<span class="badge bg-info mb-3"><i class="fas fa-wifi-slash me-1"></i>Venda Offline (Sincronizará Automaticamente)</span>';
    } else {
        tipoLabel = '<span class="badge bg-secondary mb-3"><i class="fas fa-receipt me-1"></i>Venda Não Fiscal</span>';
    }

    // Troco block (Açaidinhos style - show prominently in green for dinheiro)
    const trocoBlock = (troco > 0)
        ? `<div class="alert alert-success py-2 px-3 mb-3 d-flex justify-content-between align-items-center">
               <span class="fw-bold"><i class="fas fa-coins me-1"></i>TROCO</span>
               <span class="fw-bold fs-4">R$ ${troco.toFixed(2).replace('.', ',')}</span>
           </div>`
        : '';

    let btnPrint;
    if (isOffline || isContingencia) {
        // Offline: não pode imprimir nem emitir NFC-e até sincronizar
        btnPrint = `<div class="alert alert-info small mb-2 text-start">
            <i class="fas fa-info-circle me-1"></i>
            ${isContingencia 
                ? 'Venda fiscal registrada em <strong>modo contingência</strong>. A NFC-e será emitida automaticamente quando a internet voltar.'
                : 'Venda registrada <strong>offline</strong>. O recibo estará disponível após a sincronização automática.'}
        </div>
        <button class="btn btn-outline-secondary btn-lg fw-bold py-3 shadow-sm" disabled>
            <i class="fas fa-clock me-2"></i>AGUARDANDO SINCRONIZAÇÃO
        </button>`;
    } else if (isFiscal) {
        btnPrint = `<button class="btn btn-success btn-lg fw-bold py-3 shadow-sm" id="btnNFCeModal" onclick="issueNFCe(${saleId})">
               <i class="fas fa-file-invoice-dollar me-2"></i>EMITIR NFC-e (Nota Fiscal)
           </button>`;
    } else {
        btnPrint = `<button class="btn btn-primary btn-lg fw-bold py-3 shadow-sm" onclick="imprimirRecibo(${saleId})">
               <i class="fas fa-print me-2"></i>IMPRIMIR RECIBO
           </button>`;
    }

    const saleIdDisplay = isOffline ? saleId : `#${saleId}`;

    const modalHtml = `
        <div class="modal fade" id="modalSuccess" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-body text-center p-5">
                        <div class="mb-3">
                            <i class="fas fa-check-circle ${isOffline || isContingencia ? 'text-warning' : 'text-success'}" style="font-size: 4.5rem;"></i>
                        </div>
                        ${tipoLabel}
                        <h3 class="fw-bold mb-2">Venda Finalizada!</h3>
                        <p class="text-muted mb-3">Venda <strong>${saleIdDisplay}</strong> — <strong>R$ ${total.toFixed(2).replace('.', ',')}</strong></p>
                        ${trocoBlock}
                        <div class="d-grid gap-2">
                            ${btnPrint}
                            <button class="btn btn-link text-muted mt-1" data-bs-dismiss="modal">Fechar e Nova Venda (ESC)</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    const existing = document.getElementById('modalSuccess');
    if (existing) existing.remove();
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Auto-clear customer to ensure UI is reset and add reload on close 
    clearCustomer();
    
    const modalEl = document.getElementById('modalSuccess');
    const modal = new bootstrap.Modal(modalEl);
    modalEl.addEventListener('hidden.bs.modal', () => {
        location.reload();
    });
    modal.show();

    // For non-fiscal online: auto-open print window
    if (!isFiscal && !isOffline && !isContingencia) {
        setTimeout(() => imprimirRecibo(saleId), 400);
    }
}

function imprimirRecibo(saleId) {
    let iframe = document.getElementById('print-iframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'print-iframe';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = 'none';
        iframe.style.visibility = 'hidden';
        document.body.appendChild(iframe);
    }
    // Adiciona timestamp para evitar cache e forçar recarregamento/disparo do print
    iframe.src = 'recibo_venda.php?id=' + saleId + '&t=' + Date.now();
}

async function issueNFCe(saleId) {
    const btn = document.getElementById('btnNFCeModal') || event.currentTarget;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Aberta central de emissão SEFAZ...';

    // Open emitir.php directly which handles the Sefaz XML+SOAP and then redirects to the final DANFE.
    // This replicates the original robust behaviour fully.
    const url = `nfce/emitir.php?venda_id=${saleId}`;
    window.open(url, '_blank', 'width=800,height=900,toolbar=0,menubar=0,location=0');
    
    setTimeout(() => {
        btn.innerHTML = '<i class="fas fa-check me-2"></i>Emitindo em nova janela...';
        btn.className = 'btn btn-outline-success btn-lg fw-bold py-3 w-100';
    }, 1500);
}

// Keyboard Hotkeys
document.addEventListener('keydown', (e) => {
    // F2: Checkout
    if (e.key === 'F2') {
        e.preventDefault();
        btnCheckout.click();
    }
    // F4: Search
    if (e.key === 'F4') {
        e.preventDefault();
        pdvSearch.focus();
    }
    // F9: Pre-sales (Changed from F8)
    if (e.key === 'F9') {
        e.preventDefault();
        loadPendingPreSales();
    }
    // F10: Pause sale (Changed from F7)
    if (e.key === 'F10') {
        e.preventDefault();
        saveCurrentSaleAsPreSale();
    }

    // Payment Shortcuts
    if (e.key === 'F1') {
        e.preventDefault();
        document.getElementById('pay_dinheiro').click();
    }
    if (e.key === 'F6') {
        e.preventDefault();
        document.getElementById('pay_credito').click();
    }
    if (e.key === 'F7') {
        e.preventDefault();
        document.getElementById('pay_debito').click();
    }
    if (e.key === 'F8') {
        e.preventDefault();
        document.getElementById('pay_fiado').click();
    }
    if (e.key === 'F11') {
        e.preventDefault();
        document.getElementById('pay_boleto').click();
    }
    
    // Pix: Ctrl+F1
    if (e.key === 'F1' && e.ctrlKey) {
        e.preventDefault();
        document.getElementById('pay_pix').click();
    }

    // Discount: Ctrl+F12
    if (e.key === 'F12' && e.ctrlKey) {
        e.preventDefault();
        const discountInput = document.getElementById('discountPercent');
        discountInput.focus();
        discountInput.select();
    }

    if (e.key === 'Escape') {
        searchResults.classList.add('d-none');
    }
});

// Barcode optimization
let isProcessingBarcode = false;
async function handleBarcode(val) {
    if (isProcessingBarcode) return;
    if (val.length >= 8 && !isNaN(val)) {
        isProcessingBarcode = true;
        try {
            const response = await fetch(`vendas.php?action=search&term=${encodeURIComponent(val)}`);
            const products = await response.json();
            if (products.length === 1) {
                addToCart(products[0]);
                pdvSearch.value = '';
            }
        } finally {
            isProcessingBarcode = false;
        }
    }
}

pdvSearch.addEventListener('keyup', (e) => {
    if (e.key === 'Enter') {
        // keydown already handles most cases, but if search was empty and keyup fires, 
        // handleBarcode will just return because value is empty or isProcessingBarcode is true.
        handleBarcode(pdvSearch.value);
    }
    
    // Quick shortcut: if user types '*' in the search box, move focus to quantity
    if (e.key === '*') {
        pdvSearch.value = pdvSearch.value.replace('*', '');
        document.getElementById('pdvQty').focus();
        document.getElementById('pdvQty').select();
    }
});

// If user presses Enter in Qty, focus search
document.getElementById('pdvQty').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        pdvSearch.focus();
    }
});

// Missing intercepter to prevent default browser behavior for numeric/percentage inputs
function interceptDiscount(e) {
    // Only prevents scroll or other unwanted interactions if needed, but here ensures focus
    if (e.type === 'focus') e.target.select();
}
</script>

<style>
    .cancel-choice-card {
        transition: all 0.2s ease;
        border-width: 2px !important;
        cursor: pointer;
    }
    .cancel-choice-card:hover {
        border-color: var(--erp-primary) !important;
        background-color: #f8faff;
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .extra-small { font-size: 0.75rem; }
    .uppercase { text-transform: uppercase; }
</style>
