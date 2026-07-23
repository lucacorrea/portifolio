<?php

declare(strict_types=1);

require __DIR__ . '/caixa-action-common.php';

[$application, $session, $userId] = cash_action_context('caixa.abrir');
try {
    $application->cashManagement()->openSession((string) ($_POST['valor_abertura'] ?? '0'), $_POST['observacao'] ?? null, $userId);
    $session->flash('success', 'Caixa aberto com sucesso.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Cash opening failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível abrir o Caixa.');
}
cash_action_redirect($application);
