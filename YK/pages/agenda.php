<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

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
$reminders = $eventType === 'service_order' ? [] : $agendaService->listRemindersBetween($periodStart, $periodEnd);
$clients = $clientService->listClients();
$employees = $employeeService->listEmployees();

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

$scheduledCount = count(array_filter($orders, static fn($order): bool => $order->status() === 'agendada'));
$runningCount = count(array_filter($orders, static fn($order): bool => $order->status() === 'em_execucao'));
$urgentCount = count(array_filter($orders, static fn($order): bool => $order->priority() === 'urgente'));
$withoutTeam = count(array_filter($orders, static fn($order): bool => $order->primaryEmployeeId() === null || $order->supportEmployeeId() === null));
?>

<div class="page-body agenda-page">
<?php metric_grid([
    ['Atendimentos', (string) count($orders), 'bi-wrench', '#2563EB', 'OS no período'],
    ['Lembretes', (string) count($reminders), 'bi-alarm', '#7C3AED', 'agenda comercial'],
    ['Agendadas', (string) $scheduledCount, 'bi-calendar2-check', '#0F766E', 'com horário'],
    ['Em execução', (string) $runningCount, 'bi-play-circle', '#16A34A', 'em atendimento'],
    ['Urgentes', (string) $urgentCount, 'bi-exclamation-triangle', '#DC2626', 'prioridade alta'],
    ['Sem equipe', (string) $withoutTeam, 'bi-people', '#D97706', 'corrigir OS'],
]); ?>

