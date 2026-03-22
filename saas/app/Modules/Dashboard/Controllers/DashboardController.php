<?php
declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

require_once base_path('app/Services/MenuService.php');

use App\Services\MenuService;

final class DashboardController
{
    public function index(): string
    {
        return view('layouts/app', [
            'title'      => 'Dashboard',
            'pageTitle'  => 'Dashboard',
            'activeMenu' => 'dashboard',
            'menuItems'  => MenuService::items(),
            'cards' => [
                ['titulo' => 'Clientes', 'valor' => 127, 'desc' => 'ativos no sistema', 'trend' => '+12%'],
                ['titulo' => 'Faturamento', 'valor' => 'R$ 12.450', 'desc' => 'este mês', 'trend' => '+8%'],
                ['titulo' => 'Pendências', 'valor' => 18, 'desc' => 'aguardando ação', 'trend' => '-3%'],
                ['titulo' => 'Assinaturas', 'valor' => 42, 'desc' => 'ativas', 'trend' => '+5%'],
            ],
            'alertas' => [
                ['titulo' => '3 documentos vencem hoje', 'desc' => 'Revise os documentos.'],
                ['titulo' => '2 clientes sem responsável', 'desc' => 'Ajustar cadastro.'],
                ['titulo' => '1 assinatura expirando', 'desc' => 'Cliente precisa renovar.'],
            ],
            'contentView' => 'modules/dashboard/index',
        ]);
    }
}