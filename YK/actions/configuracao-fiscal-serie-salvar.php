<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('nota_fiscal.configurar');
try {
    $user = $application->authorization()->requireLogin();
    $application->fiscalConfiguration()->saveSeries($_POST, (int) $user->id());
    $session->flash('success', 'Série de homologação atualizada.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Fiscal series save failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível salvar a série fiscal.');
}
os_redirect_back($application, 'configuracoes-fiscais.php');
