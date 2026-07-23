<?php

declare(strict_types=1);

use App\Schedule\Service\AgendaDayBoard;

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/agenda-action-common.php';

$agendaService = $application->agendaManagement();
$view = (string) ($_GET['view'] ?? 'day');
if (!in_array($view, ['day', 'week'], true)) $view = 'day';

try {
    $currentDate = new DateTimeImmutable((string) ($_GET['date'] ?? date('Y-m-d')));
} catch (Throwable) {
    $currentDate = new DateTimeImmutable('today');
}

$periodStart = $view === 'week' ? $currentDate->modify('monday this week')->setTime(0, 0) : $currentDate->setTime(0, 0);
$periodEnd = $view === 'week' ? $periodStart->modify('+7 days') : $periodStart->modify('+1 day');
$prevDate = $view === 'week' ? $periodStart->modify('-7 days') : $periodStart->modify('-1 day');
$nextDate = $view === 'week' ? $periodStart->modify('+7 days') : $periodStart->modify('+1 day');
$statusFilter = (string) ($_GET['status'] ?? '');
if (!in_array($statusFilter, ['', 'ativo', 'concluido'], true)) $statusFilter = '';

$allCommitments = $agendaService->listRemindersBetween($periodStart, $periodEnd, true);
$commitments = array_values(array_filter(
    $allCommitments,
    static fn($commitment): bool => $statusFilter === '' || $commitment->status() === $statusFilter
));
$recovery = os_consume_form_recovery();
$events = array_map(
    static fn($commitment): array => ['type' => 'reminder', 'status' => $commitment->status(), 'time' => $commitment->start(), 'reminder' => $commitment],
    $commitments
);
$canEdit = $authorization->can('agenda.editar');
$canCancel = $authorization->can('agenda.cancelar');

function agenda_event_time(?string $start, ?string $end = null): string
{
    try {
        $text = (new DateTimeImmutable((string) $start))->format('H:i');
        if ($end) $text .= '–' . (new DateTimeImmutable($end))->format('H:i');
        return $text;
    } catch (Throwable) {
        return '-';
    }
}

function agenda_status_badge(string $status): string { return ['ativo'=>'blue','concluido'=>'green'][$status] ?? 'gray'; }
function agenda_commitment_status_label(string $status): string { return ['ativo'=>'Pendente','concluido'=>'Feito'][$status] ?? $status; }
function agenda_return_fields(string $view, DateTimeImmutable $periodStart): void
{
    return_to_field();
    echo '<input type="hidden" name="return_view" value="' . h($view) . '"><input type="hidden" name="return_date" value="' . h($periodStart->format('Y-m-d')) . '">';
}

$pendingCount = count(array_filter($allCommitments, static fn($commitment): bool => $commitment->isActive()));
$completedCount = count($allCommitments) - $pendingCount;
$eventGroups = $view === 'day'
    ? AgendaDayBoard::group($events)
    : [['key' => 'all', 'label' => 'Compromissos', 'icon' => 'bi-calendar-event', 'events' => $events]];
?>

<div class="page-body operational-page agenda-page" data-page="agenda" data-view="<?= h($view) ?>">
<div class="agenda-summary-bar" data-live-region="metrics" aria-label="Resumo da agenda interna">
    <span><i class="bi bi-calendar-event" aria-hidden="true"></i><strong><?= h((string) count($allCommitments)) ?></strong> Compromissos</span>
    <span><i class="bi bi-alarm" aria-hidden="true"></i><strong><?= h((string) $pendingCount) ?></strong> Pendentes</span>
    <span><i class="bi bi-check2-circle" aria-hidden="true"></i><strong><?= h((string) $completedCount) ?></strong> Feitos</span>
</div>

