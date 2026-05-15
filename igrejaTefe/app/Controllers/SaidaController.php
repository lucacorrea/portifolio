<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

final class SaidaController
{
    public function index(): Response
    {
        return Response::html(View::render('shared/module', [
            'title' => 'Saídas',
            'module' => 'Saídas',
            'description' => 'Registro de despesas por categoria será implementado após categorias e banco.',
        ]));
    }
}
