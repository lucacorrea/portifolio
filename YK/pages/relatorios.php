<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

function report_decimal(mixed $value): string
{
    $normalized = trim((string) $value);
    if ($normalized === '' || preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) !== 1) return '0,00';
    $negative = str_starts_with($normalized, '-');
    $unsigned = ltrim($normalized, '-');
    [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
    $integer = ltrim($integer, '0');
    $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
    return ($negative ? '-' : '') . number_format((int) ($integer === '' ? '0' : $integer), 0, ',', '.') . ',' . $fraction;
}

function report_money(mixed $value): string
{
    return 'R$ ' . report_decimal($value);
}

function report_percent(mixed $value): string
{
    return report_decimal($value) . '%';
}

function report_quantity(mixed $value): string
{
    $formatted = number_format((float) $value, 3, ',', '.');
    return rtrim(rtrim($formatted, '0'), ',');
}

function report_date_time(mixed $value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
}

function report_payment_status(mixed $value): string
{
    return [
        'pendente' => 'Pendente',
        'parcial' => 'Parcial',
        'vencida' => 'Vencida',
        'paga' => 'Paga',
        'cancelada' => 'Cancelada',
    ][(string) $value] ?? 'Pendente';
}

$requestedCompetence = trim((string) ($_GET['competencia'] ?? date('Y-m')));
$competence = preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $requestedCompetence) === 1
    ? $requestedCompetence
    : date('Y-m');
$canViewCommission = $authorization->can('relatorio.comissao.visualizar');
$canViewEmployees = $authorization->can('relatorio.funcionarios')
    || $authorization->can('relatorio.produtividade')
    || $canViewCommission;
$canViewOperations = $authorization->can('relatorio.operacional');
$canViewFinancial = $authorization->can('relatorio.financeiro');
$canViewStock = $authorization->can('relatorio.estoque');
$canViewStockCost = $canViewStock && $authorization->can('produto.visualizar_preco_custo');
$canViewCompany = $canViewOperations || $canViewFinancial || $canViewStock;
$canConfigureGoal = $authorization->can('relatorio.meta_comissao.configurar');
$canLoadReport = $canViewCompany || $canViewEmployees || $canConfigureGoal;
$report = null;
$loadError = null;

if ($canLoadReport) {
    try {
        $report = $application->reports()->monthlyReport($competence, [
            'operational' => $canViewOperations,
            'financial' => $canViewFinancial,
            'stock' => $canViewStock,
            'stock_cost' => $canViewStockCost,
            'employees' => $canViewEmployees,
            'commission' => $canViewCommission,
            'goal' => $canConfigureGoal,
        ]);
    } catch (Throwable $exception) {
        error_log('Monthly report load failed: ' . $exception->getMessage());
        $loadError = 'Não foi possível carregar o relatório deste mês. Tente novamente.';
    }
}

$goal = is_array($report['goal'] ?? null) ? $report['goal'] : [];
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$financial = is_array($report['financial'] ?? null) ? $report['financial'] : [];
$inventory = is_array($report['inventory'] ?? null) ? $report['inventory'] : [];
$companyDetails = is_array($report['company_details'] ?? null) ? $report['company_details'] : [];
$serviceRanking = is_array($report['service_ranking'] ?? null) ? $report['service_ranking'] : [];
$productRanking = is_array($report['product_ranking'] ?? null) ? $report['product_ranking'] : [];
$employees = is_array($report['employees'] ?? null) ? $report['employees'] : [];
$details = is_array($report['details'] ?? null) ? $report['details'] : [];
$periodLabel = trim((string) ($report['period_label'] ?? '')) ?: $competence;
$goalConfigured = (bool) ($goal['configured'] ?? false);
$goalAmount = (string) ($goal['amount'] ?? '0.00');
$prizePercentage = (string) ($goal['percentage'] ?? '0.00');
$employeeOrderCount = count(array_unique(array_filter(array_column($details, 'order_number'))));
$allowedViews = [];
if ($canViewCompany) $allowedViews[] = 'empresa';
if ($canViewEmployees) $allowedViews[] = 'funcionarios';
$requestedView = is_array($_GET['visao'] ?? null) ? '' : trim((string) ($_GET['visao'] ?? ''));
$activeView = in_array($requestedView, $allowedViews, true) ? $requestedView : ($allowedViews[0] ?? '');
?>

