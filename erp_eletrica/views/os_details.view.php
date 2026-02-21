<div class="row g-4">
    <!-- OS Main Data -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary"></i>Informações da OS #<?= $os['numero_os'] ?></h6>
                <?php 
                    $statusMap = [
                        'pendente' => 'warning',
                        'em_execucao' => 'primary',
                        'concluido' => 'success',
                        'cancelado' => 'danger',
                        'aguardando_pecas' => 'info'
                    ];
                    $color = $statusMap[$os['status']] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> border border-<?= $color ?> border-opacity-25 rounded-pill px-3">
                    <?= strtoupper(str_replace('_', ' ', $os['status'])) ?>
                </span>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 mb-4">
                    <div class="col-md-6 border-end">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-2">Cliente / Solicitante</label>
                        <div class="d-flex align-items-start">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px; flex-shrink: 0;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-5"><?= $os['cliente_nome'] ?></div>
                                <div class="small text-muted mb-1"><i class="fas fa-phone me-1"></i> <?= $os['cliente_fone'] ?></div>
                                <div class="small text-muted"><i class="fas fa-envelope me-1"></i> <?= $os['cliente_email'] ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-2">Cronograma de Atendimento</label>
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-2 d-flex justify-content-between">
                                <span class="text-muted">Abertura:</span>
                                <span class="fw-bold"><?= formatarData($os['data_abertura']) ?></span>
                            </li>
                            <li class="mb-2 d-flex justify-content-between">
                                <span class="text-muted">Previsão:</span>
                                <span class="fw-bold text-primary"><?= formatarData($os['data_previsao']) ?? 'A definir' ?></span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">Conclusão:</span>
                                <span class="fw-bold"><?= $os['data_conclusao'] ? formatarData($os['data_conclusao']) : 'Pendente' ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="bg-light p-3 rounded-3 border-start border-4 border-primary">
                    <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Descrição do Problema / Serviço</label>
                    <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($os['descricao'])) ?></p>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-tools me-2 text-secondary"></i>Peças e Insumos Vinculados</h6>
                <button class="btn btn-sm btn-outline-primary fw-bold px-3">
                    <i class="fas fa-plus me-1"></i>Adicionar Item
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Item / Material</th>
                                <th class="text-center">Qtd</th>
                                <th class="text-end">Unitário</th>
                                <th class="text-end pe-4">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($os['itens'] as $item): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?= $item['produto_nome'] ?></div>
                                    <div class="extra-small text-muted">REF: #<?= $item['produto_id'] ?></div>
                                </td>
                                <td class="text-center"><?= $item['quantidade'] ?></td>
                                <td class="text-end text-muted"><?= formatarMoeda($item['valor_unitario']) ?></td>
                                <td class="text-end pe-4 fw-bold"><?= formatarMoeda($item['subtotal']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($os['itens'])): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted opacity-50">Nenhum material vinculado até o momento.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-light">
                            <tr class="fw-bold fs-6">
                                <td colspan="3" class="text-end pe-3 py-3">TOTAL MATERIAIS:</td>
                                <td class="text-end pe-4 py-3 text-success"><?= formatarMoeda($os['valor_total'] ?? 0) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- OS Actions Sidebar -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-secondary text-white py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-tasks me-2"></i>Fluxo de Trabalho</h6>
            </div>
            <div class="card-body p-4 d-grid gap-2">
                <button class="btn btn-primary text-start fw-bold py-3 px-4 shadow-sm" style="background-color: #3498db; border: none;">
                    <i class="fas fa-check-circle me-3"></i>Aprovar Orçamento
                </button>
                <button class="btn btn-primary text-start fw-bold py-3 px-4 shadow-sm" style="background-color: #e67e22; border: none;">
                    <i class="fas fa-wrench me-3"></i>Iniciar Execução
                </button>
                <button class="btn btn-success text-start fw-bold py-3 px-4 shadow-sm">
                    <i class="fas fa-flag-checkered me-3"></i>Concluir Serviço
                </button>
                <hr class="my-2">
                <button class="btn btn-outline-danger text-start fw-bold py-3 px-4 border-dashed">
                    <i class="fas fa-times-circle me-3"></i>Cancelar Ordem
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 small text-muted text-uppercase">Documentação</h6>
                <div class="row g-2">
                    <div class="col-6">
                        <button class="btn btn-white border w-100 fw-bold small py-3 shadow-sm">
                            <i class="fas fa-file-pdf d-block mb-2 fs-4 text-danger"></i>
                            Imprimir OS
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-white border w-100 fw-bold small py-3 shadow-sm">
                            <i class="fas fa-receipt d-block mb-2 fs-4 text-warning"></i>
                            Geral Recibo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
