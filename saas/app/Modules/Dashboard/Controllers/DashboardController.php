<?php
namespace App\Modules\Dashboard\Controllers;

require_once base_path('app/Services/MenuService.php');
use App\Services\MenuService;

class DashboardController {
    public function index(){
        return view('layouts/app',[
            'title'=>'Dashboard Contábil',
            'pageTitle'=>'Painel do Contador',
            'menuItems'=>MenuService::items(),
            'cards'=>[
                ['titulo'=>'Empresas','valor'=>86],
                ['titulo'=>'Obrigações','valor'=>14],
                ['titulo'=>'Guias','valor'=>9],
                ['titulo'=>'Faturamento','valor'=>'R$ 28.940'],
            ],
            'contentView'=>'modules/dashboard/index'
        ]);
    }
}
