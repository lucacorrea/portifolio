<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="fw-bold mb-0"><?= $pageTitle ?></h2>
            <p class="text-muted small mb-0">Gestão de abertura, fechamento e movimentações de caixa por operador.</p>
        </div>
        <div class="col-auto">
            <?php if (!$caixaAberto): ?>
                <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalAbrirCaixa">
                    <i class="fas fa-unlock me-2"></i>Abrir Caixa
                </button>
            <?php else: ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-warning fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#modalMovimentacao">
                        <i class="fas fa-exchange-alt me-2"></i>Sangria / Suprimento
                    </button>
                    <button type="button" class="btn btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#modalFecharCaixa">
                        <i class="fas fa-lock me-2"></i>Fechar Caixa
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($caixaAberto): ?>
    <!-- Resumo do Caixa Aberto -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase mb-2">Abertura</div>
                    <h4 class="mb-0 fw-bold"><?= formatarMoeda($caixaAberto['valor_abertura']) ?></h4>
                    <div class="text-info small mt-2">
                        <i class="fas fa-clock me-1"></i><?= date('H:i', strtotime($caixaAberto['data_abertura'])) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase mb-2">Vendas (Dinheiro)</div>
                    <h4 class="mb-0 fw-bold text-success">+ <?= formatarMoeda($summary['vendas_dinheiro']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase mb-2">Movimentações</div>
                    <div class="d-flex justify-content-between">
                        <span class="text-success small">+ Sup: <?= formatarMoeda($summary['suprimentos']) ?></span>
                        <span class="text-danger small">- San: <?= formatarMoeda($summary['sangrias']) ?></span>
                    </div>
                    <h4 class="mb-0 fw-bold mt-1"><?= formatarMoeda($summary['suprimentos'] - $summary['sangrias']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary border-0 shadow h-100 bg-primary text-white">
                <div class="card-body">
                    <div class="text-white-50 small fw-bold text-uppercase mb-2">Saldo Atual (Dinheiro)</div>
                    <h3 class="mb-0 fw-bold">
                        <?= formatarMoeda($caixaAberto['valor_abertura'] + $summary['vendas_dinheiro'] + $summary['suprimentos'] - $summary['sangrias']) ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Histórico de Caixas -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold text-secondary">Histórico de Sessões</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Operador</th>
                            <th>Data/Hora Abertura</th>
                            <th>Data/Hora Fechamento</th>
                            <th>Status</th>
                            <th>Valor Abertura</th>
                            <th>Valor Fechamento</th>
                            <th class="pe-4 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($caixas as $c): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="fw-bold"><?= $c['operador_nome'] ?? 'Operador' ?></span>
                                <div class="text-muted small">Filial #<?= $c['filial_id'] ?></div>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($c['data_abertura'])) ?></td>
                            <td><?= $c['data_fechamento'] ? date('d/m/Y H:i', strtotime($c['data_fechamento'])) : '-' ?></td>
                            <td>
                                <span class="badge bg-<?= $c['status'] == 'aberto' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $c['status'] == 'aberto' ? 'success' : 'secondary' ?> rounded-pill">
                                    <?= strtoupper($c['status']) ?>
                                </span>
                            </td>
                            <td><?= formatarMoeda($c['valor_abertura']) ?></td>
                            <td><?= $c['valor_fechamento'] ? formatarMoeda($c['valor_fechamento']) : '-' ?></td>
                            <td class="pe-4 text-end">
                                <button class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Abrir Caixa -->
<div class="modal fade" id="modalAbrirCaixa" tabindex="-1">
    <div class="modal-dialog">
        <form action="caixa.php?action=abrir" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Abrir Novo Caixa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle me-2"></i>Informe o valor inicial disponível no fundo de caixa.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Valor de Abertura (R$)</label>
                    <input type="number" step="0.01" value="0.00" name="valor_abertura" class="form-control form-control-lg text-center fw-bold" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold">Abrir Caixa</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Movimentação -->
<?php if ($caixaAberto): ?>
<div class="modal fade" id="modalMovimentacao" tabindex="-1">
    <div class="modal-dialog">
        <form action="caixa.php?action=movimentar" method="POST" class="modal-content">
            <input type="hidden" name="caixa_id" value="<?= $caixaAberto['id'] ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Sangria ou Suprimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Tipo de Movimentação</label>
                    <select name="tipo" class="form-select" required>
                        <option value="suprimento">Suprimento (Entrada de valor)</option>
                        <option value="sangria">Sangria (Retirada de valor)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Valor (R$)</label>
                    <input type="number" step="0.01" name="valor" class="form-control form-control-lg text-center fw-bold" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Motivo / Descrição</label>
                    <input type="text" name="motivo" class="form-control" placeholder="Ex: Adição de troco, Retirada para almoço..." required>
                </div>

                <?php if (!in_array($_SESSION['usuario_nivel'] ?? '', ['admin', 'master'])): ?>
                <div class="card bg-light border-0 mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-shield-alt me-2"></i>Autorização Administrativa</h6>
                        <p class="extra-small text-muted mb-3">Esta operação exige liberação de um Administrador.</p>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Código de Uso Único</label>
                            <input type="text" name="auth_code" class="form-control form-control-sm text-center fw-bold" placeholder="000000">
                            <div class="form-text extra-small text-center mt-1">Obtido na tela de login via "Gerar Código"</div>
                        </div>
                        
                        <div class="text-center my-2">
                             <span class="badge bg-secondary extra-small">OU</span>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small fw-bold">Senha do Administrador</label>
                            <input type="password" name="auth_password" class="form-control form-control-sm text-center" placeholder="••••••••">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning fw-bold text-dark">Registrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Fechar Caixa -->
<div class="modal fade" id="modalFecharCaixa" tabindex="-1">
    <div class="modal-dialog">
        <form action="caixa.php?action=fechar" method="POST" class="modal-content">
            <input type="hidden" name="caixa_id" value="<?= $caixaAberto['id'] ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Fechamento de Caixa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="bg-light p-3 rounded">
                            <div class="text-muted small">Saldo Sistema</div>
                            <div class="fw-bold">
                                <?php 
                                    $totalSistema = $caixaAberto['valor_abertura'] + $summary['vendas_dinheiro'] + $summary['suprimentos'] - $summary['sangrias'];
                                    echo formatarMoeda($totalSistema);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Valor Físico em Caixa (R$)</label>
                    <input type="number" step="0.01" name="valor_fechamento" class="form-control form-control-lg text-center fw-bold" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Observações / Justificativa</label>
                    <textarea name="justificativa" class="form-control" rows="3" placeholder="Obrigatório em caso de divergência..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger fw-bold">Confirmar Fechamento</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