<form class="filter-bar agenda-filter-bar" method="get" action="agenda.php" data-live-filter="agenda" data-live-regions="metrics results">
    <input type="hidden" name="view" value="<?= h($view) ?>">
    <input type="hidden" name="date" value="<?= h($periodStart->format('Y-m-d')) ?>">
    <select class="filter-select" name="status" aria-label="Status do compromisso">
        <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Todos os compromissos</option>
        <option value="ativo" <?= $statusFilter === 'ativo' ? 'selected' : '' ?>>Pendentes</option>
        <option value="concluido" <?= $statusFilter === 'concluido' ? 'selected' : '' ?>>Feitos</option>
    </select>
    <div class="agenda-filter-actions">
        <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
        <a class="btn-filter btn-filter-ghost" href="agenda.php?view=<?= h($view) ?>&date=<?= h($periodStart->format('Y-m-d')) ?>" data-live-filter-clear><i class="bi bi-x-lg"></i> Limpar</a>
    </div>
</form>

<section class="panel agenda-panel" data-live-region="results">
    <div class="panel-header agenda-toolbar">
        <div class="agenda-period">
            <span class="agenda-period-kicker"><?= $view === 'week' ? 'Compromissos da semana' : 'Compromissos do dia' ?></span>
            <div class="panel-title"><i class="bi bi-calendar-week" aria-hidden="true"></i><?= h($view === 'week' ? 'Semana de ' . $periodStart->format('d/m/Y') : $periodStart->format('d/m/Y')) ?></div>
        </div>
        <nav class="agenda-navigation" aria-label="Navegação da agenda">
            <a class="agenda-nav-button" href="agenda.php?view=<?= h($view) ?>&date=<?= h($prevDate->format('Y-m-d')) ?>" aria-label="Período anterior" title="Anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            <a class="btn-filter btn-filter-primary" href="agenda.php?view=<?= h($view) ?>&date=<?= h(date('Y-m-d')) ?>">Hoje</a>
            <a class="agenda-nav-button" href="agenda.php?view=<?= h($view) ?>&date=<?= h($nextDate->format('Y-m-d')) ?>" aria-label="Próximo período" title="Próximo"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            <a class="btn-filter btn-filter-ghost agenda-view-toggle" href="agenda.php?view=<?= $view === 'day' ? 'week' : 'day' ?>&date=<?= h($periodStart->format('Y-m-d')) ?>"><i class="bi <?= $view === 'day' ? 'bi-calendar-week' : 'bi-calendar-day' ?>" aria-hidden="true"></i><?= $view === 'day' ? 'Semana' : 'Dia' ?></a>
        </nav>
    </div>
    <?php if ($events === []): ?>
        <?php empty_state('Nenhum compromisso no período', 'Reuniões, retiradas de encomendas e outros assuntos internos aparecerão aqui.'); ?>
    <?php else: ?>
    <div class="agenda-status-board">
        <?php foreach ($eventGroups as $group): ?>
        <section class="<?= $view === 'day' ? 'agenda-status-card agenda-status-' . h($group['key']) : 'agenda-status-flat' ?>">
            <?php if ($view === 'day'): ?><header class="agenda-status-header"><div><i class="bi <?= h($group['icon']) ?>" aria-hidden="true"></i><strong><?= h($group['label']) ?></strong></div><span><?= h((string) count($group['events'])) ?></span></header><?php endif; ?>
            <div class="agenda-event-list">
            <?php foreach ($group['events'] as $event): $commitment = $event['reminder']; ?>
                <article class="week-service-card agenda-event-card agenda-reminder-card priority-medium" data-record-actions>
                    <div class="agenda-event-time"><i class="bi bi-clock" aria-hidden="true"></i><strong><?= h(agenda_event_time($commitment->start(), $commitment->end())) ?></strong></div>
                    <div class="agenda-event-content"><div class="agenda-event-heading"><strong class="week-service-os">Compromisso</strong><span class="week-service-client"><?= h($commitment->title()) ?></span></div><div class="week-service-title"><?= h($commitment->description() ?? 'Sem descrição') ?></div></div>
                    <div class="week-service-meta agenda-event-meta"><span class="badge-soft badge-<?= h(agenda_status_badge($commitment->status())) ?>"><?= h(agenda_commitment_status_label($commitment->status())) ?></span></div>
                    <?php if ($commitment->isActive() && ($canEdit || $canCancel)): ?>
                    <div class="record-actions-source agenda-event-actions"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do compromisso <?= h($commitment->title()) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><?php if ($canEdit): ?><li><form class="agenda-inline-action" method="post" action="actions/agenda-lembrete-concluir.php"><?= $csrf->field() ?><?php agenda_return_fields($view, $periodStart); ?><input type="hidden" name="id" value="<?= h((string) $commitment->id()) ?>"><button class="dropdown-item text-success" type="submit"><i class="bi bi-check2-circle"></i> Marcar como feito</button></form></li><li><button class="dropdown-item js-reminder-edit" type="button" data-id="<?= h((string) $commitment->id()) ?>" data-title="<?= h($commitment->title()) ?>" data-description="<?= h($commitment->description() ?? '') ?>" data-start="<?= h($commitment->start()) ?>" data-end="<?= h($commitment->end() ?? '') ?>" data-bs-toggle="modal" data-bs-target="#modal-lembrete-edit"><i class="bi bi-pencil"></i> Editar</button></li><?php endif; ?><?php if ($canCancel): ?><li><button class="dropdown-item text-danger js-reminder-cancel" type="button" data-id="<?= h((string) $commitment->id()) ?>" data-title="<?= h($commitment->title()) ?>" data-bs-toggle="modal" data-bs-target="#modal-lembrete-cancel"><i class="bi bi-x-circle"></i> Cancelar</button></li><?php endif; ?></ul></div></div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
