<?php

declare(strict_types=1);

use App\ServiceOrder\DTO\ServiceOrderScheduleData;
use App\ServiceOrder\DTO\ServiceOrderTeamData;

require __DIR__ . '/os-action-common.php';

os_require_post_request();

$hasTeam = isset($_POST['team_submitted']);
$hasSchedule = trim((string) ($_POST['agendado_inicio'] ?? '')) !== '' || trim((string) ($_POST['agendado_fim'] ?? '')) !== '';
$permission = $hasTeam ? 'os.alterar_equipe' : 'os.agendar';
[$application, $session] = os_action_context($permission);

try {
    if (!$hasTeam && !$hasSchedule) {
        throw new InvalidArgumentException('Informe a equipe ou o agendamento que deseja alterar.');
    }
    if ($hasTeam && $hasSchedule) {
        $application->authorization()->requirePermission('os.agendar');
    }
    $id = os_posted_positive_int('id');
    $service = $application->serviceOrderManagement();
    if ($hasTeam && $hasSchedule) {
        $schedule = ServiceOrderScheduleData::fromArray($_POST);
        if ($schedule === null) throw new InvalidArgumentException('Informe o agendamento.');
        $service->assignTeamAndSchedule($id, ServiceOrderTeamData::fromArray($_POST), $schedule);
    } elseif ($hasTeam) {
        $service->reassignTeam($id, ServiceOrderTeamData::fromArray($_POST));
    } else {
        $schedule = ServiceOrderScheduleData::fromArray($_POST);
        if ($schedule === null) throw new InvalidArgumentException('Informe o agendamento.');
        $service->reschedule($id, $schedule);
    }
    $session->flash('success', 'Equipe e agendamento atualizados.');
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery('team', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    os_redirect_back($application, 'ordens-servico.php', ['modal' => 'team']);
} catch (Throwable $exception) {
    error_log('OS team/schedule failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível atualizar equipe ou agendamento.');
}

os_redirect_back($application);
