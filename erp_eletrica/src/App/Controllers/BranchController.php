<?php
namespace App\Controllers;

use App\Models\Filial;

class BranchController extends BaseController {
    public function index() {
        $model = new Filial();
        $branches = $model->all();

        ob_start();
        $data = ['branches' => $branches];
        extract($data);
        require __DIR__ . "/../../../views/branches.view.php";
        $content = ob_get_clean();

        $this->render('layouts/main', [
            'title' => 'Gestão de Filiais & Unidades',
            'pageTitle' => 'Administração de Unidades de Negócio',
            'content' => $content
        ]);
    }
}
