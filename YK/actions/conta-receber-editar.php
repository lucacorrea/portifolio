<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('contas_receber.negociar');

try {
    $authorization = $application->authorization();
    $authorization->requirePermission('contas_receber.alterar_vencimento');
    $authorization->requirePermission('contas_receber.configurar_lembrete');
    $user = $authorization->requireLogin();

    $application->accountsReceivableManagement()->editAccount(
        os_posted_positive_int('id'),
        (string) ($_POST['valor_total'] ?? ''),
        isset($_POST['vencimento_em']) ? (string) $_POST['vencimento_em'] : null,
        isset($_POST['proximo_lembrete_em']) ? (string) $_POST['proximo_lembrete_em'] : null,
        isset($_POST['observacao']) ? (string) $_POST['observacao'] : null,
        $user->id()
    );

    $session->flash('success', 'Conta a receber atualizada. Saldo e situação foram recalculados automaticamente.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Accounts receivable edit failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível editar a conta a receber.');
}

os_redirect_back($application, 'contas-receber.php');
