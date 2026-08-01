<?php
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

require __DIR__ . '/perfil-action-common.php';

[$application, $session] = profile_action_context('perfil.excluir');

try {
    $application->profileManagement()->deleteProfile(posted_positive_int('id'));
    $session->flash('success', 'Perfil excluído com sucesso.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Profile delete failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível concluir a operação. Tente novamente.');
}

profile_redirect($application, 'perfis-acesso.php');