<div class="page-body reports-page">
    <section class="panel mb-4">
        <div class="panel-header"><div class="panel-title"><i class="bi bi-calendar3"></i>Período do relatório</div></div>
        <form class="filter-bar" method="get" action="relatorios.php">
            <input type="hidden" id="report-active-view" name="visao" value="<?= h($activeView) ?>">
            <div class="form-group mb-0">
                <label class="form-label" for="report-competence">Mês de referência</label>
                <input class="filter-select input-date" id="report-competence" type="month" name="competencia" value="<?= h($competence) ?>" required>
            </div>
            <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Atualizar</button>

        </form>
    </section>

    <?php if ($loadError !== null): ?>
        <div class="alert alert-danger" role="alert"><?= h($loadError) ?></div>
    <?php elseif (!$canViewCompany && !$canViewEmployees): ?>
        <?php empty_state('Visualização de relatório indisponível', $canConfigureGoal ? 'Você pode configurar a meta, mas não possui permissão para visualizar os relatórios.' : 'Solicite uma permissão de relatório ao administrador.'); ?>
    <?php elseif ($report !== null): ?>
        <?php if (count($allowedViews) > 1): ?>
            <nav class="mb-4" aria-label="Escolha do relatório">
                <div class="nav nav-tabs visual-tabs" role="tablist" aria-label="Tipo de relatório">
                    <a class="nav-link<?= $activeView === 'empresa' ? ' active' : '' ?>" id="report-tab-company" href="relatorios.php?competencia=<?= h(rawurlencode($competence)) ?>&amp;visao=empresa" role="tab" aria-controls="report-panel-company" aria-selected="<?= $activeView === 'empresa' ? 'true' : 'false' ?>" data-report-tab="empresa"><i class="bi bi-building me-1"></i> Visão da empresa</a>
                    <a class="nav-link<?= $activeView === 'funcionarios' ? ' active' : '' ?>" id="report-tab-employees" href="relatorios.php?competencia=<?= h(rawurlencode($competence)) ?>&amp;visao=funcionarios" role="tab" aria-controls="report-panel-employees" aria-selected="<?= $activeView === 'funcionarios' ? 'true' : 'false' ?>" data-report-tab="funcionarios"><i class="bi bi-people me-1"></i> Por funcionário</a>
                </div>
            </nav>
        <?php endif; ?>

        <?php if ($canViewCompany): ?>
            <section id="report-panel-company" role="<?= count($allowedViews) > 1 ? 'tabpanel' : 'region' ?>" <?= count($allowedViews) > 1 ? 'aria-labelledby="report-tab-company"' : 'aria-label="Relatório da empresa"' ?> tabindex="0" data-report-panel="empresa"<?= $activeView === 'empresa' ? '' : ' hidden' ?>>
                <div class="alert alert-info" role="note"><i class="bi bi-info-circle me-1"></i>Produção usa a data de finalização da OS. Caixa usa a data real da movimentação. Os saldos a receber mostram a posição atual das OS finalizadas no período.</div>
                <?php
                $companyCards = [];
                if ($canViewOperations || $canViewFinancial) {
                    $companyCards[] = ['OS finalizadas', (string) ($summary['orders'] ?? 0), 'bi-check2-circle', '#16A34A', $periodLabel];
                    $companyCards[] = ['Clientes atendidos', (string) ($summary['clients'] ?? 0), 'bi-person-check', '#2563EB', 'clientes distintos'];
                }
                if ($canViewFinancial) {
                    $companyCards[] = ['Faturamento das OS', report_money($summary['company_total'] ?? '0.00'), 'bi-graph-up-arrow', '#7C3AED', 'regime de competência'];
                    $companyCards[] = ['Ticket médio', report_money($summary['average_ticket'] ?? '0.00'), 'bi-receipt', '#0EA5E9', 'por OS finalizada'];
                    $companyCards[] = ['Já recebido dessas OS', report_money($summary['received_total'] ?? '0.00'), 'bi-cash-stack', '#15803D', 'posição atual da carteira'];
                    $companyCards[] = ['Saldo das OS', report_money($summary['receivable_balance'] ?? '0.00'), 'bi-hourglass-split', '#D97706', 'a receber atualmente'];
                }
                if ($canViewStock) {
                    $companyCards[] = ['Produtos ativos', (string) ($inventory['active_products'] ?? 0), 'bi-box-seam', '#475569', 'cadastro atual'];
                    $companyCards[] = ['Estoque crítico', (string) (((int) ($inventory['low_stock'] ?? 0)) + ((int) ($inventory['out_of_stock'] ?? 0))), 'bi-exclamation-triangle', '#DC2626', 'baixo ou zerado'];
                }
                metric_grid($companyCards);
                ?>

                <?php if ($canViewFinancial): ?>
                    <section class="panel mb-4">
                        <div class="panel-header"><div class="panel-title"><i class="bi bi-pie-chart"></i>Composição das OS</div></div>
                        <div class="p-3 summary-box">
                            <div><span>Serviços</span><strong><?= h(report_money($summary['service_total'] ?? '0.00')) ?></strong></div>
                            <div><span>Peças e produtos</span><strong><?= h(report_money($summary['product_total'] ?? '0.00')) ?></strong></div>
                            <div><span>Outros</span><strong><?= h(report_money($summary['other_total'] ?? '0.00')) ?></strong></div>
                            <div><span>Descontos</span><strong><?= h(report_money($summary['discount_total'] ?? '0.00')) ?></strong></div>
                            <div><span>Acréscimos</span><strong><?= h(report_money($summary['increase_total'] ?? '0.00')) ?></strong></div>
                            <div class="total"><span>Total executado</span><strong><?= h(report_money($summary['company_total'] ?? '0.00')) ?></strong></div>
                        </div>
                    </section>

                    <section class="panel mb-4">
                        <div class="panel-header"><div><div class="panel-title"><i class="bi bi-arrow-left-right"></i>Fluxo financeiro de <?= h($periodLabel) ?></div><small class="text-muted">Valores líquidos de estornos no regime de caixa</small></div></div>
                        <div class="p-3 summary-box">
                            <div><span>Entradas no Caixa</span><strong><?= h(report_money($financial['cash_in'] ?? '0.00')) ?></strong></div>
                            <div><span>Saídas do Caixa</span><strong><?= h(report_money($financial['cash_out'] ?? '0.00')) ?></strong></div>
                            <div class="total"><span>Saldo líquido</span><strong><?= h(report_money($financial['cash_net'] ?? '0.00')) ?></strong></div>
                            <div><span>Despesas pagas</span><strong><?= h(report_money($financial['paid_expenses'] ?? '0.00')) ?></strong><small><?= h((string) ($financial['paid_installments'] ?? 0)) ?> parcela(s)</small></div>
                            <div><span>Vendas de peças no Caixa</span><strong><?= h(report_money($financial['sales_total'] ?? '0.00')) ?></strong><small><?= h((string) ($financial['sales'] ?? 0)) ?> venda(s) líquida(s) de estornos</small></div>
                            <div><span>OS quitadas / com saldo</span><strong><?= h((string) ($summary['paid_orders'] ?? 0)) ?> / <?= h((string) ($summary['open_orders'] ?? 0)) ?></strong></div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($canViewStock): ?>
                    <section class="panel mb-4">
                        <div class="panel-header"><div class="panel-title"><i class="bi bi-boxes"></i>Posição atual do estoque</div></div>
                        <div class="p-3 summary-box">
                            <div><span>Produtos ativos</span><strong><?= h((string) ($inventory['active_products'] ?? 0)) ?></strong></div>
                            <div><span>Estoque baixo</span><strong><?= h((string) ($inventory['low_stock'] ?? 0)) ?></strong></div>
                            <div><span>Sem estoque</span><strong><?= h((string) ($inventory['out_of_stock'] ?? 0)) ?></strong></div>
                            <?php if ($canViewStockCost): ?><div><span>Valor atual ao custo cadastrado</span><strong><?= h(report_money($inventory['stock_cost_value'] ?? '0.00')) ?></strong></div><?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (($canViewOperations && $serviceRanking !== []) || ($canViewStock && $productRanking !== [])): ?>
                    <div class="row g-4 mb-4">
                        <?php if ($canViewOperations && $serviceRanking !== []): ?>
                            <div class="col-12 col-xl-6"><section class="panel h-100"><div class="panel-header"><div class="panel-title"><i class="bi bi-tools"></i>Serviços mais executados</div></div><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Serviço</th><th>OS</th><th>Quantidade</th><?php if ($canViewFinancial): ?><th>Valor</th><?php endif; ?></tr></thead><tbody><?php foreach ($serviceRanking as $item): ?><tr><td><strong><?= h((string) ($item['description'] ?? '—')) ?></strong></td><td><?= h((string) ($item['orders'] ?? 0)) ?></td><td><?= h(report_quantity($item['quantity'] ?? 0)) ?> <?= h((string) ($item['unit'] ?? '')) ?></td><?php if ($canViewFinancial): ?><td><?= h(report_money($item['total'] ?? '0.00')) ?></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></section></div>
                        <?php endif; ?>
                        <?php if ($canViewStock && $productRanking !== []): ?>
                            <div class="col-12 col-xl-6"><section class="panel h-100"><div class="panel-header"><div class="panel-title"><i class="bi bi-box-seam"></i>Peças mais utilizadas</div></div><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Peça / produto</th><th>OS</th><th>Quantidade</th><?php if ($canViewFinancial): ?><th>Valor</th><?php endif; ?></tr></thead><tbody><?php foreach ($productRanking as $item): ?><tr><td><strong><?= h((string) ($item['description'] ?? '—')) ?></strong></td><td><?= h((string) ($item['orders'] ?? 0)) ?></td><td><?= h(report_quantity($item['quantity'] ?? 0)) ?> <?= h((string) ($item['unit'] ?? '')) ?></td><?php if ($canViewFinancial): ?><td><?= h(report_money($item['total'] ?? '0.00')) ?></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></section></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canViewOperations || $canViewFinancial): ?>
                    <section class="panel">
                        <div class="panel-header"><div><div class="panel-title"><i class="bi bi-list-check"></i>OS que compõem o relatório da empresa</div><small class="text-muted">Uma linha por OS, limitado às 200 finalizações mais recentes do mês</small></div></div>
                        <?php if ($companyDetails === []): ?>
                            <?php empty_state('Sem detalhamento', 'Nenhuma OS finalizada válida foi encontrada para este mês.'); ?>
                        <?php else: ?>
                            <div class="table-panel-wrap"><table class="os-table"><thead><tr><th>OS</th><th>Cliente</th><th>Finalização</th><?php if ($canViewFinancial): ?><th>Serviços</th><th>Peças</th><th>Outros</th><th>Desconto</th><th>Acréscimo</th><th>Total</th><th>Recebido</th><th>Saldo</th><th>Situação</th><?php endif; ?></tr></thead><tbody>
                            <?php foreach ($companyDetails as $detail): ?><tr><td><strong><?= h((string) ($detail['order_number'] ?? '—')) ?></strong></td><td><?= h((string) ($detail['client_name'] ?? '—')) ?></td><td><?= h(report_date_time($detail['finalized_at'] ?? '')) ?></td><?php if ($canViewFinancial): ?><td><?= h(report_money($detail['service_total'] ?? '0.00')) ?></td><td><?= h(report_money($detail['product_total'] ?? '0.00')) ?></td><td><?= h(report_money($detail['other_total'] ?? '0.00')) ?></td><td><?= h(report_money($detail['discount_total'] ?? '0.00')) ?></td><td><?= h(report_money($detail['increase_total'] ?? '0.00')) ?></td><td><strong><?= h(report_money($detail['executed_total'] ?? '0.00')) ?></strong></td><td><?= h(report_money($detail['received_total'] ?? '0.00')) ?></td><td><?= h(report_money($detail['balance'] ?? '0.00')) ?></td><td><?= ui_badge(report_payment_status($detail['payment_status'] ?? 'pendente')) ?></td><?php endif; ?></tr><?php endforeach; ?>
                            </tbody></table></div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($canViewEmployees): ?>
            <section id="report-panel-employees" role="<?= count($allowedViews) > 1 ? 'tabpanel' : 'region' ?>" <?= count($allowedViews) > 1 ? 'aria-labelledby="report-tab-employees"' : 'aria-label="Relatório por funcionário"' ?> tabindex="0" data-report-panel="funcionarios"<?= $activeView === 'funcionarios' ? '' : ' hidden' ?>>
                <div class="alert alert-info" role="note"><i class="bi bi-info-circle me-1"></i>Cada integrante recebe o valor integral da OS em que participou. Esses créditos servem para produtividade e não devem ser somados como faturamento da empresa.</div>
                <?php
                $employeeCards = [
                    ['OS com equipe', (string) $employeeOrderCount, 'bi-check2-circle', '#16A34A', $periodLabel],
                    ['Funcionários avaliados', (string) count($employees), 'bi-people', '#2563EB', 'inclui cadastrados sem produção'],
                ];
                if ($canViewCommission) {
                    $employeeCards[] = ['Metas atingidas', (string) ($summary['qualified_count'] ?? 0), 'bi-trophy', '#D97706', $goalConfigured ? 'meta configurada' : 'sem meta configurada'];
                }
                metric_grid($employeeCards);
                ?>

                <section class="panel mb-4">
                    <div class="panel-header"><div class="panel-title"><i class="bi bi-bullseye"></i>Meta de <?= h($periodLabel) ?></div><?= $goalConfigured ? ui_badge('Ativo') : ui_badge('Pendente') ?></div>
                    <div class="p-3">
                        <?php if (!$goalConfigured): ?><p class="section-note mb-0">Nenhuma meta foi configurada para este mês. A produção continua sendo apurada, mas não há prêmio estimado.</p>
                        <?php elseif ($canViewCommission): ?><div class="summary-box"><div><span>Meta individual</span><strong><?= h(report_money($goalAmount)) ?></strong></div><div><span>Percentual do prêmio</span><strong><?= h(report_percent($prizePercentage)) ?></strong></div><div><span>Regra</span><strong>Percentual sobre todo o valor realizado</strong></div></div>
                        <?php else: ?><p class="section-note mb-0">Há uma meta configurada para o período. Os valores e o prêmio estimado exigem permissão específica.</p><?php endif; ?>
                    </div>
                </section>

                <section class="panel mb-4">
                    <div class="panel-header"><div class="panel-title"><i class="bi bi-person-lines-fill"></i>Desempenho por funcionário</div></div>
                    <?php if ($employees === []): ?><?php empty_state('Nenhum funcionário cadastrado', 'Cadastre a equipe para acompanhar a produtividade.'); ?>
                    <?php else: ?><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Funcionário</th><th>Função</th><th>OS</th><?php if ($canViewCommission): ?><th>Valor creditado</th><th>Serviços</th><th>Progresso</th><th>Falta / excedente</th><th>Prêmio estimado</th><th>Situação</th><?php endif; ?></tr></thead><tbody>
                    <?php foreach ($employees as $employee): $qualified = (bool) ($employee['qualified'] ?? false); ?><tr><td><strong><?= h((string) ($employee['name'] ?? '—')) ?></strong><br><small class="text-muted"><?= h((string) ($employee['code'] ?? '')) ?></small></td><td><?= h((string) ($employee['function'] ?? '—')) ?></td><td><?= h((string) ($employee['orders'] ?? 0)) ?></td><?php if ($canViewCommission): ?><td><?= h(report_money($employee['realized'] ?? '0.00')) ?></td><td><?= h(report_money($employee['service_total'] ?? '0.00')) ?></td><td><?= h(report_percent($employee['progress_percent'] ?? '0.00')) ?></td><td><?= $qualified ? h(report_money($employee['exceeded'] ?? '0.00')) . ' excedente' : h(report_money($employee['remaining'] ?? '0.00')) . ' restante' ?></td><td><strong><?= h(report_money($employee['prize'] ?? '0.00')) ?></strong></td><td><?= ui_badge(!$goalConfigured ? 'Sem meta' : ($qualified ? 'Meta atingida' : 'Em andamento')) ?></td><?php endif; ?></tr><?php endforeach; ?>
                    </tbody></table></div><?php endif; ?>
                </section>

                <section class="panel">
                    <div class="panel-header"><div class="panel-title"><i class="bi bi-list-check"></i>OS por funcionário</div></div>
                    <?php if ($details === []): ?><?php empty_state('Sem detalhamento', 'Nenhuma OS com equipe foi finalizada neste mês.'); ?>
                    <?php else: ?><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Funcionário</th><th>Função</th><th>OS</th><th>Cliente</th><th>Finalização</th><?php if ($canViewCommission): ?><th>Serviços</th><th>Total executado</th><?php endif; ?></tr></thead><tbody><?php foreach ($details as $detail): ?><tr><td><?= h((string) ($detail['employee_name'] ?? '—')) ?></td><td><?= h((string) ($detail['employee_function'] ?? '—')) ?></td><td><?= h((string) ($detail['order_number'] ?? '—')) ?></td><td><?= h((string) ($detail['client_name'] ?? '—')) ?></td><td><?= h(report_date_time($detail['finalized_at'] ?? '')) ?></td><?php if ($canViewCommission): ?><td><?= h(report_money($detail['service_total'] ?? '0.00')) ?></td><td><?= h(report_money($detail['executed_total'] ?? '0.00')) ?></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                </section>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($canConfigureGoal): ?>
