<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';

RoleMiddleware::handle('recepcao');

require APP_PATH . '/Views/recepcao/novoProtocolo.php';
