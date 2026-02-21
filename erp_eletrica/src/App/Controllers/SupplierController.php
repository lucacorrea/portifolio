<?php
namespace App\Controllers;

use App\Models\Supplier;

class SupplierController extends BaseController {
    public function index() {
        $model = new Supplier();
        $suppliers = $model->all();

        ob_start();
        $data = ['suppliers' => $suppliers];
        extract($data);
        require __DIR__ . "/../../../views/suppliers.view.php";
        $content = ob_get_clean();

        $this->render('layouts/main', [
            'title' => 'Gestão de Fornecedores',
            'pageTitle' => 'Parceiros e Cadeia de Suprimentos',
            'content' => $content
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $model = new Supplier();
            $model->save($_POST);
            $this->redirect('fornecedores.php?msg=Fornecedor salvo');
        }
    }
}
