<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('recibo.emitir');

try {
    $user = $application->authorization()->requireLogin();
    $result = $application->receiptService()->emitStandalone($_POST, $user->id());
    $_SESSION['receipt_initial_print_grant'] = [
        'receipt_id' => $result['id'],
        'user_id' => $user->id(),
        'expires_at' => time() + 120,
    ];
    $session->flash('success', 'Recibo emitido com sucesso.');
    os_redirect($application, 'recibo-imprimir.php?id=' . $result['id']);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Standalone receipt emission failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível emitir o recibo.');
}

os_redirect_back($application, 'faturamento.php');
