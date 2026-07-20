<?php

declare(strict_types=1);

require __DIR__ . '/caixa-action-common.php';

[$application, $session, $userId] = cash_action_context('caixa.suprimento');
try {
    $application->cashManagement()->registerAdjustment('suprimento', (string) ($_POST['valor'] ?? ''), (string) ($_POST['motivo'] ?? ''), $userId);
    $session->flash('success', 'Suprimento registrado com auditoria.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Cash supply failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível registrar o suprimento.');
}
cash_action_redirect($application);
