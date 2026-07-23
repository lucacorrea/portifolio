<?php

declare(strict_types=1);

require __DIR__ . '/caixa-action-common.php';

[$application, $session, $userId] = cash_action_context('caixa.sangria');
try {
    $application->cashManagement()->registerAdjustment('sangria', (string) ($_POST['valor'] ?? ''), (string) ($_POST['motivo'] ?? ''), $userId);
    $session->flash('success', 'Sangria registrada com auditoria.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Cash withdrawal failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível registrar a sangria.');
}
cash_action_redirect($application);
