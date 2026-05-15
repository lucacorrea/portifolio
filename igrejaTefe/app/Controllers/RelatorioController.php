<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

final class RelatorioController
{
    public function index(): Response
    {
        return Response::html(View::render('shared/module', [
            'title' => 'Relatórios',
            'module' => 'Relatórios',
            'description' => 'Resumo por período e exportações PDF/Excel entram depois do CRUD financeiro.',
        ]));
    }
}
