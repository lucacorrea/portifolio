<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

final class ConfiguracaoController
{
    public function index(): Response
    {
        return Response::html(View::render('shared/module', [
            'title' => 'Configurações',
            'module' => 'Configurações',
            'description' => 'Perfil, senha e dados da igreja serão conectados nas próximas fases.',
        ]));
    }
}