</div>

<div class="modal fade" id="modal-lembrete" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/agenda-lembrete-salvar.php"><div class="modal-header"><h2 class="modal-title fs-5">Novo compromisso</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php agenda_return_fields($view, $periodStart); ?><div class="form-row"><div class="form-group"><label class="form-label" for="commitment-create-title">Título</label><input class="form-control-os" name="title" id="commitment-create-title" placeholder="Ex.: Reunião ou buscar encomenda" required></div><div class="form-group"><label class="form-label" for="commitment-create-start">Início</label><input class="form-control-os" type="datetime-local" name="start" id="commitment-create-start" required></div><div class="form-group"><label class="form-label" for="commitment-create-end">Fim</label><input class="form-control-os" type="datetime-local" name="end" id="commitment-create-end"></div></div><div class="form-group"><label class="form-label" for="commitment-create-description">Descrição</label><textarea class="form-control-os" name="description" id="commitment-create-description" maxlength="5000" placeholder="Detalhes importantes do compromisso"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar compromisso</button></div></form></div></div>
<div class="modal fade" id="modal-lembrete-edit" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/agenda-lembrete-salvar.php"><div class="modal-header"><h2 class="modal-title fs-5">Editar compromisso</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php agenda_return_fields($view, $periodStart); ?><input type="hidden" name="id" id="reminder-edit-id"><div class="form-row"><div class="form-group"><label class="form-label" for="reminder-edit-title">Título</label><input class="form-control-os" name="title" id="reminder-edit-title" required></div><div class="form-group"><label class="form-label" for="reminder-edit-start">Início</label><input class="form-control-os" type="datetime-local" name="start" id="reminder-edit-start" required></div><div class="form-group"><label class="form-label" for="reminder-edit-end">Fim</label><input class="form-control-os" type="datetime-local" name="end" id="reminder-edit-end"></div></div><div class="form-group"><label class="form-label" for="reminder-edit-description">Descrição</label><textarea class="form-control-os" name="description" id="reminder-edit-description" maxlength="5000"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar compromisso</button></div></form></div></div>
<div class="modal fade" id="modal-lembrete-cancel" tabindex="-1" aria-hidden="true" aria-describedby="reminder-cancel-message"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/agenda-lembrete-cancelar.php"><div class="modal-header"><h2 class="modal-title fs-5">Cancelar compromisso</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php agenda_return_fields($view, $periodStart); ?><input type="hidden" name="id" id="reminder-cancel-id"><p id="reminder-cancel-message"></p></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn-modal-save" type="submit">Cancelar compromisso</button></div></form></div></div>
<script type="application/json" id="agenda-page-data"><?= json_encode(['recoveryModal' => $recovery['modal'] ?? ($_GET['modal'] ?? null), 'recoveryData' => $recovery['data'] ?? [], 'recoveryError' => $recovery['error'] ?? null], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
