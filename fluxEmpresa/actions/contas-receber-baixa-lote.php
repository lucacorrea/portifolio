<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('contas_receber.baixa_lote');

try {
    $rawIds = $_POST['account_ids'] ?? [];
    if (!is_array($rawIds)) throw new InvalidArgumentException('Seleção de contas inválida.');
    $ids = array_map(static fn(mixed $id): int => os_positive_int($id), $rawIds);
    $user = $application->authorization()->requireLogin();
    $summary = $application->paymentManagement()->registerAccountsReceivableBatchPayment(
        $ids,
        (string) ($_POST['forma_pagamento'] ?? ''),
        isset($_POST['observacao']) ? (string) $_POST['observacao'] : null,
        $user->id()
    );
    $session->flash(
        'success',
        sprintf(
            '%d contas de %s foram quitadas em lote. Total recebido: R$ %s.',
            (int) $summary['count'],
            (string) $summary['client_name'],
            number_format((float) $summary['total'], 2, ',', '.')
        )
    );
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Accounts receivable batch payment failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível concluir a baixa em lote. Nenhuma conta foi alterada.');
}

os_redirect_back($application, 'contas-receber.php');
