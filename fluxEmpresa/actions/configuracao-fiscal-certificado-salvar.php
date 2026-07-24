<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('nota_fiscal.gerenciar_credenciais');
$expectsJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
$startedAt = microtime(true);
$status = 200;
$response = ['ok' => false, 'message' => 'Não foi possível validar e armazenar o certificado fiscal.'];
try {
    $user = $application->authorization()->requireLogin();
    $upload = isset($_FILES['certificado']) && is_array($_FILES['certificado']) ? $_FILES['certificado'] : [];
    $application->fiscalConfiguration()->saveCertificate($upload, (string) ($_POST['senha_certificado'] ?? ''), (int) $user->id());
    $response = ['ok' => true, 'message' => 'Certificado A1 validado e armazenado com segurança.'];
} catch (InvalidArgumentException $exception) {
    $status = 422;
    $response['message'] = $exception->getMessage();
} catch (Throwable $exception) {
    $status = 500;
    error_log(sprintf(
        'Fiscal certificate save failed [%s] after %.3fs.',
        get_class($exception),
        microtime(true) - $startedAt
    ));
}

if ($expectsJson) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

$session->flash($response['ok'] ? 'success' : 'danger', $response['message']);
os_redirect_back($application, 'configuracoes-fiscais.php');
