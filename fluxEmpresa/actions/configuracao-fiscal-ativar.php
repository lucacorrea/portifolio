<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('nota_fiscal.configurar');
try {
    $user = $application->authorization()->requireLogin();
    $application->fiscalConfiguration()->activate(os_posted_positive_int('configuracao_id'), (int) $user->id());
    $session->flash('success', 'Configuração local de homologação ativada. A emissão continua bloqueada até o teste real com a SEFAZ.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Fiscal configuration activation failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível ativar a configuração fiscal.');
}
os_redirect_back($application, 'configuracoes-fiscais.php');
