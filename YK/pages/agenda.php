<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/agenda-action-common.php';

$orderService = $application->serviceOrderManagement();
$agendaService = $application->agendaManagement();
$clientService = $application->clientManagement();
$employeeService = $application->employeeManagement();

$view = (string) ($_GET['view'] ?? 'day');
if (!in_array($view, ['day', 'week'], true)) $view = 'day';
try { $currentDate = new DateTimeImmutable((string) ($_GET['date'] ?? date('Y-m-d'))); } catch (Throwable) { $currentDate = new DateTimeImmutable('today'); }
$periodStart = $view === 'week' ? $currentDate->modify('monday this week')->setTime(0, 0) : $currentDate->setTime(0, 0);
$periodEnd = $view === 'week' ? $periodStart->modify('+7 days') : $periodStart->modify('+1 day');
$prevDate = $view === 'week' ? $periodStart->modify('-7 days') : $periodStart->modify('-1 day');
$nextDate = $view === 'week' ? $periodStart->modify('+7 days') : $periodStart->modify('+1 day');

$filters = [
    'client_id' => trim((string) ($_GET['client_id'] ?? '')),
    'employee_id' => trim((string) ($_GET['employee_id'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
];
$eventType = (string) ($_GET['event_type'] ?? 'all');
if (!in_array($eventType, ['all', 'service_order', 'reminder'], true)) $eventType = 'all';

$orders = $eventType === 'reminder' ? [] : $orderService->calendarBetween($periodStart, $periodEnd, $filters);
$teamsByOrder = $orderService->teamMembersForOrders($orders);
$reminders = $eventType === 'service_order' ? [] : $agendaService->listRemindersBetween($periodStart, $periodEnd);
$clients = $clientService->listClients();
$employees = $employeeService->listEmployees();
$recovery = os_consume_form_recovery();

$events = [];
foreach ($orders as $order) {
    $events[] = ['type' => 'service_order', 'time' => $order->scheduledStart() ?? '', 'order' => $order];
}
foreach ($reminders as $reminder) {
    $events[] = ['type' => 'reminder', 'time' => $reminder->start(), 'reminder' => $reminder];
}
usort($events, static fn(array $a, array $b): int => strcmp((string) $a['time'], (string) $b['time']));

$canViewOs = $authorization->can('os.visualizar');
$canEdit = $authorization->can('agenda.editar');
$canReSchedule = $authorization->can('agenda.reagendar');
$canTeam = $authorization->can('agenda.alterar_dupla');
$canCancel = $authorization->can('agenda.cancelar');
$canReminder = $authorization->can('agenda.criar_lembrete');

function agenda_event_time(?string $start, ?string $end = null): string { try { $s = new DateTimeImmutable((string) $start); $text = $s->format('H:i'); if ($end) $text .= '–' . (new DateTimeImmutable($end))->format('H:i'); return $text; } catch (Throwable) { return '-'; } }
function agenda_status_badge(string $status): string { return ['agendada'=>'teal','em_deslocamento'=>'purple','em_execucao'=>'green','aguardando_peca'=>'amber','finalizada'=>'green','cancelada'=>'red','ativo'=>'blue'][$status] ?? 'gray'; }
function agenda_label_status(string $status): string { return ['agendada'=>'Agendada','em_deslocamento'=>'Em deslocamento','em_execucao'=>'Em execução','aguardando_peca'=>'Aguardando peça','finalizada'=>'Finalizada','cancelada'=>'Cancelada'][$status] ?? $status; }
function agenda_priority_badge(string $priority): string { return ['baixa'=>'gray','media'=>'blue','alta'=>'amber','urgente'=>'red'][$priority] ?? 'blue'; }
function agenda_team_lines(array $members): string { if ($members === []) return '<span>Equipe não definida</span>'; return implode('', array_map(static fn($member): string => '<span>' . h($member->displayLine()) . '</span>', $members)); }

$scheduledCount = count(array_filter($orders, static fn($order): bool => $order->status() === 'agendada'));
$runningCount = count(array_filter($orders, static fn($order): bool => $order->status() === 'em_execucao'));
$urgentCount = count(array_filter($orders, static fn($order): bool => $order->priority() === 'urgente'));
$withoutTeam = count(array_filter($orders, static fn($order): bool => ($GLOBALS['teamsByOrder'][$order->id()] ?? []) === []));
?>

<div class="page-body operational-page agenda-page" data-page="agenda" data-view="<?= h($view) ?>">
<div class="agenda-summary-bar" data-live-region="metrics" aria-label="Resumo da agenda">
    <span><i class="bi bi-wrench" aria-hidden="true"></i><strong><?= h((string) count($orders)) ?></strong> Atendimentos</span>
    <span><i class="bi bi-alarm" aria-hidden="true"></i><strong><?= h((string) count($reminders)) ?></strong> Lembretes</span>
    <span><i class="bi bi-calendar2-check" aria-hidden="true"></i><strong><?= h((string) $scheduledCount) ?></strong> Agendadas</span>
    <span><i class="bi bi-play-circle" aria-hidden="true"></i><strong><?= h((string) $runningCount) ?></strong> Em execução</span>
    <span><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><strong><?= h((string) $urgentCount) ?></strong> Urgentes</span>
    <span><i class="bi bi-people" aria-hidden="true"></i><strong><?= h((string) $withoutTeam) ?></strong> Sem equipe</span>
</div>

<form class="filter-bar agenda-filter-bar" method="get" action="agenda.php" data-live-filter="agenda" data-live-regions="metrics results">
    <input type="hidden" name="view" value="<?= h($view) ?>">
    <input type="hidden" name="date" value="<?= h($periodStart->format('Y-m-d')) ?>">
    <select class="filter-select" name="client_id" aria-label="Cliente"><option value="">Todos os clientes</option><?php foreach ($clients as $client): ?><option value="<?= h((string) $client->id()) ?>" <?= $filters['client_id'] === (string) $client->id() ? 'selected' : '' ?>><?= h($client->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="employee_id" aria-label="Funcionário"><option value="">Todos os funcionários</option><?php foreach ($employees as $employee): ?><option value="<?= h((string) $employee->id()) ?>" <?= $filters['employee_id'] === (string) $employee->id() ? 'selected' : '' ?>><?= h($employee->displayCode() . ' — ' . $employee->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="status" aria-label="Status"><option value="">Todos os status</option><?php foreach (['agendada','em_deslocamento','em_execucao','aguardando_peca','finalizada','cancelada'] as $status): ?><option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h(agenda_label_status($status)) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="event_type" aria-label="Tipo"><option value="all" <?= $eventType === 'all' ? 'selected' : '' ?>>Todos</option><option value="service_order" <?= $eventType === 'service_order' ? 'selected' : '' ?>>Atendimentos</option><option value="reminder" <?= $eventType === 'reminder' ? 'selected' : '' ?>>Lembretes</option></select>
    <div class="agenda-filter-actions">
        <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
        <a class="btn-filter btn-filter-ghost" href="agenda.php" data-live-filter-clear><i class="bi bi-x-lg"></i> Limpar</a>
    </div>
</form>

<section class="panel agenda-panel" data-live-region="results">
    <div class="panel-header agenda-toolbar">
        <div class="agenda-period">
            <span class="agenda-period-kicker"><?= $view === 'week' ? 'Visão semanal' : 'Visão diária' ?></span>
            <div class="panel-title"><i class="bi bi-calendar-week" aria-hidden="true"></i><?= h($view === 'week' ? 'Semana de ' . $periodStart->format('d/m/Y') : $periodStart->format('d/m/Y')) ?></div>
        </div>
        <nav class="agenda-navigation" aria-label="Navegação da agenda">
            <a class="agenda-nav-button" href="agenda.php?view=<?= h($view) ?>&date=<?= h($prevDate->format('Y-m-d')) ?>" aria-label="Período anterior" title="Anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            <a class="btn-filter btn-filter-primary" href="agenda.php?view=<?= h($view) ?>&date=<?= h(date('Y-m-d')) ?>">Hoje</a>
            <a class="agenda-nav-button" href="agenda.php?view=<?= h($view) ?>&date=<?= h($nextDate->format('Y-m-d')) ?>" aria-label="Próximo período" title="Próximo"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            <a class="btn-filter btn-filter-ghost agenda-view-toggle" href="agenda.php?view=<?= $view === 'day' ? 'week' : 'day' ?>&date=<?= h($periodStart->format('Y-m-d')) ?>"><i class="bi <?= $view === 'day' ? 'bi-calendar-week' : 'bi-calendar-day' ?>" aria-hidden="true"></i><?= $view === 'day' ? 'Semana' : 'Dia' ?></a>
        </nav>
    </div>
    <?php if ($events === []): ?><?php empty_state('Nenhum evento no período', 'Atendimentos e lembretes aparecerão aqui.'); ?><?php else: ?>
    <div class="agenda-event-list">
        <?php foreach ($events as $event): ?>
            <?php if ($event['type'] === 'service_order'): $order = $event['order']; ?>
                <article class="week-service-card agenda-event-card priority-<?= h($order->priority()) ?>" data-record-actions>
                    <div class="agenda-event-time"><i class="bi bi-clock" aria-hidden="true"></i><strong><?= h(agenda_event_time($order->scheduledStart(), $order->scheduledEnd())) ?></strong></div>
                    <div class="agenda-event-content">
                        <div class="agenda-event-heading"><strong class="week-service-os"><?= h($order->displayNumber()) ?></strong><span class="week-service-client"><?= h($order->clientName()) ?></span></div>
                        <div class="week-service-title"><?= h($order->mainService() ?? 'Serviço não informado') ?></div>
                        <div class="week-service-details"><i class="bi bi-people" aria-hidden="true"></i><span><?= agenda_team_lines($teamsByOrder[$order->id()] ?? []) ?></span></div>
                    </div>
                    <div class="week-service-meta agenda-event-meta"><span class="badge-soft badge-<?= h(agenda_status_badge($order->status())) ?>"><?= h($order->displayStatus()) ?></span><span class="badge-soft badge-<?= h(agenda_priority_badge($order->priority())) ?>"><?= h($order->displayPriority()) ?></span></div>
                    <div class="record-actions-source agenda-event-actions">
                        <div class="dropdown table-action-dropdown">
                            <button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações da OS <?= h($order->displayNumber()) ?>"><i class="bi bi-three-dots-vertical"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($canViewOs): ?><li><a class="dropdown-item" href="ordens-servico.php?search=<?= h(rawurlencode($order->displayNumber())) ?>"><i class="bi bi-eye"></i> Abrir OS</a></li><?php endif; ?>
                                <?php if ($canReSchedule): ?><li><button class="dropdown-item js-agenda-schedule" data-order-id="<?= h((string) $order->id()) ?>" data-start="<?= h($order->scheduledStart() ?? '') ?>" data-end="<?= h($order->scheduledEnd() ?? '') ?>" data-bs-toggle="modal" data-bs-target="#modal-agenda-schedule" type="button"><i class="bi bi-calendar-event"></i> Reagendar</button></li><?php endif; ?>
                                <?php if ($canTeam): ?><li><button class="dropdown-item js-agenda-team" data-order-id="<?= h((string) $order->id()) ?>" data-primary-id="<?= h((string) ($order->primaryEmployeeId() ?? '')) ?>" data-support-id="<?= h((string) ($order->supportEmployeeId() ?? '')) ?>" data-bs-toggle="modal" data-bs-target="#modal-agenda-team" type="button"><i class="bi bi-people"></i> Alterar equipe</button></li><?php endif; ?>
                                <?php if ($canEdit && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item js-agenda-status" data-order-id="<?= h((string) $order->id()) ?>" data-current-status="<?= h($order->status()) ?>" data-bs-toggle="modal" data-bs-target="#modal-agenda-status" type="button"><i class="bi bi-arrow-repeat"></i> Alterar status</button></li><?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </article>
            <?php else: $reminder = $event['reminder']; ?>
                <article class="week-service-card agenda-event-card agenda-reminder-card priority-medium" data-record-actions>
                    <div class="agenda-event-time"><i class="bi bi-alarm" aria-hidden="true"></i><strong><?= h(agenda_event_time($reminder->start(), $reminder->end())) ?></strong></div>
                    <div class="agenda-event-content"><div class="agenda-event-heading"><strong class="week-service-os">Lembrete</strong><span class="week-service-client"><?= h($reminder->title()) ?></span></div><div class="week-service-title"><?= h($reminder->description() ?? 'Sem descrição') ?></div></div>
                    <div class="week-service-meta agenda-event-meta"><span class="badge-soft badge-blue"><?= h($reminder->status()) ?></span></div>
                    <div class="record-actions-source agenda-event-actions"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do lembrete <?= h($reminder->title()) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><?php if ($canEdit): ?><li><button class="dropdown-item js-reminder-edit" type="button" data-id="<?= h((string) $reminder->id()) ?>" data-title="<?= h($reminder->title()) ?>" data-description="<?= h($reminder->description() ?? '') ?>" data-start="<?= h($reminder->start()) ?>" data-end="<?= h($reminder->end() ?? '') ?>" data-bs-toggle="modal" data-bs-target="#modal-lembrete-edit"><i class="bi bi-pencil"></i> Editar</button></li><?php endif; ?><?php if ($canCancel): ?><li><button class="dropdown-item text-danger js-reminder-cancel" type="button" data-id="<?= h((string) $reminder->id()) ?>" data-title="<?= h($reminder->title()) ?>" data-bs-toggle="modal" data-bs-target="#modal-lembrete-cancel"><i class="bi bi-x-circle"></i> Cancelar</button></li><?php endif; ?></ul></div></div>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </div><?php endif; ?>
</section>
</div>

<?php function agenda_employee_options(array $employees): void { foreach ($employees as $employee) echo '<option value="' . h((string) $employee->id()) . '">' . h($employee->displayCode() . ' — ' . $employee->name()) . '</option>'; } ?>
<?php function agenda_return_fields(string $view, DateTimeImmutable $periodStart): void { return_to_field(); echo '<input type="hidden" name="return_view" value="' . h($view) . '"><input type="hidden" name="return_date" value="' . h($periodStart->format('Y-m-d')) . '">'; } ?>

<div class="modal fade" id="modal-lembrete" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/agenda-lembrete-salvar.php"><div class="modal-header"><h2 class="modal-title fs-5">Novo lembrete</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php agenda_return_fields($view, $periodStart); ?><div class="form-row"><div class="form-group"><label class="form-label">Título</label><input class="form-control-os" name="title" required></div><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="start" required></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="end"></div></div><div class="form-group"><label class="form-label">Descrição</label><textarea class="form-control-os" name="description"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-lembrete-edit" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/agenda-lembrete-salvar.php"><div class="modal-header"><h2 class="modal-title fs-5">Editar lembrete</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php agenda_return_fields($view, $periodStart); ?><input type="hidden" name="id" id="reminder-edit-id"><div class="form-row"><div class="form-group"><label class="form-label">Título</label><input class="form-control-os" name="title" id="reminder-edit-title" required></div><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="start" id="reminder-edit-start" required></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="end" id="reminder-edit-end"></div></div><div class="form-group"><label class="form-label">Descrição</label><textarea class="form-control-os" name="description" id="reminder-edit-description"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-lembrete-cancel" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/agenda-lembrete-cancelar.php"><div class="modal-header"><h2 class="modal-title fs-5">Cancelar lembrete</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php agenda_return_fields($view, $periodStart); ?><input type="hidden" name="id" id="reminder-cancel-id"><p id="reminder-cancel-message"></p></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>

<div class="modal fade" id="modal-agenda-schedule" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/agenda-reagendar.php"><div class="modal-header"><h2 class="modal-title fs-5">Reagendar OS</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php agenda_return_fields($view, $periodStart); ?><input type="hidden" name="id" id="agenda-schedule-id"><div class="form-row"><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="agendado_inicio" id="agenda-schedule-start" required></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="agendado_fim" id="agenda-schedule-end" required></div></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-agenda-team" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/agenda-alterar-dupla.php"><div class="modal-header"><h2 class="modal-title fs-5">Alterar dupla</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php agenda_return_fields($view, $periodStart); ?><input type="hidden" name="id" id="agenda-team-id"><div class="form-row"><div class="form-group"><label class="form-label">Principal</label><select class="form-control-os js-primary-employee" name="funcionario_principal_id" id="agenda-team-primary" required><option value="">Selecione</option><?php agenda_employee_options($employees); ?></select></div><div class="form-group"><label class="form-label">Apoio</label><select class="form-control-os js-support-employee" name="funcionario_apoio_id" id="agenda-team-support" required><option value="">Selecione</option><?php agenda_employee_options($employees); ?></select></div></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-agenda-status" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/agenda-status.php"><div class="modal-header"><h2 class="modal-title fs-5" id="agenda-status-title">Alterar status</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php agenda_return_fields($view, $periodStart); ?><input type="hidden" name="id" id="agenda-status-id"><input type="hidden" name="operation" id="agenda-status-operation"><p id="agenda-status-message"></p><select class="form-control-os" id="agenda-status-select"><option value="start_travel">Iniciar deslocamento</option><option value="start_execution">Iniciar execução</option><option value="wait_part">Aguardar peça</option><option value="finalize">Finalizar</option><option value="cancel">Cancelar</option></select></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>
<script type="application/json" id="agenda-page-data"><?= json_encode(['recoveryModal' => $recovery['modal'] ?? ($_GET['modal'] ?? null), 'recoveryData' => $recovery['data'] ?? [], 'recoveryError' => $recovery['error'] ?? null], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
