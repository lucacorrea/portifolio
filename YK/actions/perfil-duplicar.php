<?php
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

require __DIR__ . '/perfil-action-common.php';

[$application, $session] = profile_action_context('perfil.duplicar');

try {
    $sourceProfileId = posted_positive_int('id');
    $newProfileId = $application->profileManagement()->duplicateProfile(
        $sourceProfileId,
        (string) ($_POST['name'] ?? ''),
        isset($_POST['description']) ? (string) $_POST['description'] : null
    );
    $session->flash('success', 'Perfil duplicado com sucesso.');
    profile_redirect($application, 'perfil-formulario.php?id=' . $newProfileId);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Profile duplicate failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível concluir a operação. Tente novamente.');
}

profile_redirect($application, 'perfis-acesso.php');
