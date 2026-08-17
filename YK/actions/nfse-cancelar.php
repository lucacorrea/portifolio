<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';
os_require_post_request();
[$application,$session] = os_action_context('nfse.cancelar');
$session->flash('warning', 'Cancelamento Betha via Web Service está temporariamente indisponível pelo provedor (E900).');
os_redirect_back($application, 'faturamento.php');
