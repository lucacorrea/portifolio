<?php
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

require __DIR__ . '/perfil-action-common.php';

[$application, $session] = profile_action_context('perfil.configurar_permissoes');

try {
    $profileId = posted_positive_int('id');
    $application->authorization()->requirePermission('perfil.visualizar');
    $application->profileManagement()->syncPermissions($profileId, posted_int_array('permission_ids'));
    $session->flash('success', 'Permissões atualizadas com sucesso.');
    profile_redirect($application, 'perfil-permissoes.php?id=' . $profileId);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Profile permissions save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível concluir a operação. Tente novamente.');
}

profile_redirect($application, 'perfis-acesso.php');
