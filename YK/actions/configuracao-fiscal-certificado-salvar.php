<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('nota_fiscal.gerenciar_credenciais');
try {
    $user = $application->authorization()->requireLogin();
    $upload = isset($_FILES['certificado']) && is_array($_FILES['certificado']) ? $_FILES['certificado'] : [];
    $application->fiscalConfiguration()->saveCertificate($upload, (string) ($_POST['senha_certificado'] ?? ''), (int) $user->id());
    $session->flash('success', 'Certificado A1 validado e armazenado com segurança.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Fiscal certificate save failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível validar e armazenar o certificado fiscal.');
}
os_redirect_back($application, 'configuracoes-fiscais.php');
