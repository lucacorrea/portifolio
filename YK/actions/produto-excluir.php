<?php

declare(strict_types=1);

require __DIR__ . '/produto-action-common.php';

product_require_post_request();
[$application, $session] = product_action_context('produto.excluir');

try {
    $user = $application->authorization()->requireLogin();
    $application->productManagement()->deleteProduct(
        product_posted_positive_int('id'),
        (string) ($_POST['motivo'] ?? ''),
        $user->id()
    );
    $session->flash('success', 'Produto excluído com auditoria e histórico preservados.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Product soft deletion failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível excluir o produto.');
}

product_redirect($application);