<div class="modal fade" id="modal-configurar-meta" tabindex="-1" aria-hidden="true" aria-labelledby="report-goal-modal-title">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content visual-modal" method="post" action="actions/relatorio-meta-salvar.php" autocomplete="off">
            <div class="modal-header"><div><h2 class="modal-title fs-5" id="report-goal-modal-title">Configurar meta mensal</h2><p class="text-muted small mb-0">A regra valerá para todos os funcionários no mês selecionado.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body">
                <?= $csrf->field() ?><?php return_to_field(); ?>
                <div class="alert alert-info" role="note">Ao atingir a meta, o percentual será aplicado sobre todo o valor creditado ao funcionário, e não apenas sobre o excedente.</div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label" for="goal-competence">Mês de referência</label><input class="form-control-os" id="goal-competence" type="month" name="competencia" value="<?= h($competence) ?>" required></div>
                    <div class="form-group"><label class="form-label" for="goal-amount">Valor da meta</label><input class="form-control-os" id="goal-amount" name="valor_meta" inputmode="decimal" maxlength="20" placeholder="11.000,00" value="<?= $goalConfigured && $canViewCommission ? h(report_decimal($goalAmount)) : '' ?>" required></div>
                    <div class="form-group"><label class="form-label" for="goal-percentage">Percentual do prêmio</label><div class="input-group"><input class="form-control-os" id="goal-percentage" name="percentual_premio" inputmode="decimal" maxlength="8" placeholder="5,00" value="<?= $goalConfigured && $canViewCommission ? h(report_decimal($prizePercentage)) : '' ?>" required><span class="input-group-text">%</span></div></div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar meta</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
