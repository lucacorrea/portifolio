<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

final class CategoriaController
{
    public function index(): Response
    {
        return Response::html(View::render('shared/module', [
            'title' => 'Categorias',
            'module' => 'Categorias',
            'description' => 'CRUD de categorias será implementado após autenticação e banco.',
        ]));
    }
}
