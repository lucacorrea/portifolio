<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

function report_decimal(mixed $value): string
{
    $normalized = trim((string) $value);
    if ($normalized === '' || preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) !== 1) {
        return '0,00';
    }

    $negative = str_starts_with($normalized, '-');
    $unsigned = ltrim($normalized, '-');
    [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
    $integer = ltrim($integer, '0');
    $integer = $integer === '' ? '0' : $integer;
    $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

    return ($negative ? '-' : '')
        . number_format((int) $integer, 0, ',', '.')
        . ',' . $fraction;
}

function report_money(mixed $value): string
{
    return 'R$ ' . report_decimal($value);
}

function report_percent(mixed $value): string
{
    return report_decimal($value) . '%';
}

function report_date_time(mixed $value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
}

$requestedCompetence = trim((string) ($_GET['competencia'] ?? date('Y-m')));
$competence = preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $requestedCompetence) === 1
    ? $requestedCompetence
    : date('Y-m');
$canViewCommission = $authorization->can('relatorio.comissao.visualizar');
$canViewEmployees = $authorization->can('relatorio.funcionarios') || $canViewCommission;
$canViewProduction = $authorization->can('relatorio.operacional')
    || $authorization->can('relatorio.produtividade')
    || $canViewEmployees;
$canConfigureGoal = $authorization->can('relatorio.meta_comissao.configurar');
$report = null;
$loadError = null;

if ($canViewProduction) {
    try {
        $report = $application->reports()->monthlyReport($competence);
    } catch (Throwable $exception) {
        error_log('Monthly report load failed: ' . $exception->getMessage());
        $loadError = 'Não foi possível carregar o relatório deste mês. Tente novamente.';
    }
}

$goal = is_array($report['goal'] ?? null) ? $report['goal'] : [];
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$employees = is_array($report['employees'] ?? null) ? $report['employees'] : [];
$details = is_array($report['details'] ?? null) ? $report['details'] : [];
$periodLabel = trim((string) ($report['period_label'] ?? ''));
$periodLabel = $periodLabel !== '' ? $periodLabel : $competence;
$goalConfigured = (bool) ($goal['configured'] ?? false);
$goalAmount = (string) ($goal['amount'] ?? '0.00');
$prizePercentage = (string) ($goal['percentage'] ?? '0.00');
?>

