<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$canOperational = $authorization->can('dashboard.visualizar_operacional');
$canFinancial = $authorization->can('dashboard.visualizar_financeiro');
$canViewOrders = $canOperational && $authorization->can('os.visualizar');
$canViewWeeklyPanel = $canOperational && $authorization->can('painel_semanal.visualizar');
$overview = $application->dashboard()->overview(
    $canOperational,
    $canFinancial,
    $canViewWeeklyPanel,
    $canViewOrders,
    $authorization->can('os.visualizar_valores')
);
$operational = $overview['operational'];
$financial = $overview['financial'];
$weeklyOrders = $overview['weekly_orders'];
$latestOrders = $overview['latest_orders'];
$showOrderValues = (bool) $overview['show_order_values'];

function dashboard_order_number(array $order): string
{
    return trim((string) ($order['numero'] ?? '')) ?: sprintf('OS-%06d', (int) $order['id']);
}

function dashboard_status_label(string $status): string
{
    return [
        'rascunho' => 'Rascunho', 'aberta' => 'Aberta',
        'aguardando_agendamento' => 'Aguardando agendamento', 'agendada' => 'Agendada',
        'em_deslocamento' => 'Em deslocamento', 'em_execucao' => 'Em execução',
        'aguardando_peca' => 'Aguardando peça', 'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
    ][$status] ?? $status;
}

function dashboard_date(string $value, string $format): string
{
    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Throwable) {
        return '-';
    }
}

$quickActions = [];
if ($canViewOrders && $authorization->can('os.criar')) {
    $quickActions[] = ['Nova OS', 'bi-plus-circle', 'ordens-servico.php?modal=create'];
}
if ($authorization->can('orcamento.visualizar')) {
    $quickActions[] = ['Orçamentos', 'bi-file-earmark-text', 'orcamentos.php'];
}
if ($authorization->can('agenda.visualizar') && $authorization->can('agenda.criar_lembrete')) {
    $quickActions[] = ['Novo compromisso', 'bi-calendar-plus', 'agenda.php?modal=reminder'];
}
if ($authorization->can('caixa.visualizar')) {
    $quickActions[] = ['Ver caixa', 'bi-cash-coin', 'caixa.php'];
}
if ($authorization->can('caixa.registrar_venda')) {
    $quickActions[] = ['Frente de Caixa', 'bi-shop-window', 'frente-caixa.php'];
}
?>

<div class="page-body dashboard-page">
<?php if ($canOperational): ?>
    <?php metric_grid([
        ['OS abertas', (string) $operational['open_count'], 'bi-folder2-open', '#2563EB', 'a atender ou agendar'],
        ['OS em andamento', (string) $operational['in_service'], 'bi-arrow-repeat', '#D97706', 'equipes em atendimento'],
        ['Serviços da semana', (string) $operational['week_services'], 'bi-calendar-week', '#0EA5E9', 'agenda operacional'],
        ['Aguardando peça', (string) $operational['waiting_part'], 'bi-box-seam', '#7C3AED', 'serviços pendentes'],
        ['Orçamentos pendentes', (string) $operational['waiting_budgets'], 'bi-file-earmark-text', '#F59E0B', 'aguardando cliente'],
        ['Estoque crítico', (string) $operational['low_stock'], 'bi-exclamation-triangle', '#DC2626', 'no mínimo ou sem saldo'],
    ]); ?>
<?php endif; ?>

<?php if ($canFinancial): ?>
    <?php metric_grid([
        ['Contas pendentes', (string) $financial['pending_accounts'], 'bi-wallet2', '#9333EA', money($financial['pending_balance']) . ' em aberto'],
        ['Recebimentos do mês', money($financial['received_month']), 'bi-cash-stack', '#16A34A', 'pagamentos de clientes'],
    ]); ?>
<?php endif; ?>

<?php if (!$canOperational && !$canFinancial): ?>
    <?php empty_state('Sem indicadores disponíveis', 'Seu perfil não possui acesso aos indicadores operacionais ou financeiros.'); ?>
<?php endif; ?>

<?php if ($quickActions !== []): ?>
<section class="quick-actions panel">
    <div class="panel-header"><div class="panel-title"><i class="bi bi-lightning-charge"></i>Ações rápidas</div></div>
    <div class="quick-grid">
        <?php foreach ($quickActions as [$label, $icon, $href]): ?>
            <a class="quick-action text-decoration-none" href="<?= h($href) ?>"><i class="bi <?= h($icon) ?>"></i><span><?= h($label) ?></span></a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($canViewWeeklyPanel): ?>
<section class="panel mb-4">
    <div class="panel-header">
        <div class="panel-title"><i class="bi bi-calendar2-week"></i>Serviços da semana</div>
        <a class="btn-filter btn-filter-ghost" href="painel-semanal.php">Ver painel</a>
    </div>
    <?php if ($weeklyOrders === []): ?>
        <?php empty_state('Nenhum serviço agendado nesta semana', 'Os agendamentos de OS aparecerão aqui.'); ?>
    <?php else: ?>
        <div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Dia</th><th>Horário</th><th>Cliente</th><th>Serviço</th><th>Equipe</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($weeklyOrders as $order): ?>
            <tr>
                <td><?= h(dashboard_date((string) $order['agendado_inicio'], 'd/m/Y')) ?></td>
                <td><?= h(dashboard_date((string) $order['agendado_inicio'], 'H:i')) ?></td>
                <td><?= h((string) $order['cliente_nome']) ?></td>
                <td><?= h((string) $order['servico']) ?></td>
                <td><?= h(trim((string) ($order['equipe'] ?? '')) ?: 'Equipe não definida') ?></td>
                <td><?= ui_badge(dashboard_status_label((string) $order['status'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($canViewOrders): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><i class="bi bi-clock-history"></i>Últimas Ordens de Serviço</div>
        <a class="btn-filter btn-filter-ghost" href="ordens-servico.php">Ver todas</a>
    </div>
    <?php if ($latestOrders === []): ?>
        <?php empty_state('Nenhuma ordem de serviço cadastrada', 'As novas ordens aparecerão aqui.'); ?>
    <?php else: ?>
        <div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Número</th><th>Cliente</th><th>Serviço</th><th>Equipe</th><th>Status</th><?php if ($showOrderValues): ?><th>Valor</th><?php endif; ?><th>Cadastro</th><th>Ações</th></tr></thead><tbody>
        <?php foreach ($latestOrders as $order): ?>
            <tr>
                <td><strong><?= h(dashboard_order_number($order)) ?></strong></td>
                <td><?= h((string) $order['cliente_nome']) ?></td>
                <td><?= h((string) $order['servico']) ?></td>
                <td><?= h(trim((string) ($order['equipe'] ?? '')) ?: 'Equipe não definida') ?></td>
                <td><?= ui_badge(dashboard_status_label((string) $order['status'])) ?></td>
                <?php if ($showOrderValues): ?><td><?= money((string) $order['total']) ?></td><?php endif; ?>
                <td><?= h(dashboard_date((string) $order['criado_em'], 'd/m/Y H:i')) ?></td>
                <td class="table-actions-cell">
                    <div class="dropdown table-action-dropdown">
                        <button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações da OS <?= h(dashboard_order_number($order)) ?>"><i class="bi bi-three-dots-vertical"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="ordens-servico.php?search=<?= h(rawurlencode(dashboard_order_number($order))) ?>"><i class="bi bi-eye"></i> Abrir Ordem de Serviço</a></li>
                        </ul>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
</div>
