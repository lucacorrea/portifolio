<?php

declare(strict_types=1);

require __DIR__ . '/agenda-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('agenda.editar');
$session->flash('warning', 'Altere o status em Serviços da Semana ou na própria OS.');
agenda_redirect($application, agenda_return_target());
