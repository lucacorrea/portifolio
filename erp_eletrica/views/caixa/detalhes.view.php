<?php
    $caixa = $details['caixa'];
    $summary = $details['summary'];
    $vendas = $details['vendas'];
    $fiadosPagamentos = $details['fiados_pagamentos'];
    $movimentacoes = $details['movimentacoes'];
    $isFechado = $caixa['status'] === 'fechado';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4 align-items-center">
        <div class="col">
            <a href="caixa.php" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="fas fa-arrow-left me-2"></i>Voltar ao Controle de Caixa
            </a>
            <div class="d-flex align-items-center gap-3">
                <h2 class="fw-bold mb-0">Sessão #<?= $caixa['id'] ?></h2>
                <span class="badge bg-<?= $isFechado ? 'secondary' : 'success' ?> bg-opacity-10 text-<?= $isFechado ? 'secondary' : 'success' ?> rounded-pill fs-6">
                    <?= strtoupper($caixa['status']) ?>
                </span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                <i class="fas fa-user me-1"></i><?= htmlspecialchars($caixa['operador_nome'] ?? 'Operador') ?>
                &nbsp;·&nbsp;
                <i class="fas fa-store me-1"></i>Filial #<?= $caixa['filial_id'] ?>
                &nbsp;·&nbsp;
                <i class="fas fa-calendar me-1"></i><?= date('d/m/Y H:i', strtotime($caixa['data_abertura'])) ?>
                <?php if ($isFechado): ?>
                    até <?= date('d/m/Y H:i', strtotime($caixa['data_fechamento'])) ?>
                <?php else: ?>
                    <span class="text-success fw-bold">— Em andamento</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-4">
        <div class="col">
            <div class="card border-primary border-0 h-100 bg-primary text-black shadow-lg" style="transform: scale(1.02);">
                <div class="card-body">
                    <div class="text-white-50 small fw-bold text-uppercase mb-2"><i class="fas fa-wallet me-2"></i>Saldo Gaveta</div>
                    <h3 class="mb-0 fw-bold text-white"><?= formatarMoeda($caixa['valor_abertura'] + $summary['dinheiro_em_gaveta']) ?></h3>
                    <div class="text-white-50 extra-small mt-2 fw-bold">Abertura: <?= formatarMoeda($caixa['valor_abertura']) ?></div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 h-100 shadow-sm bg-secondary text-white">
                <div class="card-body">
                    <div class="text-white-50 small fw-bold text-uppercase mb-2"><i class="fas fa-chart-line me-2"></i>Vendido (Total)</div>
                    <h4 class="mb-0 fw-bold text-white"><?= formatarMoeda($summary['total_bruto']) ?></h4>
                    <div class="text-white-50 extra-small mt-2 fw-bold"><?= count($vendas) ?> venda(s) registrada(s)</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase mb-2"><i class="fas fa-money-bill-wave me-2"></i>Físico</div>
                    <h5 class="mb-0 fw-bold text-success">+ <?= formatarMoeda($summary['vendas_dinheiro']) ?></h5>
                    <div class="text-muted extra-small mt-2">Dinheiro + Sinal</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 h-100 shadow-sm bg-light">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase mb-2"><i class="fab fa-pix text-primary me-2"></i>Digitais</div>
                    <h5 class="mb-0 fw-bold text-primary">+ <?= formatarMoeda($summary['vendas_pix'] + $summary['vendas_cartao'] + $summary['vendas_boleto']) ?></h5>
                    <div class="text-muted extra-small mt-2">Pix, Cartões e Boleto</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de Suprimento/Sangria -->
    <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
        <div class="col">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;min-width:48px;">
                        <i class="fas fa-arrow-down text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Suprimentos</div>
                        <h5 class="mb-0 fw-bold text-success">+ <?= formatarMoeda($summary['suprimentos']) ?></h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;min-width:48px;">
                        <i class="fas fa-arrow-up text-danger"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Sangrias</div>
                        <h5 class="mb-0 fw-bold text-danger">- <?= formatarMoeda($summary['sangrias']) ?></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fechamento (se fechado) -->
    <?php if ($isFechado): ?>
    <?php
        $totalSistema = $caixa['valor_abertura'] + $summary['dinheiro_em_gaveta'];
        $diferenca = ($caixa['valor_fechamento'] ?? 0) - $totalSistema;
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-dark py-3">
            <h6 class="mb-0 fw-bold text-white"><i class="fas fa-lock me-2"></i>Informações de Fechamento</h6>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-3 text-center">
                    <div class="text-muted small fw-bold text-uppercase mb-1">Valor Sistema</div>
                    <h5 class="fw-bold"><?= formatarMoeda($totalSistema) ?></h5>
                </div>
                <div class="col-md-3 text-center">
                    <div class="text-muted small fw-bold text-uppercase mb-1">Valor Informado</div>
                    <h5 class="fw-bold"><?= formatarMoeda($caixa['valor_fechamento'] ?? 0) ?></h5>
                </div>
                <div class="col-md-3 text-center">
                    <div class="text-muted small fw-bold text-uppercase mb-1">Diferença</div>
                    <h5 class="fw-bold <?= $diferenca == 0 ? 'text-success' : ($diferenca > 0 ? 'text-primary' : 'text-danger') ?>">
                        <?= $diferenca >= 0 ? '+' : '' ?><?= formatarMoeda($diferenca) ?>
                    </h5>
                </div>
                <div class="col-md-3 text-center">
                    <div class="text-muted small fw-bold text-uppercase mb-1">Situação</div>
                    <?php if ($diferenca == 0): ?>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i>Conferido</span>
                    <?php elseif ($diferenca > 0): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2"><i class="fas fa-plus-circle me-1"></i>Sobra</span>
                    <?php else: ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2"><i class="fas fa-exclamation-triangle me-1"></i>Falta</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($caixa['observacao'])): ?>
            <div class="mt-3 p-3 bg-light rounded">
                <div class="text-muted small fw-bold mb-1"><i class="fas fa-comment me-1"></i>Observação:</div>
                <p class="mb-0 small"><?= htmlspecialchars($caixa['observacao']) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabela de Vendas -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-shopping-cart me-2"></i>Vendas Realizadas</h6>
            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill"><?= count($vendas) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($vendas)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-2x mb-3 opacity-50"></i>
                    <p class="mb-0">Nenhuma venda registrada nesta sessão.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Cliente</th>
                            <th>Vendedor</th>
                            <th>Forma Pagamento</th>
                            <th class="text-end">Valor</th>
                            <th class="pe-4 text-end">Data/Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendas as $v): ?>
                        <tr>
                            <td class="ps-4"><span class="fw-bold text-muted"><?= $v['id'] ?></span></td>
                            <td><?= htmlspecialchars($v['cliente_nome'] ?? 'Consumidor') ?></td>
                            <td class="small"><?= htmlspecialchars($v['vendedor_nome'] ?? '-') ?></td>
                            <td>
                                <?php
                                    $formaIcons = [
                                        'dinheiro' => ['icon' => 'money-bill-wave', 'color' => 'success'],
                                        'pix' => ['icon' => 'bolt', 'color' => 'primary'],
                                        'cartao_credito' => ['icon' => 'credit-card', 'color' => 'info'],
                                        'cartao_debito' => ['icon' => 'credit-card', 'color' => 'info'],
                                        'cartao' => ['icon' => 'credit-card', 'color' => 'info'],
                                        'boleto' => ['icon' => 'barcode', 'color' => 'warning'],
                                        'fiado' => ['icon' => 'hand-holding-usd', 'color' => 'danger'],
                                    ];
                                    $forma = strtolower($v['forma_pagamento'] ?? '');
                                    $fi = $formaIcons[$forma] ?? ['icon' => 'receipt', 'color' => 'secondary'];
                                ?>
                                <span class="badge bg-<?= $fi['color'] ?> bg-opacity-10 text-<?= $fi['color'] ?> rounded-pill">
                                    <i class="fas fa-<?= $fi['icon'] ?> me-1"></i><?= ucfirst(str_replace('_', ' ', $v['forma_pagamento'] ?? '-')) ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold"><?= formatarMoeda($v['valor_total']) ?></td>
                            <td class="pe-4 text-end small text-muted"><?= date('d/m/Y H:i', strtotime($v['data_venda'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-light">
                        <tr>
                            <td colspan="4" class="ps-4 fw-bold text-end">Total Vendas:</td>
                            <td class="text-end fw-bold"><?= formatarMoeda(array_sum(array_column($vendas, 'valor_total'))) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabela de Recebimentos de Fiados -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-hand-holding-usd me-2"></i>Recebimentos de Fiados</h6>
            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill"><?= count($fiadosPagamentos) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($fiadosPagamentos)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-2x mb-3 opacity-50"></i>
                    <p class="mb-0">Nenhum recebimento de fiado nesta sessão.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Cliente</th>
                            <th>Método</th>
                            <th class="text-end">Valor Recebido</th>
                            <th class="pe-4 text-end">Data/Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fiadosPagamentos as $fp): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= htmlspecialchars($fp['cliente_nome'] ?? 'Não identificado') ?></td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">
                                    <?= ucfirst($fp['metodo'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold text-success">+ <?= formatarMoeda($fp['valor']) ?></td>
                            <td class="pe-4 text-end small text-muted"><?= date('d/m/Y H:i', strtotime($fp['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-light">
                        <tr>
                            <td colspan="2" class="ps-4 fw-bold text-end">Total Recebido:</td>
                            <td class="text-end fw-bold text-success">+ <?= formatarMoeda(array_sum(array_column($fiadosPagamentos, 'valor'))) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabela de Movimentações -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-exchange-alt me-2"></i>Movimentações da Gaveta</h6>
            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill"><?= count($movimentacoes) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($movimentacoes)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-2x mb-3 opacity-50"></i>
                    <p class="mb-0">Nenhuma movimentação registrada nesta sessão.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Tipo</th>
                            <th>Operador</th>
                            <th>Motivo</th>
                            <th class="text-end">Valor</th>
                            <th class="pe-4 text-end">Data/Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimentacoes as $m): ?>
                        <tr>
                            <td class="ps-4">
                                <?php if (in_array($m['tipo'], ['suprimento', 'entrada'])): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill">
                                        <i class="fas fa-arrow-down me-1"></i><?= ucfirst($m['tipo']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill">
                                        <i class="fas fa-arrow-up me-1"></i><?= ucfirst($m['tipo']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= htmlspecialchars($m['operador_nome'] ?? '-') ?></td>
                            <td class="small"><?= htmlspecialchars($m['motivo'] ?? '-') ?></td>
                            <td class="text-end fw-bold <?= in_array($m['tipo'], ['suprimento', 'entrada']) ? 'text-success' : 'text-danger' ?>">
                                <?= in_array($m['tipo'], ['suprimento', 'entrada']) ? '+' : '-' ?> <?= formatarMoeda($m['valor']) ?>
                            </td>
                            <td class="pe-4 text-end small text-muted"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
