<?php

declare(strict_types=1);

use App\Controllers\RecepcaoController;

return [
    'GET' => [
        '/recepcao/dashboard' => [RecepcaoController::class, 'dashboard'],
    ],
];
