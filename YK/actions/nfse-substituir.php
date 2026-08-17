<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';
os_require_post_request();
[$application,$session] = os_action_context('nfse.substituir');
$session->flash('warning', 'Substituição Betha via Web Service está temporariamente indisponível pelo provedor (E901).');
os_redirect_back($application, 'faturamento.php');
