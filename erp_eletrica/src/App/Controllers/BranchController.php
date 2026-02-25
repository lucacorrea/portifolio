<?php
namespace App\Controllers;

use App\Models\Filial;

class BranchController extends BaseController {
    public function index() {
        $model = new Filial();
        $branches = $model->all();

        $this->render('branches', [
            'branches' => $branches,
            'title' => 'Gestão de Filiais & Unidades',
            'pageTitle' => 'Administração de Unidades de Negócio'
        ]);
    }
}
