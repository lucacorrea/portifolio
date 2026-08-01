<?php
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

require __DIR__ . '/perfil-action-common.php';

[$application, $session] = profile_action_context('perfil.desativar');

try {
    $profileId = posted_positive_int('id');
    $status = (string) ($_POST['status'] ?? '');

    if (!in_array($status, ['ativo', 'inativo'], true)) {
        throw new InvalidArgumentException('Status de perfil invalido.');
    }

    if ($status === 'ativo') {
        $application->profileManagement()->activateProfile($profileId);
        $session->flash('success', 'Perfil ativado com sucesso.');
    } else {
        $application->profileManagement()->deactivateProfile($profileId);
        $session->flash('success', 'Perfil desativado com sucesso.');
    }
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Profile status failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível concluir a operação. Tente novamente.');
}

profile_redirect($application, 'perfis-acesso.php');
