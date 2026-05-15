<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

final class EntradaController
{
    public function index(): Response
    {
        return Response::html(View::render('shared/module', [
            'title' => 'Entradas',
            'module' => 'Entradas',
            'description' => 'Registro de dízimos e ofertas será implementado após o schema financeiro.',
        ]));
    }
}
