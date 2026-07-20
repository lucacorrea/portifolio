<?php

declare(strict_types=1);

require __DIR__ . '/caixa-action-common.php';

[$application, $session, $userId] = cash_action_context('caixa.registrar_venda');
try {
    $sale = $application->cashManagement()->createSale($_POST, $userId);
    $session->flash('success', 'Venda ' . $sale['numero'] . ' concluída, com Caixa e estoque atualizados.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('POS sale failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível concluir a venda. Nenhum dado foi alterado.');
}
cash_action_redirect($application);