<form class="filter-bar" method="get" action="agenda.php">
    <input type="hidden" name="view" value="<?= h($view) ?>">
    <input type="hidden" name="date" value="<?= h($periodStart->format('Y-m-d')) ?>">
    <select class="filter-select" name="client_id" aria-label="Cliente"><option value="">Todos os clientes</option><?php foreach ($clients as $client): ?><option value="<?= h((string) $client->id()) ?>" <?= $filters['client_id'] === (string) $client->id() ? 'selected' : '' ?>><?= h($client->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="employee_id" aria-label="Funcionário"><option value="">Todos os funcionários</option><?php foreach ($employees as $employee): ?><option value="<?= h((string) $employee->id()) ?>" <?= $filters['employee_id'] === (string) $employee->id() ? 'selected' : '' ?>><?= h($employee->displayCode() . ' — ' . $employee->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="status" aria-label="Status"><option value="">Todos os status</option><?php foreach (['agendada','em_deslocamento','em_execucao','aguardando_peca','finalizada','cancelada'] as $status): ?><option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h(agenda_label_status($status)) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="event_type" aria-label="Tipo"><option value="all" <?= $eventType === 'all' ? 'selected' : '' ?>>Todos</option><option value="service_order" <?= $eventType === 'service_order' ? 'selected' : '' ?>>Atendimentos</option><option value="reminder" <?= $eventType === 'reminder' ? 'selected' : '' ?>>Lembretes</option></select>
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="agenda.php"><i class="bi bi-x-lg"></i> Limpar filtros</a>
</form>

<section class="panel">
    <div class="panel-header"><div class="panel-title"><i class="bi bi-calendar-week"></i><?= h($view === 'week' ? 'Semana de ' . $periodStart->format('d/m/Y') : $periodStart->format('d/m/Y')) ?></div><div class="d-flex gap-2"><a class="btn-filter btn-filter-ghost" href="agenda.php?view=<?= h($view) ?>&date=<?= h($prevDate->format('Y-m-d')) ?>">Anterior</a><a class="btn-filter btn-filter-primary" href="agenda.php?view=<?= h($view) ?>&date=<?= h(date('Y-m-d')) ?>">Hoje</a><a class="btn-filter btn-filter-ghost" href="agenda.php?view=<?= h($view) ?>&date=<?= h($nextDate->format('Y-m-d')) ?>">Próximo</a><a class="btn-filter btn-filter-ghost" href="agenda.php?view=<?= $view === 'day' ? 'week' : 'day' ?>&date=<?= h($periodStart->format('Y-m-d')) ?>"><?= $view === 'day' ? 'Ver semana' : 'Ver dia' ?></a></div></div>
    <?php if ($events === []): ?><?php empty_state('Nenhum evento no período', 'Atendimentos e lembretes aparecerão aqui.'); ?><?php else: ?>
    <div class="agenda-event-list">
        <?php foreach ($events as $event): ?>
            <?php if ($event['type'] === 'service_order'): $order = $event['order']; ?>
                <article class="week-service-card priority-<?= h($order->priority()) ?>">
                    <div class="week-service-time"><?= h(agenda_event_time($order->scheduledStart(), $order->scheduledEnd())) ?></div>
                    <strong class="week-service-os"><?= h($order->displayNumber()) ?></strong>
                    <div class="week-service-client"><?= h($order->clientName()) ?></div>
                    <div class="week-service-title"><?= h($order->mainService() ?? 'Serviço não informado') ?></div>
                    <div class="week-service-details"><span>Principal: <?= h($order->displayPrimaryEmployee() ?? '-') ?></span><span>Apoio: <?= h($order->displaySupportEmployee() ?? '-') ?></span></div>
                    <div class="week-service-meta"><span class="badge-soft badge-<?= h(agenda_status_badge($order->status())) ?>"><?= h($order->displayStatus()) ?></span><span class="badge-soft badge-<?= h(agenda_priority_badge($order->priority())) ?>"><?= h($order->displayPriority()) ?></span></div>
                    <div class="mt-2 d-flex flex-wrap gap-2">
                        <?php if ($canViewOs): ?><a class="btn-filter btn-filter-ghost" href="ordens-servico.php?search=<?= h(rawurlencode($order->displayNumber())) ?>">Abrir OS</a><?php endif; ?>
                        <?php if ($canReSchedule): ?><button class="btn-filter btn-filter-ghost js-agenda-schedule" data-order-id="<?= h((string) $order->id()) ?>" data-start="<?= h($order->scheduledStart() ?? '') ?>" data-end="<?= h($order->scheduledEnd() ?? '') ?>" data-bs-toggle="modal" data-bs-target="#modal-agenda-schedule" type="button">Reagendar</button><?php endif; ?>
                        <?php if ($canTeam): ?><button class="btn-filter btn-filter-ghost js-agenda-team" data-order-id="<?= h((string) $order->id()) ?>" data-primary-id="<?= h((string) ($order->primaryEmployeeId() ?? '')) ?>" data-support-id="<?= h((string) ($order->supportEmployeeId() ?? '')) ?>" data-bs-toggle="modal" data-bs-target="#modal-agenda-team" type="button">Alterar dupla</button><?php endif; ?>
                        <?php if ($canEdit): ?><button class="btn-filter btn-filter-ghost js-agenda-status" data-order-id="<?= h((string) $order->id()) ?>" data-operation="start_execution" data-label="Alterar status" data-bs-toggle="modal" data-bs-target="#modal-agenda-status" type="button">Status</button><?php endif; ?>
                    </div>
                </article>
            <?php else: $reminder = $event['reminder']; ?>
                <article class="week-service-card priority-medium">
                    <div class="week-service-time"><?= h(agenda_event_time($reminder->start(), $reminder->end())) ?></div>
                    <strong class="week-service-os"><?= h($reminder->title()) ?></strong>
                    <div class="week-service-client"><?= h($reminder->description() ?? '-') ?></div>
                    <div class="week-service-meta"><span class="badge-soft badge-blue"><?= h($reminder->status()) ?></span></div>
                    <div class="mt-2 d-flex gap-2"><?php if ($canEdit): ?><button class="btn-filter btn-filter-ghost js-reminder-edit" type="button" data-id="<?= h((string) $reminder->id()) ?>" data-title="<?= h($reminder->title()) ?>" data-description="<?= h($reminder->description() ?? '') ?>" data-start="<?= h($reminder->start()) ?>" data-end="<?= h($reminder->end() ?? '') ?>" data-bs-toggle="modal" data-bs-target="#modal-lembrete-edit">Editar</button><?php endif; ?><?php if ($canCancel): ?><button class="btn-filter btn-filter-ghost text-danger js-reminder-cancel" type="button" data-id="<?= h((string) $reminder->id()) ?>" data-title="<?= h($reminder->title()) ?>" data-bs-toggle="modal" data-bs-target="#modal-lembrete-cancel">Cancelar</button><?php endif; ?></div>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </div><?php endif; ?>
</section>
</div>

<?php function agenda_employee_options(array $employees): void { foreach ($employees as $employee) echo '<option value="' . h((string) $employee->id()) . '">' . h($employee->displayCode() . ' — ' . $employee->name()) . '</option>'; } ?>

<div class="modal fade" id="modal-lembrete" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/agenda-lembrete-salvar.php"><div class="modal-header"><h2 class="modal-title fs-5">Novo lembrete</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><div class="form-row"><div class="form-group"><label class="form-label">Título</label><input class="form-control-os" name="title" required></div><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="start" required></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="end"></div></div><div class="form-group"><label class="form-label">Descrição</label><textarea class="form-control-os" name="description"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-lembrete-edit" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/agenda-lembrete-salvar.php"><div class="modal-header"><h2 class="modal-title fs-5">Editar lembrete</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><input type="hidden" name="id" id="reminder-edit-id"><div class="form-row"><div class="form-group"><label class="form-label">Título</label><input class="form-control-os" name="title" id="reminder-edit-title" required></div><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="start" id="reminder-edit-start" required></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="end" id="reminder-edit-end"></div></div><div class="form-group"><label class="form-label">Descrição</label><textarea class="form-control-os" name="description" id="reminder-edit-description"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-lembrete-cancel" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/agenda-lembrete-cancelar.php"><div class="modal-header"><h2 class="modal-title fs-5">Cancelar lembrete</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><input type="hidden" name="id" id="reminder-cancel-id"><p id="reminder-cancel-message"></p></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>

<div class="modal fade" id="modal-agenda-schedule" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/agenda-reagendar.php"><div class="modal-header"><h2 class="modal-title fs-5">Reagendar OS</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><input type="hidden" name="id" id="agenda-schedule-id"><div class="form-row"><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="agendado_inicio" id="agenda-schedule-start" required></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="agendado_fim" id="agenda-schedule-end" required></div></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-agenda-team" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/agenda-alterar-dupla.php"><div class="modal-header"><h2 class="modal-title fs-5">Alterar dupla</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><input type="hidden" name="id" id="agenda-team-id"><div class="form-row"><div class="form-group"><label class="form-label">Principal</label><select class="form-control-os js-primary-employee" name="funcionario_principal_id" id="agenda-team-primary" required><option value="">Selecione</option><?php agenda_employee_options($employees); ?></select></div><div class="form-group"><label class="form-label">Apoio</label><select class="form-control-os js-support-employee" name="funcionario_apoio_id" id="agenda-team-support" required><option value="">Selecione</option><?php agenda_employee_options($employees); ?></select></div></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-agenda-status" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/agenda-status.php"><div class="modal-header"><h2 class="modal-title fs-5" id="agenda-status-title">Alterar status</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><input type="hidden" name="id" id="agenda-status-id"><input type="hidden" name="operation" id="agenda-status-operation"><p id="agenda-status-message"></p><select class="form-control-os" id="agenda-status-select"><option value="start_travel">Iniciar deslocamento</option><option value="start_execution">Iniciar execução</option><option value="wait_part">Aguardar peça</option><option value="finalize">Finalizar</option><option value="cancel">Cancelar</option></select></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>
