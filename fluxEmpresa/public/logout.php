<?php

require_once __DIR__ . '/../app/bootstrap.php';

use FluxEmpresa\Core\Auth;
use FluxEmpresa\Core\Csrf;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

Csrf::requireValid();
Auth::logout();

redirect('login.php');
