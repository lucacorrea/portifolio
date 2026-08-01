<?php

declare(strict_types=1);

require __DIR__ . '/caixa-action-common.php';

[$application, $session, $userId] = cash_action_context('caixa.fechar');
try {
    $application->cashManagement()->closeSession((string) ($_POST['saldo_informado'] ?? ''), $_POST['observacao'] ?? null, $userId);
    $session->flash('success', 'Caixa conferido e fechado com sucesso.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Cash closing failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível fechar o Caixa.');
}
cash_action_redirect($application);
