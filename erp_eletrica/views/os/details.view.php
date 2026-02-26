<div class="row g-4">
    <div class="col-lg-8">
        <!-- Status & Scope -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold d-block mb-1">Status do Workflow</span>
                        <h4 class="mb-0 fw-bold" style="color: <?= getStatusColor($os['status']) ?>">
                            <i class="fas fa-circle-play me-2"></i><?= strtoupper(str_replace('_', ' ', $os['status'])) ?>
                        </h4>
                    </div>
                    <button class="btn btn-primary px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalStatus">
                        <i class="fas fa-edit me-2"></i>Alterar Estágio
                    </button>
                </div>

                <div class="bg-light rounded-3 p-3 mb-4 border-start border-4 border-primary">
                    <label class="text-muted extra-small fw-bold text-uppercase mb-1 d-block">Descrição da Solicitação</label>
                    <p class="mb-0 text-dark"><?= nl2br($os['descricao']) ?></p>
                </div>

                <h6 class="fw-bold text-secondary mb-3 mt-4">Materiais e Insumos Aplicados</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Item / Descrição</th>
                                <th class="text-center">Qtd</th>
                                <th>UN</th>
                                <th>Unitário</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($os['itens'] as $item): ?>
                            <tr>
                                <td class="fw-medium text-dark"><?= $item['produto_nome'] ?: 'Manual: ' . $item['descricao'] ?></td>
                                <td class="text-center"><?= $item['quantidade'] ?></td>
                                <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= $item['unidade'] ?></span></td>
                                <td><?= formatarMoeda($item['valor_unitario']) ?></td>
                                <td class="text-end fw-bold"><?= formatarMoeda($item['subtotal']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="4" class="text-end fw-bold">TOTAL GERAL:</td>
                                <td class="text-end fw-bold text-primary fs-5"><?= formatarMoeda($os['valor_total']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Customer Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-secondary">Dados do Requisitante</h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 me-3">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?= $os['cliente_nome'] ?></div>
                        <div class="text-muted small"><?= $os['cliente_fone'] ?></div>
                    </div>
                </div>
                <div class="text-muted small mb-1 border-top pt-3">E-mail Corporativo</div>
                <div class="fw-medium text-dark"><?= $os['cliente_email'] ?></div>
            </div>
        </div>

        <!-- Technical Area -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-secondary">Responsável Técnico</h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-info bg-opacity-10 text-info rounded-circle p-3 me-3">
                        <i class="fas fa-user-gear"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?= $os['tecnico_nome'] ?: 'Não atribuído' ?></div>
                        <div class="text-muted small">Técnico Encarregado</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Status Change -->
<div class="modal fade" id="modalStatus" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Atualizar Estágio da OS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="os.php?action=save" method="POST">
                <input type="hidden" name="id" value="<?= $os['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Novo Status</label>
                        <select name="status" class="form-select border-0 bg-light">
                            <option value="orcamento" <?= $os['status'] == 'orcamento' ? 'selected' : '' ?>>Orçamento</option>
                            <option value="aprovado" <?= $os['status'] == 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                            <option value="em_andamento" <?= $os['status'] == 'em_andamento' ? 'selected' : '' ?>>Em Execução</option>
                            <option value="aguardando_peca" <?= $os['status'] == 'aguardando_peca' ? 'selected' : '' ?>>Aguardando Peça</option>
                            <option value="concluido" <?= $os['status'] == 'concluido' ? 'selected' : '' ?>>Concluído</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold small text-uppercase">Nota de Observação (Opcional)</label>
                        <textarea name="obs_status" class="form-control border-0 bg-light" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">Processar Mudança</button>
                </div>
            </form>
        </div>
    </div>
</div>
