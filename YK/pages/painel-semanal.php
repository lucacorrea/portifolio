<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/painel-semanal-action-common.php';

$orderService = $application->serviceOrderManagement();
$employeeService = $application->employeeManagement();
$clientService = $application->clientManagement();
$catalogService = $application->serviceManagement();

try { $baseWeek = new DateTimeImmutable((string) ($_GET['week'] ?? date('Y-m-d'))); } catch (Throwable) { $baseWeek = new DateTimeImmutable('today'); }
$weekStart = $baseWeek->modify('monday this week')->setTime(0, 0);
$prevWeek = $weekStart->modify('-7 days');
$nextWeek = $weekStart->modify('+7 days');

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'employee_id' => trim((string) ($_GET['employee_id'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'priority' => trim((string) ($_GET['priority'] ?? '')),
    'service' => trim((string) ($_GET['service'] ?? '')),
    'equipment' => trim((string) ($_GET['equipment'] ?? '')),
];
$employees = $employeeService->listEmployees();
$clients = $clientService->listClients();
$services = $catalogService->listServices(['status' => 'ativo']);
$recovery = os_consume_form_recovery();
$weekGroups = $orderService->weekSchedule($weekStart, $filters);
$orders = [];
foreach ($weekGroups as $dayOrders) foreach ($dayOrders as $order) $orders[] = $order;
$teamsByOrder = $orderService->teamMembersForOrders($orders);

$summary = ['agendada'=>0,'em_deslocamento'=>0,'em_execucao'=>0,'aguardando_peca'=>0,'finalizada'=>0,'urgente'=>0];
$teams = [];
foreach ($orders as $order) {
    if (isset($summary[$order->status()])) $summary[$order->status()]++;
    if ($order->priority() === 'urgente') $summary['urgente']++;
    $members = $teamsByOrder[$order->id()] ?? [];
    if ($members !== []) {
        $ids = array_map(static fn($member): int => $member->employeeId(), $members);
        sort($ids);
        $teams[implode('-', $ids)] = true;
    }
}

$canEdit = $authorization->can('painel_semanal.editar');
$canCreate = $authorization->can('painel_semanal.adicionar');
$canTeam = $authorization->can('painel_semanal.alterar_dupla');
$canSchedule = $authorization->can('painel_semanal.alterar_horario');
$canStatus = $authorization->can('painel_semanal.alterar_status');
$canCancel = $authorization->can('painel_semanal.cancelar');

function weekly_status_label(string $status): string { return ['agendada'=>'Agendada','em_deslocamento'=>'Em deslocamento','em_execucao'=>'Em execução','aguardando_peca'=>'Aguardando peça','finalizada'=>'Finalizada','cancelada'=>'Cancelada','aberta'=>'Aberta','aguardando_agendamento'=>'Aguardando agendamento'][$status] ?? $status; }
function weekly_priority_label(string $priority): string { return ['baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','urgente'=>'Urgente'][$priority] ?? 'Média'; }
function weekly_time(?string $start, ?string $end): string { try { return (new DateTimeImmutable((string) $start))->format('H:i') . '–' . (new DateTimeImmutable((string) $end))->format('H:i'); } catch (Throwable) { return '-'; } }
function weekly_team_name(array $members): string { if ($members === []) return 'Equipe nao definida'; return implode(' / ', array_map(static fn($member): string => $member->displayLine(), $members)); }
function weekly_team_lines(array $members): string { if ($members === []) return '<span>Equipe nao definida</span>'; return implode('', array_map(static fn($member): string => '<span>' . h($member->displayLine()) . '</span>', $members)); }
$days = ['Monday'=>'Segunda','Tuesday'=>'Terça','Wednesday'=>'Quarta','Thursday'=>'Quinta','Friday'=>'Sexta','Saturday'=>'Sábado','Sunday'=>'Domingo'];
?>

<div class="page-body weekly-page">
<?php metric_grid([
    ['Agendadas', (string) $summary['agendada'], 'bi-calendar2-check', '#0F766E', 'na semana'],
    ['Em deslocamento', (string) $summary['em_deslocamento'], 'bi-truck', '#7C3AED', 'em rota'],
    ['Em execução', (string) $summary['em_execucao'], 'bi-play-circle', '#16A34A', 'em atendimento'],
    ['Aguardando peça', (string) $summary['aguardando_peca'], 'bi-box-seam', '#D97706', 'pendências'],
    ['Finalizadas', (string) $summary['finalizada'], 'bi-check2-circle', '#15803D', 'concluídas'],
    ['Urgentes', (string) $summary['urgente'], 'bi-exclamation-triangle', '#DC2626', 'prioridade urgente'],
    ['Equipes utilizadas', (string) count($teams), 'bi-people', '#2563EB', 'sem repetição'],
]); ?>

<form class="filter-bar" method="get" action="painel-semanal.php" data-live-filter="weekly" data-live-regions="metrics results">
    <input type="hidden" name="week" value="<?= h($weekStart->format('Y-m-d')) ?>">
    <div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" type="search" name="search" value="<?= h($filters['search']) ?>" placeholder="OS, cliente, serviço, equipamento ou funcionário"></div>
    <select class="filter-select" name="employee_id"><option value="">Todos os funcionários</option><?php foreach ($employees as $employee): ?><option value="<?= h((string) $employee->id()) ?>" <?= $filters['employee_id'] === (string) $employee->id() ? 'selected' : '' ?>><?= h($employee->displayCode() . ' — ' . $employee->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="status"><option value="">Todos os status</option><?php foreach (['agendada','em_deslocamento','em_execucao','aguardando_peca','finalizada','cancelada'] as $status): ?><option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h(weekly_status_label($status)) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="priority"><option value="">Todas as prioridades</option><?php foreach (['baixa','media','alta','urgente'] as $priority): ?><option value="<?= h($priority) ?>" <?= $filters['priority'] === $priority ? 'selected' : '' ?>><?= h(weekly_priority_label($priority)) ?></option><?php endforeach; ?></select>
    <input class="filter-select" name="service" value="<?= h($filters['service']) ?>" placeholder="Serviço">
    <input class="filter-select" name="equipment" value="<?= h($filters['equipment']) ?>" placeholder="Equipamento">
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="painel-semanal.php?week=<?= h($weekStart->format('Y-m-d')) ?>" data-live-filter-clear><i class="bi bi-x-lg"></i> Limpar</a>
</form>

<section class="panel weekly-board-panel" data-live-region="results">
    <div class="panel-header"><div class="panel-title"><i class="bi bi-calendar-week"></i>Semana de <?= h($weekStart->format('d/m/Y')) ?></div><div class="d-flex gap-2"><a class="btn-filter btn-filter-ghost" href="painel-semanal.php?week=<?= h($prevWeek->format('Y-m-d')) ?>">Semana anterior</a><a class="btn-filter btn-filter-primary" href="painel-semanal.php?week=<?= h(date('Y-m-d')) ?>">Hoje</a><a class="btn-filter btn-filter-ghost" href="painel-semanal.php?week=<?= h($nextWeek->format('Y-m-d')) ?>">Próxima semana</a></div></div>
    <div class="weekly-board">
        <?php for ($i = 0; $i < 7; $i++): $day = $weekStart->modify('+' . $i . ' days'); $key = $day->format('Y-m-d'); $dayOrders = $weekGroups[$key] ?? []; ?>
            <section class="week-day-column <?= $key === date('Y-m-d') ? 'is-today' : '' ?>">
                <header class="week-day-header"><strong><?= h($days[$day->format('l')]) ?></strong><span><?= h($day->format('d/m')) ?></span></header>
                <div class="week-day-body">
                    <?php if ($dayOrders === []): ?><div class="week-empty-state">Sem atendimentos</div><?php else: ?>
                        <?php $byTeam = []; foreach ($dayOrders as $order) { $byTeam[weekly_team_name($teamsByOrder[$order->id()] ?? [])][] = $order; } ?>
                        <?php foreach ($byTeam as $teamName => $teamOrders): ?>
                            <section class="team-group"><header class="team-group-header"><div class="team-info"><strong class="team-names"><?= h($teamName) ?></strong><span class="team-role"><?= h(count($teamOrders) . ' atendimento' . (count($teamOrders) === 1 ? '' : 's')) ?></span></div></header>
                                <?php foreach ($teamOrders as $order): ?>
                                    <article class="week-service-card priority-<?= h($order->priority()) ?>" data-record-actions>
                                        <div class="week-service-time"><?= h(weekly_time($order->scheduledStart(), $order->scheduledEnd())) ?></div>
                                        <strong class="week-service-os"><?= h($order->displayNumber()) ?></strong>
                                        <div class="week-service-client"><?= h($order->clientName()) ?></div>
                                        <div class="week-service-title"><?= h($order->mainService() ?? 'Serviço não informado') ?></div>
                                        <div class="week-service-details"><span><?= h($order->displayEquipment()) ?></span><?= weekly_team_lines($teamsByOrder[$order->id()] ?? []) ?></div>
                                        <div class="week-service-meta"><span class="priority-label"><?= h(weekly_priority_label($order->priority())) ?></span><span><?= h(weekly_status_label($order->status())) ?></span></div>
                                        <div class="mt-2 d-flex justify-content-end record-actions-source">
                                            <div class="dropdown table-action-dropdown">
                                                <button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações da OS <?= h($order->displayNumber()) ?>"><i class="bi bi-three-dots-vertical"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <?php if ($canEdit): ?><li><a class="dropdown-item" href="ordens-servico.php?search=<?= h(rawurlencode($order->displayNumber())) ?>"><i class="bi bi-eye"></i> Abrir OS</a></li><?php endif; ?>
                                                    <?php if ($canSchedule): ?><li><button class="dropdown-item js-week-schedule" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-start="<?= h($order->scheduledStart() ?? '') ?>" data-end="<?= h($order->scheduledEnd() ?? '') ?>" data-bs-toggle="modal" data-bs-target="#modal-week-schedule"><i class="bi bi-calendar-event"></i> Reagendar</button></li><?php endif; ?>
                                                    <?php if ($canTeam): ?><li><button class="dropdown-item js-week-team" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-primary-id="<?= h((string) ($order->primaryEmployeeId() ?? '')) ?>" data-support-id="<?= h((string) ($order->supportEmployeeId() ?? '')) ?>" data-bs-toggle="modal" data-bs-target="#modal-week-team"><i class="bi bi-people"></i> Alterar equipe</button></li><?php endif; ?>
                                                    <?php if ($canStatus && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item js-week-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-current-status="<?= h($order->status()) ?>" data-bs-toggle="modal" data-bs-target="#modal-week-status"><i class="bi bi-arrow-repeat"></i> Alterar status</button></li><?php endif; ?>
                                                    <?php if ($canCancel && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item text-danger js-week-cancel" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-order-number="<?= h($order->displayNumber()) ?>" data-bs-toggle="modal" data-bs-target="#modal-week-cancel"><i class="bi bi-x-circle"></i> Cancelar</button></li><?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endfor; ?>
    </div>
</section>
</div>

<?php function week_employee_options(array $employees): void { foreach ($employees as $employee) echo '<option value="' . h((string) $employee->id()) . '">' . h($employee->displayCode() . ' — ' . $employee->name()) . '</option>'; } ?>
<?php function week_client_options(array $clients): void { foreach ($clients as $client) echo '<option value="' . h((string) $client->id()) . '">' . h($client->name()) . '</option>'; } ?>
<?php function week_service_options(array $services): void { foreach ($services as $service) echo '<option value="' . h((string) $service->id()) . '" data-duration="' . h((string) $service->durationMinutes()) . '">' . h($service->displayCode() . ' — ' . $service->name()) . '</option>'; } ?>
<?php function week_return_fields(DateTimeImmutable $weekStart): void { return_to_field(); echo '<input type="hidden" name="return_week" value="' . h($weekStart->format('Y-m-d')) . '">'; } ?>
<?php if ($canCreate): ?>
<div class="modal fade" id="modal-week-create" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/painel-semanal-servico-salvar.php"><div class="modal-header"><div><h2 class="modal-title fs-5">Adicionar serviço</h2><p class="text-muted small mb-0">Cria uma OS agendada e retorna para esta semana.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php week_return_fields($weekStart); ?><section class="form-section"><h3 class="form-section-title">Atendimento</h3><div class="form-row"><div class="form-group"><label class="form-label">Cliente</label><select class="form-control-os" name="client_id" id="week-create-client" required><option value="">Selecione</option><?php week_client_options($clients); ?></select></div><div class="form-group"><label class="form-label">Serviço</label><select class="form-control-os" name="service_id" id="week-create-service" required><option value="">Selecione</option><?php week_service_options($services); ?></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Prioridade</label><select class="form-control-os" name="priority"><option value="baixa">Baixa</option><option value="media" selected>Média</option><option value="alta">Alta</option><option value="urgente">Urgente</option></select></div><div class="form-group"><label class="form-label">Local</label><input class="form-control-os" name="equipment_location" maxlength="150"></div></div></section><section class="form-section"><h3 class="form-section-title">Equipe e horário</h3><div class="form-row"><div class="form-group"><label class="form-label">Principal</label><select class="form-control-os js-primary-employee" name="funcionario_principal_id" required><option value="">Selecione</option><?php week_employee_options($employees); ?></select></div><div class="form-group"><label class="form-label">Apoio</label><select class="form-control-os js-support-employee" name="funcionario_apoio_id"><option value="">Sem apoio</option><?php week_employee_options($employees); ?></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="agendado_inicio" id="week-create-start" required></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="agendado_fim" id="week-create-end" required></div></div></section><div class="form-group"><label class="form-label">Observação</label><textarea class="form-control-os" name="notes" maxlength="1000"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar no painel</button></div></form></div></div>
<?php endif; ?>
<div class="modal fade" id="modal-week-schedule" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/painel-semanal-reagendar.php"><div class="modal-header"><h2 class="modal-title fs-5">Reagendar</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php week_return_fields($weekStart); ?><input type="hidden" name="id" id="week-schedule-id"><div class="form-row"><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="agendado_inicio" id="week-schedule-start" required></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="agendado_fim" id="week-schedule-end" required></div></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-week-team" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/painel-semanal-alterar-dupla.php"><div class="modal-header"><h2 class="modal-title fs-5">Alterar equipe</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php week_return_fields($weekStart); ?><input type="hidden" name="id" id="week-team-id"><div class="form-row"><div class="form-group"><label class="form-label">Principal</label><select class="form-control-os js-primary-employee" name="funcionario_principal_id" id="week-team-primary" required><option value="">Selecione</option><?php week_employee_options($employees); ?></select></div><div class="form-group"><label class="form-label">Apoio</label><select class="form-control-os js-support-employee" name="funcionario_apoio_id" id="week-team-support"><option value="">Sem apoio</option><?php week_employee_options($employees); ?></select></div></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-week-status" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/painel-semanal-status.php"><div class="modal-header"><h2 class="modal-title fs-5">Alterar status</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php week_return_fields($weekStart); ?><input type="hidden" name="id" id="week-status-id"><input type="hidden" name="operation" id="week-status-operation"><select class="form-control-os" id="week-status-select"><option value="start_travel">Iniciar deslocamento</option><option value="start_execution">Iniciar execução</option><option value="wait_part">Aguardar peça</option><option value="finalize">Finalizar</option></select></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>
<div class="modal fade" id="modal-week-cancel" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/painel-semanal-cancelar.php"><div class="modal-header"><h2 class="modal-title fs-5">Cancelar OS</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php week_return_fields($weekStart); ?><input type="hidden" name="id" id="week-cancel-id"><p id="week-cancel-message"></p></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>
<script type="application/json" id="weekly-page-data"><?= json_encode(['recoveryModal' => $recovery['modal'] ?? ($_GET['modal'] ?? null), 'recoveryData' => $recovery['data'] ?? [], 'recoveryError' => $recovery['error'] ?? null], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
