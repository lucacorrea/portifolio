<?php

declare(strict_types=1);

use App\Access\Exception\AuthorizationException;

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('recibo.emitir');

try {
    $user = $application->authorization()->requireLogin();
    $result = $application->receiptService()->emitForPayment(
        os_posted_positive_int('pagamento_id'),
        $user->id()
    );
    if (!$result['created']) {
        $application->authorization()->requirePermission('recibo.reimprimir');
    } else {
        $_SESSION['receipt_initial_print_grant'] = [
            'receipt_id' => $result['id'],
            'user_id' => $user->id(),
            'expires_at' => time() + 120,
        ];
    }
    os_redirect($application, 'recibo-imprimir.php?id=' . $result['id']);
} catch (AuthorizationException) {
    $session->flash('danger', 'Você não possui permissão para reimprimir recibos existentes.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Receipt emission failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível emitir o recibo.');
}

os_redirect_back($application);
