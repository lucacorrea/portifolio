<?php

declare(strict_types=1);

require __DIR__ . '/caixa-action-common.php';

[$application, $session, $userId] = cash_action_context('venda_avulsa.estornar');
try {
    $application->cashManagement()->reverseSale(
        cash_action_positive_int($_POST['venda_id'] ?? null),
        (string) ($_POST['motivo'] ?? ''),
        $userId
    );
    $session->flash('success', 'Venda estornada com devolução do estoque e compensação no Caixa.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('POS reversal failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível estornar a venda. Nenhum dado foi alterado.');
}
cash_action_redirect($application);
