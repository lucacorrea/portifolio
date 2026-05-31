<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

use App\Core\Response;
use App\Security\Auth;

Auth::logout();

Response::redirect('login.php');
