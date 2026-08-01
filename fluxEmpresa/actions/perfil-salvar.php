<?php
declare(strict_types=1);

use App\Access\DTO\ProfileFormData;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

require __DIR__ . '/perfil-action-common.php';

$profileId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$permission = is_int($profileId) ? 'perfil.editar' : 'perfil.criar';
[$application, $session] = profile_action_context($permission);

try {
    $service = $application->profileManagement();
    $data = ProfileFormData::fromArray([
        'name' => $_POST['name'] ?? '',
        'description' => $_POST['description'] ?? null,
        'status' => $_POST['status'] ?? 'ativo',
    ]);

    if (is_int($profileId)) {
        $service->updateProfile($profileId, $data);
        $session->flash('success', 'Perfil atualizado com sucesso.');
    } else {
        $copyProfileId = filter_input(INPUT_POST, 'copy_profile_id', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $permissionIds = is_int($copyProfileId) ? $service->permissionIdsForProfile($copyProfileId) : [];
        $profileId = $service->createProfile($data, $permissionIds);
        $session->flash('success', 'Perfil criado com sucesso.');
    }

    profile_redirect($application, 'perfil-formulario.php?id=' . $profileId);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Profile save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível concluir a operação. Tente novamente.');
}

profile_redirect($application, is_int($profileId) ? 'perfil-formulario.php?id=' . $profileId : 'perfil-formulario.php');
