<?php

declare(strict_types=1);

namespace App\Controllers;

final class RecepcaoController
{
    public function dashboard(): void
    {
        require dirname(__DIR__) . '/Views/recepcao/dashboard.php';
    }
}
