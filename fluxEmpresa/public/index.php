<?php

require_once __DIR__ . '/../app/bootstrap.php';

use FluxEmpresa\Core\Auth;

if (Auth::isLogged()) {
    redirect('dashboard.php');
}

redirect('login.php');
