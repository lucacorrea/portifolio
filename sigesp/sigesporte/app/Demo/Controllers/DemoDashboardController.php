<?php
declare(strict_types=1);

namespace Sigesp\Demo\Controllers;

use Sigesp\Core\{Request, Response, View};
use Sigesp\Demo\DashboardDemoData;

final class DemoDashboardController
{
    public function index(Request $request): Response
    {
        $data = DashboardDemoData::all();
        $data['title'] = 'Visão geral';
        $data['demoMode'] = true;
        $data['demoUser'] = ['nome' => 'Marcos Oliveira', 'perfil' => 'Administrador do Sistema'];
        return new Response(View::render('dashboard/index', $data));
    }
}