<div class="page-body reports-page">
    <section class="panel mb-4">
        <div class="panel-header">
            <div class="panel-title"><i class="bi bi-calendar3"></i>Período do relatório</div>
        </div>
        <form class="filter-bar" method="get" action="relatorios.php">
            <div class="form-group mb-0">
                <label class="form-label" for="report-competence">Mês de referência</label>
                <input class="filter-select input-date" id="report-competence" type="month" name="competencia" value="<?= h($competence) ?>" required>
            </div>
            <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Atualizar</button>
            <?php if ($canConfigureGoal): ?>
                <button class="btn-filter btn-filter-ghost" type="button" data-bs-toggle="modal" data-bs-target="#modal-configurar-meta">
                    <i class="bi bi-bullseye"></i> Configurar meta
                </button>
            <?php endif; ?>
        </form>
    </section>

    <?php if ($loadError !== null): ?>
        <div class="alert alert-danger" role="alert"><?= h($loadError) ?></div>
    <?php elseif (!$canViewProduction): ?>
        <?php empty_state('Nenhum relatório disponível para este perfil', 'Esta tela apresenta produção e metas. Solicite uma permissão compatível ao administrador.'); ?>
    <?php elseif ($report !== null): ?>
        <div class="alert alert-info" role="note">
            <i class="bi bi-info-circle me-1"></i>
            Cada integrante recebe o valor integral da OS em que participou. O consolidado da empresa contabiliza cada OS apenas uma vez, evitando duplicidade no faturamento geral.
        </div>

        <?php
        $cards = [
            ['OS finalizadas', (string) ($summary['orders'] ?? 0), 'bi-check2-circle', '#16A34A', $periodLabel],
            ['Funcionários avaliados', (string) ($summary['employee_count'] ?? 0), 'bi-people', '#2563EB', 'cadastrados no sistema'],
        ];
        if ($canViewCommission) {
            $cards[] = ['Metas atingidas', (string) ($summary['qualified_count'] ?? 0), 'bi-trophy', '#D97706', $goalConfigured ? 'meta configurada' : 'sem meta configurada'];
            $cards[] = ['Faturamento consolidado', report_money($summary['company_total'] ?? '0.00'), 'bi-cash-stack', '#7C3AED', 'cada OS contabilizada uma vez'];
            $cards[] = ['Serviços executados', report_money($summary['service_total'] ?? '0.00'), 'bi-tools', '#0EA5E9', 'base de serviços do período'];
        }
        metric_grid($cards);
        ?>

        <section class="panel mb-4">
            <div class="panel-header">
                <div class="panel-title"><i class="bi bi-bullseye"></i>Meta de <?= h($periodLabel) ?></div>
                <?= $goalConfigured ? ui_badge('Ativo') : ui_badge('Pendente') ?>
            </div>
            <div class="p-3">
                <?php if (!$goalConfigured): ?>
                    <p class="section-note mb-0">Nenhuma meta foi configurada para este mês. A produção continua sendo apurada, mas não há prêmio estimado.</p>
                <?php elseif ($canViewCommission): ?>
                    <div class="summary-box">
                        <div><span>Meta individual</span><strong><?= h(report_money($goalAmount)) ?></strong></div>
                        <div><span>Percentual do prêmio</span><strong><?= h(report_percent($prizePercentage)) ?></strong></div>
                        <div><span>Regra</span><strong>Percentual sobre todo o valor realizado</strong></div>
                    </div>
                <?php else: ?>
                    <p class="section-note mb-0">Há uma meta configurada para o período. Os valores e o prêmio estimado exigem permissão específica.</p>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($canViewEmployees): ?>
            <section class="panel mb-4">
                <div class="panel-header">
                    <div class="panel-title"><i class="bi bi-person-lines-fill"></i>Desempenho por funcionário</div>
                </div>
                <?php if ($employees === []): ?>
                    <?php empty_state('Nenhuma produção no período', 'Não há funcionários vinculados a OS finalizadas neste mês.'); ?>
                <?php else: ?>
                    <div class="table-panel-wrap">
                        <table class="os-table">
                            <thead><tr>
                                <th>Funcionário</th><th>Função</th><th>OS</th>
                                <?php if ($canViewCommission): ?>
                                    <th>Valor creditado</th><th>Serviços</th><th>Progresso</th><th>Falta / excedente</th><th>Prêmio estimado</th>
                                    <th>Situação</th>
                                <?php endif; ?>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($employees as $employee): ?>
                                <?php $qualified = (bool) ($employee['qualified'] ?? false); ?>
                                <tr>
                                    <td><strong><?= h((string) ($employee['name'] ?? '—')) ?></strong><br><small class="text-muted"><?= h((string) ($employee['code'] ?? '')) ?></small></td>
                                    <td><?= h((string) ($employee['function'] ?? '—')) ?></td>
                                    <td><?= h((string) ($employee['orders'] ?? 0)) ?></td>
                                    <?php if ($canViewCommission): ?>
                                        <td><?= h(report_money($employee['realized'] ?? '0.00')) ?></td>
                                        <td><?= h(report_money($employee['service_total'] ?? '0.00')) ?></td>
                                        <td><?= h(report_percent($employee['progress_percent'] ?? '0.00')) ?></td>
                                        <td><?= $qualified
                                            ? h(report_money($employee['exceeded'] ?? '0.00')) . ' excedente'
                                            : h(report_money($employee['remaining'] ?? '0.00')) . ' restante' ?></td>
                                        <td><strong><?= h(report_money($employee['prize'] ?? '0.00')) ?></strong></td>
                                        <td><?= ui_badge(!$goalConfigured ? 'Sem meta' : ($qualified ? 'Meta atingida' : 'Em andamento')) ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($canViewEmployees): ?>
            <section class="panel">
                <div class="panel-header"><div class="panel-title"><i class="bi bi-list-check"></i>OS que compõem o relatório</div></div>
                <?php if ($details === []): ?>
                    <?php empty_state('Sem detalhamento', 'Nenhuma OS finalizada foi encontrada para este mês.'); ?>
                <?php else: ?>
                    <div class="table-panel-wrap">
                        <table class="os-table">
                            <thead><tr><th>Funcionário</th><th>Função</th><th>OS</th><th>Cliente</th><th>Finalização</th><?php if ($canViewCommission): ?><th>Serviços</th><th>Total executado</th><?php endif; ?></tr></thead>
                            <tbody>
                            <?php foreach ($details as $detail): ?>
                                <tr>
                                    <td><?= h((string) ($detail['employee_name'] ?? '—')) ?></td>
                                    <td><?= h((string) ($detail['employee_function'] ?? '—')) ?></td>
                                    <td><?= h((string) ($detail['order_number'] ?? '—')) ?></td>
                                    <td><?= h((string) ($detail['client_name'] ?? '—')) ?></td>
                                    <td><?= h(report_date_time($detail['finalized_at'] ?? '')) ?></td>
                                    <?php if ($canViewCommission): ?>
                                        <td><?= h(report_money($detail['service_total'] ?? '0.00')) ?></td>
                                        <td><?= h(report_money($detail['executed_total'] ?? '0.00')) ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif (!$canViewCommission): ?>
            <?php empty_state('Relatório indisponível para este perfil', 'Solicite a permissão de relatório de funcionários ou comissão ao administrador.'); ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($canConfigureGoal): ?>
<div class="modal fade" id="modal-configurar-meta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content visual-modal" method="post" action="actions/relatorio-meta-salvar.php" autocomplete="off">
            <div class="modal-header">
                <div><h2 class="modal-title fs-5">Configurar meta mensal</h2><p class="text-muted small mb-0">A regra valerá para todos os funcionários no mês selecionado.</p></div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?= $csrf->field() ?>
                <div class="alert alert-info" role="note">Ao atingir a meta, o percentual será aplicado sobre todo o valor creditado ao funcionário, e não apenas sobre o excedente.</div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label" for="goal-competence">Mês de referência</label><input class="form-control-os" id="goal-competence" type="month" name="competencia" value="<?= h($competence) ?>" required></div>
                    <div class="form-group"><label class="form-label" for="goal-amount">Valor da meta</label><input class="form-control-os" id="goal-amount" name="valor_meta" inputmode="decimal" maxlength="20" placeholder="11.000,00" value="<?= $goalConfigured && $canViewCommission ? h(report_decimal($goalAmount)) : '' ?>" required></div>
                    <div class="form-group"><label class="form-label" for="goal-percentage">Percentual do prêmio</label><div class="input-group"><input class="form-control-os" id="goal-percentage" name="percentual_premio" inputmode="decimal" maxlength="8" placeholder="5,00" value="<?= $goalConfigured && $canViewCommission ? h(report_decimal($prizePercentage)) : '' ?>" required><span class="input-group-text">%</span></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar meta</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
